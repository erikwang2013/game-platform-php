<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Jobs;

use Illuminate\Database\Eloquent\Collection;
use Erikwang2013\WebmanScout\Searchable;

class RemoveableScoutCollection extends Collection
{
    /**
     * Get the Scout identifiers for all of the entities.
     *
     * @return array
     */
    public function getQueueableIds()
    {
        if ($this->isEmpty()) {
            return [];
        }

        return in_array(Searchable::class, class_uses_recursive($this->first()))
                    ? $this->map->getScoutKey()->all()
                    : parent::getQueueableIds();
    }
}
