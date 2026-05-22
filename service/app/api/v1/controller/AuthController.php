<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\User;
use common\model\UserWallet;
use hg\apidoc\annotation as Apidoc;
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
     * @Apidoc\Url("/api/auth/register")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="username", type="string", require=true, desc="用户名")
     * @Apidoc\Param(name="password", type="string", require=true, desc="密码")
     * @Apidoc\Param(name="email", type="string", require=false, desc="邮箱")
     */
    public function register(Request $request): Response
    {
        $validator = validator($request->all(), [
            'username' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'password' => 'required|min:6|max:32',
            'email'    => 'nullable|email',
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

        // Create user
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

        // Generate tokens
        $accessToken  = jwt()->create(['sub' => $userId, 'username' => $username]);
        $refreshToken = jwt()->create(['sub' => $userId, 'type' => 'refresh']);

        return $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'       => $this->encodeId($userId),
                'username' => $username,
                'nickname' => $user->nickname,
                'avatar'   => $user->avatar,
            ],
        ], 'Registration successful');
    }

    /**
     * @Apidoc\Title("用户登录")
     * @Apidoc\Url("/api/auth/login")
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

        // Update login info
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $request->getRealIp() ?? '';
        $user->save();

        // Generate tokens
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
        ], 'Login successful');
    }

    /**
     * @Apidoc\Title("刷新Token")
     * @Apidoc\Url("/api/auth/refresh")
     * @Apidoc\Method("POST")
     */
    public function refresh(Request $request): Response
    {
        try {
            $accessToken = jwt()->refresh();
        } catch (\Throwable $e) {
            $accessToken = jwt()->create(['sub' => $request->userId]);
        }

        $refreshToken = jwt()->create(['sub' => $request->userId]);

        return $this->success([
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ], 'Token refreshed');
    }
}
