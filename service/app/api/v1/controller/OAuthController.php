<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\User;
use app\model\UserOauth;
use app\model\UserWallet;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("OAuth认证")
 * @Apidoc\Group("auth")
 */
class OAuthController extends BaseController
{
    const PROVIDERS = ['google', 'facebook', 'apple', 'twitter', 'microsoft', 'linkedin', 'github'];

    const BASE_URLS = [
        'google'    => 'https://accounts.google.com/o/oauth2/v2/auth',
        'facebook'  => 'https://www.facebook.com/v18.0/dialog/oauth',
        'apple'     => 'https://appleid.apple.com/auth/authorize',
        'twitter'   => 'https://twitter.com/i/oauth2/authorize',
        'microsoft' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
        'linkedin'  => 'https://www.linkedin.com/oauth/v2/authorization',
        'github'    => 'https://github.com/login/oauth/authorize',
    ];

    const TOKEN_URLS = [
        'google'    => 'https://oauth2.googleapis.com/token',
        'facebook'  => 'https://graph.facebook.com/v18.0/oauth/access_token',
        'apple'     => 'https://appleid.apple.com/auth/token',
        'twitter'   => 'https://api.x.com/2/oauth2/token',
        'microsoft' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
        'linkedin'  => 'https://www.linkedin.com/oauth/v2/accessToken',
        'github'    => 'https://github.com/login/oauth/access_token',
    ];

    const USERINFO_URLS = [
        'google'    => 'https://www.googleapis.com/oauth2/v3/userinfo',
        'facebook'  => 'https://graph.facebook.com/me',
        'apple'     => '',
        'twitter'   => 'https://api.x.com/2/users/me',
        'microsoft' => 'https://graph.microsoft.com/v1.0/me',
        'linkedin'  => 'https://api.linkedin.com/v2/userinfo',
        'github'    => 'https://api.github.com/user',
    ];

    /**
     * @Apidoc\Title("OAuth授权跳转")
     * @Apidoc\Url("/api/auth/oauth/{provider}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="provider", type="string", require=true, desc="第三方平台(google/facebook/apple/twitter/microsoft/linkedin/github)", in="path")
     */
    public function redirect(Request $request, string $provider): Response
    {
        $provider = strtolower($provider);
        if (!in_array($provider, self::PROVIDERS, true)) {
            return $this->fail('Invalid OAuth provider', 422);
        }

        $state = bin2hex(random_bytes(16));
        \support\Redis::setex("oauth_state:{$state}", 600, $provider);

        $redirectUri = config("oauth.{$provider}.redirect_uri", '');
        $clientId    = config("oauth.{$provider}.client_id", '');

        $params = [
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => $this->getScope($provider),
            'state'         => $state,
        ];

        // Twitter requires PKCE (code_challenge)
        if ($provider === 'twitter') {
            $codeVerifier = $this->generateCodeVerifier();
            \support\Redis::setex("oauth_pkce:{$state}", 600, $codeVerifier);
            $params['code_challenge'] = $this->computeCodeChallenge($codeVerifier);
            $params['code_challenge_method'] = 'S256';
        }

        $url = self::BASE_URLS[$provider] . '?' . http_build_query($params);
        return $this->success(['redirect_url' => $url]);
    }

