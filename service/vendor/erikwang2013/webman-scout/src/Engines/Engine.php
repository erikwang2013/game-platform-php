<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Engines;

use Erikwang2013\WebmanScout\Builder;

abstract class Engine
{
    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    abstract public function update($models);

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    abstract public function delete($models);

    /**
     * Perform the given search on the engine.
     *
     * @param  \Erikwang2013\WebmanScout\Builder  $builder
     * @return mixed
     */
    abstract public function search(Builder $builder);

    /**
     * Perform the given search on the engine.
     *
     * @param  \Erikwang2013\WebmanScout\Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    abstract public function paginate(Builder $builder, $perPage, $page);

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    abstract public function mapIds($results);

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Erikwang2013\WebmanScout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    abstract public function map(Builder $builder, $results, $model);

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  \Erikwang2013\WebmanScout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\LazyCollection
     */
    abstract public function lazyMap(Builder $builder, $results, $model);

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    abstract public function getTotalCount($results);

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    abstract public function flush($model);

    /**
     * Create a search index.
     *
     * @param  string  $name
     * @param  array  $options
     * @return mixed
     */
    abstract public function createIndex($name, array $options = []);

    /**
     * Delete a search index.
     *
     * @param  string  $name
     * @return mixed
     */
/*     abstract public function vectorSearch($vector, ?string $vectorField = null, array $options = []);
    abstract public function advancedSearch(Builder $builder);
    abstract public function whereAdvanced(
        string $field,
        string $operator,
        $value,
        string $boolean = 'and',
        bool $nested = false
    );
    abstract public function whereRange(string $field, array $range, bool $inclusive = true);
    abstract public function whereGeoDistance(string $field, float $lat, float $lng, float $radius);
    abstract public function updateIndexMappings(string $index, array $mappings);
    abstract public function fulltextSearch(string $query, array $fields = [], array $options = []);
    abstract public function orderByVectorSimilarity(array $vector, ?string $vectorField = null);
    abstract public function addResultProcessor(callable $processor);
    abstract public function aggregate(string $name, string $type, string $field, array $options = []);
    abstract public function facet(string $field, array $options = []);
    abstract public function getAggregations();
    abstract public function getFacets();
    abstract public function getVectorSearch();
    abstract public function getAdvancedWheres();
    abstract public function getSorts();
    abstract public function getAggregationConfig();
    abstract public function getFacetConfig();
    abstract public function  clearAdvancedConditions();
 */

    /**
     * Pluck and return the primary keys of the given results using the given key name.
     *
     * @param  mixed  $results
     * @param  string  $key
     * @return \Illuminate\Support\Collection
     */
    public function mapIdsFrom($results, $key)
    {
        return $this->mapIds($results);
    }

    /**
     * Get the results of the query as a Collection of primary keys.
     *
     * @param  \Erikwang2013\WebmanScout\Builder  $builder
     * @return \Illuminate\Support\Collection
     */
    public function keys(Builder $builder)
    {
        return $this->mapIds($this->search($builder));
    }

    /**
     * Get the results of the given query mapped onto models.
     *
     * @param  \Erikwang2013\WebmanScout\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get(Builder $builder)
    {
        return $this->map(
            $builder,
            $builder->applyAfterRawSearchCallback($this->search($builder)),
            $builder->model
        );
    }

    /**
     * Get a lazy collection for the given query mapped onto models.
     *
     * @param  \Erikwang2013\WebmanScout\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function cursor(Builder $builder)
    {
        return $this->lazyMap(
            $builder,
            $builder->applyAfterRawSearchCallback($this->search($builder)),
            $builder->model
        );
    }
}
