<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use support\Request;
use support\Response;
use Throwable;

class CaptchaController
{
    /**
     * 生成点击验证码
     * POST /api/captcha/generate
     *
     * 返回: { key, image (base64 PNG), extra: { targets: [{order, text}] } }
     */
    public function generate(Request $request): Response
    {
        $difficulty = $request->input('difficulty', 'medium');

        try {
            $result = captcha_create('click', ['difficulty' => $difficulty]);

            return json([
                'code' => 0,
                'message' => 'success',
                'data' => [
                    'key' => $result['key'],
                    'image' => explode(',', $result['image'], 2)[1] ?? $result['image'], // base64 PNG
                    'extra' => [
                        'targets' => $result['extra']['targets'],
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            return json([
                'code' => 500,
                'message' => '验证码生成失败',
                'data' => [],
            ]);
        }
    }

    /**
     * 校验点击验证码
     * POST /api/captcha/verify
     *
     * 请求: { key, clicks: [{x, y}, ...] }
     */
    public function verify(Request $request): Response
    {
        $key = $request->input('key', '');
        $clicks = $request->input('clicks', []);

        if (empty($key) || empty($clicks)) {
            return json(['code' => 422, 'message' => '缺少验证参数', 'data' => []]);
        }

        $valid = captcha_verify($key, 'click', captcha_clicks($clicks));

        return json([
            'code' => $valid ? 0 : 422,
            'message' => $valid ? '验证通过' : '验证失败，请重试',
            'data' => ['valid' => $valid],
        ]);
    }
}
