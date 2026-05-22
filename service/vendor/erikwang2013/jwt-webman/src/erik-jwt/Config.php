<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik
 * Author: erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt;

class Config
{
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }

    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (isset($config[$k]) && !is_array($config[$k])) {
                throw new JWTException("Cannot set '{$key}': '{$k}' is already a non-array value");
            }
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }

    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * 从文件加载配置
     */
    public static function fromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new JWTException("Config file not found: {$filePath}");
        }

        $config = require $filePath;
        return new self($config);
    }
}