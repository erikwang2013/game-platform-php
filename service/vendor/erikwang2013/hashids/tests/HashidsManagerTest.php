<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026  erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Hashids\Tests;

use Erikwang2013\Hashids\HashidsFactory;
use Erikwang2013\Hashids\HashidsManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HashidsManagerTest extends TestCase
{
    public function test_encodes_and_decodes_with_default_connection(): void
    {
        $manager = new HashidsManager([
            'default' => 'main',
            'connections' => [
                'main' => ['salt' => 'unit-test', 'length' => 8],
            ],
        ], new HashidsFactory());

        $hash = $manager->encode(1, 2, 3);
        self::assertSame([1, 2, 3], $manager->decode($hash));
    }

    public function test_named_connection(): void
    {
        $manager = new HashidsManager([
            'default' => 'a',
            'connections' => [
                'a' => ['salt' => 'one', 'length' => 6],
                'b' => ['salt' => 'two', 'length' => 6],
            ],
        ], new HashidsFactory());

        $hashA = $manager->connection('a')->encode(42);
        $hashB = $manager->connection('b')->encode(42);
        self::assertNotSame($hashA, $hashB);
    }

    public function test_unknown_connection_throws(): void
    {
        $manager = new HashidsManager([
            'default' => 'main',
            'connections' => [
                'main' => ['salt' => 'x', 'length' => 4],
            ],
        ], new HashidsFactory());

        $this->expectException(InvalidArgumentException::class);
        $manager->connection('missing');
    }

    public function test_magic_call_forwards_to_default_connection(): void
    {
        $manager = new HashidsManager([
            'default' => 'main',
            'connections' => [
                'main' => ['salt' => 'magic', 'length' => 8],
            ],
        ], new HashidsFactory());

        $hash = $manager->encode(99);
        self::assertIsString($hash);
        self::assertSame([99], $manager->decode($hash));
    }

    public function test_get_default_connection_returns_main_when_not_configured(): void
    {
        $manager = new HashidsManager([
            'connections' => [
                'main' => ['salt' => 'x', 'length' => 4],
            ],
        ], new HashidsFactory());

        self::assertSame('main', $manager->getDefaultConnection());
    }

    public function test_get_default_connection_returns_main_for_non_string_value(): void
    {
        $manager = new HashidsManager([
            'default' => true,
            'connections' => [
                'main' => ['salt' => 'x', 'length' => 4],
            ],
        ], new HashidsFactory());

        self::assertSame('main', $manager->getDefaultConnection());
    }

    public function test_default_connection_with_explicit_null(): void
    {
        $manager = new HashidsManager([
            'default' => 'main',
            'connections' => [
                'main' => ['salt' => 'test', 'length' => 6],
            ],
        ], new HashidsFactory());

        $hash = $manager->connection(null)->encode(7);
        self::assertIsString($hash);
    }

    public function test_empty_default_connection_falls_back_to_main(): void
    {
        $manager = new HashidsManager([
            'default' => '',
            'connections' => [
                'main' => ['salt' => 'x', 'length' => 4],
            ],
        ], new HashidsFactory());

        $hash = $manager->connection()->encode(1);
        self::assertIsString($hash);
    }

    public function test_explicit_empty_connection_name_throws(): void
    {
        $manager = new HashidsManager([
            'default' => 'main',
            'connections' => [
                'main' => ['salt' => 'x', 'length' => 4],
            ],
        ], new HashidsFactory());

        $this->expectException(InvalidArgumentException::class);
        $manager->connection('');
    }

    public function test_connection_caching(): void
    {
        $manager = new HashidsManager([
            'default' => 'main',
            'connections' => [
                'main' => ['salt' => 'cache', 'length' => 4],
            ],
        ], new HashidsFactory());

        $a = $manager->connection('main');
        $b = $manager->connection('main');
        self::assertSame($a, $b);
    }
}
