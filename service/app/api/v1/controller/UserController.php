<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\User;
use support\Request;
use support\Response;

class UserController extends BaseController
{
    /**
     * GET /api/user/profile
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
     * PUT /api/user/profile
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
