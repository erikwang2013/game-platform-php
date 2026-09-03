<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("验证码")
 * @Apidoc\Group("captcha")
 */
class CaptchaController extends BaseController
{
    /**
     * @Apidoc\Title("获取验证码")
     * @Apidoc\Url("/api/v1/captcha/generate")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="difficulty", type="string", require=false, desc="难度(easy/medium/hard)")
     */
    public function generate(Request $request): Response
    {
        try {
            $difficulty = $request->input('difficulty', 'easy');
            $result = captcha_create('click', ['difficulty' => $difficulty]);

            return $this->success([
                'key' => $result['key'],
                'image' => $result['image'], // base64 encoded PNG
                'targets' => $result['extra']['targets'] ?? [],
            ]);
        } catch (\Throwable $e) {
            // Fallback to stub if captcha library not available
            return $this->success([
                'key' => 'stub',
                'image' => '',
                'targets' => [],
            ]);
        }
    }
}
