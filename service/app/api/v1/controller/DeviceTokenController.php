<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\api\v1\controller;
use common\model\DeviceToken;
use support\Request;
use support\Response;

class DeviceTokenController extends BaseController
{
    public function register(Request $request): Response
    {
        $validator = validator($request->all(), [
            'platform' => 'required|in:fcm,apns,harmonyos',
            'token' => 'required|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $platform = $request->input('platform');
        $token = $request->input('token');
        $userId = $request->userId;

        $existing = DeviceToken::where('token', $token)->first();
        if ($existing) {
            $existing->user_id = $userId;
            $existing->platform = $platform;
            $existing->save();
        } else {
            $dt = new DeviceToken();
            $dt->id = $this->generateId();
            $dt->user_id = $userId;
            $dt->platform = $platform;
            $dt->token = $token;
            $dt->created_at = date('Y-m-d H:i:s');
            $dt->save();
        }

        return $this->success(['registered' => true]);
    }

    public function unregister(Request $request): Response
    {
        $validator = validator($request->all(), [
            'token' => 'required|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        DeviceToken::where('user_id', $request->userId)
            ->where('token', $request->input('token'))
            ->delete();

        return $this->success(['unregistered' => true]);
    }
}
