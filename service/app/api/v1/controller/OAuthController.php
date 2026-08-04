<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\User;
use app\model\UserOauth;
use app\model\UserWallet;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("OAuth认证")
 * @Apidoc\Group("auth")
 */
class OAuthController extends BaseController
{
    /**
     * @Apidoc\Title("OAuth授权跳转")
     * @Apidoc\Url("/api/auth/oauth/{provider}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="provider", type="string", require=true, desc="第三方平台(google/facebook/apple)", in="path")
     */
    public function redirect(Request $request, string $provider): Response
    {
        $provider = strtolower($provider);

        if (!in_array($provider, ['google', 'facebook', 'apple'], true)) {
            return $this->fail('Invalid OAuth provider', 422);
        }

        $state = bin2hex(random_bytes(16));
        \support\Redis::setex("oauth_state:{$state}", 600, $provider);
        $redirectUri = config("oauth.{$provider}.redirect_uri", '');
        $clientId    = config("oauth.{$provider}.client_id", '');

        $baseUrls = [
            'google'   => 'https://accounts.google.com/o/oauth2/v2/auth',
            'facebook' => 'https://www.facebook.com/v18.0/dialog/oauth',
            'apple'    => 'https://appleid.apple.com/auth/authorize',
        ];

        $url = $baseUrls[$provider] . '?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => $this->getScope($provider),
            'state'         => $state,
        ]);

        return $this->success(['redirect_url' => $url]);
    }

    /**
     * @Apidoc\Title("OAuth回调")
     * @Apidoc\Url("/api/auth/oauth/{provider}/callback")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="provider", type="string", require=true, desc="第三方平台(google/facebook/apple)", in="path")
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

        if (!in_array($provider, ['google', 'facebook', 'apple'], true)) {
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

        // Exchange authorization code for access token and user info
        // In production, exchange $code for access_token via provider API,
        // then fetch user profile (open_id, nickname, avatar, email, etc.).
        $oauthUser = $this->exchangeCode($provider, $code);

        $openId  = $oauthUser['open_id'];
        $unionId = $oauthUser['union_id'] ?? null;

        // Check if this OAuth binding already exists
        $existingOauth = UserOauth::where('provider', $provider)
            ->where('open_id', $openId)
            ->first();

        if ($existingOauth) {
            // Existing user: login
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
                'user'          => [
                    'id'       => $this->encodeId($user->id),
                    'username' => $user->username,
                    'nickname' => $user->nickname,
                    'avatar'   => $user->avatar,
                ],
                'is_new' => false,
            ], 'OAuth login successful');
        }

        // New user: create user + oauth binding + wallet
        $userId   = $this->generateId();
        $uniqueSuffix = substr(bin2hex(random_bytes(4)), 0, 6);
        $username = $provider . '_' . $uniqueSuffix;
        $nickname = $oauthUser['nickname'] ?? $username;

        $user = new User();
        $user->id            = $userId;
        $user->username      = $username;
        $user->password      = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $user->nickname      = $nickname;
        $user->avatar        = $oauthUser['avatar'] ?? '';
        $user->email         = $oauthUser['email'] ?? null;
        $user->status        = 1;
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $request->getRealIp() ?? '';
        $user->save();

        // Create OAuth binding
        $oauth = new UserOauth();
        $oauth->id         = $this->generateId();
        $oauth->user_id    = $userId;
        $oauth->provider   = $provider;
        $oauth->open_id    = $openId;
        $oauth->union_id   = $unionId;
        $oauth->access_token = $oauthUser['access_token'] ?? '';
        $oauth->refresh_token = $oauthUser['refresh_token'] ?? '';
        $oauth->token_expires_at = $oauthUser['token_expires_at'] ?? null;
        $oauth->raw_data   = json_encode($oauthUser);
        $oauth->save();

        // Create wallet
        $wallet = new UserWallet();
        $wallet->id             = $this->generateId();
        $wallet->user_id        = $userId;
        $wallet->balance        = '0.0000';
        $wallet->frozen_balance = '0.0000';
        $wallet->total_earned   = '0.0000';
        $wallet->total_spent    = '0.0000';
        $wallet->version        = 0;
        $wallet->save();

        $accessToken  = jwt()->create(['sub' => $userId, 'username' => $username]);
        $refreshToken = jwt()->create(['sub' => $userId, 'type' => 'refresh']);

        return $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'       => $this->encodeId($userId),
                'username' => $username,
                'nickname' => $nickname,
                'avatar'   => $user->avatar,
            ],
            'is_new' => true,
        ], 'OAuth registration successful');
    }

    /**
     * Get the OAuth scope string for a provider.
     */
    private function getScope(string $provider): string
    {
        $scopes = [
            'google'   => 'openid email profile',
            'facebook' => 'email public_profile',
            'apple'    => 'name email',
        ];

        return $scopes[$provider] ?? 'openid';
    }

    /**
     * Exchange authorization code for user info.
     * Production: calls Google/Facebook/Apple token endpoints.
     */
    private function exchangeCode(string $provider, string $code): array
    {
        $config = $this->getOAuthConfig($provider);
        if (!$config) {
            throw new \RuntimeException('OAuth provider not configured');
        }

        switch ($provider) {
            case 'google':
                return $this->exchangeGoogle($code, $config);
            case 'facebook':
                return $this->exchangeFacebook($code, $config);
            case 'apple':
                return $this->exchangeApple($code, $config);
            default:
                throw new \RuntimeException('Unknown provider');
        }
    }

    private function getOAuthConfig(string $provider): ?array
    {
        // Read from PlatformConfig
        $configJson = \app\model\PlatformConfig::get('oauth', $provider, null);
        if (!$configJson) {
            // Fallback to env
            $envPrefix = strtoupper($provider);
            return [
                'client_id' => getenv("OAUTH_{$envPrefix}_CLIENT_ID") ?: '',
                'client_secret' => getenv("OAUTH_{$envPrefix}_CLIENT_SECRET") ?: '',
                'redirect_uri' => getenv("OAUTH_{$envPrefix}_REDIRECT_URI") ?: '',
            ];
        }
        return json_decode($configJson, true);
    }

    private function exchangeGoogle(string $code, array $config): array
    {
        // POST https://oauth2.googleapis.com/token
        // Returns: { access_token, id_token }
        // Then GET https://www.googleapis.com/oauth2/v3/userinfo
        $http = new \GuzzleHttp\Client(['timeout' => 10]);

        $resp = $http->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'code' => $code,
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect_uri'],
                'grant_type' => 'authorization_code',
            ],
        ]);
        $tokenData = json_decode((string)$resp->getBody(), true);

        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('Google token exchange failed');
        }

        $userResp = $http->get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => ['Authorization' => 'Bearer ' . $tokenData['access_token']],
        ]);
        $userData = json_decode((string)$userResp->getBody(), true);

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

    private function exchangeFacebook(string $code, array $config): array
    {
        $http = new \GuzzleHttp\Client(['timeout' => 10]);

        $resp = $http->get('https://graph.facebook.com/v18.0/oauth/access_token', [
            'query' => [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect_uri'],
                'code' => $code,
            ],
        ]);
        $tokenData = json_decode((string)$resp->getBody(), true);

        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('Facebook token exchange failed');
        }

        $userResp = $http->get('https://graph.facebook.com/me', [
            'query' => [
                'fields' => 'id,name,email,picture',
                'access_token' => $tokenData['access_token'],
            ],
        ]);
        $userData = json_decode((string)$userResp->getBody(), true);

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

    private function exchangeApple(string $code, array $config): array
    {
        // Apple Sign In requires JWT client_secret generation
        // For production, generate client_secret JWT with ES256 key
        $http = new \GuzzleHttp\Client(['timeout' => 10]);

        $resp = $http->post('https://appleid.apple.com/auth/token', [
            'form_params' => [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $config['redirect_uri'],
            ],
        ]);
        $tokenData = json_decode((string)$resp->getBody(), true);

        $idToken = $tokenData['id_token'] ?? '';
        if (empty($idToken)) {
            throw new \RuntimeException('Apple id_token missing');
        }

        $parts = explode('.', $idToken);
        $payload = isset($parts[1]) ? json_decode(base64_decode($parts[1]), true) : [];

        if (empty($payload['sub'])) {
            throw new \RuntimeException('Apple user info missing sub');
        }

        return [
            'provider' => 'apple',
            'open_id' => $payload['sub'],
            'nickname' => 'Apple User',
            'email' => $payload['email'] ?? '',
            'avatar' => '',
        ];
    }
}
