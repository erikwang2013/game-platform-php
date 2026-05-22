<?php

declare(strict_types=1);

namespace CryptoSm;

use CryptoSm\SM2\Sm2 as SM2Crypto;
use CryptoSm\SM3\Sm3;
use CryptoSm\SM4\Sm4;

/**
 * Main facade class for CryptoSM library.
 */
class SmCrypto
{
    public static function sm2(): SM2Crypto
    {
        return new SM2Crypto();
    }

    public static function sm3(): Sm3
    {
        return new Sm3();
    }

    public static function sm4(): Sm4
    {
        return new Sm4();
    }
}
