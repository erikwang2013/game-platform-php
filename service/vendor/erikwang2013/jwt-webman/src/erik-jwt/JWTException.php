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

use Exception;

class JWTException extends Exception
{
    const TOKEN_EXPIRED = 1;
    const TOKEN_INVALID = 2;
    const TOKEN_BLACKLISTED = 3;
    const STORAGE_ERROR = 4;
    const CONFIG_ERROR = 5;
    const NETWORK_ERROR = 6;

    public static function expired(): self
    {
        return new self('Token has expired', self::TOKEN_EXPIRED);
    }

    public static function invalid(string $message = 'Invalid token'): self
    {
        return new self($message, self::TOKEN_INVALID);
    }

    public static function blacklisted(): self
    {
        return new self('Token has been blacklisted', self::TOKEN_BLACKLISTED);
    }

    public static function storageError(string $message): self
    {
        return new self('Storage error: ' . $message, self::STORAGE_ERROR);
    }

    public static function configError(string $message): self
    {
        return new self('Configuration error: ' . $message, self::CONFIG_ERROR);
    }

    public static function networkError(string $message): self
    {
        return new self('Network error: ' . $message, self::NETWORK_ERROR);
    }

    /**
     * 从底层异常创建JWT异常
     */
    public static function fromException(Exception $e, string $context = ''): self
    {
        $message = $context ? "{$context}: {$e->getMessage()}" : $e->getMessage();
        
        if (strpos($e->getMessage(), 'connection') !== false || 
            strpos($e->getMessage(), 'timeout') !== false) {
            return self::networkError($message);
        }
        
        return self::storageError($message);
    }
}