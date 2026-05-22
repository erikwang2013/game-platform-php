<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace  Erikwang2013\WebmanScout\Engines;

use Illuminate\Database\Eloquent\Collection;
use  Erikwang2013\WebmanScout\Builder;
use Elastic\Elasticsearch\Client as ElasticSearchClient;

class ElasticSearchEngine extends Engine
{
    /**
     * Elastic client.
     *
     * @var ElasticSearchClient
     */
    protected $elasticsearch;

    /**
     * Determines if soft deletes for Scout are enabled or not.
     *
     * @var bool
     */
    protected $softDelete;

    /**
     * Create a new engine instance.
     *
     * @param ElasticSearchClient $elasticsearch
     * @return void
     */
    public function __construct(ElasticSearchClient $elasticsearch, $softDelete = false)
    {
        $this->elasticsearch = $elasticsearch;
        $this->softDelete = $softDelete;
    }

    public function lazyMap(Builder $builder, $results, $model)
    {
        return Collection::make($results['hits']['hits'])->map(function ($hit) use ($model) {
            return $model->newFromBuilder($hit['_source']);
        });
    }

    public function createIndex($name, array $options = [])
    {
        $have = $this->elasticsearch->indices()->exists(['index' => $name]);
        if ($have->asBool()) {
            return;
        }
        $params = [
            'index' => $name,
        ];

        if(empty($options) && $config = scout_config('elasticsearch.'.$name)) {
            if (isset($config['settings'])) {
                $params['body']['settings'] = $config['settings'];
            }
            $this->elasticsearch->indices()->create($params);
            if (isset($config['aliases'])) {
                foreach ($config['aliases'] as $alias) {
                    $this->elasticsearch->indices()->updateAliases([
                        "body" => [
                            'actions' => [
                                [
                                    'add' => [
                                        'index' => $name,
                                        'alias' => $alias
                                    ]
                                ]
                            ]
                        ]
                    ]);
                }
            }

            if (isset($config['mappings'])) {
                foreach ($config['mappings'] as $type => $mapping) {
                    $this->elasticsearch->indices()->putMapping([
                        'index' => $name,
                        'type' => $type,
                        'body' => $mapping,
                        "include_type_name" => true
                    ]);
                }
            }
        }else{
            $this->elasticsearch->indices()->create(array_merge($params,$options));
        }
    }

    public function deleteIndex($name)
    {
        $this->elasticsearch->indices()->delete(['index' => $name]);
    }

    /**
     * Update the given model in the index.
     *
     * @param  Collection  $models
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }
        if ($this->usesSoftDelete($models->first()) && $this->softDelete) {
            $models->each->pushSoftDeleteMetadata();
        }
        $params['body'] = [];


        $models->each(function ($model) use (&$params) {
            if (empty($searchableData = $model->toSearchableArray())) {
                return;
            }
            $params['body'][] = [
                'update' => [
                    '_id' => $model->getScoutKey(),
                    '_index' => $model->searchableAs(),
                   // '_type' => get_class($model),
                ]
            ];
            $params['body'][] = [
                'doc' => $searchableData,
                'doc_as_upsert' => true
            ];
        });

        $this->elasticsearch->bulk($params);
    }

    /**
     * Remove the given model from the index.
     *
     * @param  Collection  $models
     * @return void
     */
    public function delete($models)
    {
        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'delete' => [
                    '_id' => $model->getKey(),
                    '_index' => $model->searchableAs(),
             //       '_type' => get_class($model),
                ]
            ];
        });

        $this->elasticsearch->bulk($params);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'size' => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'from' => (($page * $perPage) - $perPage),
            'size' => $perPage,
        ]);

       return $result;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder  $builder
     * @param  array  $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $params = [
            'index' => $builder->model->searchableAs(),
           // 'type' => get_class($builder->model),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [['query_string' => ['query' => "*{$builder->query}*"]]]
                    ]
                ]
            ]
        ];

        if ($sort = $this->sort($builder)) {
            $params['body']['sort'] = $sort;
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        if (isset($options['numericFilters']) && count($options['numericFilters'])) {
            $params['body']['query']['bool']['must'] = array_merge(
                $params['body']['query']['bool']['must'],
                $options['numericFilters']
            );
        }

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->elasticsearch,
                $builder->query,
                $params
            );
        }

        return $this->elasticsearch->search($params);
    }

    /**
     * Get the filter array for the query.
     *
     * @param  Builder  $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            if (is_array($value)) {
                return ['terms' => [$key => $value]];
            }

            return ['match_phrase' => [$key => $value]];
        })->values()->all();
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results['hits']['total'] === 0) {
            return $model->newCollection();
        }

        $keys = collect($results['hits']['hits'])->pluck('_id')->values()->all();

        $modelIdPositions = array_flip($keys);

        return $model->getScoutModelsByIds(
            $builder,
            $keys
        )->filter(function ($model) use ($keys) {
            return in_array($model->getScoutKey(), $keys);
        })->sortBy(function ($model) use ($modelIdPositions) {
            return $modelIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total']['value'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush($model)
    {
        $model->newQuery()
            ->orderBy($model->getKeyName())
            ->unsearchable();
    }

    /**
     * Generates the sort if theres any.
     *
     * @param  Builder $builder
     * @return array|null
     */
    protected function sort($builder)
    {
        if (count($builder->orders) == 0) {
            return null;
        }

        return collect($builder->orders)->map(function ($order) {
            return [$order['column'] => $order['direction']];
        })->toArray();
    }
    /**
     * Determine if the given model uses soft deletes.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model));
    }
    /**
     * Dynamically call the MeiliSearch client instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->elasticsearch->$method(...$parameters);
    }
}
