<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Bridge\ThinkPHP;

use Psr\Container\ContainerInterface;

/**
 * Wraps ThinkPHP's application/container when it does not implement {@see ContainerInterface},
 * so {@see \Erikwang2013\Encryptable\Encryption::setContainer()} can resolve bindings consistently.
 */
final class ThinkPsrContainerAdapter implements ContainerInterface
{
    public function __construct(
        private object $think
    ) {
    }

    public function get(string $id): mixed
    {
        return $this->think->make($id);
    }

    public function has(string $id): bool
    {
        if (method_exists($this->think, 'bound') && $this->think->bound($id)) {
            return true;
        }

        if ($this->think instanceof ContainerInterface) {
            return $this->think->has($id);
        }

        if (method_exists($this->think, 'has')) {
            return (bool) $this->think->has($id);
        }

        return false;
    }
}
