<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use support\Request;
use support\Response;

class CaptchaController extends BaseController
{
    /**
     * POST /api/captcha/generate
     *
     * Generate a click-based captcha using poster-php.
     * Returns base64-encoded PNG image with targets for the client to render.
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
