<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Tests;

use app\api\v1\controller\ReferralController;
use app\service\ActivityService;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * 1062 捕获的**成对判据**：认对了键才吞，键名不匹配一律上抛。
 *
 * 两处都在本批被我收窄：ReferralController::apply 的 catch（罩住整个推荐事务）与
 * ActivityService::grantRewards 的 reward_log 落库。两处原先都按「错误码 / 消息里出现
 * Duplicate entry」一刀切，于是**事务内任何**唯一键冲突（典型是流水表主键 snowflake 撞号）
 * 都被伪装成正常业务分支：
 *  - 推荐：用户看到「你已经用过推荐码」——一句用户错误掩盖一个要运维介入的故障；
 *  - 活动：这条奖励被当成「已发过」静默跳过——钱没发、fail_reason 没有、error 日志没有，
 *    participation 照样进终态，用户看到的「已发」是假的。
 *
 * 判据必须成对，只有「认对」那一半会让「验证」变成把新故障也吞掉：
 *  1) 撞在指定键上 ⇒ true（吞）
 *  2) 1062 但键名不匹配（模拟流水表撞号）⇒ **false**（上抛）← 守禁的那一半
 *  3) 非 1062 的 PDO 异常 ⇒ false
 *  4) 非 PDO 异常（没有 errorInfo）⇒ false
 *
 * 纯单元：不连库。异常是**构造**的（消息形状取自实测的 MySQL 原文
 * `Duplicate entry '...' for key 'game_user_identity.uk_user_id'`）。
 */
class DuplicateKeyNarrowingTest extends TestCase
{
    /** 造一个 1062：$key 决定消息里出现的唯一键名（真实 PDO 消息会带 库.键 或 表.键 限定名） */
    private static function duplicateKey(string $key, int $driverCode = 1062): PDOException
    {
        $message = "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-a-1' for key '{$key}'";
        $e = new PDOException($message, 0);
        $e->errorInfo = [23000, $driverCode, "Duplicate entry '1-a-1' for key '{$key}'"];

        return $e;
    }

    /** @return array<string, array{object, string}> [对象, 私有方法名] */
    public static function predicates(): array
    {
        return [
            'ReferralController' => [new ReferralController(), 'isDuplicateOnKey'],
            'ActivityService'    => [new ActivityService(), 'isDuplicateOnKey'],
        ];
    }

    #[Test]
    public function referralMatchesOnlyItsOwnKey(): void
    {
        $predicate = $this->predicate(new ReferralController());

        $this->assertTrue($predicate(self::duplicateKey('game_referral.uk_referred_id'), 'uk_referred_id'),
            '并发双提交撞 uk_referred_id 是我们自己认的那一种');
        $this->assertTrue($predicate(self::duplicateKey('uk_referred_id'), 'uk_referred_id'),
            '键名不带限定前缀也要认（MySQL 版本差异）');
    }

    #[Test]
    public function referralDoesNotSwallowUnrelatedDuplicateKey(): void
    {
        $predicate = $this->predicate(new ReferralController());

        // 模拟同一个事务里流水表主键撞号：用户会看到「你已经用过推荐码」，运维什么都看不到
        $this->assertFalse($predicate(self::duplicateKey('game_transaction.PRIMARY'), 'uk_referred_id'),
            '流水表撞号绝不能被吞成「你已经用过推荐码」');
        $this->assertFalse($predicate(self::duplicateKey('game_referral.PRIMARY'), 'uk_referred_id'),
            '推荐表自己的主键撞号同样不是「重复提交」');
        // 已知取舍（如实钉住，别让后人以为它是精确匹配）：判据是**子串**匹配，
        // 所以理论上一个叫 uk_referred_id_backup 的键会被一起吞掉。这张表上没有这种键
        // （只有 uk_referred_id / PRIMARY / idx_referrer_id / idx_code），故不额外加解析逻辑。
        $this->assertTrue($predicate(self::duplicateKey('game_referral.uk_referred_id_backup'), 'uk_referred_id'),
            '子串匹配的已知上限：形如 uk_referred_id_* 的键名会被一并认下（当前表上不存在这种键）');
    }

    #[Test]
    public function activityMatchesOnlyItsOwnKey(): void
    {
        $predicate = $this->predicate(new ActivityService());

        $this->assertTrue($predicate(self::duplicateKey('game_activity_reward_log.uk_idempotent'), 'uk_idempotent'),
            'uk_idempotent 撞键 = 这条奖励已发过，跳过是正确的幂等语义');
        $this->assertFalse($predicate(self::duplicateKey('game_activity_reward_log.PRIMARY'), 'uk_idempotent'),
            'reward_log 主键（snowflake）撞号 ⇒ 这条奖励一条都没发，必须上抛，不能静默跳过');
    }

    #[Test]
    public function nonDuplicateAndNonPdoErrorsAreNeverSwallowed(): void
    {
        $referral = $this->predicate(new ReferralController());
        $activity = $this->predicate(new ActivityService());

        $other = self::duplicateKey('uk_referred_id', 1213); // 死锁：错误码不是 1062
        $this->assertFalse($referral($other, 'uk_referred_id'), '只有 1062 才是重复键');
        $this->assertFalse($activity($other, 'uk_idempotent'));

        $this->assertFalse($referral(new RuntimeException("Duplicate entry 'x' for key 'uk_referred_id'"), 'uk_referred_id'),
            '非 PDO 异常没有 errorInfo（消息里恰好出现键名也不行）');
    }

    /** 取私有静态判据的闭包：测的是**生产用的那个函数**，不是复制品 */
    private function predicate(object $instance): callable
    {
        $method = new ReflectionMethod($instance, 'isDuplicateOnKey');
        $method->setAccessible(true);

        return static fn (\Throwable $e, string $key): bool => (bool) $method->invoke(null, $e, $key);
    }
}
