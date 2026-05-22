<?php
/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * This source file is subject to the MIT license that is bundled with this package.
 */

namespace Erikwang2013\Poster\Adapters\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class Poster extends Facade
{
    protected static function getFacadeAccessor(): string { return 'poster.builder'; }
}
