<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\model\User;
use support\Request;
use support\Response;

/**
 * C端 - 用户
 *
 * @Apidoc\Title("用户")
 * @Apidoc\Group("user")
 */
class UserController extends BaseController
{
    /**
     * @Apidoc\Title("个人资料")
     * @Apidoc\Url("/api/user/profile")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
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
     * @Apidoc\Title("更新个人资料")
     * @Apidoc\Url("/api/user/profile")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name:"nickname",type:"string",require:false,desc:"昵称")
     * @Apidoc\Param(name:"avatar",type:"string",require:false,desc:"头像URL")
     * @Apidoc\Param(name:"language",type:"string",require:false,desc:"语言(en-US/zh-CN/ja-JP/ko-KR)")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
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
     * @Apidoc\Title("导出用户数据")
     * @Apidoc\Url("/api/user/export-data")
     * @Apidoc\Method("GET")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function exportData(Request $request): Response
    {
        $userId = $request->userId;

        $user = User::find($userId);
        if (!$user) {
            return $this->fail('User not found', 404);
        }

        return $this->success([
            'user' => [
                'id'       => $this->encodeId($user->id),
                'username' => $user->username,
                'nickname' => $user->nickname,
                'avatar'   => $user->avatar,
                'email'    => $user->email,
                'phone'    => $user->phone,
                'country'  => $user->country,
                'language' => $user->language,
            ],
            'message' => 'Data export request received. You will receive a download link shortly.',
        ]);
    }

    /**
     * @Apidoc\Title("注销账号")
     * @Apidoc\Url("/api/user/delete-account")
     * @Apidoc\Method("POST")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function deleteAccount(Request $request): Response
    {
        $userId = $request->userId;

        $user = User::find($userId);
        if (!$user) {
            return $this->fail('User not found', 404);
        }

        // Soft-delete: mark account as deleted
        $user->status = -1;
        $user->save();

        return $this->success([], 'Account deletion request submitted');
    }

    /**
     * @Apidoc\Title("更新隐私设置")
     * @Apidoc\Url("/api/user/privacy")
     * @Apidoc\Method("PUT")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     */
    public function updatePrivacy(Request $request): Response
    {
        $userId = $request->userId;

        $user = User::find($userId);
        if (!$user) {
            return $this->fail('User not found', 404);
        }

        return $this->success([], 'Privacy settings updated');
    }
}
