<?php

declare(strict_types=1);

namespace CryptoSm\SM3;

class Sm3
{
    private const IV = [
        0x7380166f,
        0x4914b2b9,
        0x172442d7,
        0xda8a0600,
        0xa96f30bc,
        0x163138aa,
        0xe38dee4d,
        0xb0fb0e4e,
    ];

    public static function sm3(string $data): string
    {
        return self::computeHash($data);
    }

    public static function hash(string $data): string
    {
        return self::sm3($data);
    }

    private static function computeHash(string $msg): string
    {
        $blocks = self::pad($msg);
        $v = self::IV;

        foreach ($blocks as $block) {
            [$w, $wPrime] = self::expand($block);

            [$a, $b, $c, $d, $e, $f, $g, $h] = $v;

            for ($j = 0; $j < 64; $j++) {
                $t = $j < 16 ? 0x79cc4519 : 0x7a879d8a;
                $ss1 = self::rol(self::p32(self::rol($a, 12) + $e + self::rol($t, $j)), 7);
                $ss2 = $ss1 ^ self::rol($a, 12);
                $ff = $j < 16 ? ($a ^ $b ^ $c) : (($a & $b) | ($a & $c) | ($b & $c));
                $gg = $j < 16 ? ($e ^ $f ^ $g) : (($e & $f) | ((~$e) & $g));
                $tt1 = self::p32($ff + $d + $ss2 + $wPrime[$j]);
                $tt2 = self::p32($gg + $h + $ss1 + $w[$j]);

                $d = $c;
                $c = self::rol($b, 9);
                $b = $a;
                $a = $tt1;
                $h = $g;
                $g = self::rol($f, 19);
                $f = $e;
                $e = self::p0($tt2);
            }

            $v = [
                self::p32($v[0] ^ $a),
                self::p32($v[1] ^ $b),
                self::p32($v[2] ^ $c),
                self::p32($v[3] ^ $d),
                self::p32($v[4] ^ $e),
                self::p32($v[5] ^ $f),
                self::p32($v[6] ^ $g),
                self::p32($v[7] ^ $h),
            ];
        }

        return sprintf(
            '%08x%08x%08x%08x%08x%08x%08x%08x',
            ...$v
        );
    }

    /** @return array<int,array<int,int>> */
    private static function expand(string $block): array
    {
        $w = array_values(unpack('N16', $block));
        for ($j = 16; $j < 68; $j++) {
            $w[$j] = self::p1($w[$j - 16] ^ $w[$j - 9] ^ self::rol($w[$j - 3], 15))
                ^ self::rol($w[$j - 13], 7) ^ $w[$j - 6];
        }

        $wPrime = [];
        for ($j = 0; $j < 64; $j++) {
            $wPrime[$j] = self::p32($w[$j] ^ $w[$j + 4]);
        }

        return [$w, $wPrime];
    }

    /** @return array<int,string> */
    private static function pad(string $msg): array
    {
        $len = strlen($msg);
        $bitLen = $len * 8;

        $msg .= chr(0x80);
        $padLen = (56 - (($len + 1) % 64) + 64) % 64;
        if ($padLen) {
            $msg .= str_repeat(chr(0x00), $padLen);
        }
        $msg .= pack('N2', $bitLen >> 32, $bitLen & 0xFFFFFFFF);

        return str_split($msg, 64);
    }

    private static function p0(int $x): int
    {
        return self::p32($x ^ self::rol($x, 9) ^ self::rol($x, 17));
    }

    private static function p1(int $x): int
    {
        return self::p32($x ^ self::rol($x, 15) ^ self::rol($x, 23));
    }

    private static function rol(int $x, int $n): int
    {
        $n &= 31;
        return (($x << $n) | ($x >> (32 - $n))) & 0xFFFFFFFF;
    }

    private static function p32(int $x): int
    {
        return $x & 0xFFFFFFFF;
    }
}
