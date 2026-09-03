<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\ShareLink;
use common\model\User;
use app\model\User2FA;
use common\model\UserWallet;
use hg\apidoc\annotation as Apidoc;
use support\Db;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("用户认证")
 * @Apidoc\Group("auth")
 */
class AuthController extends BaseController
{
    /**
     * @Apidoc\Title("用户注册")
     * @Apidoc\Url("/api/v1/auth/register")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="username", type="string", require=true, desc="用户名")
     * @Apidoc\Param(name="password", type="string", require=true, desc="密码")
     * @Apidoc\Param(name="email", type="string", require=false, desc="邮箱")
     * @Apidoc\Param(name="share_code", type="string", require=false, desc="分享短码(裂变转化)")
     */
    public function register(Request $request): Response
    {
        $validator = validator($request->all(), [
            'username'   => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'password'   => 'required|min:6|max:32',
            'email'      => 'nullable|email',
            'share_code' => 'nullable|string|max:12',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $username = $request->input('username');
        $password = $request->input('password');
        $email    = $request->input('email');

        // Check username unique
        $exists = User::where('username', $username)->exists();
        if ($exists) {
            return $this->fail('Username already exists', 422);
        }

        // Create user + wallet in one transaction: wallet failure must roll back
        // the user, otherwise a crashed signup leaves an orphan account
        $user = null;
        $userId = Db::transaction(function () use ($username, $password, $email, $request, &$user) {
            $userId = $this->generateId();

            $user = new User();
            $user->id            = $userId;
            $user->username      = $username;
            $user->password      = password_hash($password, PASSWORD_BCRYPT);
            $user->nickname      = $username;
            $user->avatar        = '';
            $user->email         = $email;
            $user->status        = 1;
            $user->last_login_at = date('Y-m-d H:i:s');
            $user->last_login_ip = $request->getRealIp() ?? '';
            $user->save();

            // Create wallet with 0 balance
            $wallet = new UserWallet();
            $wallet->id            = $this->generateId();
            $wallet->user_id       = $userId;
            $wallet->balance       = '0.0000';
            $wallet->frozen_balance = '0.0000';
            $wallet->total_earned  = '0.0000';
            $wallet->total_spent   = '0.0000';
            $wallet->version       = 0;
            $wallet->save();

            return $userId;
        });

        // Generate tokens
        $accessToken  = jwt_wrapper()->create(['sub' => $userId, 'username' => $username]);
        $refreshToken = jwt_wrapper()->create(['sub' => $userId, 'token_type' => 'refresh']);

        // M4 裂变转化：注册带分享短码 → 绑定（无效码/已绑定/异常静默返回 null，不阻断注册）
        $shareBinding = null;
        if ($shareCode = trim((string) $request->input('share_code', ''))) {
            $shareBinding = ShareLink::bindConversion($userId, $shareCode);
        }

        return $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'       => $this->encodeId($userId),
                'username' => $username,
                'nickname' => $user->nickname,
                'avatar'   => $user->avatar,
            ],
            'share_binding' => $shareBinding,
        ], 'Registration successful');
    }

    /**
     * @Apidoc\Title("用户登录")
     * @Apidoc\Url("/api/v1/auth/login")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="username", type="string", require=true, desc="用户名")
     * @Apidoc\Param(name="password", type="string", require=true, desc="密码")
     */
    public function login(Request $request): Response
    {
        $validator = validator($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $username = $request->input('username');
        $password = $request->input('password');

        // Find user by username
        $user = User::where('username', $username)->first();
        if (!$user || !password_verify($password, $user->password)) {
            return $this->fail('Invalid username or password', 401);
        }

        // Check status
        if ((int) $user->status !== 1) {
            return $this->fail('Account is disabled', 403);
        }

        // 2FA 已开启：不签发正式 token，返回短期票据供 verify 换发
        $has2fa = User2FA::where('user_id', $user->id)
            ->where('is_enabled', 1)
            ->exists();
        if ($has2fa) {
            $pendingToken = jwt_wrapper()->create(['sub' => $user->id, 'scope' => 'pending_2fa'], 600);
            return $this->success([
                'require_2fa'      => true,
                'pending_2fa_token' => $pendingToken,
            ], '2FA verification required');
        }

        // Update login info
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $request->getRealIp() ?? '';
        $user->save();

        // Generate tokens
        $accessToken  = jwt_wrapper()->create(['sub' => $user->id, 'username' => $user->username]);
        $refreshToken = jwt_wrapper()->create(['sub' => $user->id, 'token_type' => 'refresh']);

        return $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'       => $this->encodeId($user->id),
                'username' => $user->username,
                'nickname' => $user->nickname,
                'avatar'   => $user->avatar,
            ],
        ], 'Login successful');
    }

    /**
     * @Apidoc\Title("刷新Token")
     * @Apidoc\Url("/api/v1/auth/refresh")
     * @Apidoc\Method("POST")
     */
    public function refresh(Request $request): Response
    {
        try {
            // 校验原 refresh token（token_type=refresh 且未过期/未拉黑），并轮换为新 refresh token
            $newRefresh = jwt_wrapper()->refresh();
            $payload = jwt_wrapper()->decode($newRefresh);
            $sub = (int) ($payload['sub'] ?? 0);
            if ($sub <= 0) {
                return $this->fail('Invalid refresh token', 401);
            }
            $accessToken = jwt_wrapper()->create(['sub' => $sub]);
        } catch (\Throwable $e) {
            return $this->fail('Invalid or expired refresh token', 401);
        }

        return $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $newRefresh,
        ], 'Token refreshed');
    }
}
