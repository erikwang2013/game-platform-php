<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace app\api\v1\controller;
use common\model\DeviceToken;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Log;
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
            $this->rebind($existing, $userId, $platform);
            return $this->success(['registered' => true]);
        }

        try {
            $dt = new DeviceToken();
            $dt->id = $this->generateId();
            $dt->user_id = $userId;
            $dt->platform = $platform;
            $dt->token = $token;
            $dt->created_at = date('Y-m-d H:i:s');
            $dt->save();
        } catch (\PDOException $e) {
            // 并发同 token：预查时对手还没提交，这里撞上 idx_token。语义与顺序路径完全一致
            // （谁后注册谁持有），故按改绑收尾，而不是把这个竞态暴露成 500 / 用户错误。
            if (!self::isDuplicateOnKey($e, 'idx_token')) {
                throw $e;
            }
            $raced = DeviceToken::where('token', $token)->first();
            if ($raced === null) {
                throw $e; // 撞的不是我们认识的那条路径（例如 snowflake 撞主键）：原样上抛
            }
            $this->rebind($raced, $userId, $platform);
        }

        return $this->success(['registered' => true]);
    }

    /**
     * 改绑：把 token 归属换成 $userId，归属**真的变了**时留一条可查记录。
     *
     * 为什么允许改绑：推送令牌属于设备不属于账号（FCM/APNS 标准做法，谁持有谁注册），
     * 而 service 没有 logout 端点 —— 只认原主会让同设备换人登录后这个 token 永久绑死旧账号。
     * 留痕是为了让「token 被改绑到谁」这件事有据可查（同设备账号关联是风控关注面）。
     *
     * 只记归属变化：同账号重复注册是每次冷启动都发生的高频路径，记了只是噪音。
     * 不记 token 原文（推送凭据，日志会外发），device_token_id 足以定位到行。
     */
    private function rebind(DeviceToken $row, int $userId, string $platform): void
    {
        if ((int) $row->user_id !== $userId) {
            Log::info('Device token rebound', [
                'device_token_id' => $row->id,
                'from_user_id' => $row->user_id,
                'to_user_id' => $userId,
                'platform' => $platform,
            ]);
        }

        $row->user_id = $userId;
        $row->platform = $platform;
        $row->save();
    }

    /**
     * 这次重复键冲突是不是「撞在指定唯一键上」。
     *
     * 与 ReferralController / ActivityService 同一口径（成对判据，两半都要有）：
     *  - 是 idx_token ⇒ 并发双写，与顺序路径同义 ⇒ 改绑收尾；
     *  - 不是（典型是 snowflake 撞主键）⇒ **原样上抛**。
     * 只按错误码 1062 一刀切会把系统性撞号伪装成正常改绑，比不捕获更糟。
     */
    private static function isDuplicateOnKey(\Throwable $e, string $key): bool
    {
        if (!$e instanceof \PDOException) {
            return false; // 非 PDO 异常没有 errorInfo：不认，交给上层原样上抛
        }

        return in_array($e->errorInfo[1] ?? null, [1062, 23000], true)
            && str_contains($e->getMessage(), $key);
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
