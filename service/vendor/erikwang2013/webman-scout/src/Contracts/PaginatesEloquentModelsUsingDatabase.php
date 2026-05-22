<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Contracts;

use Erikwang2013\WebmanScout\Builder;

interface PaginatesEloquentModelsUsingDatabase
{
    /**
     * Paginate the given search on the engine.
     *
     * @param  \Erikwang2013\WebmanScout\Builder  $builder
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateUsingDatabase(Builder $builder, $perPage, $pageName, $page);

    /**
     * Paginate the given search on the engine using simple pagination.
     *
     * @param  \Erikwang2013\WebmanScout\Builder  $builder
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int  $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simplePaginateUsingDatabase(Builder $builder, $perPage, $pageName, $page);
}
