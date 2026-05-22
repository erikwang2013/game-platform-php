<?php

declare(strict_types=1);

namespace CryptoSm\Utils;

/**
 * Utility class for hexadecimal encoding/decoding.
 */
class Hex
{
    public static function toBytes(string $hex): array
    {
        $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        $bytes = [];
        for ($i = 0; $i < strlen($hex); $i += 2) {
            $byte = hexdec(substr($hex, $i, 2));
            $bytes[] = $byte;
        }
        return $bytes;
    }

    public static function toHex(array $bytes): string
    {
        $hex = '';
        foreach ($bytes as $byte) {
            $hex .= str_pad(dechex($byte & 0xff), 2, '0', STR_PAD_LEFT);
        }
        return $hex;
    }

    public static function toHexString(string $str): string
    {
        $hex = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $hex .= str_pad(dechex(ord($str[$i])), 2, '0', STR_PAD_LEFT);
        }
        return $hex;
    }

    public static function fromHex(string $hex): string
    {
        $hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        $str = '';
        for ($i = 0; $i < strlen($hex); $i += 2) {
            $str .= chr(hexdec(substr($hex, $i, 2)));
        }
        return $str;
    }
}
