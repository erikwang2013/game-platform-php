<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Concerns;

use Erikwang2013\WebmanScout\Exceptions\ScoutException;

/**
 * Webman 的 app() 多为 Illuminate\Container\Container，无 getNamespace()；
 * Laravel Application 才有。短类名同时兼容 App\Models\ 与 app\model\。
 */
trait ResolvesScoutModel
{
    protected function resolveModelClass(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new ScoutException('Model class name is required.');
        }
        if (class_exists($name)) {
            return $name;
        }
        $root = $this->appRootNamespace();
        $candidates = [
            $root . 'Models\\' . $name,
            $root . 'model\\' . $name,
        ];
        foreach ($candidates as $fqcn) {
            if (class_exists($fqcn)) {
                return $fqcn;
            }
        }
        throw new ScoutException("Model [{$name}] not found.");
    }

    protected function appRootNamespace(): string
    {
        $app = app();
        if (is_object($app) && method_exists($app, 'getNamespace')) {
            return $app->getNamespace();
        }

        return 'app\\';
    }
}
