<?php

declare(strict_types=1);

namespace CryptoSm\SM2;

use CryptoSm\Exception\CryptoException;
use CryptoSm\Exception\InvalidKeyException;
use CryptoSm\Interface\SignerInterface;
use CryptoSm\SM3\Sm3;
use CryptoSm\Utils\Hex;

class Sm2 implements SignerInterface
{
    public const CIPHER_MODE_1 = 1; // C1C3C2
    public const CIPHER_MODE_0 = 0; // C1C2C3

    private static array $eccTable = [
        'n' => 'FFFFFFFEFFFFFFFFFFFFFFFFFFFFFFFF7203DF6B21C6052B53BBF40939D54123',
        'p' => 'FFFFFFFEFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF00000000FFFFFFFFFFFFFFFF',
        'a' => 'FFFFFFFEFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF00000000FFFFFFFFFFFFFFFC',
        'b' => '28E9FA9E9D9F5E344D5A9E4BCF6509A7F39789F515AB8F92DDBCBD414D940E93',
        'gX' => '32c4ae2c1f1981195f9904466a39c9948fe30bbff2660be1715a4589334c74c7',
        'gY' => 'bc3736a2f4f6779c59bdcee36b692153d0a9877cc62a474002df32e52139f0a0',
    ];

    public static function generateKeyPairHex(): Keypair
    {
        $pair = self::generateKeyPair();
        return new Keypair($pair['privateKey'], $pair['publicKey']);
    }

    private static function generateKeyPair(): array
    {
        $n = gmp_init(self::$eccTable['n'], 16);
        do {
            $d = gmp_random_range(gmp_init(1), gmp_sub($n, gmp_init(1)));
        } while (gmp_cmp($d, 1) < 0 || gmp_cmp($d, $n) >= 0);

        $privateKey = str_pad(gmp_strval($d, 16), 64, '0', STR_PAD_LEFT);
        $publicKey = self::pointMultiply($privateKey);

        return ['privateKey' => $privateKey, 'publicKey' => $publicKey];
    }

    public static function doEncrypt(string $data, string $publicKey, ?Sm2CipherOptions $options = null): string
    {
        $options ??= new Sm2CipherOptions();
        $cipherMode = $options->getCipherMode();

        if (!self::isOnCurve($publicKey)) {
            throw new InvalidKeyException('Invalid public key');
        }

        do {
            $k = bin2hex(random_bytes(32));

            $x1y1 = self::pointMultiply($k);
            $x1 = substr($x1y1, 0, 64);
            $y1 = substr($x1y1, 64);
            $C1 = $x1y1;

            $x2y2 = self::pointMultiply($publicKey, $k);
            $x2 = substr($x2y2, 0, 64);
            $y2 = substr($x2y2, 64);

            $dataLen = strlen($data);
            $t = self::kdf($x2 . $y2, $dataLen);
            if (self::isAllZero($t)) {
                continue;
            }

            $C2 = '';
            for ($i = 0; $i < $dataLen; $i++) {
                $C2 .= chr(ord($data[$i]) ^ ord($t[$i]));
            }
            $C2Hex = bin2hex($C2);
            $C3 = Sm3::sm3(Hex::fromHex($x2) . $data . Hex::fromHex($y2));
        } while (false);

        return $cipherMode === self::CIPHER_MODE_1 ? $C1 . $C3 . $C2Hex : $C1 . $C2Hex . $C3;
    }

    public static function doDecrypt(string $data, string $privateKey, ?Sm2CipherOptions $options = null): string
    {
        $options ??= new Sm2CipherOptions();
        $cipherMode = $options->getCipherMode();

        if (strlen($data) < 192 || strlen($data) % 2 !== 0) {
            throw new InvalidKeyException('Invalid ciphertext');
        }

        $C1 = substr($data, 0, 128);
        if ($cipherMode === self::CIPHER_MODE_1) {
            $C3 = substr($data, 128, 64);
            $C2 = substr($data, 192);
        } else {
            $C2 = substr($data, 128, -64);
            $C3 = substr($data, -64);
        }

        $p = gmp_init(self::$eccTable['p'], 16);
        $a = gmp_init(self::$eccTable['a'], 16);
        $C1Point = self::parsePoint($C1);
        if ($C1Point === null || !self::isOnCurve($C1)) {
            throw new InvalidKeyException('Invalid C1');
        }

        $x2y2 = self::pointMultiply($C1, $privateKey);
        $x2 = substr($x2y2, 0, 64);
        $y2 = substr($x2y2, 64);

        $dataLen = strlen($C2) / 2;
        $t = self::kdf($x2 . $y2, $dataLen);
        if (self::isAllZero($t)) {
            throw new CryptoException('KDF derived all-zero key');
        }

        $M = '';
        $C2Bin = Hex::fromHex($C2);
        for ($i = 0; $i < $dataLen; $i++) {
            $M .= chr(ord($C2Bin[$i]) ^ ord($t[$i]));
        }

        $u = Sm3::sm3(Hex::fromHex($x2) . $M . Hex::fromHex($y2));
        if ($u !== $C3) {
            throw new CryptoException('SM2 signature verification failed');
        }

        return $M;
    }