    /**
     * @Apidoc\Title("OAuth回调")
     * @Apidoc\Url("/api/auth/oauth/{provider}/callback")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="provider", type="string", require=true, desc="第三方平台(google/facebook/apple/twitter/microsoft/linkedin/github)", in="path")
     * @Apidoc\Param(name="code", type="string", require=true, desc="授权码")
     * @Apidoc\Param(name="state", type="string", require=true, desc="状态码")
     */
    public function callback(Request $request, string $provider): Response
    {
        $provider = strtolower($provider);

        $validator = validator($request->all(), [
            'code'  => 'required|string',
            'state' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        if (!in_array($provider, self::PROVIDERS, true)) {
            return $this->fail('Invalid OAuth provider', 422);
        }

        $code  = $request->input('code');
        $state = $request->input('state');

        $stateKey = "oauth_state:{$state}";
        $storedProvider = \support\Redis::get($stateKey);
        if (!$storedProvider || $storedProvider !== $provider) {
            return $this->fail('Invalid or expired state parameter', 403);
        }
        \support\Redis::del($stateKey);

        $codeVerifier = null;
        if ($provider === 'twitter') {
            $codeVerifier = \support\Redis::get("oauth_pkce:{$state}");
            \support\Redis::del("oauth_pkce:{$state}");
        }

        $oauthUser = $this->exchangeCode($provider, $code, $codeVerifier);
        $openId  = $oauthUser['open_id'];
        $unionId = $oauthUser['union_id'] ?? null;

        $existingOauth = UserOauth::where('provider', $provider)
            ->where('open_id', $openId)
            ->first();

        if ($existingOauth) {
            $user = User::find($existingOauth->user_id);
            if (!$user) {
                return $this->fail('User not found', 404);
            }
            if ((int) $user->status !== 1) {
                return $this->fail('Account is disabled', 403);
            }

            $user->last_login_at = date('Y-m-d H:i:s');
            $user->last_login_ip = $request->getRealIp() ?? '';
            $user->save();

            $accessToken  = jwt()->create(['sub' => $user->id, 'username' => $user->username]);
            $refreshToken = jwt()->create(['sub' => $user->id, 'type' => 'refresh']);

            return $this->success([
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'user' => [
                    'id' => $this->encodeId($user->id),
                    'username' => $user->username,
                    'nickname' => $user->nickname,
                    'avatar' => $user->avatar,
                ],
                'is_new' => false,
            ], 'OAuth login successful');
        }

        $userId = $this->generateId();
        $uniqueSuffix = substr(bin2hex(random_bytes(4)), 0, 6);
        $username = $provider . '_' . $uniqueSuffix;
        $nickname = $oauthUser['nickname'] ?? $username;

        $user = new User();
        $user->id = $userId;
        $user->username = $username;
        $user->password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $user->nickname = $nickname;
        $user->avatar = $oauthUser['avatar'] ?? '';
        $user->email = $oauthUser['email'] ?? null;
        $user->status = 1;
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $request->getRealIp() ?? '';
        $user->save();

        $oauth = new UserOauth();
        $oauth->id = $this->generateId();
        $oauth->user_id = $userId;
        $oauth->provider = $provider;
        $oauth->open_id = $openId;
        $oauth->union_id = $unionId;
        $oauth->access_token = $oauthUser['access_token'] ?? '';
        $oauth->refresh_token = $oauthUser['refresh_token'] ?? '';
        $oauth->token_expires_at = $oauthUser['token_expires_at'] ?? null;
        $oauth->raw_data = json_encode($oauthUser);
        $oauth->save();

        $wallet = new UserWallet();
        $wallet->id = $this->generateId();
        $wallet->user_id = $userId;
        $wallet->balance = '0.0000';
        $wallet->frozen_balance = '0.0000';
        $wallet->total_earned = '0.0000';
        $wallet->total_spent = '0.0000';
        $wallet->version = 0;
        $wallet->save();

        $accessToken  = jwt()->create(['sub' => $userId, 'username' => $username]);
        $refreshToken = jwt()->create(['sub' => $userId, 'type' => 'refresh']);

        return $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user' => [
                'id' => $this->encodeId($userId),
                'username' => $username,
                'nickname' => $nickname,
                'avatar' => $user->avatar,
            ],
            'is_new' => true,
        ], 'OAuth registration successful');
    }

    private function getScope(string $provider): string
    {
        $scopes = [
            'google'    => 'openid email profile',
            'facebook'  => 'email public_profile',
            'apple'     => 'name email',
            'twitter'   => 'users.read tweet.read',
            'microsoft' => 'openid email profile User.Read',
            'linkedin'  => 'openid profile email',
            'github'    => 'read:user user:email',
        ];
        return $scopes[$provider] ?? 'openid';
    }

    private function exchangeCode(string $provider, string $code, ?string $codeVerifier = null): array
    {
        $config = $this->getOAuthConfig($provider);
        if (!$config) {
            throw new \RuntimeException('OAuth provider not configured');
        }

        switch ($provider) {
            case 'google':    return $this->exchangeGoogle($code, $config);
            case 'facebook':  return $this->exchangeFacebook($code, $config);
            case 'apple':     return $this->exchangeApple($code, $config);
            case 'twitter':   return $this->exchangeTwitter($code, $config, $codeVerifier);
            case 'microsoft': return $this->exchangeMicrosoft($code, $config);
            case 'linkedin':  return $this->exchangeLinkedin($code, $config);
            case 'github':    return $this->exchangeGithub($code, $config);
            default:
                throw new \RuntimeException('Unknown provider');
        }
    }

    private function getOAuthConfig(string $provider): ?array
    {
        $configJson = \app\model\PlatformConfig::get('oauth', $provider, null);
        if (!$configJson) {
            $envPrefix = strtoupper($provider);
            return [
                'client_id' => getenv("OAUTH_{$envPrefix}_CLIENT_ID") ?: '',
                'client_secret' => getenv("OAUTH_{$envPrefix}_CLIENT_SECRET") ?: '',
                'redirect_uri' => getenv("OAUTH_{$envPrefix}_REDIRECT_URI") ?: '',
            ];
        }
        return json_decode($configJson, true);
    }

    private function http(): \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client(['timeout' => 10, 'http_errors' => false]);
    }

    // ─── Google ────────────────────────────────────────────

    private function exchangeGoogle(string $code, array $config): array
    {
        $http = $this->http();

        $resp = $http->post(self::TOKEN_URLS['google'], [
            'form_params' => [
                'code' => $code, 'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect_uri'],
                'grant_type' => 'authorization_code',
            ],
        ]);
        $tokenData = json_decode((string) $resp->getBody(), true);
        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('Google token exchange failed');
        }

        $userResp = $http->get(self::USERINFO_URLS['google'], [
            'headers' => ['Authorization' => 'Bearer ' . $tokenData['access_token']],
        ]);
        $userData = json_decode((string) $userResp->getBody(), true);
        if (empty($userData['sub'])) {
            throw new \RuntimeException('Google user info missing sub');
        }

        return [
            'provider' => 'google',
            'open_id' => $userData['sub'],
            'nickname' => $userData['name'] ?? '',
            'email' => $userData['email'] ?? '',
            'avatar' => $userData['picture'] ?? '',
        ];
    }

    // ─── Facebook ──────────────────────────────────────────

    private function exchangeFacebook(string $code, array $config): array
    {
        $http = $this->http();

        $resp = $http->get(self::TOKEN_URLS['facebook'], [
            'query' => [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect_uri'],
                'code' => $code,
            ],
        ]);
        $tokenData = json_decode((string) $resp->getBody(), true);
        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('Facebook token exchange failed');
        }

        $userResp = $http->get(self::USERINFO_URLS['facebook'], [
            'query' => [
                'fields' => 'id,name,email,picture',
                'access_token' => $tokenData['access_token'],
            ],
        ]);
        $userData = json_decode((string) $userResp->getBody(), true);
        if (empty($userData['id'])) {
            throw new \RuntimeException('Facebook user info missing id');
        }

        return [
            'provider' => 'facebook',
            'open_id' => $userData['id'],
            'nickname' => $userData['name'] ?? '',
            'email' => $userData['email'] ?? '',
            'avatar' => $userData['picture']['data']['url'] ?? '',
        ];
    }

    // ─── Apple ─────────────────────────────────────────────

    private function exchangeApple(string $code, array $config): array
    {
        $http = $this->http();

        $resp = $http->post(self::TOKEN_URLS['apple'], [
            'form_params' => [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $config['redirect_uri'],
            ],
        ]);
        $tokenData = json_decode((string) $resp->getBody(), true);
        $idToken = $tokenData['id_token'] ?? '';
        if (empty($idToken)) {
            throw new \RuntimeException('Apple id_token missing');
        }

        try {
            $payload = $this->verifyAppleIdToken($idToken, $config);
        } catch (\Throwable $e) {
            \support\Log::error('Apple id_token verification failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Apple authentication failed');
        }

        return [
            'provider' => 'apple',
            'open_id' => $payload['sub'],
            'nickname' => 'Apple User',
            'email' => $payload['email'] ?? '',
            'avatar' => '',
        ];
    }

    private function verifyAppleIdToken(string $idToken, array $config): array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Apple id_token malformed');
        }

        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);
        $kid = is_array($header) ? ($header['kid'] ?? '') : '';
        if (!is_string($kid) || $kid === '') {
            throw new \RuntimeException('Apple id_token missing kid');
        }

        $key = null;
        foreach ($this->getAppleJwks() as $jwk) {
            if (($jwk['kid'] ?? '') === $kid) {
                $key = JWK::parseKey($jwk);
                break;
            }
        }
        // kid 未命中时刷新一次 JWKS，覆盖 Apple 轮换密钥但进程内缓存未过期的情况
        if ($key === null) {
            foreach ($this->getAppleJwks(true) as $jwk) {
                if (($jwk['kid'] ?? '') === $kid) {
                    $key = JWK::parseKey($jwk);
                    break;
                }
            }
        }
        if ($key === null) {
            throw new \RuntimeException('Apple id_token key not found in JWKS');
        }

        try {
            // 校验 signature(RS256, Apple JWKS 仅发布 RSA 密钥；alg 白名单由 Key 携带) + exp(过期拒绝)
            $decoded = JWT::decode($idToken, $key);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Apple id_token verification failed: ' . $e->getMessage());
        }

        $payload = (array) $decoded;
        if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
            throw new \RuntimeException('Apple id_token issuer mismatch');
        }
        if (($payload['aud'] ?? '') !== $config['client_id']) {
            throw new \RuntimeException('Apple id_token audience mismatch');
        }
        if (empty($payload['sub'])) {
            throw new \RuntimeException('Apple id_token missing sub');
        }

        return $payload;
    }

    private function getAppleJwks(bool $refresh = false): array
    {
        // 进程内静态缓存，避免每请求拉取；Apple 密钥轮换时由 kid 未命中触发刷新
        static $keys = null;
        if ($keys === null || $refresh) {
            $resp = $this->http()->get('https://appleid.apple.com/auth/keys');
            $data = json_decode((string) $resp->getBody(), true);
            $keys = $data['keys'] ?? [];
            if (!is_array($keys) || count($keys) === 0) {
                throw new \RuntimeException('Apple JWKS fetch failed');
            }
        }
        return $keys;
    }

    // ─── Twitter / X (OAuth 2.0 with PKCE) ─────────────────

    private function exchangeTwitter(string $code, array $config, ?string $codeVerifier): array
    {
        if (empty($codeVerifier)) {
            throw new \RuntimeException('Twitter PKCE code_verifier required');
        }

        $http = $this->http();

        $resp = $http->post(self::TOKEN_URLS['twitter'], [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'form_params' => [
                'code' => $code,
                'client_id' => $config['client_id'],
                'grant_type' => 'authorization_code',
                'redirect_uri' => $config['redirect_uri'],
                'code_verifier' => $codeVerifier,
            ],
        ]);
        $tokenData = json_decode((string) $resp->getBody(), true);
        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('Twitter token exchange failed: ' . ($tokenData['error_description'] ?? 'unknown'));
        }

        $userResp = $http->get(self::USERINFO_URLS['twitter'], [
            'headers' => ['Authorization' => 'Bearer ' . $tokenData['access_token']],
            'query' => ['user.fields' => 'id,name,username,profile_image_url'],
        ]);
        $userData = json_decode((string) $userResp->getBody(), true);
        $userInfo = $userData['data'] ?? [];

        if (empty($userInfo['id'])) {
            throw new \RuntimeException('Twitter user info missing id');
        }

        return [
            'provider' => 'twitter',
            'open_id' => $userInfo['id'],
            'union_id' => $userInfo['username'] ?? null,
            'nickname' => $userInfo['name'] ?? $userInfo['username'] ?? 'X User',
            'email' => '',
            'avatar' => $userInfo['profile_image_url'] ?? '',
        ];
    }

    // ─── Microsoft ─────────────────────────────────────────

    private function exchangeMicrosoft(string $code, array $config): array
    {
        $http = $this->http();

        $resp = $http->post(self::TOKEN_URLS['microsoft'], [
            'form_params' => [
                'code' => $code, 'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect_uri'],
                'grant_type' => 'authorization_code',
            ],
        ]);
        $tokenData = json_decode((string) $resp->getBody(), true);
        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('Microsoft token exchange failed');
        }

        $userResp = $http->get(self::USERINFO_URLS['microsoft'], [
            'headers' => ['Authorization' => 'Bearer ' . $tokenData['access_token']],
        ]);
        $userData = json_decode((string) $userResp->getBody(), true);
        if (empty($userData['id'])) {
            throw new \RuntimeException('Microsoft user info missing id');
        }

        return [
            'provider' => 'microsoft',
            'open_id' => $userData['id'],
            'nickname' => $userData['displayName'] ?? '',
            'email' => $userData['mail'] ?? $userData['userPrincipalName'] ?? '',
            'avatar' => '',
        ];
    }

    // ─── LinkedIn ──────────────────────────────────────────

    private function exchangeLinkedin(string $code, array $config): array
    {
        $http = $this->http();

        $resp = $http->post(self::TOKEN_URLS['linkedin'], [
            'form_params' => [
                'code' => $code, 'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect_uri'],
                'grant_type' => 'authorization_code',
            ],
        ]);
        $tokenData = json_decode((string) $resp->getBody(), true);
        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('LinkedIn token exchange failed');
        }

        $userResp = $http->get(self::USERINFO_URLS['linkedin'], [
            'headers' => ['Authorization' => 'Bearer ' . $tokenData['access_token']],
        ]);
        $userData = json_decode((string) $userResp->getBody(), true);
        if (empty($userData['sub'])) {
            throw new \RuntimeException('LinkedIn user info missing sub');
        }

        return [
            'provider' => 'linkedin',
            'open_id' => $userData['sub'],
            'nickname' => $userData['name'] ?? '',
            'email' => $userData['email'] ?? '',
            'avatar' => $userData['picture'] ?? '',
        ];
    }

    // ─── GitHub ────────────────────────────────────────────

    private function exchangeGithub(string $code, array $config): array
    {
        $http = $this->http();

        $resp = $http->post(self::TOKEN_URLS['github'], [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => [
                'code' => $code, 'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect_uri'],
            ],
        ]);
        $tokenData = json_decode((string) $resp->getBody(), true);
        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('GitHub token exchange failed: ' . ($tokenData['error_description'] ?? 'unknown'));
        }

        // Get primary user info
        $userResp = $http->get(self::USERINFO_URLS['github'], [
            'headers' => [
                'Authorization' => 'Bearer ' . $tokenData['access_token'],
                'Accept' => 'application/json',
            ],
        ]);
        $userData = json_decode((string) $userResp->getBody(), true);
        if (empty($userData['id'])) {
            throw new \RuntimeException('GitHub user info missing id');
        }

        // Try to get primary email
        $email = $userData['email'] ?? '';
        if (empty($email)) {
            try {
                $emailResp = $http->get('https://api.github.com/user/emails', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $tokenData['access_token'],
                        'Accept' => 'application/json',
                    ],
                ]);
                $emails = json_decode((string) $emailResp->getBody(), true);
                if (is_array($emails)) {
                    foreach ($emails as $e) {
                        if (!empty($e['primary']) && !empty($e['verified'])) {
                            $email = $e['email'];
                            break;
                        }
                    }
                    if (empty($email) && !empty($emails[0]['email'])) {
                        $email = $emails[0]['email'];
                    }
                }
            } catch (\Throwable $e) {}
        }

        return [
            'provider' => 'github',
            'open_id' => (string) $userData['id'],
            'nickname' => $userData['name'] ?? $userData['login'] ?? '',
            'email' => $email,
            'avatar' => $userData['avatar_url'] ?? '',
        ];
    }

    // ─── PKCE helpers ──────────────────────────────────────

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function computeCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
