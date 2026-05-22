<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use hg\apidoc\annotation as Apidoc;
use common\service\TranslationService;
use support\Request;
use support\Response;

/**
 * @Apidoc\Title("Language")
 * @Apidoc\Group("language")
 */
class LanguageController extends BaseController
{
    /**
     * @Apidoc\Title("Language List")
     * @Apidoc\Url("/api/language/list")
     * @Apidoc\Method("GET")
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
     * @Apidoc\Title("Switch Language")
     * @Apidoc\Url("/api/language/switch")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name:"locale",type:"string",require:true,desc:"Locale code (en-US, zh-CN, ja-JP, ko-KR)")
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

        // If logged in, update user language preference
        if ($request->userId ?? null) {
            $user = \common\model\User::find($request->userId);
            if ($user) {
                $user->update(['language' => $locale]);
            }
        }

        return $this->success(['locale' => $locale]);
    }
}
