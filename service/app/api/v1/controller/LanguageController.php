<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use common\service\TranslationService;
use support\Request;
use support\Response;

class LanguageController extends BaseController
{
    /**
     * 可用语言列表
     * GET /api/language/list
     */
    public function list(Request $request): Response
    {
        $languages = TranslationService::getAvailableLanguages();

        return $this->success([
            'current' => TranslationService::getLocale(),
            'languages' => $languages,
        ]);
    }

    /**
     * 切换语言
     * POST /api/language/switch
     */
    public function switch(Request $request): Response
    {
        $validator = validator($request->all(), [
            'locale' => 'required|in:en-US,zh-CN,ja-JP,ko-KR',
        ]);

        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $locale = $request->input('locale');
        TranslationService::setLocale($locale);

        // 如果已登录，更新用户语言偏好
        if ($request->userId ?? null) {
            $user = \common\model\User::find($request->userId);
            if ($user) {
                $user->update(['language' => $locale]);
            }
        }

        return $this->success(['locale' => $locale]);
    }
}
