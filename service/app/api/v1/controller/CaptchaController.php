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
     * Stub for MVP — returns empty image with a dummy key.
     */
    public function generate(Request $request): Response
    {
        return $this->success([
            'key'   => 'stub',
            'image' => '',
        ]);
    }
}
