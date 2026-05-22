<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Engines;

use Erikwang2013\WebmanScout\Builder as AdvancedScoutBuilder;
use support\Log;
use OpenSearch\Client;

class AdvancedOpenSearchEngine extends OpenSearchEngine
{

    /**
     * 执行高级搜索
     */
    public function advancedSearch(AdvancedScoutBuilder $builder): array
    {
        $params = $this->buildAdvancedSearchParams($builder);
        
        $result = $this->opensearch->search($params);
        
        return $this->processAdvancedResults($result, $builder);
    }

    /**
     * 构建高级搜索参数
     */
    protected function buildAdvancedSearchParams(AdvancedScoutBuilder $builder): array
    {
        $index = $builder->index ?? $builder->model->searchableAs();
        $params = [
            'index' => $index,
            'body' => [
                'query' => $this->buildAdvancedQuery($builder),
                'size' => $builder->limit ?: 1000,
                'from' => $builder->offset ?: 0,
                '_source' => $this->getSourceFields($builder),
            ],
        ];

        // 添加排序
        if ($sorts = $builder->getSorts()) {
            $params['body']['sort'] = $this->buildAdvancedSorts($sorts);
        }

        // 添加聚合
        if ($aggregations = $builder->getAggregationConfig()) {
            $params['body']['aggs'] = $this->buildAggregations($aggregations);
        }

        // 添加高亮
        if ($builder->query && ($builder->options['highlight'] ?? true)) {
            $params['body']['highlight'] = $this->buildHighlight($builder);
        }

        // 添加向量搜索
        if ($vectorSearch = $builder->getVectorSearch()) {
            $params = $this->addVectorSearch($params, $vectorSearch);
        }

        // 添加分面搜索
        if ($facets = $builder->getFacetConfig()) {
            $params['body']['aggs'] = array_merge(
                $params['body']['aggs'] ?? [],
                $this->buildFacets($facets)
            );
        }

        // 添加建议
        if ($builder->options['suggest'] ?? false) {
            $params['body']['suggest'] = $this->buildSuggest($builder);
        }

        // 自定义回调
        if ($builder->callback) {
            return call_user_func($builder->callback, $this->opensearch, $builder, $params);
        }

        return $params;
    }

    /**
     * 构建高级查询
     */
    protected function buildAdvancedQuery(AdvancedScoutBuilder $builder): array
    {
        $query = ['bool' => []];

        // 处理基本查询
        if ($builder->query) {
            $query['bool']['must'][] = [
                'multi_match' => [
                    'query' => $builder->query,
                    'fields' => $this->getSearchFields($builder),
                    'type' => 'best_fields',
                    'operator' => 'AND',
                    'fuzziness' => 'AUTO',
                    'boost' => $builder->options['boost'] ?? 1.0,
                ],
            ];
        }

        // 处理基本 where 条件（确保不向 OpenSearch 传入字符串 "false"/"true"，避免 query_shard_exception）
        foreach ($builder->wheres as $field => $value) {
            $value = $this->coerceTermValue($value);
            if ($value === null) {
                continue;
            }
            $query['bool']['filter'][] = is_array($value)
                ? ['terms' => [$field => array_values(array_map([$this, 'coerceTermValue'], $value))]]
                : ['term' => [$field => $value]];
        }

        // 处理高级 where 条件（OpenSearch bool 只支持 must/filter/should/must_not，将 and/or/not 映射过去）
        foreach ($builder->getAdvancedWheres() as $condition) {
            $queryClause = $this->buildAdvancedCondition($condition);
            if ($queryClause) {
                $boolKey = $this->mapBooleanToBoolKey($condition['boolean'] ?? 'and');
                $query['bool'][$boolKey][] = $queryClause;
            }
        }

        // 处理 whereIn
        foreach ($builder->whereIns as $field => $values) {
            $query['bool']['filter'][] = ['terms' => [$field => $values]];
        }

        // 处理 whereNotIn
        foreach ($builder->whereNotIns as $field => $values) {
            $query['bool']['must_not'][] = ['terms' => [$field => $values]];
        }

        // 如果没有查询条件，返回 match_all
        if (empty($query['bool']['must']) && empty($query['bool']['filter'])) {
            return ['match_all' => new \stdClass()];
        }

        return $query;
    }