    public static function encrypt(string $data, string $publicKey, mixed $options = null): string
    {
        return self::doEncrypt($data, $publicKey, $options);
    }

    public static function decrypt(string $data, string $privateKey, mixed $options = null): string
    {
        return self::doDecrypt($data, $privateKey, $options);
    }

    public static function sign(string $data, string $privateKey, mixed $options = null): string
    {
        return self::doSignature($data, $privateKey, $options);
    }

    public static function verify(string $data, string $signature, string $publicKey, mixed $options = null): bool
    {
        return self::doVerifySignature($data, $signature, $publicKey, $options);
    }

    public static function doSignature(string $data, string $privateKey, ?SignatureOptions $options = null): string
    {
        $options ??= new SignatureOptions();

        $der = $options->getDer();
        $hash = $options->getHash();
        $publicKey = $options->getPublicKey();
        $userId = $options->getUserId();

        $e = self::calcE($data, $hash, $publicKey ?: self::pointMultiply($privateKey), $userId);

        $n = gmp_init(self::$eccTable['n'], 16);
        $d = gmp_init($privateKey, 16);
        $dPlus1Inv = gmp_invert(gmp_add($d, gmp_init(1)), $n);

        do {
            $k = gmp_random_range(gmp_init(1), gmp_sub($n, gmp_init(1)));
            $x1y1 = self::pointMultiply(gmp_strval($k, 16));
            $x1Dec = gmp_init(substr($x1y1, 0, 64), 16);

            if (gmp_cmp($x1Dec, $n) >= 0 || gmp_cmp($x1Dec, 0) === 0) {
                continue;
            }

            $r = gmp_mod(gmp_add($e, $x1Dec), $n);
            if (gmp_cmp($r, 0) === 0 || gmp_cmp(gmp_add($r, $k), $n) === 0) {
                continue;
            }

            $tmp = gmp_mod(gmp_sub($k, gmp_mul($r, $d)), $n);
            $s = gmp_mod(gmp_mul($dPlus1Inv, $tmp), $n);
        } while (gmp_cmp($s, 0) === 0);

        $rHex = str_pad(gmp_strval($r, 16), 64, '0', STR_PAD_LEFT);
        $sHex = str_pad(gmp_strval($s, 16), 64, '0', STR_PAD_LEFT);

        return $der ? self::createDerSignature($sHex, $rHex) : $rHex . $sHex;
    }

    public static function doVerifySignature(string $data, string $signature, string $publicKey, ?SignatureOptions $options = null): bool
    {
        $options ??= new SignatureOptions();
        $der = $options->getDer() || strtolower(substr($signature, 0, 2)) === '30';
        $hash = $options->getHash();
        $userId = $options->getUserId();

        if (!self::isOnCurve($publicKey)) {
            return false;
        }

        [$x1Hex, $sHex] = $der ? self::parseDerSignature($signature) : [substr($signature, 0, 64), substr($signature, 64)];
        if ($x1Hex === null || $sHex === null) {
            return false;
        }

        $n = gmp_init(self::$eccTable['n'], 16);
        $x1Dec = gmp_init($x1Hex, 16);
        $sDec = gmp_init($sHex, 16);
        if (gmp_cmp($x1Dec, 0) <= 0 || gmp_cmp($x1Dec, $n) >= 0 || gmp_cmp($sDec, 0) <= 0 || gmp_cmp($sDec, $n) >= 0) {
            return false;
        }

        $e = self::calcE($data, $hash, $publicKey, $userId);
        $t = gmp_mod(gmp_add($x1Dec, $sDec), $n);
        if (gmp_cmp($t, 0) === 0) {
            return false;
        }

        $point1 = self::pointMultiply('', gmp_strval($sDec, 16));
        $point2 = self::pointMultiply($publicKey, gmp_strval($t, 16));
        if ($point1 === null || $point2 === null) {
            return false;
        }

        $p = gmp_init(self::$eccTable['p'], 16);
        $a = gmp_init(self::$eccTable['a'], 16);
        $sum = self::pointAdd(
            gmp_init(substr($point1, 0, 64), 16),
            gmp_init(substr($point1, 64), 16),
            gmp_init(substr($point2, 0, 64), 16),
            gmp_init(substr($point2, 64), 16),
            $p,
            $a
        );
        if ($sum === null) {
            return false;
        }

        $x2Hex = str_pad(gmp_strval($sum['x'], 16), 64, '0', STR_PAD_LEFT);
        $v = gmp_mod(gmp_add($e, gmp_init($x2Hex, 16)), $n);
        return gmp_strval($v, 16) === gmp_strval($x1Dec, 16);
    }

