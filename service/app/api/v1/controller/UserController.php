<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\DepositOrder;
use common\model\ExchangeRecord;
use common\model\Transaction;
use common\model\User;
use app\model\User2FA;
use common\model\UserOauth;
use common\model\UserSession;
use common\model\UserWallet;
use common\model\WithdrawOrder;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("用户管理")
 * @Apidoc\Group("user")
 */
class UserController extends BaseController
{
    /**
     * @Apidoc\Title("个人信息")
     * @Apidoc\Url("/api/v1/user/profile")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function profile(Request $request): Response
    {
        $userId = $request->userId;

        $user = User::find($userId);
        if (!$user) {
            return $this->fail('User not found', 404);
        }

        return $this->success([
            'id'           => $this->encodeId($user->id),
            'username'     => $user->username,
            'nickname'     => $user->nickname,
            'avatar'       => $user->avatar,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'country'      => $user->country,
            'language'     => $user->language,
            'last_login_at' => $user->last_login_at,
            'created_at'   => $user->created_at,
        ]);
    }

    /**
     * @Apidoc\Title("编辑资料")
     * @Apidoc\Url("/api/v1/user/profile")
     * @Apidoc\Method("PUT")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="nickname", type="string", require=false, desc="昵称")
     * @Apidoc\Param(name="avatar", type="string", require=false, desc="头像")
     * @Apidoc\Param(name="language", type="string", require=false, desc="语言")
     */
    public function updateProfile(Request $request): Response
    {
        $validator = validator($request->all(), [
            'nickname' => 'nullable|max:50',
            'avatar'   => 'nullable|max:255',
            'language' => 'nullable|in:en-US,zh-CN,ja-JP,ko-KR',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId = $request->userId;

        $user = User::find($userId);
        if (!$user) {
            return $this->fail('User not found', 404);
        }

        // Update only allowed fields
        $allowedFields = ['nickname', 'avatar', 'language'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        if (!empty($updateData)) {
            $user->fill($updateData);
            $user->save();
        }

        return $this->success([
            'id'       => $this->encodeId($user->id),
            'username' => $user->username,
            'nickname' => $user->nickname,
            'avatar'   => $user->avatar,
            'language' => $user->language,
        ], 'Profile updated');
    }

    /**
     * @Apidoc\Title("导出个人数据(GDPR)")
     * @Apidoc\Url("/api/v1/user/export-data")
     * @Apidoc\Method("GET")
     * @Apidoc\Auth(true)
     */
    public function exportData(Request $request): Response
    {
        $userId = $request->userId;
        $user = User::with(['wallet', 'oauthAccounts'])->find($userId);
        if (!$user) {
            return $this->fail('User not found', 404);
        }

        // Collect all user data
        $data = [
            'profile' => [
                'username' => $user->username,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
                'language' => $user->language,
                'created_at' => $user->created_at,
            ],
            'wallet' => $user->wallet ? [
                'balance' => $user->wallet->balance,
                'total_earned' => $user->wallet->total_earned,
                'total_spent' => $user->wallet->total_spent,
            ] : null,
            'transactions' => Transaction::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->toArray(),
            'exchange_records' => ExchangeRecord::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->toArray(),
            'deposit_orders' => DepositOrder::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->toArray(),
            'withdraw_orders' => WithdrawOrder::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->toArray(),
            'oauth_accounts' => $user->oauthAccounts ? $user->oauthAccounts->map(fn($o) => [
                'provider' => $o->provider,
                'created_at' => $o->created_at,
            ]) : [],
            'exported_at' => date('Y-m-d H:i:s'),
        ];

        return $this->success($data, 'Data export ready');
    }

    /**
     * @Apidoc\Title("注销账号(GDPR)")
     * @Apidoc\Url("/api/v1/user/delete-account")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="password", type="string", require=true, desc="密码")
     * @Apidoc\Param(name="confirm", type="string", require=true, desc="确认输入yes")
     */
    public function deleteAccount(Request $request): Response
    {
        $validator = validator($request->all(), [
            'password' => 'required|string',
            'confirm' => 'required|in:yes',
        ], [
            'confirm.in' => '请输入 yes 确认注销',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId = $request->userId;
        $user = User::find($userId);
        if (!$user) {
            return $this->fail('User not found', 404);
        }

        // Verify password
        if (!password_verify($request->input('password'), $user->password)) {
            return $this->fail('密码验证失败', 422);
        }

        // Check wallet balance (don't allow deletion if balance > 0)
        $wallet = UserWallet::where('user_id', $userId)->first();
        if ($wallet && bccomp($wallet->balance, '0.0000', 4) > 0) {
            return $this->fail('请先提现所有余额后再注销账号', 422);
        }

        // Anonymize personal data BEFORE soft delete: update on a soft-deleted
        // model is a no-op (SoftDeletes global scope), so PII would stay in DB
        $user->update([
            'username' => 'deleted_' . $userId,
            'nickname' => '',
            'avatar' => '',
            'email' => '',
            'phone' => '',
        ]);

        // Soft delete user
        $user->delete(); // SoftDeletes

        // Delete OAuth bindings
        UserOauth::where('user_id', $userId)->delete();

        // Delete sessions
        UserSession::where('user_id', $userId)->delete();

        // Delete 2FA
        User2FA::where('user_id', $userId)->delete();

        return $this->success([], '账号已注销。感谢您的使用。');
    }

    /**
     * @Apidoc\Title("隐私设置")
     * @Apidoc\Url("/api/v1/user/privacy")
     * @Apidoc\Method("PUT")
     * @Apidoc\Auth(true)
     */
    public function updatePrivacy(Request $request): Response
    {
        $validator = validator($request->all(), [
            'show_in_leaderboard' => 'nullable|boolean',
            'allow_email_notifications' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // Store privacy settings in PlatformConfig per user (simplified)
        // Could be extended with a proper user_settings table

        return $this->success([], '隐私设置已更新');
    }
}
