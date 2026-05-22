<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026  erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Hashids\Tests;

use Erikwang2013\Hashids\HashidsFactory;
use hashids\Hashids;
use PHPUnit\Framework\TestCase;

final class HashidsFactoryTest extends TestCase
{
    public function test_make_with_minimal_config(): void
    {
        $factory = new HashidsFactory();
        $hashids = $factory->make(['salt' => 'test', 'length' => 8]);

        $hash = $hashids->encode(1, 2, 3);
        self::assertSame([1, 2, 3], $hashids->decode($hash));
    }

    public function test_make_with_custom_alphabet(): void
    {
        $factory = new HashidsFactory();
        $hashids = $factory->make([
            'salt' => 'test',
            'length' => 8,
            'alphabet' => 'abcdefghijklmnopqrstuvwxyz',
        ]);

        $hash = $hashids->encode(42);
        self::assertMatchesRegularExpression('/^[a-z]+$/', $hash);
    }

    public function test_make_without_alphabet_key(): void
    {
        $factory = new HashidsFactory();
        $hashids = $factory->make(['salt' => 'x']);

        $hash = $hashids->encode(1);
        self::assertIsString($hash);
    }

    public function test_make_with_empty_alphabet_uses_default(): void
    {
        $factory = new HashidsFactory();
        $hashids = $factory->make([
            'salt' => 'test',
            'length' => 0,
            'alphabet' => '',
        ]);

        $hash = $hashids->encode(123);
        self::assertIsString($hash);
        self::assertNotEmpty($hash);
    }

    public function test_make_with_empty_config(): void
    {
        $factory = new HashidsFactory();
        $hashids = $factory->make([]);

        $hash = $hashids->encode(1);
        self::assertIsString($hash);
        self::assertNotEmpty($hash);
    }
}
