<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

return [
    // ── Image Driver 图像处理驱动 ──
    'image' => [
        // 驱动类型 / Driver type: 'auto' | 'gd' | 'imagick'
        // 'auto' auto-detects available driver / 自动检测可用驱动
        'driver' => 'auto',

        // JPEG output quality / JPEG 输出质量 0-100
        'quality' => 90,

        // 默认字体路径 / Default font path
        // null = use bundled font / 使用包自带字体
        'font' => null,
    ],

    // ── Captcha Module 验证码模块 ──
    'captcha' => [
        // 验证数据存储 / Storage driver: 'auto' | 'file' | 'session' | 'redis'
        // 'auto': Redis > Session > File, auto-detect / 自动检测
        'storage' => 'auto',

        // 验证码有效期（秒）/ TTL in seconds
        // Key expires after this duration / 超时后 key 作废
        'ttl' => 300,

        // 同一 key 最多验证次数 / Max verification attempts per key
        // Prevents brute-force enumeration / 防暴力枚举
        'max_attempts' => 3,

        // 默认难度 / Default difficulty: 'easy' | 'medium' | 'hard'
        'default_difficulty' => 'medium',

        // 验证误差容忍 / Verification tolerance
        'tolerance' => [
            'click'  => 18,   // 点击验证像素半径 / Click: pixel radius
            'rotate' => 5,    // 旋转验证角度 / Rotate: degrees
            'slider' => 4,    // 滑块验证像素 / Slider: pixels
        ],

        // Redis 存储配置（storage=redis 时生效）/ Redis config (effective when storage=redis)
        'redis' => [
            // Redis key prefix / Redis 键前缀
            'prefix'     => 'poster:captcha:',
            // Redis connection name (framework-specific) / Redis 连接名（框架相关）
            'connection' => 'default',
        ],

        // 文件存储配置（storage=file 时生效）/ File storage config (effective when storage=file)
        'file' => [
            // 存储路径 / Storage path, null = system temp dir / 系统临时目录
            'path' => null,
        ],
    ],

    // ── Poster Module 海报生成模块 ──
    'poster' => [
        // 画布默认宽高（px）/ Default canvas width & height
        'default_width'  => 750,
        'default_height' => 1334,

        // 默认字体路径 / Default font path
        // null = use bundled font / 使用包自带字体
        'font' => null,

        // JPEG output quality / JPEG 输出质量 0-100
        'jpeg_quality' => 90,

        // PNG compression level / PNG 压缩级别 0-9
        // 0 = no compression / 不压缩, 9 = max / 最大压缩
        'png_compression' => 6,
    ],
];
