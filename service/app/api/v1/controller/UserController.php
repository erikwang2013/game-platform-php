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
 * @Apidoc\Title("User")
 * @Apidoc\Group("user")
 */
class UserController extends BaseController
{
    /**
     * @Apidoc\Title("User Profile")
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
     * @Apidoc\Title("Update Profile")
     * @Apidoc\Url("/api/user/profile")
     * @Apidoc\Method("PUT")
     * @Apidoc\Header(name:"Authorization",require:true,desc:"Bearer Token")
     * @Apidoc\Param(name:"nickname",type:"string",require:false,desc:"Nickname (max 50 chars)")
     * @Apidoc\Param(name:"avatar",type:"string",require:false,desc:"Avatar URL")
     * @Apidoc\Param(name:"language",type:"string",require:false,desc:"Language preference (en-US, zh-CN, ja-JP, ko-KR)")
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
}
