<?php

declare(strict_types=1);

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryption\Guomi;

use Erikwang2013\Encryption\Exception\UnsupportedNationalAlgorithmException;

/**
 * SM1、SM7、SM9 在公开 PHP 生态中无标准实现：SM1 未公开；SM7 面向 RFID；SM9 为基于身份的密码体系，需专门协议栈。
 * 调用下列方法将抛出明确异常，便于在业务层统一捕获或接入厂商 SDK / 加密机。
 */
final class UnavailableNationalAlgorithms
{
    public static function sm1(): never
    {
        throw new UnsupportedNationalAlgorithmException(
            'SM1 为不公开的商用分组密码，请使用国密加密机或芯片厂商提供的 SDK。'
        );
    }

    public static function sm7(): never
    {
        throw new UnsupportedNationalAlgorithmException(
            'SM7 主要用于 RFID 等领域，请使用对应专用设备或厂商接口。'
        );
    }

    public static function sm9(): never
    {
        throw new UnsupportedNationalAlgorithmException(
            'SM9（基于身份的密码）需完整 IBC 协议与密钥管理，请使用国密中间件或专用库集成。'
        );
    }
}