    private static function calcE(string $data, bool $hash, string $publicKey, string $userId): \GMP
    {
        if ($hash) {
            $x = substr($publicKey, 0, 64);
            $y = substr($publicKey, 64);
            $userId = is_string($userId) ? $userId : '';
            $z = Sm3::sm3(self::getUserIdHash($userId, $x, $y));
            $dataHex = empty($data) ? '00' : $z . bin2hex($data);
            return gmp_init($dataHex, 16);
        }
        $dataHex = empty($data) ? '00' : bin2hex($data);
        return gmp_init($dataHex, 16);
    }

    private static function getUserIdHash(string $userId, string $x, string $y): string
    {
        $len = strlen($userId) * 8;
        $userIdHex = Hex::toHexString($userId);

        $a = self::$eccTable['a'];
        $b = self::$eccTable['b'];
        $gX = self::$eccTable['gX'];
        $gY = self::$eccTable['gY'];

        return $userIdHex . self::intToHex($len) . $a . $b . $gX . $gY . $x . $y;
    }

    private static function intToHex(int $n): string
    {
        return str_pad(dechex($n), 8, '0', STR_PAD_LEFT);
    }

    private static function kdf(string $seed, int $keyLen): string
    {
        $ct = 1;
        $key = '';
        while (strlen($key) < $keyLen) {
            $hash = Sm3::sm3(Hex::fromHex($seed . self::intToHex($ct)));
            $key .= Hex::fromHex($hash);
            $ct++;
        }
        return substr($key, 0, $keyLen);
    }

    private static function pointMultiply(string $point, ?string $factor = null): ?string
    {
        $p = gmp_init(self::$eccTable['p'], 16);
        $a = gmp_init(self::$eccTable['a'], 16);

        $Gx = gmp_init(self::$eccTable['gX'], 16);
        $Gy = gmp_init(self::$eccTable['gY'], 16);

        if (strlen($point) > 64) {
            $pointX = gmp_init(substr($point, 0, 64), 16);
            $pointY = gmp_init(substr($point, 64), 16);
        } else {
            $pointX = $Gx;
            $pointY = $Gy;
            $factor ??= $point;
        }

        $factor ??= bin2hex(random_bytes(32));
        $factorDec = gmp_init($factor, 16);
        $factorBin = gmp_strval($factorDec, 2);

        $resultX = null;
        $resultY = null;
        $currentX = $pointX;
        $currentY = $pointY;

        for ($i = strlen($factorBin) - 1; $i >= 0; $i--) {
            if ($factorBin[$i] === '1') {
                if ($resultX === null) {
                    $resultX = $currentX;
                    $resultY = $currentY;
                } else {
                    $temp = self::pointAdd($resultX, $resultY, $currentX, $currentY, $p, $a);
                    if ($temp === null) {
                        return null;
                    }
                    $resultX = $temp['x'];
                    $resultY = $temp['y'];
                }
            }
            $temp = self::pointDouble($currentX, $currentY, $p, $a);
            $currentX = $temp['x'];
            $currentY = $temp['y'];
        }

        if ($resultX === null) {
            return str_repeat('0', 128);
        }

        return str_pad(gmp_strval($resultX, 16), 64, '0', STR_PAD_LEFT) .
            str_pad(gmp_strval($resultY, 16), 64, '0', STR_PAD_LEFT);
    }

    private static function pointAdd(\GMP $x1, \GMP $y1, \GMP $x2, \GMP $y2, \GMP $p, \GMP $a): ?array
    {
        if (gmp_cmp($x1, $x2) === 0) {
            if (gmp_cmp($y1, $y2) === 0) {
                return self::pointDouble($x1, $y1, $p, $a);
            }
            return null;
        }

        $lambda = gmp_mod(gmp_mul(gmp_sub($y2, $y1), gmp_invert(gmp_sub($x2, $x1), $p)), $p);
        $x3 = gmp_mod(gmp_sub(gmp_sub(gmp_pow($lambda, 2), $x1), $x2), $p);
        $y3 = gmp_mod(gmp_sub(gmp_mul($lambda, gmp_sub($x1, $x3)), $y1), $p);

        return ['x' => $x3, 'y' => $y3];
    }

