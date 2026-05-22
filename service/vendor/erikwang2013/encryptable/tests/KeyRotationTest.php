<?php

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Erikwang2013\Encryptable\Tests;

use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Erikwang2013\Encryptable\Encryption;
use Erikwang2013\Encryptable\PHPEncrypter;
use Erikwang2013\Encryptable\Support\PreviousKeysParser;
use PHPUnit\Framework\TestCase;

final class KeyRotationTest extends TestCase
{
    protected function tearDown(): void
    {
        Encryption::setFallbackConfig(null);
        parent::tearDown();
    }

    public function test_previous_keys_parser_accepts_comma_and_json(): void
    {
        self::assertSame(['a', 'b'], PreviousKeysParser::parse('a,b'));
        self::assertSame(['x', 'y'], PreviousKeysParser::parse('["x","y"]'));
        self::assertSame(['v'], PreviousKeysParser::parse(['k' => 'v']));
    }

    public function test_decrypt_tries_previous_key_after_primary_changed(): void
    {
        $old = str_repeat('0', 16);
        $new = str_repeat('1', 16);

        $oldCfg = new KeyRingTestConfig($old, []);
        $payload = (new PHPEncrypter($oldCfg))->encrypt('hello', true);

        $newCfg = new KeyRingTestConfig($new, [$old]);

        self::assertSame('hello', (new PHPEncrypter($newCfg))->decrypt($payload, true));
    }

    public function test_rotate_to_current_key_re_encrypts_with_primary(): void
    {
        $old = str_repeat('a', 16);
        $new = str_repeat('b', 16);

        $oldCfg = new KeyRingTestConfig($old, []);
        $payload = (new PHPEncrypter($oldCfg))->encrypt('payload-text', true);

        $newCfg = new KeyRingTestConfig($new, [$old]);
        $enc = new PHPEncrypter($newCfg);
        $rotated = $enc->rotateToCurrentKey($payload, true);

        self::assertNotSame($payload, $rotated);
        self::assertSame('payload-text', $enc->decrypt($rotated, true));
    }

    public function test_encryption_php_rotate_to_current_key_delegates(): void
    {
        $old = str_repeat('c', 16);
        $new = str_repeat('d', 16);

        $blob = (new PHPEncrypter(new KeyRingTestConfig($old, [])))->encrypt('ping', true);

        Encryption::setFallbackConfig(new KeyRingTestConfig($new, [$old]));
        $out = Encryption::php()->rotateToCurrentKey($blob, true);

        self::assertSame('ping', Encryption::php()->decrypt($out, true));
    }
}

final class KeyRingTestConfig implements EncryptableConfigContract
{
    /**
     * @param  list<string>  $previousKeys
     */
    public function __construct(
        private string $key,
        private array $previousKeys
    ) {
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getCipher(): ?string
    {
        return 'aes-128-ecb';
    }

    public function getPreviousKeys(): array
    {
        return $this->previousKeys;
    }
}
