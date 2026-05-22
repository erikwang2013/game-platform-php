<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout;

use Erikwang2013\WebmanScout\Engines\Engine;
use Erikwang2013\WebmanScout\Jobs\MakeSearchable;
use Erikwang2013\WebmanScout\Jobs\RemoveFromSearch;

class Scout
{
    /**
     * The Scout library version.
     *
     * @var string
     */
    const VERSION = '10.23.0';

    /**
     * The job class that should make models searchable.
     *
     * @var string
     */
    public static $makeSearchableJob = MakeSearchable::class;

    /**
     * The job that should remove models from the search index.
     *
     * @var string
     */
    public static $removeFromSearchJob = RemoveFromSearch::class;

    /**
     * Get a Scout engine instance.
     */
    public static function engine(string $engine): Engine
    {
        return app(EngineManager::class)->engine($engine);
    }

    /**
     * Specify the job class that should make models searchable.
     *
     * @param  string  $class
     * @return void
     */
    public static function makeSearchableUsing(string $class)
    {
        static::$makeSearchableJob = $class;
    }

    /**
     * Specify the job class that should remove models from the search index.
     *
     * @param  string  $class
     * @return void
     */
    public static function removeFromSearchUsing(string $class)
    {
        static::$removeFromSearchJob = $class;
    }
}
