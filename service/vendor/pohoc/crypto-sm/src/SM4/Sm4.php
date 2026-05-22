<?php

declare(strict_types=1);

namespace CryptoSm\SM4;

use CryptoSm\Exception\CryptoException;
use CryptoSm\Exception\InvalidKeyException;
use CryptoSm\Interface\CipherInterface;

class Sm4 implements CipherInterface
{
    public const MODE_ECB = 'ecb';
    public const MODE_CBC = 'cbc';

    public static function encrypt(string $data, string $key, mixed $options = null): string
    {
        return self::crypt($data, $key, true, $options instanceof Sm4Options ? $options : null);
    }

    public static function decrypt(string $data, string $key, mixed $options = null): string
    {
        return self::crypt($data, $key, false, $options instanceof Sm4Options ? $options : null);
    }

    private static function crypt(string $data, string $key, bool $encrypt, ?Sm4Options $options = null): string
    {
        $options ??= new Sm4Options();
        $mode = strtolower($options->getMode());
        $padding = strtolower($options->getPadding());
        $iv = $options->getIv();

        self::validateHexKey($key);
        if ($mode === self::MODE_CBC) {
            self::validateHexKey($iv, 'IV');
            $ivBin = hex2bin($iv);
        } elseif ($mode !== self::MODE_ECB) {
            throw new InvalidKeyException('Mode must be ecb or cbc');
        }

        $keyBin = hex2bin($key);

        if ($encrypt) {
            $input = self::maybePad($data, $padding);
            $cipher = openssl_encrypt(
                $input,
                $mode === self::MODE_ECB ? 'SM4-ECB' : 'SM4-CBC',
                $keyBin,
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                $mode === self::MODE_CBC ? $ivBin : ''
            );
            if ($cipher === false) {
                throw new CryptoException('SM4 encryption failed');
            }
            return bin2hex($cipher);
        }

        $cipher = hex2bin($data);
        if ($cipher === false || strlen($cipher) % 16 !== 0) {
            throw new InvalidKeyException('Invalid ciphertext hex');
        }
        $plain = openssl_decrypt(
            $cipher,
            $mode === self::MODE_ECB ? 'SM4-ECB' : 'SM4-CBC',
            $keyBin,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $mode === self::MODE_CBC ? $ivBin : ''
        );
        if ($plain === false) {
            throw new CryptoException('SM4 decryption failed');
        }

        return self::maybeUnpad($plain, $padding);
    }

    private static function validateHexKey(string $hex, string $label = 'Key'): void
    {
        if (!preg_match('/^[0-9a-fA-F]{32}$/', $hex)) {
            $msg = $label === 'IV' ? 'CBC mode requires IV' : 'Key must be 128 bits (32 hex chars)';
            throw new InvalidKeyException($msg);
        }
    }

    private static function maybePad(string $data, string $padding): string
    {
        if ($padding === 'none') {
            if (strlen($data) % 16 !== 0) {
                throw new InvalidKeyException('Data length must be multiple of 16 when padding=none');
            }
            return $data;
        }
        $block = 16;
        $pad = $block - (strlen($data) % $block);
        $pad = $pad === 0 ? $block : $pad;
        return $data . str_repeat(chr($pad), $pad);
    }

    private static function maybeUnpad(string $data, string $padding): string
    {
        if ($padding === 'none') {
            return $data;
        }
        $len = strlen($data);
        if ($len === 0 || $len % 16 !== 0) {
            throw new InvalidKeyException('Invalid padded plaintext');
        }
        $pad = ord($data[$len - 1]);
        if ($pad < 1 || $pad > 16 || substr($data, -$pad) !== str_repeat(chr($pad), $pad)) {
            throw new InvalidKeyException('Invalid PKCS padding');
        }
        return substr($data, 0, $len - $pad);
    }

    public static function hexToBytesStatic(string $hex): array
    {
        $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        $bytes = [];
        for ($i = 0; $i < strlen($hex); $i += 2) {
            $bytes[] = hexdec(substr($hex, $i, 2));
        }
        return $bytes;
    }

    public static function utf8ToArray(string $str): array
    {
        return array_values(unpack('C*', $str));
    }
}