    private static function pointDouble(\GMP $x, \GMP $y, \GMP $p, \GMP $a): array
    {
        $lambda = gmp_mod(
            gmp_mul(
                gmp_add(gmp_mul(gmp_init(3), gmp_pow($x, 2)), $a),
                gmp_invert(gmp_mul(gmp_init(2), $y), $p)
            ),
            $p
        );
        $x3 = gmp_mod(gmp_sub(gmp_pow($lambda, 2), gmp_mul(gmp_init(2), $x)), $p);
        $y3 = gmp_mod(gmp_sub(gmp_mul($lambda, gmp_sub($x, $x3)), $y), $p);
        return ['x' => $x3, 'y' => $y3];
    }

    private static function createDerSignature(string $sHex, string $rHex): string
    {
        $rDer = self::derEncodeInteger(Hex::toBytes($rHex));
        $sDer = self::derEncodeInteger(Hex::toBytes($sHex));
        $seq = $rDer . $sDer;
        return bin2hex(chr(0x30) . chr(strlen($seq)) . $seq);
    }

    private static function derEncodeInteger(array $bytes): string
    {
        while (count($bytes) > 1 && $bytes[0] === 0) {
            array_shift($bytes);
        }
        if ($bytes[0] > 0x7f) {
            array_unshift($bytes, 0);
        }
        $len = count($bytes);
        $lenBytes = $len < 128 ? chr($len) : chr(0x81) . chr($len);
        return chr(0x02) . $lenBytes . implode('', array_map('chr', $bytes));
    }

    private static function parseDerSignature(string $sig): array
    {
        if (strlen($sig) < 8 || substr($sig, 0, 2) !== '30') {
            return [null, null];
        }
        $offset = 2;
        $seqLen = hexdec(substr($sig, $offset, 2));
        $offset += 2;
        if (substr($sig, $offset, 2) !== '02') {
            return [null, null];
        }
        $offset += 2;
        $rLen = hexdec(substr($sig, $offset, 2));
        $offset += 2;
        $r = substr($sig, $offset, $rLen * 2);
        $offset += $rLen * 2;
        if (substr($sig, $offset, 2) !== '02') {
            return [null, null];
        }
        $offset += 2;
        $sLen = hexdec(substr($sig, $offset, 2));
        $offset += 2;
        $s = substr($sig, $offset, $sLen * 2);

        $r = ltrim($r, '0');
        $s = ltrim($s, '0');
        $r = $r === '' ? '0' : $r;
        $s = $s === '' ? '0' : $s;
        if (strlen($r) % 2 === 1) {
            $r = '0' . $r;
        }
        if (strlen($s) % 2 === 1) {
            $s = '0' . $s;
        }
        return [str_pad($r, 64, '0', STR_PAD_LEFT), str_pad($s, 64, '0', STR_PAD_LEFT)];
    }

    private static function isAllZero(string $data): bool
    {
        return trim($data, "\0") === '';
    }

    private static function isOnCurve(string $publicKey): bool
    {
        if (strlen($publicKey) !== 128) {
            return false;
        }
        $p = gmp_init(self::$eccTable['p'], 16);
        $a = gmp_init(self::$eccTable['a'], 16);
        $b = gmp_init(self::$eccTable['b'], 16);
        $x = gmp_init(substr($publicKey, 0, 64), 16);
        $y = gmp_init(substr($publicKey, 64), 16);

        if (gmp_cmp($x, 0) < 0 || gmp_cmp($x, $p) >= 0 || gmp_cmp($y, 0) < 0 || gmp_cmp($y, $p) >= 0) {
            return false;
        }

        $left = gmp_mod(gmp_pow($y, 2), $p);
        $right = gmp_mod(gmp_add(gmp_add(gmp_pow($x, 3), gmp_mul($a, $x)), $b), $p);
        return gmp_cmp($left, $right) === 0;
    }

    private static function parsePoint(string $hex): ?array
    {
        if (strlen($hex) !== 128) {
            return null;
        }
        return [
            'x' => gmp_init(substr($hex, 0, 64), 16),
            'y' => gmp_init(substr($hex, 64), 16),
        ];
    }
}
