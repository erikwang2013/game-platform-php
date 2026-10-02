<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\Locale;
use common\model\DepositOrder;
use common\model\ExchangeRecord;
use common\model\Transaction;
use common\model\User;
use app\model\User2FA;
use common\model\UserOauth;
use common\model\UserSession;
use common\model\UserWallet;
use common\model\WithdrawOrder;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("用户管理")]
#[Apidoc\Group("user")]
class UserController extends BaseController
{
    #[Apidoc\Title("个人信息")]
    #[Apidoc\Url("/api/v1/user/profile")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function profile(Request $request): Response
    {
        $userId = $request->userId;

        $user = User::find($userId);
        if (!$user) {
            return $this->fail(trans('User not found'), 404);
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

    #[Apidoc\Title("编辑资料")]
    #[Apidoc\Url("/api/v1/user/profile")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Auth(true)]
    #[Apidoc\Param(name: "nickname", type: "string", require: false, desc: "昵称")]
    #[Apidoc\Param(name: "avatar", type: "string", require: false, desc: "头像")]
    #[Apidoc\Param(name: "language", type: "string", require: false, desc: "语言")]
    public function updateProfile(Request $request): Response
    {
        $validator = validator($request->all(), [
            'nickname' => 'nullable|max:50',
            'avatar'   => 'nullable|max:255',
            // 同上：白名单派生自 Locale，别在这里手写语言列表
            'language' => 'nullable|in:' . implode(',', Locale::accepted()),
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId = $request->userId;

        $user = User::find($userId);
        if (!$user) {
            return $this->fail(trans('User not found'), 404);
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

    #[Apidoc\Title("导出个人数据(GDPR)")]
    #[Apidoc\Url("/api/v1/user/export-data")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Auth(true)]
    public function exportData(Request $request): Response
    {
        $userId = $request->userId;
        $user = User::with(['wallet', 'oauthAccounts'])->find($userId);
        if (!$user) {
            return $this->fail(trans('User not found'), 404);
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

        return $this->success($data, trans('Data export ready'));
    }

    #[Apidoc\Title("注销账号(GDPR)")]
    #[Apidoc\Url("/api/v1/user/delete-account")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Auth(true)]
    #[Apidoc\Param(name: "password", type: "string", require: true, desc: "密码")]
    #[Apidoc\Param(name: "confirm", type: "string", require: true, desc: "确认输入yes")]
    public function deleteAccount(Request $request): Response
    {
        $validator = validator($request->all(), [
            'password' => 'required|string',
            'confirm' => 'required|in:yes',
        ], [
            'confirm.in' => trans('Please type yes to confirm account closure'),
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $userId = $request->userId;
        $user = User::find($userId);
        if (!$user) {
            return $this->fail(trans('User not found'), 404);
        }

        // Verify password
        if (!password_verify($request->input('password'), $user->password)) {
            return $this->fail(trans('Password verification failed'), 422);
        }

        // Check wallet balance (don't allow deletion if balance > 0)
        $wallet = UserWallet::where('user_id', $userId)->first();
        if ($wallet && bccomp($wallet->balance, '0.0000', 4) > 0) {
            return $this->fail(trans('Withdraw all balances before closing the account'), 422);
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

        return $this->success([], trans('Account closed. Thank you for using our service.'));
    }

    #[Apidoc\Title("隐私设置")]
    #[Apidoc\Url("/api/v1/user/privacy")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Auth(true)]
    public function updatePrivacy(Request $request): Response
    {
        $validator = validator($request->all(), [
            'show_in_leaderboard' => 'nullable|boolean',
            'allow_email_notifications' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // ⚠ 本端点**不写入任何地方**（原注释自认 "simplified / Could be extended"，但返回空 data +
        // "Privacy settings updated" ＝ **假成功**：客户端会以为设置已生效）。事实是：
        // show_in_leaderboard / allow_email_notifications 两个字段**全仓零读者**，也没有 user_settings
        // 表或 PlatformConfig 的 per-user 写入口 ⇒ 本次提交的值不会在任何地方生效。
        // 现在改成**仅回显** + 显式 persisted=false，让「没存」在报文里可判，且不静默吞掉输入。
        // 文案同步改成 "Privacy settings were not saved"：en 表是空表（键名即英文原句，回落即输出），
        // 只改 12 份译文的值、不动键名的话，英文用户仍会读到「已更新」那句假成功。
        // 要真生效得先有存储（产品功能，另批）。
        return $this->success([
            'persisted'                  => false,
            'show_in_leaderboard'        => $request->input('show_in_leaderboard'),
            'allow_email_notifications'  => $request->input('allow_email_notifications'),
        ], trans('Privacy settings were not saved'));
    }
}
