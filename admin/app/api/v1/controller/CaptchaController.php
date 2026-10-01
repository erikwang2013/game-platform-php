<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;
use Throwable;

#[Apidoc\Title("点击验证码")]
#[Apidoc\Group("captcha")]
class CaptchaController
{
    /**
     * 生成点击验证码
     * POST /api/captcha/generate
     *
     * 返回: { key, image (base64 PNG), extra: { texts: [{order, text}] } }
     */
    #[Apidoc\Url("/api/v1/captcha/generate")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "difficulty", type: "string", default: "medium", desc: "验证码难度：easy/medium/hard")]
    #[Apidoc\Returned(name: "key", type: "string", desc: "验证码 key，校验时回传")]
    #[Apidoc\Returned(name: "image", type: "string", desc: "验证码图片（base64 PNG）")]
    #[Apidoc\Returned(name: "extra", type: "object", desc: "附加数据，texts 为待点击文字列表（元素含 order/text）")]
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
                        'texts' => $result['extra']['texts'],
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            return json([
                'code' => 500,
                'message' => trans('Failed to generate captcha'),
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
    #[Apidoc\Url("/api/v1/captcha/verify")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "key", type: "string", require: true, desc: "验证码 key")]
    #[Apidoc\Param(name: "clicks", type: "array", require: true, desc: "点击坐标集合，元素含 x/y")]
    #[Apidoc\Returned(name: "valid", type: "boolean", desc: "是否验证通过")]
    public function verify(Request $request): Response
    {
        $key = $request->input('key', '');
        $clicks = $request->input('clicks', []);

        // key 必须是字符串：`empty()` 挡不住非空数组（`key[]=xxx` ⇒ `['xxx']`，empty 为 false），
        // 数组会喂给下面那句校验的 `string $key` 形参抛 TypeError；本文件是 strict_types，
        // 而 verify() 又没有 try/catch ⇒ 异常漏到框架层被渲染成 HTTP 500 + 完整堆栈（含绝对路径）。
        // 这里用 is_string 而不是追加 catch：这是入参形状，属于信任边界，该在最外层挡掉。
        // 同族先例见 config/plugin/erikwang2013/apidoc/route.php:29-45（`password[]=x`）。
        if (!is_string($key) || $key === '' || empty($clicks)) {
            return json(['code' => 422, 'message' => trans('Missing verification parameters'), 'data' => []]);
        }

        $valid = captcha_verify_from_ip($request->getRealIp(), $key, 'click', captcha_clicks($clicks));

        return json([
            'code' => $valid ? 0 : 422,
            'message' => $valid ? trans('Verification passed') : trans('Verification failed, please try again'),
            'data' => ['valid' => $valid],
        ]);
    }
}
