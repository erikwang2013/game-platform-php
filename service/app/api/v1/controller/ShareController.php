<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\model\ShareLink;
use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * 分享短码（M4）：生成短码 + 落地页点击上报（匿名）。
 * 裂变转化（conversions）由注册链路按 short_code 落库（AuthController::register → ShareLink::bindConversion）。
 *
 * @Apidoc\Title("分享")
 * @Apidoc\Group("share")
 */
class ShareController extends BaseController
{
    private const CODE_CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * @Apidoc\Title("生成分享短码")
     * @Apidoc\Url("/api/v1/shares")
     * @Apidoc\Method("POST")
     * @Apidoc\Auth(true)
     * @Apidoc\Param(name="activity_id", type="string", require=false, desc="关联活动(hashid)")
     */
    public function create(Request $request): Response
    {
        $activityId = 0;
        if ($request->input('activity_id')) {
            $activityId = $this->decodeId($request->input('activity_id'));
        }

        $link = new ShareLink();
        $link->id = $this->generateId();
        $link->user_id = $request->userId;
        $link->activity_id = $activityId;
        $link->short_code = $this->uniqueCode();
        $link->save();

        return $this->success([
            'short_code'  => $link->short_code,
            'expires_at'  => $link->expires_at,
        ], 'Created');
    }

    /**
     * @Apidoc\Title("分享落地页点击")
     * @Apidoc\Url("/api/v1/shares/visit")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="short_code", type="string", require=true, desc="分享短码")
     */
    public function visit(Request $request): Response
    {
        $validator = validator($request->all(), [
            'short_code' => 'required|string|max:12',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $code = trim($request->input('short_code'));
        $link = ShareLink::where('short_code', $code)->first();
        if (!$link || ($link->expires_at && strtotime($link->expires_at) < time())) {
            return $this->fail('Invalid short code', 404);
        }

        // 原子自增，不返回分享者信息（匿名落地页）
        ShareLink::where('id', $link->id)->increment('clicks');

        return $this->success([]);
    }

    private function uniqueCode(): string
    {
        for ($i = 0; $i < 3; $i++) {
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= self::CODE_CHARS[random_int(0, 61)];
            }
            if (!ShareLink::where('short_code', $code)->exists()) {
                return $code;
            }
        }
        // ponytail: 3 次碰撞重试，61^8 空间下理论不可达；仍撞则报错不静默复用
        throw new \RuntimeException('short code collision');
    }
}