    /**
     * 构建 range 条件，仅包含有效数值，避免 "false"/false 导致 query_shard_exception；无效时返回 null 以跳过该条件
     */
    protected function buildRangeCondition(string $field, $gte, $lte, float $boost = 1.0): ?array
    {
        $clause = [];
        if ($gte !== null && $gte !== false && $gte !== 'false' && (is_numeric($gte) || $gte instanceof \DateTimeInterface)) {
            $clause['gte'] = is_numeric($gte) ? (strpos((string)$gte, '.') !== false ? (float)$gte : (int)$gte) : $gte->getTimestamp();
        }
        if ($lte !== null && $lte !== false && $lte !== 'false' && (is_numeric($lte) || $lte instanceof \DateTimeInterface)) {
            $clause['lte'] = is_numeric($lte) ? (strpos((string)$lte, '.') !== false ? (float)$lte : (int)$lte) : $lte->getTimestamp();
        }
        if ($clause === []) {
            return null;
        }
        if ($boost !== 1.0) {
            $clause['boost'] = $boost;
        }
        return ['range' => [$field => $clause]];
    }

    /**
     * 规整 term/terms 的值，避免传入 "false"/"true" 导致 query_shard_exception
     */
    protected function coerceTermValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if ($value === false || $value === 'false') {
            return 0;
        }
        if ($value === true || $value === 'true') {
            return 1;
        }
        if (is_numeric($value)) {
            return strpos((string)$value, '.') !== false ? (float)$value : (int)$value;
        }
        return $value;
    }

    /**
     * 将 Laravel 风格 boolean（and/or/not）映射为 OpenSearch bool 合法键
     * OpenSearch bool 仅支持: must, filter, should, must_not
     */
    protected function mapBooleanToBoolKey(string $boolean): string
    {
        return match (strtolower($boolean)) {
            'or' => 'should',
            'not' => 'must_not',
            'and', 'must', 'filter' => 'filter',
            default => 'filter',
        };
    }

    /**
     * 构建高级条件
     */
    protected function buildAdvancedCondition(array $condition): ?array
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        $options = $condition['options'] ?? [];

        return match($operator) {
            'range' => $this->buildRangeCondition(
                $field,
                $value['range'][0] ?? null,
                $value['range'][1] ?? null,
                $options['boost'] ?? 1.0
            ),
            'date_range' => [
                'range' => [
                    $field => [
                        'gte' => $value['range'][0] ?? null,
                        'lte' => $value['range'][1] ?? null,
                        'format' => $options['format'] ?? 'yyyy-MM-dd HH:mm:ss',
                        'time_zone' => $options['time_zone'] ?? '+08:00',
                    ],
                ],
            ],
            'geo_distance' => [
                'geo_distance' => [
                    'distance' => ($value['radius'] ?? 10) . ($value['unit'] ?? 'km'),
                    $field => [
                        'lat' => $value['lat'],
                        'lon' => $value['lng'],
                    ],
                    'distance_type' => $options['distance_type'] ?? 'plane',
                    'validation_method' => $options['validation_method'] ?? 'STRICT',
                    'boost' => $options['boost'] ?? 1.0,
                ],
            ],
            'geo_bounding_box' => [
                'geo_bounding_box' => [
                    $field => [
                        'top_left' => $value['top_left'],
                        'bottom_right' => $value['bottom_right'],
                    ],
                    'type' => $options['type'] ?? 'memory',
                ],
            ],
            'exists' => ['exists' => ['field' => $field]],
            'missing' => ['bool' => ['must_not' => [['exists' => ['field' => $field]]]]],
            'wildcard' => [
                'wildcard' => [
                    $field => [
                        'value' => $value,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'regexp' => [
                'regexp' => [
                    $field => [
                        'value' => $value,
                        'flags' => $options['flags'] ?? 'ALL',
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'prefix' => [
                'prefix' => [
                    $field => [
                        'value' => $value,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'match' => [
                'match' => [
                    $field => [
                        'query' => $value,
                        'operator' => $options['operator'] ?? 'or',
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'match_phrase' => [
                'match_phrase' => [
                    $field => [
                        'query' => $value,
                        'slop' => $options['slop'] ?? 0,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            '>', 'gt' => [
                'range' => [
                    $field => ['gt' => $value],
                ],
            ],
            '>=', 'gte' => [
                'range' => [
                    $field => ['gte' => $value],
                ],
            ],
            '<', 'lt' => [
                'range' => [
                    $field => ['lt' => $value],
                ],
            ],
            '<=', 'lte' => [
                'range' => [
                    $field => ['lte' => $value],
                ],
            ],
            default => [
                'term' => [
                    $field => [
                        'value' => $value,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
        };
    }

    /**
     * 获取需要返回的源字段
     */
    protected function getSourceFields(AdvancedScoutBuilder $builder): array
    {
        if ($fields = $builder->options['_source'] ?? null) {
            return is_array($fields) ? $fields : explode(',', $fields);
        }

        if ($excludes = $builder->options['_source_excludes'] ?? null) {
            return [
                'excludes' => is_array($excludes) ? $excludes : explode(',', $excludes),
            ];
        }

        if ($includes = $builder->options['_source_includes'] ?? null) {
            return [
                'includes' => is_array($includes) ? $includes : explode(',', $includes),
            ];
        }

        return ['*'];
    }

    /**
     * 获取搜索字段
     */
    protected function getSearchFields(AdvancedScoutBuilder $builder): array
    {
        if ($builder->options['fields'] ?? false) {
            return is_array($builder->options['fields']) 
                ? $builder->options['fields']
                : explode(',', $builder->options['fields']);
        }

        return parent::getSearchableFields($builder);
    }

    /**
     * 添加向量搜索
     */
    protected function addVectorSearch(array $params, array $vectorSearch): array
    {
        $vector = $vectorSearch['vector'] ?? null;
        $field = $vectorSearch['field'] ?? 'vector';
        $options = $vectorSearch['options'] ?? [];

        if (!$vector) {
            return $params;
        }

        // OpenSearch KNN 搜索
        $knnQuery = [
            'knn' => [
                $field => [
                    'vector' => $vector,
                    'k' => $options['k'] ?? 10,
                ],
            ],
        ];

        if (isset($options['filter'])) {
            $knnQuery['knn'][$field]['filter'] = $options['filter'];
        }

        // 混合搜索：结合向量搜索和关键词搜索
        if ($params['body']['query']['bool']['must'] ?? false) {
            $params['body']['query'] = [
                'bool' => [
                    'should' => [
                        $knnQuery,
                        $params['body']['query'],
                    ],
                    'minimum_should_match' => 1,
                ],
            ];
        } else {
            $params['body']['query'] = $knnQuery;
        }

        // 如果需要向量相似度得分
        if ($options['include_score'] ?? true) {
            $params['body']['_source'] = array_merge(
                $params['body']['_source'] ?? ['*'],
                ['_score', '_knn_score']
            );
        }

        return $params;
    }

    /**
     * 构建高级排序
     */
    protected function buildAdvancedSorts(array $sorts): array
    {
        $opensearchSorts = [];

        foreach ($sorts as $sort) {
            $type = $sort['type'] ?? 'field';
            $direction = $sort['direction'] ?? 'asc';
            $options = $sort['options'] ?? [];

            if ($type === 'vector_similarity') {
                $opensearchSorts[] = [
                    '_score' => ['order' => 'desc'],
                ];
            } elseif ($type === 'geo_distance') {
                $opensearchSorts[] = [
                    '_geo_distance' => [
                        $sort['field'] => [
                            'lat' => $sort['location']['lat'],
                            'lon' => $sort['location']['lng'],
                        ],
                        'order' => $direction,
                        'unit' => $sort['unit'] ?? 'km',
                        'distance_type' => $sort['distance_type'] ?? 'plane',
                    ],
                ];
            } elseif ($type === 'random') {
                $opensearchSorts[] = [
                    '_script' => [
                        'type' => 'number',
                        'script' => 'Math.random()',
                        'order' => 'asc',
                    ],
                ];
            } else {
                $field = $sort['field'];
                $opensearchSorts[$field] = [
                    'order' => $direction,
                    'missing' => $options['missing'] ?? '_last',
                    'mode' => $options['mode'] ?? 'min',
                ];
            }
        }

        return $opensearchSorts;
    }

    /**
     * 构建聚合
     */
    protected function buildAggregations(array $aggregations): array
    {
        $opensearchAggs = [];

        foreach ($aggregations as $name => $config) {
            $type = $config['type'];
            $field = $config['field'];
            $options = $config['options'] ?? [];

            $opensearchAggs[$name] = match($type) {
                'terms' => [
                    'terms' => [
                        'field' => $field,
                        'size' => $options['size'] ?? 10,
                        'order' => $options['order'] ?? ['_count' => 'desc'],
                        'min_doc_count' => $options['min_doc_count'] ?? 1,
                    ],
                ],
                'range' => [
                    'range' => [
                        'field' => $field,
                        'ranges' => $options['ranges'] ?? [],
                        'keyed' => true,
                    ],
                ],
                'date_range' => [
                    'date_range' => [
                        'field' => $field,
                        'ranges' => $options['ranges'] ?? [],
                        'format' => $options['format'] ?? 'yyyy-MM-dd',
                    ],
                ],
                'histogram' => [
                    'histogram' => [
                        'field' => $field,
                        'interval' => $options['interval'] ?? 100,
                    ],
                ],
                'date_histogram' => [
                    'date_histogram' => [
                        'field' => $field,
                        'calendar_interval' => $options['interval'] ?? '1d',
                        'format' => $options['format'] ?? 'yyyy-MM-dd',
                    ],
                ],
                'stats' => ['stats' => ['field' => $field]],
                'extended_stats' => ['extended_stats' => ['field' => $field]],
                'cardinality' => ['cardinality' => ['field' => $field]],
                default => null,
            };
        }

        return array_filter($opensearchAggs);
    }

    /**
     * 构建分面
     */
    protected function buildFacets(array $facets): array
    {
        $opensearchFacets = [];

        foreach ($facets as $field => $options) {
            $opensearchFacets["facet_{$field}"] = [
                'terms' => [
                    'field' => $field,
                    'size' => $options['size'] ?? 10,
                    'order' => $options['order'] ?? ['_count' => 'desc'],
                ],
            ];
        }

        return $opensearchFacets;
    }

    /**
     * 构建高亮
     */
    protected function buildHighlight(AdvancedScoutBuilder $builder): array
    {
        $fields = [];
        $highlightFields = $builder->options['highlight_fields'] ?? $this->getSearchFields($builder);

        foreach ($highlightFields as $field) {
            $fields[$field] = [
                'fragment_size' => $builder->options['fragment_size'] ?? 150,
                'number_of_fragments' => $builder->options['number_of_fragments'] ?? 3,
            ];
        }

        return [
            'fields' => $fields,
            'pre_tags' => $builder->options['highlight_pre_tags'] ?? ['<mark>'],
            'post_tags' => $builder->options['highlight_post_tags'] ?? ['</mark>'],
            'encoder' => $builder->options['highlight_encoder'] ?? 'html',
        ];
    }

    /**
     * 构建建议
     */
    protected function buildSuggest(AdvancedScoutBuilder $builder): array
    {
        $suggest = [];

        foreach ($builder->options['suggest_fields'] ?? [] as $field) {
            $suggest["suggest_{$field}"] = [
                'text' => $builder->query,
                'term' => [
                    'field' => $field,
                    'size' => $builder->options['suggest_size'] ?? 5,
                    'suggest_mode' => $builder->options['suggest_mode'] ?? 'always',
                ],
            ];
        }

        return $suggest;
    }


    /**
     * 处理高级搜索结果
     */
    protected function processAdvancedResults(array $result, AdvancedScoutBuilder $builder): array
    {
        $hits = $result['hits']['hits'] ?? [];
        $total = $result['hits']['total']['value'] ?? 0;

        $processedResults = [
            'hits' => array_map(function ($hit) use ($builder) {
                $document = $hit['_source'] ?? [];
                $document['_score'] = $hit['_score'] ?? 0.0;
                $document['_id'] = $hit['_id'] ?? null;
                $document['_index'] = $hit['_index'] ?? null;
                
                // 添加高亮
                if (isset($hit['highlight'])) {
                    $document['_highlight'] = $hit['highlight'];
                }
                
                // 添加向量搜索得分
                if ($builder->getVectorSearch() && isset($hit['_score'])) {
                    $document['_vector_score'] = $hit['_score'];
                }
                
                return $document;
            }, $hits),
            'total' => $total,
            'max_score' => $result['hits']['max_score'] ?? null,
            'aggregations' => $result['aggregations'] ?? [],
            'suggestions' => $result['suggest'] ?? [],
            'took' => $result['took'] ?? 0,
            'timed_out' => $result['timed_out'] ?? false,
        ];

        // 应用结果处理器
        foreach ($builder->getResultProcessors() as $processor) {
            $processedResults = $processor($processedResults);
        }

        return $processedResults;
    }

    /**
     * 获取聚合结果
     */
    public function getAggregations(AdvancedScoutBuilder $builder): array
    {
        $params = $this->buildAdvancedSearchParams($builder);
        
        // 只获取聚合，不返回文档
        unset($params['body']['size']);
        unset($params['body']['from']);
        
        $result = $this->opensearch->search($params);
        
        return $result['aggregations'] ?? [];
    }

    /**
     * 获取分面结果
     */
    public function getFacets(AdvancedScoutBuilder $builder): array
    {
        return $this->getAggregations($builder);
    }

    /**
     * 更新向量
     */
    public function updateVectors($models, array $vectors): void
    {
        $params = ['body' => []];
        $count = 0;

        foreach ($models as $index => $model) {
            $id = $model->getScoutKey();
            $indexName = $model->searchableAs();

            if (isset($vectors[$index])) {
                $params['body'][] = [
                    'update' => [
                        '_index' => $indexName,
                        '_id' => $id,
                    ],
                ];
                $params['body'][] = [
                    'doc' => [
                        'vector' => $vectors[$index],
                        'vector_updated_at' => now()->toISOString(),
                    ],
                    'doc_as_upsert' => true,
                ];

                $count++;

                if ($count % $this->bulkSize === 0) {
                    $this->executeBulk($params);
                    $params['body'] = [];
                }
            }
        }

        if (!empty($params['body'])) {
            $this->executeBulk($params);
        }
    }

    /**
     * 创建向量索引
     */
    public function createVectorIndex(string $index, int $dimensions = 1536, array $settings = []): bool
    {
        try {
            $defaultSettings = [
                'settings' => [
                    'index' => [
                        'number_of_shards' => $settings['shards'] ?? 1,
                        'number_of_replicas' => $settings['replicas'] ?? 1,
                        'knn' => true,
                        'knn.algo_param.ef_search' => $settings['ef_search'] ?? 100,
                        'knn.algo_param.ef_construction' => $settings['ef_construction'] ?? 512,
                        'knn.algo_param.m' => $settings['m'] ?? 16,
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'vector' => [
                            'type' => 'knn_vector',
                            'dimension' => $dimensions,
                            'method' => [
                                'name' => 'hnsw',
                                'space_type' => $settings['space_type'] ?? 'cosinesimil',
                                'engine' => 'nmslib',
                                'parameters' => $settings['parameters'] ?? [
                                    'ef_construction' => 512,
                                    'm' => 16,
                                ],
                            ],
                        ],
                    ],
                ],
            ];

            // 合并自定义设置
            if (!empty($settings['mappings']['properties'])) {
                $defaultSettings['mappings']['properties'] = array_merge(
                    $defaultSettings['mappings']['properties'],
                    $settings['mappings']['properties']
                );
            }

            if (!empty($settings['settings'])) {
                $defaultSettings['settings'] = array_merge_recursive(
                    $defaultSettings['settings'],
                    $settings['settings']
                );
            }

            $this->createIndex($index, $defaultSettings);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create OpenSearch vector index', [
                'index' => $index,
                'dimensions' => $dimensions,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * 执行 KNN 搜索
     */
    public function knnSearch(string $index, array $vector, int $k = 10, array $filter = null): array
    {
        $query = [
            'knn' => [
                'vector' => [
                    'vector' => $vector,
                    'k' => $k,
                ],
            ],
        ];

        if ($filter) {
            $query['knn']['vector']['filter'] = $filter;
        }

        $params = [
            'index' => $index,
            'body' => [
                'query' => $query,
                'size' => $k,
            ],
        ];

        $result = $this->opensearch->search($params);

        return array_map(function ($hit) {
            return [
                'id' => $hit['_id'],
                'score' => $hit['_score'],
                'source' => $hit['_source'],
            ];
        }, $result['hits']['hits'] ?? []);
    }

    /**
     * 执行混合搜索（关键词 + 向量）
     */
    public function hybridSearch(string $index, string $query, array $vector, float $alpha = 0.5, int $k = 10): array
    {
        $keywordQuery = [
            'multi_match' => [
                'query' => $query,
                'fields' => ['*'],
                'type' => 'best_fields',
            ],
        ];

        $vectorQuery = [
            'knn' => [
                'vector' => [
                    'vector' => $vector,
                    'k' => $k,
                ],
            ],
        ];

        $params = [
            'index' => $index,
            'body' => [
                'query' => [
                    'bool' => [
                        'should' => [
                            [
                                'function_score' => [
                                    'query' => $keywordQuery,
                                    'weight' => 1 - $alpha,
                                ],
                            ],
                            [
                                'function_score' => [
                                    'query' => $vectorQuery,
                                    'weight' => $alpha,
                                ],
                            ],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ],
                'size' => $k,
            ],
        ];

        $result = $this->opensearch->search($params);

        return array_map(function ($hit) {
            return [
                'id' => $hit['_id'],
                'score' => $hit['_score'],
                'source' => $hit['_source'],
            ];
        }, $result['hits']['hits'] ?? []);
    }

    /**
     * 获取引擎信息
     */
    public function getEngineInfo(): array
    {
        try {
            $info = $this->opensearch->info();
            $health = $this->opensearch->cluster()->health();
            
            return [
                'type' => 'opensearch',
                'version' => $info['version']['number'] ?? 'unknown',
                'distribution' => $info['version']['distribution'] ?? 'opensearch',
                'cluster_name' => $info['cluster_name'] ?? 'unknown',
                'cluster_status' => $health['status'] ?? 'unknown',
                'node_count' => $health['number_of_nodes'] ?? 0,
                'data_nodes' => $health['number_of_data_nodes'] ?? 0,
                'isHealthy' => ($health['status'] ?? 'red') !== 'red',
                'supportsVectors' => true,
                'supportsKNN' => true,
            ];
        } catch (\Exception $e) {
            return [
                'type' => 'opensearch',
                'error' => $e->getMessage(),
                'isHealthy' => false,
            ];
        }
    }

    /**
     * 优化后的搜索方法，支持 AdvancedScoutBuilder
     */
    public function search($builder)
    {
        if ($builder instanceof AdvancedScoutBuilder) {
            return $this->advancedSearch($builder);
        }

        return parent::search($builder);
    }

    /**
     * 优化后的分页方法，支持 AdvancedScoutBuilder
     */
    public function paginate($builder, $perPage, $page)
    {
        if ($builder instanceof AdvancedScoutBuilder) {
            $builder->limit = $perPage;
            $builder->offset = ($page - 1) * $perPage;
            return $this->advancedSearch($builder);
        }

        return parent::paginate($builder, $perPage, $page);
    }

    /**
     * 优化后的映射方法
     */
    public function map($builder, $results, $model)
    {
        if ($builder instanceof AdvancedScoutBuilder) {
            return $this->mapAdvancedResults($builder, $results, $model);
        }

        return parent::map($builder, $results, $model);
    }

    /**
     * 获取总数（兼容高级搜索处理后的结果格式）
     */
    public function getTotalCount($results)
    {
        if (isset($results['total']) && is_numeric($results['total'])) {
            return (int) $results['total'];
        }
        return parent::getTotalCount($results);
    }

    /**
     * 映射高级搜索结果
     */
    protected function mapAdvancedResults(AdvancedScoutBuilder $builder, $results, $model)
    {
        if (($results['total'] ?? 0) === 0) {
            return $model->newCollection();
        }

        $hits = $results['hits'] ?? [];
        $ids = array_column($hits, '_id');
        $idPositions = array_flip($ids);

        $models = $model->getScoutModelsByIds($builder, $ids)
            ->filter(fn($model) => in_array($model->getScoutKey(), $ids))
            ->sortBy(fn($model) => $idPositions[$model->getScoutKey()])
            ->values();

        // 添加额外的元数据
        foreach ($models as $index => $model) {
            if (isset($hits[$index])) {
                $hit = $hits[$index];
                $model->_score = $hit['_score'] ?? null;
                $model->_highlight = $hit['_highlight'] ?? null;
                $model->_vector_score = $hit['_vector_score'] ?? null;
            }
        }

        return $models;
    }
}