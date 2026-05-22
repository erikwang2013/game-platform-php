<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Attributes;

use Attribute;
use Illuminate\Support\Arr;

#[Attribute]
class SearchUsingFullText
{
    /**
     * The full-text columns.
     *
     * @var array
     */
    public $columns = [];

    /**
     * The full-text options.
     */
    public $options = [];

    /**
     * Create a new attribute instance.
     *
     * @param  array  $columns
     * @param  array  $options
     * @return void
     */
    public function __construct($columns, $options = [])
    {
        $this->columns = Arr::wrap($columns);
        $this->options = Arr::wrap($options);
    }
}
