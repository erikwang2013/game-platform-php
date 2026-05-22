<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\User;
use common\model\UserOauth;
use common\model\UserWallet;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("OAuth")
 * @Apidoc\Group("auth")
 */
class OAuthController extends BaseController
{
    /**
     * @Apidoc\Title("OAuth Redirect")
     * @Apidoc\Url("/api/auth/oauth/{provider}")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name:"provider",type:"string",require:true,desc:"OAuth provider (google, facebook, apple)")
     */
    public function redirect(Request $request, string $provider): Response
    {
        $provider = strtolower($provider);

        if (!in_array($provider, ['google', 'facebook', 'apple'], true)) {
            return $this->fail('Unsupported OAuth provider. Supported: google, facebook, apple', 422);
        }

        $config = $this->getProviderConfig($provider);
        if (empty($config['client_id']) || empty($config['redirect_uri'])) {
            return $this->fail("OAuth provider [{$provider}] is not configured", 500);
        }

        $redirectUrl = $this->buildAuthUrl($provider, $config);

        return $this->success([
            'provider'     => $provider,
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * @Apidoc\Title("OAuth Callback")
     * @Apidoc\Url("/api/auth/oauth/{provider}/callback")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name:"provider",type:"string",require:true,desc:"OAuth provider (google, facebook, apple)")
     * @Apidoc\Param(name:"code",type:"string",require:true,desc:"Authorization code from provider")
     * @Apidoc\Param(name:"state",type:"string",require:false,desc:"State parameter for CSRF protection")
     */
    public function callback(Request $request, string $provider): Response
    {
        $provider = strtolower($provider);

        if (!in_array($provider, ['google', 'facebook', 'apple'], true)) {
            return $this->fail('Unsupported OAuth provider', 422);
        }

        $validator = validator($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $code = $request->input('code');

        $oauthUser = $this->fetchOauthUser($provider, $code);
        if (!$oauthUser) {
            return $this->fail('Failed to authenticate with ' . $provider, 401);
        }

        // Check if this OAuth account is already linked
        $existingOauth = UserOauth::where('provider', $provider)
            ->where('provider_user_id', $oauthUser['id'])
            ->first();

        if ($existingOauth) {
            $userId = $existingOauth->user_id;
        } else {
            // Try to find user by email
            $email = $oauthUser['email'] ?? null;
            $user = null;

            if ($email) {
                $user = User::where('email', $email)->first();
            }

            if (!$user) {
                // Create new user
                $userId = $this->generateId();
                $username = $this->generateUniqueUsername($oauthUser['name'] ?? ($provider . '_user'));

                $user = new User();
                $user->id       = $userId;
                $user->username = $username;
                $user->password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
                $user->nickname = $oauthUser['name'] ?? $username;
                $user->avatar   = $oauthUser['avatar'] ?? '';
                $user->email    = $email;
                $user->status   = 1;
                $user->last_login_at = date('Y-m-d H:i:s');
                $user->last_login_ip = $request->getRealIp() ?? '';
                $user->save();

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
            } else {
                $userId = $user->id;
            }

            // Link OAuth
            $oauth = new UserOauth();
            $oauth->id              = $this->generateId();
            $oauth->user_id         = $userId;
            $oauth->provider        = $provider;
            $oauth->provider_user_id = $oauthUser['id'];
            $oauth->access_token    = $oauthUser['access_token'] ?? '';
            $oauth->refresh_token   = $oauthUser['refresh_token'] ?? '';
            $oauth->raw_data        = $oauthUser['raw'] ?? [];
            $oauth->save();
        }

        $user = User::find($userId);
        if (!$user || (int) $user->status !== 1) {
            return $this->fail('Account is disabled', 403);
        }

        // Update login info
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $request->getRealIp() ?? '';
        $user->save();

        // Generate tokens
        $accessToken  = jwt()->create(['sub' => $userId, 'username' => $user->username]);
        $refreshToken = jwt()->create(['sub' => $userId, 'type' => 'refresh']);

        return $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'       => $this->encodeId($userId),
                'username' => $user->username,
                'nickname' => $user->nickname,
                'avatar'   => $user->avatar,
            ],
        ], 'OAuth login successful');
    }

    /**
     * Get OAuth provider configuration from environment.
     */
    private function getProviderConfig(string $provider): array
    {
        $prefix = strtoupper($provider);
        return [
            'client_id'     => getenv("OAUTH_{$prefix}_CLIENT_ID") ?: '',
            'client_secret' => getenv("OAUTH_{$prefix}_CLIENT_SECRET") ?: '',
            'redirect_uri'  => getenv("OAUTH_{$prefix}_REDIRECT_URI") ?: '',
        ];
    }

    /**
     * Build the authorization URL for the given provider.
     */
    private function buildAuthUrl(string $provider, array $config): string
    {
        $state = bin2hex(random_bytes(16));

        switch ($provider) {
            case 'google':
                return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                    'client_id'     => $config['client_id'],
                    'redirect_uri'  => $config['redirect_uri'],
                    'response_type' => 'code',
                    'scope'         => 'openid email profile',
                    'state'         => $state,
                    'access_type'   => 'offline',
                    'prompt'        => 'consent',
                ]);

