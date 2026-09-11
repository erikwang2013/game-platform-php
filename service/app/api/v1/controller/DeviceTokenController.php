<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\api\v1\controller;
use common\model\DeviceToken;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("设备令牌")]
#[Apidoc\Group("device")]
class DeviceTokenController extends BaseController
{
    #[Apidoc\Title("注册设备推送令牌")]
    #[Apidoc\Url("/api/v1/device/token")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "platform", type: "string", require: true, desc: "推送平台：fcm/apns/harmonyos")]
    #[Apidoc\Param(name: "token", type: "string", require: true, desc: "推送令牌（最长 500）")]
    #[Apidoc\Returned(name: "registered", type: "boolean", desc: "是否注册成功")]
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

    #[Apidoc\Title("注销设备推送令牌")]
    #[Apidoc\Url("/api/v1/device/token")]
    #[Apidoc\Method("DELETE")]
    #[Apidoc\Param(name: "token", type: "string", require: true, desc: "推送令牌（最长 500）")]
    #[Apidoc\Returned(name: "unregistered", type: "boolean", desc: "是否注销成功")]
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
