<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\api\v1\controller;

use app\model\AdminUser;
use common\SnowflakeService;
use erikwang2013\apidoc\annotation as Apidoc;
use support\Container;
use support\Redis;
use support\Request;
use support\Response;
use Erikwang2013\Jwt\JWT;
use Throwable;

#[Apidoc\Title("管理员认证")]
#[Apidoc\Group("auth")]
class AuthController
{
    // 注册口令强度：8-32 位且同时包含小写字母、大写字母、数字
    // 与 admin 侧 UserController 的策略字符串保持一致
    // 仅约束注册（新口令）；登录为 verify-only 不校验强度，否则存量短口令用户无法登录
    private const PASSWORD_RULE = 'required|string|min:8|max:32|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/';

    private static ?JWT $jwt = null;

    private static function getJWT(): JWT
    {
        if (self::$jwt === null) {
            self::$jwt = jwt_instance();
        }
        return self::$jwt;
    }

    /**
     * 登录（需先通过点击验证码）
     * POST /api/auth/login
     */
    #[Apidoc\Url("/api/v1/auth/login")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "username", type: "string", require: true, desc: "用户名（3-50 字符）")]
    #[Apidoc\Param(name: "password", type: "string", require: true, desc: "密码")]
    #[Apidoc\Param(name: "captcha_key", type: "string", require: true, desc: "点击验证码 key")]
    #[Apidoc\Param(name: "clicks", type: "array", require: true, desc: "点击坐标集合，元素含 x/y（至少 2 个）")]
    #[Apidoc\Returned(name: "access_token", type: "string", desc: "访问令牌")]
    #[Apidoc\Returned(name: "refresh_token", type: "string", desc: "刷新令牌")]
    #[Apidoc\Returned(name: "expires_in", type: "int", desc: "访问令牌有效期（秒）")]
    #[Apidoc\Returned(name: "user", type: "object", desc: "管理员信息，含 id(hashid)/username/real_name")]
    public function login(Request $request): Response
    {
        $validator = validator($request->all(), [
            'username'    => 'required|string|min:3|max:50',
            'password'    => 'required|string|min:6|max:32',
            'captcha_key' => 'required|string',
            'clicks'      => 'required|array|min:2',
        ]);

        if ($validator->fails()) {
            return json(['code' => 422, 'message' => $validator->errors()->first(), 'data' => []]);
        }

        // 验证点击验证码（身份按客户端 IP 归属，见 support/helpers.php:captcha_verify_from_ip）
        if (!captcha_verify_from_ip($request->getRealIp(), $request->input('captcha_key'), 'click', captcha_clicks($request->input('clicks')))) {
            return json(['code' => 422, 'message' => trans('Incorrect captcha, please try again'), 'data' => []]);
        }

        // 校验用户凭证
        $username = $request->input('username');
        $user = AdminUser::where('username', $username)->first();

        // 账号锁定检查（5次失败/15分钟）
        $lockKey = "account_lock:{$username}";
        try {
            if (Redis::get($lockKey)) {
                return json(['code' => 429, 'message' => trans('Account is temporarily locked, please try again in 15 minutes'), 'data' => []]);
            }
        } catch (\Throwable) {}

        if (!$user || !password_verify($request->input('password'), $user->password)) {
            // 登录失败：计数 + 锁定
            try {
                $failKey = "login_fail:{$username}";
                $fails = Redis::incr($failKey);
                if ($fails === 1) Redis::expire($failKey, 900);
                if ($fails >= 5) {
                    Redis::setex($lockKey, 900, '1');
                    Redis::del($failKey);
                    return json(['code' => 429, 'message' => trans('Account is temporarily locked, please try again in 15 minutes'), 'data' => []]);
                }
            } catch (\Throwable) {}
            return json(['code' => 401, 'message' => trans('Incorrect username or password'), 'data' => []]);
        }

        // 登录成功：清除失败计数
        try { Redis::del("login_fail:{$username}"); Redis::del($lockKey); } catch (\Throwable) {}

        if ($user->status === 0) {
            return json(['code' => 403, 'message' => trans('Account has been disabled'), 'data' => []]);
        }

        // 签发 JWT
        $jwt = self::getJWT();
        $tokenExpire = (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200);
        $token = $jwt->encode(['sub' => $user->id, 'username' => $user->username]);
        $refreshToken = $jwt->encode(['sub' => $user->id, 'token_type' => 'refresh'],
            (int)(config('plugin.erikwang2013.jwt.jwt.refresh_expire') ?: 1209600)
        );

        // 并发会话限制
        $this->trackSession($user->id, $token, $refreshToken, $tokenExpire);

        // 更新登录信息
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $request->getRealIp();
        $user->save();

        return json([
            'code'    => 0,
            'message' => trans('Login successful'),
            'data'    => [
                'access_token'  => $token,
                'refresh_token' => $refreshToken,
                'expires_in'    => (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200),
                'user'          => [
                    'id'        => Container::get('hashids')->encode($user->id),
                    'username'  => $user->username,
                    'real_name' => $user->real_name,
                ],
            ],
        ]);
    }

