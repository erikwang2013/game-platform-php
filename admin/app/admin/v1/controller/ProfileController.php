<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use app\model\AdminUser;
use support\Request;
use support\Response;
use support\Redis;
use Erikwang2013\Jwt\JWT;

#[Apidoc\Title("个人中心")]
#[Apidoc\Group("profile")]
class ProfileController extends BaseController
{
    private static ?JWT $jwt = null;

    private static function getJWT(): JWT
    {
        if (self::$jwt === null) {
            self::$jwt = jwt_instance();
        }
        return self::$jwt;
    }

    #[Apidoc\Title("更新个人信息")]
    #[Apidoc\Desc("更新当前登录管理员的个人信息")]
    #[Apidoc\Url("/admin/v1/profile")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "real_name", type: "string", require: false, desc: "真实姓名")]
    #[Apidoc\Param(name: "phone", type: "string", require: false, desc: "手机号")]
    #[Apidoc\Param(name: "email", type: "string", require: false, desc: "邮箱")]
    public function updateProfile(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        $user    = AdminUser::find($adminId);
        if (!$user) {
            return $this->fail(trans('User not found'), 404);
        }

        if ($request->has('real_name')) {
            $user->real_name = $request->input('real_name');
        }
        if ($request->has('phone')) {
            $user->phone = $request->input('phone', '');
        }
        if ($request->has('email')) {
            $user->email = $request->input('email', '');
        }

        $user->save();

        $data = $user->toArray();
        unset($data['password'], $data['id_card']);
        // phone/email 由 Encryptable cast 自动加解密，无需额外处理

        return $this->success($this->encodeIds($data), trans('Updated successfully'));
    }

    #[Apidoc\Title("修改密码")]
    #[Apidoc\Desc("修改当前登录管理员的登录密码")]
    #[Apidoc\Url("/admin/v1/profile/password")]
    #[Apidoc\Method("PUT")]
    #[Apidoc\Author("erik")]
    #[Apidoc\Param(name: "old_password", type: "string", require: true, desc: "旧密码")]
    #[Apidoc\Param(name: "new_password", type: "string", require: true, desc: "新密码(8-32位，需含大小写字母和数字)")]
    public function updatePassword(Request $request): Response
    {
        $adminId = $request->adminId ?? 0;
        $user    = AdminUser::find($adminId);
        if (!$user) {
            return $this->fail(trans('User not found'), 404);
        }

        $oldPassword = $request->input('old_password', '');
        $newPassword = $request->input('new_password', '');

        if (empty($oldPassword) || empty($newPassword)) {
            return $this->fail(trans('Please enter both the old and new password'), 422);
        }

        if (!password_verify($oldPassword, $user->password)) {
            return $this->fail(trans('Old password is incorrect'), 422);
        }

        if (strlen($newPassword) < 8 || strlen($newPassword) > 32 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $newPassword)) {
            return $this->fail(trans('New password must be 8-32 characters and contain uppercase, lowercase letters and digits'), 422);
        }

        $user->password = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->save();

        return $this->success([], trans('Password changed successfully'));
    }

    #[Apidoc\Title("登出")]
    #[Apidoc\Desc("将当前JWT令牌加入黑名单，实现安全登出")]
    #[Apidoc\Url("/admin/v1/profile/logout")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Author("erik")]
    public function logout(Request $request): Response
    {
        $token = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            return $this->fail(trans('Not logged in'), 401);
        }

        try {
            $jwt     = self::getJWT();
            $payload = $jwt->decode($token);
            $ttl     = max((int)($payload['exp'] ?? 0) - time(), 0);
            Redis::setex('jwt_blacklist:' . md5($token), $ttl, '1');

            // 本会话的 refresh 令牌一并吊销。refresh 不走上面那把 md5 键（那是 AdminAuth 查 access 用的），
            // 它只认 jti 黑名单，因此必须走 blacklist()。吊销成功才删映射，失败留待下次登出重试。
            $sessionKey = 'session_refresh:' . md5($token);
            $refreshToken = Redis::get($sessionKey);
            if ($refreshToken && $jwt->blacklist($refreshToken)) {
                Redis::del($sessionKey);
            }
        } catch (\Throwable $e) {
            // token 无效也视为登出成功
        }

        return $this->success([], trans('Logged out'));
    }
}
