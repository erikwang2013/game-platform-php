<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\Locale;
use common\service\TranslationService;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("语言管理")]
#[Apidoc\Group("language")]
class LanguageController extends BaseController
{
    #[Apidoc\Title("语言列表")]
    #[Apidoc\Url("/api/v1/language/list")]
    #[Apidoc\Method("GET")]
    public function list(Request $request): Response
    {
        $languages = TranslationService::getAvailableLanguages();

        return $this->success([
            'current' => TranslationService::getLocale(),
            'languages' => $languages,
        ]);
    }

    #[Apidoc\Title("切换语言")]
    #[Apidoc\Url("/api/v1/language/switch")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "locale", type: "string", require: true, desc: "语言代码，取值见 /api/v1/language/list 的 languages 键（如 zh-CN，短码 zh 亦可）")]
    public function switch(Request $request): Response
    {
        $validator = validator($request->all(), [
            // 白名单由 Locale 派生（短码 + C 端全码），13 语言一并生效；
            // 手写列表会随语言扩充静默过期，症状是"选了语言却 422"
            'locale' => 'required|in:' . implode(',', Locale::accepted()),
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $locale = $request->input('locale');
        TranslationService::setLocale($locale);

        // 已登录则把偏好写回 user.language。
        // ⚠ 该字段目前**服务端零消费**：PushService / VerificationService 都不读它（站外消息的语种
        // 仍按请求头走），全仓读它的只有用户资料自身的回显（UserController::profile/updateProfile）。
        // 所以这一枪只保证「资料里带着它」，**不会**让邮件/推送换语言 —— 接通它属于产品功能，另批。
        if ($request->userId ?? null) {
            $user = \common\model\User::find($request->userId);
            if ($user) {
                $user->update(['language' => $locale]);
            }
        }

        return $this->success(['locale' => $locale]);
    }
}
