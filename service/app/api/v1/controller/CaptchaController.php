<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("验证码")]
#[Apidoc\Group("captcha")]
class CaptchaController extends BaseController
{
    #[Apidoc\Title("获取验证码")]
    #[Apidoc\Url("/api/v1/captcha/generate")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "difficulty", type: "string", require: false, desc: "难度(easy/medium/hard)")]
    public function generate(Request $request): Response
    {
        try {
            $difficulty = $request->input('difficulty', 'easy');
            $result = captcha_create('click', ['difficulty' => $difficulty]);

            return $this->success([
                'key' => $result['key'],
                'image' => explode(',', $result['image'], 2)[1] ?? $result['image'], // base64 PNG(剥离 data URI 前缀)
                // 库只下发 texts(text/order, 刻意不含 x/y: 坐标为服务端校验依据, 下发即泄题)
                'extra' => [
                    'texts' => $result['extra']['texts'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            // Fallback to stub if captcha library not available
            return $this->success([
                'key' => 'stub',
                'image' => '',
                'extra' => [
                    'texts' => [],
                ],
            ]);
        }
    }
}