    /**
     * 注册（需先通过点击验证码）—— **端点已摘除，不在 API 文档里发布**。
     *
     * 2026-10-01 摘除，与 admin/config/route.php:324-330 的墓碑同步（那里删掉的是路由注册）。
     * 理由：匿名（一次点击验证码即可）就能建出 status=1 的 admin_user 并当场签发 access+refresh，
     * 而 /api/v1 组不挂 AdminAuth/AdminPermission、也不挂 OperationLog ⇒ 凭空多出的管理员账号不留审计。
     * 方法体保留未删，便于日后恢复；恢复前提：先解决「匿名建管理员」——改成 C 端用户体系，
     * 或加管理员邀请/审批。（service 侧的 /api/v1/auth/register 是 C 端注册，不受影响。）
     *
     * ⚠ 本方法挂了 `NotParse` 注解（下面那一行）：解析器在 ParseApiDetail.php:83 优先判定它，
     * 先于任何其它注解 —— 即便日后有人补回 Url/Title 等注解，本方法照样被跳过，端点不会复活。
     * 这是**结构性拒绝**；只靠"零注解"不安全：删光只是当下没人写，补回一条就复活，且无任何报错。
     */
    #[Apidoc\NotParse()]
    public function register(Request $request): Response
    {
        $validator = validator($request->all(), [
            'username'    => 'required|string|min:3|max:50',
            'password'    => self::PASSWORD_RULE,
            'real_name'   => 'required|string|max:50',
            'captcha_key' => 'required|string',
            'clicks'      => 'required|array|min:2',
            // phone/email 的**形状**必须在这里挡住：它们是可选字段、原先完全没过校验，
            // 数组值（`phone[]=x`）会喂给 encryptable 转型抛 SerializationException，
            // 而 register() 无 try/catch ⇒ 公开端点 500（debug 开时还带完整堆栈）。
            // 用 nullable|string 而不是 string：`string` 规则会把 JSON null 判失败，
            // 而 `$request->input('phone','')` 遇到显式 null 现在是原样透传的 —— 不能收紧既有可接受输入。
            'phone'       => 'nullable|string',
            'email'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return json(['code' => 422, 'message' => $validator->errors()->first(), 'data' => []]);
        }

        if (!captcha_verify_from_ip($request->getRealIp(), $request->input('captcha_key'), 'click', captcha_clicks($request->input('clicks')))) {
            return json(['code' => 422, 'message' => trans('Incorrect captcha, please try again'), 'data' => []]);
        }

        $username = $request->input('username');
        if (AdminUser::where('username', $username)->exists()) {
            return json(['code' => 422, 'message' => trans('Username already exists'), 'data' => []]);
        }

        $user = new AdminUser();
        $user->id = SnowflakeService::generate();
        $user->username = $username;
        $user->password = password_hash($request->input('password'), PASSWORD_BCRYPT);
        $user->real_name = $request->input('real_name');
        $user->phone = $request->input('phone', '');
        $user->email = $request->input('email', '');
        $user->status = 1;
        $user->save();

        $jwt = self::getJWT();
        $tokenExpire = (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200);
        $token = $jwt->encode(['sub' => $user->id, 'username' => $user->username]);
        $refreshToken = $jwt->encode(['sub' => $user->id, 'token_type' => 'refresh'],
            (int)(config('plugin.erikwang2013.jwt.jwt.refresh_expire') ?: 1209600)
        );

        $this->trackSession($user->id, $token, $refreshToken, $tokenExpire);

        return json([
            'code'    => 0,
            'message' => trans('Registered successfully'),
            'data'    => [
                'access_token'  => $token,
                'refresh_token' => $refreshToken,
                'expires_in'    => (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200),
                'user'          => [
                    'id'        => Container::get('hashids')->encode($user->id),
                    'username'  => $user->username,
                    'real_name' => $user->real_name,
                ],
            ],
        ]);
    }

    /**
     * 刷新令牌
     * POST /api/auth/refresh
     */
    #[Apidoc\Url("/api/v1/auth/refresh")]
    #[Apidoc\Method("POST")]
    #[Apidoc\Param(name: "refresh_token", type: "string", require: true, desc: "刷新令牌")]
    #[Apidoc\Returned(name: "access_token", type: "string", desc: "新的访问令牌")]
    #[Apidoc\Returned(name: "refresh_token", type: "string", desc: "新的刷新令牌")]
    #[Apidoc\Returned(name: "expires_in", type: "int", desc: "访问令牌有效期（秒）")]
    public function refresh(Request $request): Response
    {
        $refreshToken = $request->input('refresh_token', '');

        if (empty($refreshToken)) {
            return json(['code' => 422, 'message' => trans('Missing refresh token'), 'data' => []]);
        }

        try {
            $jwt = self::getJWT();
            $payload = $jwt->decode($refreshToken, true);

            // decode(allowRefresh: true) 只是"允许"refresh 令牌（v2.1.2 起默认拒绝），access 令牌同样能过闸；
            // 不显式要求 token_type=refresh，等于拿 2 小时有效的 access 令牌换一枚 14 天有效的 refresh 令牌
            if (($payload['token_type'] ?? '') !== 'refresh') {
                return json(['code' => 401, 'message' => trans('Refresh token is invalid or expired'), 'data' => []]);
            }

            // 刷新时更新最后登录时间和IP
            $userId = $payload['sub'] ?? 0;
            if ($userId) {
                $user = AdminUser::find($userId);
                if ($user) {
                    $user->last_login_at = date('Y-m-d H:i:s');
                    $user->last_login_ip = $request->getRealIp();
                    $user->save();
                }
            }

            $tokenExpire = (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200);
            $token = $jwt->encode(['sub' => $payload['sub'], 'username' => $payload['username'] ?? '']);

            // 轮换：旧 refresh 的 jti 立即入黑名单，同一枚 refresh 令牌用过即废。
            // 刻意排在签发 access 之后 —— 此前任何一步失败都不会把客户端手上的 refresh 打成死票。
            // 用 refresh() 而非手写 encode：它同时强制 token_type=refresh，且黑名单写失败会抛错走 401
            // （fail-closed），不会发出"吊销不掉"的新会话。
            $newRefresh = $jwt->refresh($refreshToken);

            // 并发会话限制
            $this->trackSession($userId, $token, $newRefresh, $tokenExpire);

            return json([
                'code'    => 0,
                'message' => 'success',
                'data'    => [
                    'access_token'  => $token,
                    'refresh_token' => $newRefresh,
                    'expires_in'    => (int)(config('plugin.erikwang2013.jwt.jwt.default_expire') ?: 7200),
                ],
            ]);
        } catch (Throwable $e) {
            return json(['code' => 401, 'message' => trans('Refresh token is invalid or expired'), 'data' => []]);
        }
    }

    /**
     * 并发会话限制 — 同一管理员最多 3 个有效会话（一个会话＝一枚 access + 其配对 refresh）
     *
     * zset 成员是 access 令牌的 md5（score 为到期时间），驱逐靠 jwt_blacklist:{md5} 生效。
     * 驱逐时必须连同该会话配对的 refresh 一起吊销：只杀 access 的话，一次刷新就能把会话复活，
     * 上限形同虚设。旧 access 令牌在其有效期内始终可用，故刷新时不去掉旧 access 的计数条目。
     *
     * @param int $userId 用户 ID
     * @param string $token 新签发的 access_token
     * @param string $refreshToken 与本条 access 配对的 refresh_token
     * @param int $expiresIn access_token 有效期（秒）
     */
    private function trackSession(int $userId, string $token, string $refreshToken, int $expiresIn): void
    {
        try {
            $key = "user_tokens:{$userId}";
            $exp = time() + $expiresIn;
            $member = md5($token);

            // access → 配对 refresh：登出时手上只有 access，靠这张表才能顺带吊销 refresh。
            // TTL 取 access 有效期 + 1 小时余量，与下面 zset 的余量口径一致，保证任何能通过
            // decode（含 leeway 宽限）的 access 令牌都还在窗口内找得到自己的 refresh
            Redis::setex("session_refresh:{$member}", $expiresIn + 3600, $refreshToken);

            // 清理已过期的 token
            Redis::zremrangebyscore($key, 0, time());
            // 添加新 token
            Redis::zadd($key, $exp, $member);
            // 超过 3 个 → 踢出最旧的
            $count = Redis::zcard($key);
            if ($count > 3) {
                $oldest = Redis::zrange($key, 0, 0, true);
                if ($oldest) {
                    $oldMember = array_key_first($oldest);
                    $oldExp = (int) $oldest[$oldMember];
                    $ttl = max($oldExp - time(), 0);
                    Redis::zrem($key, $oldMember);
                    if ($ttl > 0) {
                        Redis::setex("jwt_blacklist:{$oldMember}", $ttl, '1');
                    }
                    // 被驱逐会话的 refresh 一并吊销：refresh 只认 jti 黑名单，不走上面那把 md5 键
                    $pairKey = "session_refresh:{$oldMember}";
                    $oldRefresh = Redis::get($pairKey);
                    if ($oldRefresh) {
                        self::getJWT()->blacklist($oldRefresh);
                        Redis::del($pairKey);
                    }
                }
            }
            Redis::expire($key, $expiresIn + 3600);
        } catch (\Throwable) {
            // Redis 故障不影响登录
        }
    }
}
