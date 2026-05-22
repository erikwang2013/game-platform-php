<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026  erik <erik@erik.xyz> (https://erik.xyz)
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Hashids;

use Hashids\Hashids;
use InvalidArgumentException;

final class HashidsManager
{
    /** @var array<string, mixed> */
    private array $config;

    private HashidsFactory $factory;

    /** @var array<string, Hashids> */
    private array $connections = [];

    /**
     * @param array{default?: string, connections?: array<string, array<string, mixed>>} $config
     */
    public function __construct(array $config, HashidsFactory $factory)
    {
        $this->config = $config;
        $this->factory = $factory;
    }

    public function getFactory(): HashidsFactory
    {
        return $this->factory;
    }

    /**
     * Resolve a named connection or the default.
     */
    public function connection(?string $name = null): Hashids
    {
        $name = $name ?? $this->getDefaultConnection();

        if ($name === '') {
            throw new InvalidArgumentException('Hashids connection name cannot be empty.');
        }

        if (!isset($this->connections[$name])) {
            $connections = $this->config['connections'] ?? [];
            if (!isset($connections[$name]) || !is_array($connections[$name])) {
                throw new InvalidArgumentException(sprintf('Hashids connection [%s] is not configured.', $name));
            }

            $this->connections[$name] = $this->factory->make($connections[$name]);
        }

        return $this->connections[$name];
    }

    public function getDefaultConnection(): string
    {
        $default = $this->config['default'] ?? 'main';

        if (!is_string($default) || $default === '') {
            return 'main';
        }

        return $default;
    }

    /**
     * Dynamically pass methods onto the default connection.
     *
     * @param array<int, mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->{$method}(...$parameters);
    }
}
