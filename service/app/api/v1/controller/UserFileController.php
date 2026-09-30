<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use AetherUpload\Util;
use common\model\User;
use common\model\UserIdentity;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * 个人件读取（头像 / KYC 三照）—— aetherupload 上传物在 C 端**唯一**的读取口。
 *
 * 为什么不直接用插件的 display 路由：那个端点只挂「有没有登录」，**不校验这个 savedPath 属于谁**
 * —— KYC 证件照挂在它上面等于任何登录用户拿到路径就能看。所以 `service/config/plugin/…/route.php`
 * 刻意没有注册 display/download，个人件统一走这里。
 *
 * 可见性策略（两类东西本来就不一样）：
 * - **头像**：聊天 / 好友列表里**别人也要看**（`ChatController`、`FriendController` 都读 peer 的
 *   avatar）⇒ 只要这个 savedPath 是某个用户的头像就放行（登录即可读）；
 * - **KYC 三照**：只有本人（与后台审核）该看 ⇒ 仅归属人可读。
 *
 * 落库形态：C 端存的是**相对 URL** `/api/v1/user/file/{savedPath}`（历史值可能是第三方 OAuth 的
 * 绝对 URL，那类不走这里）。故匹配时同时认「裸 savedPath」与「任意前缀 + /savedPath」两种写法。
 */
class UserFileController extends BaseController
{
    /** 相对 URL 前缀：前端存库与读取都用它，换路径时只改这一处 */
    public const URL_PREFIX = '/api/v1/user/file/';

    #[Apidoc\Title("个人件读取")]
    #[Apidoc\Desc("头像对所有登录用户可读；KYC 三照仅归属人可读。非归属返回 403")]
    public function show(Request $request, string $savedPath): Response
    {
        $userId = (int) $request->userId;

        if (!$this->readable($userId, $savedPath)) {
            // 403 而非 404：文件可能确实存在，只是不属于你 —— 两者分开才好排查
            return $this->fail('无权访问该文件', 403);
        }

        $resource = Util::getResource($savedPath);
        if ($resource === false || $resource->exists() === false) {
            return $this->fail('文件不存在', 404);
        }

        // nosniff：上传物只按白名单扩展名放行（svg 已排除），别让浏览器自己猜类型
        return response()->file($resource->realPath)->withHeader('X-Content-Type-Options', 'nosniff');
    }

    private function readable(int $userId, string $savedPath): bool
    {
        // 先挡住畸形输入：路径来自 URL 段，超长/空串直接拒（列宽 255）
        if ($savedPath === '' || strlen($savedPath) > 255) {
            return false;
        }

        $user = User::find($userId);
        if ($user && $this->storedMatches((string) $user->avatar, $savedPath)) {
            return true;
        }

        $identity = UserIdentity::where('user_id', $userId)->first();
        if ($identity !== null) {
            foreach (['id_front_photo', 'id_back_photo', 'selfie_photo'] as $column) {
                if ($this->storedMatches((string) $identity->{$column}, $savedPath)) {
                    return true; // 本人的证件照
                }
            }
        }

        // 头像对别人可见（聊天/好友列表）⇒ 是某个用户的头像就放行；两条等式便于走索引
        return User::where('avatar', $savedPath)->orWhere('avatar', self::URL_PREFIX . $savedPath)->exists();
    }

    /** 库里的值可能是裸 savedPath，也可能是 `/api/v1/user/file/{savedPath}`（或带域名的同形） */
    private function storedMatches(string $stored, string $savedPath): bool
    {
        return $stored === $savedPath || str_ends_with($stored, '/' . $savedPath);
    }
}
