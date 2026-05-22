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
 * @Apidoc\Title("Captcha")
 * @Apidoc\Group("captcha")
 */
class CaptchaController extends BaseController
{
    /**
     * @Apidoc\Title("Generate Captcha")
     * @Apidoc\Url("/api/captcha/generate")
     * @Apidoc\Method("POST")
     */
    public function generate(Request $request): Response
    {
        return $this->success([
            'key'   => 'stub',
            'image' => '',
        ]);
    }
}