            case 'facebook':
                return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
                    'client_id'     => $config['client_id'],
                    'redirect_uri'  => $config['redirect_uri'],
                    'response_type' => 'code',
                    'scope'         => 'email public_profile',
                    'state'         => $state,
                ]);

            case 'apple':
                return 'https://appleid.apple.com/auth/authorize?' . http_build_query([
                    'client_id'     => $config['client_id'],
                    'redirect_uri'  => $config['redirect_uri'],
                    'response_type' => 'code',
                    'scope'         => 'name email',
                    'response_mode' => 'form_post',
                    'state'         => $state,
                ]);

            default:
                return '';
        }
    }

    /**
     * Fetch OAuth user info from provider using authorization code.
     */
    private function fetchOauthUser(string $provider, string $code): ?array
    {
        $config = $this->getProviderConfig($provider);

        try {
            switch ($provider) {
                case 'google':
                    return $this->fetchGoogleUser($code, $config);
                case 'facebook':
                    return $this->fetchFacebookUser($code, $config);
                case 'apple':
                    return $this->fetchAppleUser($code, $config);
                default:
                    return null;
            }
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Fetch Google user info.
     */
    private function fetchGoogleUser(string $code, array $config): ?array
    {
        // Exchange code for token
        $tokenRes = $this->httpPost('https://oauth2.googleapis.com/token', [
            'client_id'     => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $config['redirect_uri'],
        ]);

        if (empty($tokenRes['access_token'])) {
            return null;
        }

        // Fetch user info
        $userInfo = $this->httpGet('https://www.googleapis.com/oauth2/v2/userinfo', [
            'Authorization: Bearer ' . $tokenRes['access_token'],
        ]);

        if (empty($userInfo['id'])) {
            return null;
        }

        return [
            'id'            => $userInfo['id'],
            'name'          => $userInfo['name'] ?? '',
            'email'         => $userInfo['email'] ?? '',
            'avatar'        => $userInfo['picture'] ?? '',
            'access_token'  => $tokenRes['access_token'] ?? '',
            'refresh_token' => $tokenRes['refresh_token'] ?? '',
            'raw'           => $userInfo,
        ];
    }

    /**
     * Fetch Facebook user info.
     */
    private function fetchFacebookUser(string $code, array $config): ?array
    {
        // Exchange code for token
        $tokenRes = $this->httpGet('https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
            'client_id'     => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code'          => $code,
            'redirect_uri'  => $config['redirect_uri'],
        ]));

        if (empty($tokenRes['access_token'])) {
            return null;
        }

        // Fetch user info
        $userInfo = $this->httpGet('https://graph.facebook.com/me?' . http_build_query([
            'fields'       => 'id,name,email,picture',
            'access_token' => $tokenRes['access_token'],
        ]));

        if (empty($userInfo['id'])) {
            return null;
        }

        return [
            'id'            => $userInfo['id'],
            'name'          => $userInfo['name'] ?? '',
            'email'         => $userInfo['email'] ?? '',
            'avatar'        => $userInfo['picture']['data']['url'] ?? '',
            'access_token'  => $tokenRes['access_token'] ?? '',
            'refresh_token' => '',
            'raw'           => $userInfo,
        ];
    }

    /**
     * Fetch Apple user info.
     */
    private function fetchAppleUser(string $code, array $config): ?array
    {
        // Apple uses JWT client secret generation
        // Simplified implementation - in production, generate client_secret from private key
        $tokenRes = $this->httpPost('https://appleid.apple.com/auth/token', [
            'client_id'     => $config['client_id'],
            'client_secret' => $this->generateAppleClientSecret($config),
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $config['redirect_uri'],
        ]);

        if (empty($tokenRes['id_token'])) {
            return null;
        }

        // Decode the ID token (JWT) to get user info
        $parts = explode('.', $tokenRes['id_token']);
        if (count($parts) < 2) {
            return null;
        }
        $payload = json_decode(base64_decode($parts[1]), true);

        if (empty($payload['sub'])) {
            return null;
        }

        return [
            'id'            => $payload['sub'],
            'name'          => $payload['email'] ?? '',
            'email'         => $payload['email'] ?? '',
            'avatar'        => '',
            'access_token'  => $tokenRes['access_token'] ?? '',
            'refresh_token' => $tokenRes['refresh_token'] ?? '',
            'raw'           => $payload,
        ];
    }

    /**
     * Generate Apple client secret (simplified — production should sign with private key).
     */
    private function generateAppleClientSecret(array $config): string
    {
        // In production, create a signed JWT with ES256 using the private key from Apple Developer
        // This stub returns empty; configure proper signing in production
        return $config['client_secret'] ?? '';
    }

    /**
     * Generate a unique username from the OAuth display name.
     */
    private function generateUniqueUsername(string $name): string
    {
        $base = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        $base = substr($base, 0, 30);
        if (empty($base)) {
            $base = 'user';
        }

        $username = $base;
        $suffix = 0;
        while (User::where('username', $username)->exists()) {
            $suffix++;
            $username = $base . '_' . $suffix;
        }

        return $username;
    }

    /**
     * Simple HTTP GET request with curl.
     */
    private function httpGet(string $url, array $headers = []): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Simple HTTP POST request with curl.
     */
    private function httpPost(string $url, array $data): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return null;
        }

        return json_decode($response, true);
    }
}
