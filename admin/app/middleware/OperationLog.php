<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use common\SnowflakeService;

class OperationLog implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $method = $request->method();

        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return $handler($request);
        }

        $response = $handler($request);

        try {
            // 递归过滤敏感字段
            $input = $this->filterSensitive($request->all());

            $log = new \app\model\OperationLog();
            $log->id         = SnowflakeService::generate();
            $log->user_id    = $request->adminId ?? 0;
            $log->action     = $method;
            $log->method     = $method;
            $log->path       = $this->stripVersionSegment($request->path());
            $log->ip         = $request->getRealIp();
            $log->source     = $this->detectSource($request);
            $log->input      = json_encode($input, JSON_UNESCAPED_UNICODE);
            $log->created_at = date('Y-m-d H:i:s');
            $log->save();
        } catch (\Throwable $e) {
            // 日志记录失败不应影响业务请求
        }

        return $response;
    }

    /**
     * 剔除 URL 路径中的版本段（/admin/v1/user → /admin/user），保持审计记录与权限 slug 语义一致
     */
    private function stripVersionSegment(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        if (count($segments) > 1 && preg_match('/^v\d+$/', $segments[1])) {
            unset($segments[1]);
        }
        return '/' . implode('/', $segments);
    }

    /** 命中即整体打码的词（按「词」匹配，不是整串匹配） */
    private const SENSITIVE_WORDS = [
        'password', 'passwd', 'pwd', 'secret', 'token', 'credential',
        'signature', 'salt', 'private', 'key', 'config',
        // PII 单字：写入侧 AdminUser 的这两个字段走 encryptable 存密文、出参是 138****5678 /
        // a***@x.com，而日志里曾是明文。phone/email 是本仓实际的提交键名（UserController::store/update、
        // ProfileController::update、ImportController 均以此收件），contact_phone 这类前缀变体同样命中。
        'phone', 'email',
    ];

    /**
     * 复合词敏感键：按「相邻词拼接」整段比。
     *
     * real_name 切词得 [real, name]、id_card 得 [id, card]、id_number 得 [id, number] ——
     * 单比 name / card / number 会连带打码 role_name、game_name、card_type 这些**审计要留的正文**
     * （角色的名字、改了哪张卡），故只能整段比：real_name / realName / admin_real_name 都拼得出 realname。
     *
     * 三个词都是照写入侧实际键名定的：AdminUser.real_name（VARCHAR(50)）、AdminUser.id_card
     * （encryptable 密文 + 模型 $hidden）、game_user_identity.id_number（密文）。
     * 自由文本 note **刻意不在列，别顺手补上**：RiskEventController::review 的处置备注只落操作日志
     * （它的成功文案自己写着 auditable in operation logs，函数里没有 save、库里没有第二份），
     * 打码等于删审计。同键名在 IdentityController / AntiCheatController 确有 review_note 列冗余，
     * 但中间件分不开同名的两种语义 —— 前者不足以推翻后者。
     */
    private const SENSITIVE_PHRASES = ['realname', 'idcard', 'idnumber'];

    /**
     * 递归过滤敏感字段，防止密码/密钥等泄露到日志
     */
    private function filterSensitive(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = $this->filterSensitive($value);
            }
        }
        return $data;
    }

    /**
     * 敏感键判定：按「词」切分后匹配，覆盖 api_key / api_secret / secret_access_key /
     * access_token / newPassword 这类前后缀式命名。
     *
     * 旧实现是 `in_array($key, $keys, true)` 整串精确匹配，只挡得住自成一词的
     * password/token/secret，所以 api_key、api_secret、config 全部原样落进操作日志
     * —— 而这三者恰恰是 Game.api_secret（SDK 签名密钥）与 CDN/支付凭据（库内密文、
     * 日志明文）的载体。
     *
     * 迁移无损性：旧表 8 条词条在新规则下**逐条仍然命中** —— password、old_password、
     * new_password、new_password_confirmation、token、secret、access_token、refresh_token
     * 切词后各自切得出 password / token / secret（如 old_password → [old, password]）。
     * 这次改动只扩大覆盖面，不缩小。
     *
     * 两处刻意的取舍，都不是凭感觉收窄，是照本仓实际字段名定的：
     * 1. `config` 一律整体打码，不递归。CdnProviderController.php:93 与
     *    PaymentController.php:118 都把它当凭据载体，而 CdnProbeService.php:44-45 读的键名
     *    含缩写（ak / sk），缩写过不了任何词表 ⇒ 只递归而不整体打码等于假修。
     *    代价：ActivityController / RiskRuleController 的 config 正文（非凭据）也不再入日志，
     *    中间件里无法区分同名字段的两种语义。路径 + 业务主键仍足以定位改动。
     * 2. 裸 `key`（且它必须是键名里唯一的词）不打码：本仓叫 `key` 的字段是标识符不是凭据 ——
     *    AchievementController.php:50 的成就 key、ConfigController.php:88 的配置键名。
     *    打码会把「改了哪个配置项/哪个成就」从审计里抹掉。`api_key` / `secret_key` 这类
     *    组合词仍然命中（见上）。
     */
    private function isSensitiveKey(string $key): bool
    {
        // 先在分隔符（_ - . 空格）和 camelCase 驼峰处切词；不带 i 修饰符，否则 [A-Z] 会匹配小写、
        // 把每个字符边界都切一刀；大小写统一交给 strtolower。
        $words = preg_split('/[^A-Za-z0-9]+|(?<=[a-z0-9])(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_map('strtolower', $words);

        foreach ($words as $word) {
            if (!in_array($word, self::SENSITIVE_WORDS, true)) {
                continue;
            }
            if ($word === 'key' && count($words) === 1) {
                continue;
            }
            return true;
        }

        // 复合词（见 SENSITIVE_PHRASES）：相邻 2~3 个词拼接后整段比。名单最长 3 词，
        // 故上界取 3；逐段比而不是 str_contains，否则 valid_card 也会拼出 idcard 被误伤。
        $count = count($words);
        for ($i = 0; $i < $count; $i++) {
            for ($n = 2; $n <= 3 && $i + $n <= $count; $n++) {
                if (in_array(implode('', array_slice($words, $i, $n)), self::SENSITIVE_PHRASES, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 从请求头检测客户端来源端
     */
    private function detectSource(Request $request): string
    {
        // 优先使用原生客户端显式声明的平台头
        $platform = $request->header('X-Client-Platform', '');
        if ($platform && in_array(strtolower($platform), [
            'ipados', 'macos', 'windows', 'linux', 'ios', 'android', 'harmonyos', 'web',
        ], true)) {
            return strtolower($platform);
        }

        // 通过 User-Agent 推断
        $ua = $request->header('User-Agent', '');

        if (stripos($ua, 'HarmonyOS') !== false || stripos($ua, 'OpenHarmony') !== false) {
            return 'harmonyos';
        }
        if (stripos($ua, 'iPad') !== false) {
            return 'ipados';
        }
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iOS') !== false || stripos($ua, 'CFNetwork') !== false) {
            return 'ios';
        }
        if (stripos($ua, 'Android') !== false) {
            return 'android';
        }
        if (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) {
            return 'macos';
        }
        if (stripos($ua, 'Windows') !== false) {
            return 'windows';
        }
        if (stripos($ua, 'Linux') !== false) {
            return 'linux';
        }

        return 'web';
    }
}
