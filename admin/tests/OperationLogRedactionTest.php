<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\middleware\OperationLog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 操作日志脱敏
 *
 * 旧实现是 `in_array($key, $keys, true)` 整串精确匹配，只挡得住自成一词的
 * password/token/secret，于是 api_key / api_secret / config 全部原样落进
 * game_operation_log.input —— 而它们正是 Game.api_secret（SDK 签名密钥）与
 * CDN/支付凭据的载体（库内走 encryptable 存密文，日志里却是明文）。
 * 这些明文又能被 GET /admin/v1/dashboard 这条**已播种的低权 slug** 读到。
 */
class OperationLogRedactionTest extends TestCase
{
    /** filterSensitive 是私有的，且 process() 会写库——只反射这一个纯函数，不碰 DB。 */
    private function filter(array $data): array
    {
        $method = new ReflectionMethod(OperationLog::class, 'filterSensitive');

        return $method->invoke(new OperationLog(), $data);
    }

    /**
     * 命中即打码的键名。api_* 与 config 是本次修复的四个真实泄漏口，
     * 其余是同一族的前后缀变体（含驼峰）。
     */
    public static function sensitiveKeys(): array
    {
        return [
            'api_secret (Game SDK 签名密钥)' => ['api_secret'],
            'api_key'                        => ['api_key'],
            'secret_access_key (CDN 凭据)'   => ['secret_access_key'],
            'access_key_secret (CDN 凭据)'   => ['access_key_secret'],
            'config (CDN/支付凭据载体)'       => ['config'],
            'access_token'                   => ['access_token'],
            'refresh_token'                  => ['refresh_token'],
            'old_password'                   => ['old_password'],
            'new_password_confirmation'      => ['new_password_confirmation'],
            'clientSecret (驼峰)'            => ['clientSecret'],
            'private_key'                    => ['private_key'],
            'signature'                      => ['signature'],
        ];
    }

    #[Test]
    #[DataProvider('sensitiveKeys')]
    public function sensitiveKeyValuesAreMasked(string $key): void
    {
        $filtered = $this->filter([$key => 'super-secret-value']);

        $this->assertSame('***', $filtered[$key], "{$key} 未被脱敏");
    }

    /**
     * 嵌套结构也必须过一遍：CDN/支付真正提交的形状就是 config 里套着凭据字段。
     */
    #[Test]
    public function nestedApiSecretIsMasked(): void
    {
        $input = [
            'name'   => 'aws-cdn',
            'config' => ['api_secret' => 'sk-live-42', 'zone_id' => 'z-1'],
        ];

        $filtered = $this->filter($input);

        // config 是凭据载体 ⇒ 整体打码（缩写键名 ak/sk 过不了词表，只递归等于假修）
        $this->assertSame('***', $filtered['config']);
        $this->assertSame('aws-cdn', $filtered['name']);
    }

    /**
     * 上面那条钉的是"config 整体打码"，这条单独钉"嵌套里的小写 api_secret"——
     * 即派单要求的最小用例，把它放在不叫 config 的父键下，确保是键名匹配在起作用。
     */
    #[Test]
    public function nestedApiSecretUnderNeutralParentIsMasked(): void
    {
        $input = [
            'provider' => 'aliyun',
            'options'  => [
                'endpoint'   => 'oss-cn-hangzhou.aliyuncs.com',
                'api_secret' => 'sk-live-42',
                'deep'       => ['api_secret' => 'sk-deep', 'keep' => 'ok'],
            ],
        ];

        $filtered = $this->filter($input);

        $this->assertSame('***', $filtered['options']['api_secret']);
        $this->assertSame('***', $filtered['options']['deep']['api_secret']);
        $this->assertSame('oss-cn-hangzhou.aliyuncs.com', $filtered['options']['endpoint']);
        $this->assertSame('ok', $filtered['options']['deep']['keep']);
    }

    /**
     * 数字键（JSON 数组）不能把脱敏打断，也不能误判成敏感键。
     */
    #[Test]
    public function listShapedNestingIsHandled(): void
    {
        $input = ['items' => [['api_key' => 'k1'], ['api_key' => 'k2']]];

        $filtered = $this->filter($input);

        $this->assertSame('***', $filtered['items'][0]['api_key']);
        $this->assertSame('***', $filtered['items'][1]['api_key']);
    }

    /**
     * 反向：不打码的必须原样保留。
     *
     * 裸 `key` 是这里唯一的刻意收窄，依据是本仓实际字段名 ——
     * AchievementController.php:50 的成就 key、ConfigController.php:88 的配置键名
     * 都是标识符不是凭据，打码会把「改了哪个成就/哪个配置项」从审计里抹掉。
     * keyword 用于证明匹配是按「词」切分（不是 str_contains 子串）。
     * document_id / monkey 用于证明词内出现 key/secret 不会被误伤。
     */
    #[Test]
    public function nonCredentialKeysSurvive(): void
    {
        $input = [
            'key'         => 'first_login',
            'keyword'     => '充值',
            'monkey'      => 1,
            'document_id' => 'doc-1',
            'group'       => 'withdraw',
            'value'       => '1',
            'status'      => 1,
        ];

        $this->assertSame($input, $this->filter($input));
    }

    /**
     * 大小写不敏感：API_SECRET / ApiKey 与 snake_case 等价。
     */
    #[Test]
    public function keyMatchingIsCaseInsensitive(): void
    {
        $filtered = $this->filter(['API_SECRET' => 'v', 'ApiKey' => 'v', 'Token' => 'v']);

        $this->assertSame(['API_SECRET' => '***', 'ApiKey' => '***', 'Token' => '***'], $filtered);
    }
}
