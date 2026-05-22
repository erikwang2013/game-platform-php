<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

use Erikwang2013\WebmanScout\EngineManager;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Meilisearch\Client as MeilisearchClient;

if (! function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param  string|null  $abstract
     * @param  array  $parameters
     * @return mixed|\Illuminate\Contracts\Foundation\Application
     */
    function app($abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($abstract, $parameters);
    }
}

if (! function_exists('event')) {
    /**
     * Dispatch an event and call the listeners.
     *
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     * @return array|null
     */
    function event(...$args)
    {
        return app(Dispatcher::class)->dispatch(...$args);
    }
}

if (! function_exists('scout_config')) {
    /**
     * Read a Scout option relative to the active config root (see ScoutConfig).
     *
     * @param  string|null  $key
     * @return mixed
     */
    function scout_config(?string $key = null, $default = null)
    {
        return \Erikwang2013\WebmanScout\ScoutConfig::get($key, $default);
    }
}

if (class_exists(MeilisearchClient::class)) {
    app()->singleton(MeilisearchClient::class, function () {
        $c = scout_config('meilisearch', []);

        return new MeilisearchClient(
            $c['host'] ?? 'http://127.0.0.1:7700',
            $c['key'] ?? null
        );
    });
}

app()->singleton(Dispatcher::class, function ($app) {
    return new Dispatcher($app);
});

app()->singleton(EngineManager::class, function ($app) {
    return new EngineManager($app);
});
