<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\ActivityController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 活动 config 里 reward 的类型白名单与金额值域。
 *
 * 旧实现只校验 reward 是「一个数组」，type/amount 一个都不看 ⇒ `{"type":"coupon","amount":"999999"}`
 * 一路存进 game_activity.config。后果不是报错而是**静默**：出钱那一侧
 * ActivityService::creditWallet() 对非 platform_coin/game_coin 返回 'unsupported reward_type'，
 * 超 MAX_REWARD_PER_ENTRY 返回 'amount ... exceeds ...'，两条都只跳过该条、落 fail_reason ——
 * 运营配了个永远不会发的奖，直到用户达标那天才在日志里看见。
 *
 * 口径：这里钉的是 **写入路径的卫生**（提前拒），不是钱的闸。上界数字与
 * service/app/service/ActivityService.php:47 是同一个，两条断言都钉在「边界值本身仍放行」上，
 * 免得把闸门挪紧一档、在真闸之前先拦下合法配置。
 */
class ActivityRewardConfigBoundsTest extends TestCase
{
    /** parseConfig 是私有的，只反射这一个纯函数：handler 工厂不碰 DB。 */
    private function parse(?string $raw, string $type): ?array
    {
        $method = new ReflectionMethod(ActivityController::class, 'parseConfig');

        return $method->invoke(new ActivityController(), $raw, $type);
    }

    /** 三条分支各配一个形状合法的壳，只换 reward —— 只修一条分支的实现会在另外两条上红。 */
    private static function signin(string $reward): string
    {
        return '{"rewards":[{"day":1,"reward":' . $reward . '}]}';
    }

    private static function dailyTask(string $reward): string
    {
        return '{"tasks":[{"event":"deposit.completed","target":1,"reward":' . $reward . '}]}';
    }

    private static function invite(string $reward): string
    {
        return '{"target":1,"rewards":[{"reward":' . $reward . '}]}';
    }

    /** @return array<string, array{0:string, 1:string, 2:bool}> [壳, reward, 期望通过] */
    public static function cases(): array
    {
        $reward = static fn (string $r): array => [
            ['signin', self::signin($r), true],
            ['daily_task', self::dailyTask($r), true],
            ['invite', self::invite($r), true],
        ];

        $out = [];
        foreach ([
            'platform_coin 字符串金额'   => '{"type":"platform_coin","amount":"10"}',
            'game_coin 字符串金额'       => '{"type":"game_coin","amount":"10"}',
            '整数金额（非字符串）'       => '{"type":"platform_coin","amount":10}',
            '上界整 10000（含）'         => '{"type":"platform_coin","amount":"10000"}',
            '上界小数 10000.00（含）'    => '{"type":"platform_coin","amount":"10000.00"}',
            '最小 0.00000001'            => '{"type":"platform_coin","amount":"0.00000001"}',
        ] as $name => $r) {
            foreach ($reward($r) as [$t, $json, $_]) {
                $out["{$name} / {$t}"] = [$json, $t, true];
            }
        }

        foreach ([
            '未知类型 coupon'            => '{"type":"coupon","amount":"1"}',
            '未知类型 vip_exp'           => '{"type":"vip_exp","amount":"1"}',
            '类型大小写不符'             => '{"type":"PLATFORM_COIN","amount":"1"}',
            '缺 type'                    => '{"amount":"1"}',
            'type 非字符串'              => '{"type":123,"amount":"1"}',
            '缺 amount'                  => '{"type":"platform_coin"}',
            '金额 0'                     => '{"type":"platform_coin","amount":"0"}',
            '金额 0.00'                  => '{"type":"platform_coin","amount":"0.00"}',
            '金额负数'                   => '{"type":"platform_coin","amount":"-10"}',
            '金额超上界 10000.01'        => '{"type":"platform_coin","amount":"10000.01"}',
            '金额超上界 10000.00000001'  => '{"type":"platform_coin","amount":"10000.00000001"}',
            '金额非数字'                 => '{"type":"platform_coin","amount":"abc"}',
            '金额空串'                   => '{"type":"platform_coin","amount":""}',
            '金额是 float（JSON 小数）'  => '{"type":"platform_coin","amount":1.5}',
            '金额是数组'                 => '{"type":"platform_coin","amount":["10"]}',
            'amount 科学计数法'          => '{"type":"platform_coin","amount":"1e3"}',
            'reward 不是对象'            => '"platform_coin"',
        ] as $name => $r) {
            foreach ($reward($r) as [$t, $json, $_]) {
                $out["{$name} / {$t}"] = [$json, $t, false];
            }
        }

        return $out;
    }

    #[Test]
    #[DataProvider('cases')]
    public function rewardIsCheckedInEveryBranch(string $config, string $type, bool $ok): void
    {
        $parsed = $this->parse($config, $type);

        if ($ok) {
            $this->assertNotNull($parsed, "config 应当通过：{$config}");
            return;
        }

        $this->assertNull($parsed, "config 应当被拒：{$config}");
    }

    /** 空配置走 handler 默认值（默认值本身必须过得去，否则运营一进页面就被自己的兜底挡住）。 */
    #[Test]
    public function emptyConfigFallsBackToHandlerDefault(): void
    {
        foreach (['signin', 'daily_task', 'invite'] as $type) {
            $parsed = $this->parse('', $type);
            $this->assertIsArray($parsed, "{$type} 的默认配置不该是 null");
            $this->assertNotSame([], $parsed, "{$type} 的默认配置不该是空数组");
        }
    }
}
