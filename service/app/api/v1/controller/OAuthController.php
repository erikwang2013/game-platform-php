<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\User;
use common\model\UserOauth;
use common\model\UserWallet;
use support\Request;
use support\Response;

class OAuthController extends BaseController
{
    /**
     * GET /api/auth/oauth/{provider}
     *
     * Build and return the OAuth authorization URL for the given provider.
     * MVP: returns a redirect URL; the actual token exchange happens in callback.
     */
    public function redirect(Request $request, string $provider): Response
    {
        $provider = strtolower($provider);

        if (!in_array($provider, ['google', 'facebook', 'apple'], true)) {
            return $this->fail('Invalid OAuth provider', 422);
        }

        // MVP: build a simple redirect URL with state for CSRF protection.
        // In production, integrate with the provider's actual OAuth SDK.
        $state = bin2hex(random_bytes(16));
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
     * POST /api/auth/oauth/{provider}/callback
     *
     * Exchange the authorization code for user info and authenticate.
     * MVP: accepts the code and extracts mock user data.
     * Production: exchange code for access_token, then fetch user info.
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

        // MVP: extract mock user data from the code.
        // In production, exchange $code for access_token via provider API,
        // then fetch user profile (open_id, nickname, avatar, email, etc.).
        $oauthUser = $this->mockExchangeCode($provider, $code);

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
     * MVP: mock the authorization code exchange.
     *
     * In production, this method should:
     * 1. POST to the provider's token endpoint with code, client_id, client_secret, redirect_uri
     * 2. Receive access_token + refresh_token
     * 3. GET the provider's userinfo endpoint with the access_token
     * 4. Return normalized user data (open_id, union_id, nickname, avatar, email)
     */
    private function mockExchangeCode(string $provider, string $code): array
    {
        // For MVP, derive a deterministic open_id from the code.
        // In production, the open_id comes from the provider's userinfo endpoint.
        $mockId = substr(hash('sha256', $provider . $code), 0, 16);

        $mockData = [
            'google' => [
                'open_id'  => 'goog_' . $mockId,
                'nickname' => 'GoogleUser',
                'avatar'   => '',
                'email'    => 'google_' . $mockId . '@example.com',
            ],
            'facebook' => [
                'open_id'  => 'fb_' . $mockId,
                'nickname' => 'FBUser',
                'avatar'   => '',
                'email'    => 'fb_' . $mockId . '@example.com',
            ],
            'apple' => [
                'open_id'  => 'apple_' . $mockId,
                'nickname' => 'AppleUser',
                'avatar'   => '',
                'email'    => 'apple_' . $mockId . '@example.com',
            ],
        ];

        $data = $mockData[$provider] ?? $mockData['google'];

        $data['access_token']  = 'mock_at_' . $mockId;
        $data['refresh_token'] = 'mock_rt_' . $mockId;
        $data['token_expires_at'] = date('Y-m-d H:i:s', time() + 3600);

        return $data;
    }
}
