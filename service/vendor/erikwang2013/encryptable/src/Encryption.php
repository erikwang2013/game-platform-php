<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable;

use Erikwang2013\Encryptable\Config\EnvEncryptableConfig;
use Erikwang2013\Encryptable\Contracts\EncryptableConfigContract;
use Psr\Container\ContainerInterface;
use RuntimeException;

class Encryption
{
    private static ?ContainerInterface $container = null;

    private static ?EncryptableConfigContract $fallbackConfig = null;

    /** @var null|callable(string): object */
    private static $resolver = null;

    private Encrypter $encrypter;

    public function __construct(Encrypter $encrypter)
    {
        $this->encrypter = $encrypter;
    }

    public static function setContainer(?ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function setFallbackConfig(?EncryptableConfigContract $config): void
    {
        self::$fallbackConfig = $config;
    }

    /**
     * @param callable(string): object $resolver
     */
    public static function setResolver(callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function php(): self
    {
        return new self(
            self::resolve(PHPEncrypter::class)
        );
    }

    public static function db(): self
    {
        return new self(
            self::resolve(DBEncrypter::class)
        );
    }

    public static function isEncrypted(mixed $value): bool
    {
        return self::php()->encrypter
            ->isEncrypted($value);
    }

    public function encrypt(mixed $value, bool $serialize = true): ?string
    {
        return $this->encrypter
            ->encrypt($value, $serialize);
    }

    public function decrypt(?string $payload, bool $unserialize = true): mixed
    {
        return $this->encrypter
            ->decrypt($payload, $unserialize);
    }

    /**
     * Re-encrypt a payload with the current primary key after decrypting with any key in the ring.
     * Only available when using {@see self::php()} (application-level OpenSSL payloads).
     */
    public function rotateToCurrentKey(?string $payload, bool $serialize = true): ?string
    {
        if (! $this->encrypter instanceof PHPEncrypter) {
            throw new RuntimeException('rotateToCurrentKey is only supported for Encryption::php().');
        }

        return $this->encrypter->rotateToCurrentKey($payload, $serialize);
    }

    private static function resolve(string $abstract): object
    {
        if (self::$resolver !== null) {
            return (self::$resolver)($abstract);
        }

        if (class_exists(\Hyperf\Context\ApplicationContext::class)) {
            try {
                $hyperf = \Hyperf\Context\ApplicationContext::getContainer();
                if ($hyperf->has($abstract)) {
                    return $hyperf->get($abstract);
                }
            } catch (\Throwable) {
                // not in a Hyperf worker context
            }
        }

        if (function_exists('app')) {
            $app = app();
            if ($app instanceof ContainerInterface && $app->has($abstract)) {
                return $app->get($abstract);
            }
            if (is_object($app) && method_exists($app, 'bound') && method_exists($app, 'make') && $app->bound($abstract)) {
                return $app->make($abstract);
            }
        }

        if (self::$container !== null && self::$container->has($abstract)) {
            return self::$container->get($abstract);
        }

        if ($abstract === PHPEncrypter::class) {
            return new PHPEncrypter(self::fallbackEncryptableConfig());
        }

        throw new RuntimeException(
            "Unable to resolve [{$abstract}]. Register bindings in your framework service provider, ".
            'or call Encryption::setResolver() with a PSR-11 container callback.'
        );
    }

    private static function fallbackEncryptableConfig(): EncryptableConfigContract
    {
        return self::$fallbackConfig ?? new EnvEncryptableConfig;
    }
}
