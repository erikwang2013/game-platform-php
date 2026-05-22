<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Engines;

use Erikwang2013\WebmanScout\Builder as AdvancedScoutBuilder;


class AdvancedElasticsearchEngine extends ElasticsearchEngine
{
    /**
     * 获取需要返回的源字段
     */
    protected function getSourceFields(AdvancedScoutBuilder $builder): array
    {
        // 如果指定了特定字段，只返回这些字段
        if ($fields = $builder->options['_source'] ?? null) {
            return is_array($fields) ? $fields : explode(',', $fields);
        }

        // 如果指定了排除字段
        if ($excludes = $builder->options['_source_excludes'] ?? null) {
            return [
                'excludes' => is_array($excludes) ? $excludes : explode(',', $excludes),
            ];
        }

        // 如果指定了包含字段
        if ($includes = $builder->options['_source_includes'] ?? null) {
            return [
                'includes' => is_array($includes) ? $includes : explode(',', $includes),
            ];
        }

        // 默认返回所有字段
        return ['*'];
    }

    /**
     * 获取搜索字段
     */
    protected function getSearchFields(AdvancedScoutBuilder $builder): array
    {
        // 优先使用 builder 指定的字段
        if ($builder->options['fields'] ?? false) {
            return is_array($builder->options['fields']) 
                ? $builder->options['fields']
                : explode(',', $builder->options['fields']);
        }

        // 使用模型定义的搜索字段
        if (method_exists($builder->model, 'searchableFields')) {
            $fields = $builder->model->searchableFields();
            
            // 如果是键值对格式，则提取键名
            if (array_values($fields) !== $fields) {
                return array_keys($fields);
            }
            
            return $fields;
        }

        // 默认搜索所有字段
        return ['*'];
    }

    /**
     * 为嵌套查询构建查询条件
     */
    protected function buildQueryForNested(array $query): array
    {
        $nestedQuery = ['bool' => []];

        foreach ($query as $condition) {
            if (!isset($condition['field']) || !isset($condition['operator'])) {
                continue;
            }

            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'] ?? null;
            $boolean = $condition['boolean'] ?? 'must';
            $options = $condition['options'] ?? [];

            $queryClause = $this->buildNestedCondition($field, $operator, $value, $options);

            if ($queryClause) {
                $nestedQuery['bool'][$boolean][] = $queryClause;
            }
        }

        return $nestedQuery;
    }

    /**
     * 构建嵌套查询条件
     */
    protected function buildNestedCondition(string $field, string $operator, $value, array $options = []): array
    {
        return match($operator) {
            '=' => [
                'term' => [
                    $field => [
                        'value' => $value,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            '!=' => [
                'bool' => [
                    'must_not' => [
                        [
                            'term' => [
                                $field => [
                                    'value' => $value,
                                    'boost' => $options['boost'] ?? 1.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '>' => [
                'range' => [
                    $field => [
                        'gt' => $value,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            '>=' => [
                'range' => [
                    $field => [
                        'gte' => $value,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            '<' => [
                'range' => [
                    $field => [
                        'lt' => $value,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            '<=' => [
                'range' => [
                    $field => [
                        'lte' => $value,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'range' => [
                'range' => [
                    $field => [
                        'gte' => $value['range'][0] ?? null,
                        'lte' => $value['range'][1] ?? null,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'in' => [
                'terms' => [
                    $field => is_array($value) ? $value : [$value],
                    'boost' => $options['boost'] ?? 1.0,
                ],
            ],
            'not_in' => [
                'bool' => [
                    'must_not' => [
                        [
                            'terms' => [
                                $field => is_array($value) ? $value : [$value],
                                'boost' => $options['boost'] ?? 1.0,
                            ],
                        ],
                    ],
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
                        'max_determinized_states' => $options['max_determinized_states'] ?? 10000,
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
            'fuzzy' => [
                'fuzzy' => [
                    $field => [
                        'value' => $value,
                        'fuzziness' => $options['fuzziness'] ?? 'AUTO',
                        'max_expansions' => $options['max_expansions'] ?? 50,
                        'prefix_length' => $options['prefix_length'] ?? 0,
                        'transpositions' => $options['transpositions'] ?? true,
                        'rewrite' => $options['rewrite'] ?? 'constant_score',
                    ],
                ],
            ],
            'match' => [
                'match' => [
                    $field => [
                        'query' => $value,
                        'operator' => $options['operator'] ?? 'or',
                        'minimum_should_match' => $options['minimum_should_match'] ?? '1',
                        'fuzziness' => $options['fuzziness'] ?? 'AUTO',
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'match_phrase' => [
                'match_phrase' => [
                    $field => [
                        'query' => $value,
                        'slop' => $options['slop'] ?? 0,
                        'analyzer' => $options['analyzer'] ?? null,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'match_phrase_prefix' => [
                'match_phrase_prefix' => [
                    $field => [
                        'query' => $value,
                        'slop' => $options['slop'] ?? 0,
                        'max_expansions' => $options['max_expansions'] ?? 50,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'multi_match' => [
                'multi_match' => [
                    'query' => $value['query'] ?? $value,
                    'fields' => $value['fields'] ?? [$field],
                    'type' => $options['type'] ?? 'best_fields',
                    'operator' => $options['operator'] ?? 'or',
                    'minimum_should_match' => $options['minimum_should_match'] ?? '1',
                    'fuzziness' => $options['fuzziness'] ?? 'AUTO',
                    'tie_breaker' => $options['tie_breaker'] ?? 0.3,
                    'boost' => $options['boost'] ?? 1.0,
                ],
            ],
            default => null,
        };
    }

    /**
     * 构建 Elasticsearch 条件（增强版）
     */
    protected function buildCondition(array $condition): array
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        $options = $condition['options'] ?? [];

        return match($operator) {
            'range' => [
                'range' => [
                    $field => array_merge(
                        [
                            'gte' => $value['range'][0] ?? null,
                            'lte' => $value['range'][1] ?? null,
                        ],
                        isset($value['boost']) ? ['boost' => $value['boost']] : [],
                        isset($options['format']) ? ['format' => $options['format']] : [],
                        isset($options['time_zone']) ? ['time_zone' => $options['time_zone']] : [],
                        isset($options['relation']) ? ['relation' => $options['relation']] : []
                    ),
                ],
            ],
            'date_range' => [
                'range' => [
                    $field => array_merge(
                        [
                            'gte' => $value['range'][0] ?? null,
                            'lte' => $value['range'][1] ?? null,
                        ],
                        isset($options['format']) ? ['format' => $options['format']] : [],
                        isset($options['time_zone']) ? ['time_zone' => $options['time_zone']] : []
                    ),
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
                    'ignore_unmapped' => $options['ignore_unmapped'] ?? false,
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
                    'validation_method' => $options['validation_method'] ?? 'STRICT',
                    'ignore_unmapped' => $options['ignore_unmapped'] ?? false,
                ],
            ],
            'geo_polygon' => [
                'geo_polygon' => [
                    $field => [
                        'points' => $value['points'],
                    ],
                ],
            ],
            'geo_shape' => [
                'geo_shape' => [
                    $field => [
                        'shape' => $value['shape'],
                        'relation' => $value['relation'] ?? 'intersects',
                    ],
                    'ignore_unmapped' => $options['ignore_unmapped'] ?? true,
                ],
            ],
            'exists' => ['exists' => ['field' => $field]],
            'missing' => ['bool' => ['must_not' => [['exists' => ['field' => $field]]]]],
            'wildcard' => [
                'wildcard' => [
                    $field => [
                        'value' => $value,
                        'case_insensitive' => $options['case_insensitive'] ?? false,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'regexp' => [
                'regexp' => [
                    $field => [
                        'value' => $value,
                        'flags' => $options['flags'] ?? 'ALL',
                        'max_determinized_states' => $options['max_determinized_states'] ?? 10000,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'prefix' => [
                'prefix' => [
                    $field => [
                        'value' => $value,
                        'case_insensitive' => $options['case_insensitive'] ?? false,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'fuzzy' => [
                'fuzzy' => [
                    $field => [
                        'value' => $value,
                        'fuzziness' => $options['fuzziness'] ?? 'AUTO',
                        'max_expansions' => $options['max_expansions'] ?? 50,
                        'prefix_length' => $options['prefix_length'] ?? 0,
                        'transpositions' => $options['transpositions'] ?? true,
                        'rewrite' => $options['rewrite'] ?? 'constant_score',
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'match' => [
                'match' => [
                    $field => [
                        'query' => $value,
                        'operator' => $options['operator'] ?? 'or',
                        'minimum_should_match' => $options['minimum_should_match'] ?? '1',
                        'fuzziness' => $options['fuzziness'] ?? 'AUTO',
                        'analyzer' => $options['analyzer'] ?? null,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'match_phrase' => [
                'match_phrase' => [
                    $field => [
                        'query' => $value,
                        'slop' => $options['slop'] ?? 0,
                        'analyzer' => $options['analyzer'] ?? null,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'match_phrase_prefix' => [
                'match_phrase_prefix' => [
                    $field => [
                        'query' => $value,
                        'slop' => $options['slop'] ?? 0,
                        'max_expansions' => $options['max_expansions'] ?? 50,
                        'analyzer' => $options['analyzer'] ?? null,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'multi_match' => [
                'multi_match' => [
                    'query' => $value['query'] ?? $value,
                    'fields' => $value['fields'] ?? [$field],
                    'type' => $options['type'] ?? 'best_fields',
                    'operator' => $options['operator'] ?? 'or',
                    'minimum_should_match' => $options['minimum_should_match'] ?? '1',
                    'fuzziness' => $options['fuzziness'] ?? 'AUTO',
                    'analyzer' => $options['analyzer'] ?? null,
                    'tie_breaker' => $options['tie_breaker'] ?? 0.3,
                    'boost' => $options['boost'] ?? 1.0,
                ],
            ],
            'query_string' => [
                'query_string' => [
                    'query' => $value['query'] ?? $value,
                    'default_field' => $value['default_field'] ?? $field,
                    'fields' => $value['fields'] ?? null,
                    'default_operator' => $options['default_operator'] ?? 'OR',
                    'analyzer' => $options['analyzer'] ?? 'standard',
                    'quote_analyzer' => $options['quote_analyzer'] ?? null,
                    'allow_leading_wildcard' => $options['allow_leading_wildcard'] ?? true,
                    'enable_position_increments' => $options['enable_position_increments'] ?? true,
                    'fuzziness' => $options['fuzziness'] ?? null,
                    'fuzzy_prefix_length' => $options['fuzzy_prefix_length'] ?? 0,
                    'fuzzy_max_expansions' => $options['fuzzy_max_expansions'] ?? 50,
                    'phrase_slop' => $options['phrase_slop'] ?? 0,
                    'analyze_wildcard' => $options['analyze_wildcard'] ?? false,
                    'max_determinized_states' => $options['max_determinized_states'] ?? 10000,
                    'minimum_should_match' => $options['minimum_should_match'] ?? null,
                    'lenient' => $options['lenient'] ?? false,
                    'time_zone' => $options['time_zone'] ?? null,
                    'boost' => $options['boost'] ?? 1.0,
                ],
            ],
            'simple_query_string' => [
                'simple_query_string' => [
                    'query' => $value['query'] ?? $value,
                    'fields' => $value['fields'] ?? [$field],
                    'default_operator' => $options['default_operator'] ?? 'OR',
                    'analyzer' => $options['analyzer'] ?? 'standard',
                    'flags' => $options['flags'] ?? 'ALL',
                    'lenient' => $options['lenient'] ?? false,
                    'minimum_should_match' => $options['minimum_should_match'] ?? '1',
                    'boost' => $options['boost'] ?? 1.0,
                ],
            ],
            'term' => [
                'term' => [
                    $field => [
                        'value' => $value,
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'terms' => [
                'terms' => [
                    $field => is_array($value) ? $value : [$value],
                    'boost' => $options['boost'] ?? 1.0,
                ],
            ],
            'terms_set' => [
                'terms_set' => [
                    $field => [
                        'terms' => is_array($value) ? $value : [$value],
                        'minimum_should_match_script' => [
                            'source' => $options['minimum_should_match_script'] ?? 'params.num_terms',
                        ],
                        'boost' => $options['boost'] ?? 1.0,
                    ],
                ],
            ],
            'nested' => [
                'nested' => [
                    'path' => $field,
                    'query' => $this->buildQueryForNested($value['query'] ?? $value),
                    'score_mode' => $value['score_mode'] ?? 'avg',
                    'ignore_unmapped' => $options['ignore_unmapped'] ?? false,
                    'inner_hits' => $value['inner_hits'] ?? null,
                ],
            ],
            'has_child' => [
                'has_child' => [
                    'type' => $field,
                    'query' => $this->buildQueryForNested($value['query'] ?? $value),
                    'score_mode' => $value['score_mode'] ?? 'none',
                    'min_children' => $value['min_children'] ?? null,
                    'max_children' => $value['max_children'] ?? null,
                    'inner_hits' => $value['inner_hits'] ?? null,
                ],
            ],
            'has_parent' => [
                'has_parent' => [
                    'parent_type' => $field,
                    'query' => $this->buildQueryForNested($value['query'] ?? $value),
                    'score' => $value['score'] ?? false,
                    'inner_hits' => $value['inner_hits'] ?? null,
                ],
            ],
            'parent_id' => [
                'parent_id' => [
                    'type' => $field,
                    'id' => $value['id'] ?? $value,
                    'ignore_unmapped' => $options['ignore_unmapped'] ?? false,
                ],
            ],
            'script' => [
                'script' => [
                    'script' => [
                        'source' => $value['source'] ?? $value,
                        'lang' => $value['lang'] ?? 'painless',
                        'params' => $value['params'] ?? [],
                    ],
                    'boost' => $options['boost'] ?? 1.0,
                ],
            ],
            'more_like_this' => [
                'more_like_this' => [
                    'fields' => $value['fields'] ?? [$field],
                    'like' => $value['like'] ?? $value,
                    'unlike' => $value['unlike'] ?? null,
                    'max_query_terms' => $options['max_query_terms'] ?? 25,
                    'min_term_freq' => $options['min_term_freq'] ?? 2,
                    'min_doc_freq' => $options['min_doc_freq'] ?? 5,
                    'max_doc_freq' => $options['max_doc_freq'] ?? null,
                    'min_word_length' => $options['min_word_length'] ?? 0,
                    'max_word_length' => $options['max_word_length'] ?? 0,
                    'stop_words' => $options['stop_words'] ?? [],
                    'analyzer' => $options['analyzer'] ?? null,
                    'minimum_should_match' => $options['minimum_should_match'] ?? '30%',
                    'boost_terms' => $options['boost_terms'] ?? null,
                    'include' => $options['include'] ?? false,
                    'fail_on_unsupported_field' => $options['fail_on_unsupported_field'] ?? true,
                    'boost' => $options['boost'] ?? 1.0,
                ],
            ],
            'distance_feature' => [
                'distance_feature' => [
                    'field' => $field,
                    'pivot' => $value['pivot'],
                    'origin' => $value['origin'],
                    'boost' => $options['boost'] ?? 1.0,
                ],
            ],
            'percolate' => [
                'percolate' => [
                    'field' => $field,
                    'document' => $value['document'],
                    'document_type' => $value['document_type'] ?? null,
                    'indexed_document_index' => $value['indexed_document_index'] ?? null,
                    'indexed_document_id' => $value['indexed_document_id'] ?? null,
                    'indexed_document_routing' => $value['indexed_document_routing'] ?? null,
                    'indexed_document_preference' => $value['indexed_document_preference'] ?? null,
                    'indexed_document_version' => $value['indexed_document_version'] ?? null,
                ],
            ],
            'shape' => [
                'shape' => [
                    $field => [
                        'shape' => $value['shape'],
                        'relation' => $value['relation'] ?? 'intersects',
                    ],
                    'ignore_unmapped' => $options['ignore_unmapped'] ?? false,
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
     * 处理高级搜索结果
     */
    protected function processAdvancedResults(array $result, AdvancedScoutBuilder $builder): array
    {
        $hits = $result['hits']['hits'] ?? [];
        $total = $result['hits']['total']['value'] ?? 0;
        
        $maxScore = $result['hits']['max_score'] ?? null;

        $processedResults = [
            'hits' => array_map(function ($hit) use ($builder) {
                $document = $hit['_source'] ?? [];
                $document['_score'] = $hit['_score'] ?? 0.0;
                $document['_id'] = $hit['_id'] ?? null;
                $document['_index'] = $hit['_index'] ?? null;
                $document['_type'] = $hit['_type'] ?? '_doc';
                
                // 添加高亮
                if (isset($hit['highlight'])) {
                    $document['_highlight'] = $hit['highlight'];
                }
                
                // 添加向量相似度得分
                if ($vectorSearch = $builder->getVectorSearch()) {
                    $document['_vector_score'] = $hit['_score'] ?? 0.0;
                }
                
                // 添加 inner_hits（嵌套查询结果）
                if (isset($hit['inner_hits'])) {
                    $document['_inner_hits'] = $hit['inner_hits'];
                }
                
                // 添加 matched_queries（匹配的查询名称）
                if (isset($hit['matched_queries'])) {
                    $document['_matched_queries'] = $hit['matched_queries'];
                }
                
                return $document;
            }, $hits),
            'total' => $total,
            'max_score' => $maxScore,
            'aggregations' => $result['aggregations'] ?? [],
            'suggestions' => $result['suggest'] ?? [],
            'took' => $result['took'] ?? 0,
            'timed_out' => $result['timed_out'] ?? false,
            '_shards' => $result['_shards'] ?? [],
        ];

        // 应用结果处理器
        foreach ($builder->getResultProcessors() as $processor) {
            $processedResults = $processor($processedResults);
        }

        return $processedResults;
    }

    /**
     * 构建向量搜索脚本
     */
    protected function getVectorScript(string $metric): string
    {
        return match($metric) {
            'cosine' => "cosineSimilarity(params.query_vector, doc[params.field]) + 1.0",
            'dotproduct' => "dotProduct(params.query_vector, doc[params.field])",
            'l1' => "1 / (1 + l1norm(params.query_vector, doc[params.field]))",
            'l2', 'euclidean' => "1 / (1 + l2norm(params.query_vector, doc[params.field]))",
            'hamming' => "1 - (hammingDistance(params.query_vector, doc[params.field]) / params.vector_length)",
            'jaccard' => "1 - (jaccardDistance(params.query_vector, doc[params.field]) / params.vector_length)",
            'manhattan' => "1 / (1 + manhattanDistance(params.query_vector, doc[params.field]))",
            'chebyshev' => "1 / (1 + chebyshevDistance(params.query_vector, doc[params.field]))",
            'minkowski' => "1 / (1 + minkowskiDistance(params.query_vector, doc[params.field], params.p))",
            default => "cosineSimilarity(params.query_vector, doc[params.field]) + 1.0",
        };
    }

    /**
     * 获取结果处理器列表
     * 这是一个辅助方法，从 builder 中获取结果处理器
     */
    protected function getResultProcessors(AdvancedScoutBuilder $builder): array
    {
        // 通过反射获取私有属性，或者修改 AdvancedScoutBuilder 添加 getResultProcessors 方法
        try {
            $reflection = new \ReflectionClass($builder);
            $property = $reflection->getProperty('resultProcessors');
            $property->setAccessible(true);
            return $property->getValue($builder);
        } catch (\ReflectionException $e) {
            return [];
        }
    }

    /**
     * 计算分页页码
     */
    protected function calculatePage(AdvancedScoutBuilder $builder): int
    {
        if ($builder->limit && $builder->offset) {
            return (int) ($builder->offset / $builder->limit) + 1;
        }
        
        return 1;
    }

    /**
     * 获取查询字段（用于搜索）
     */
    protected function getQueryByFields(AdvancedScoutBuilder $builder): string
    {
        $fields = $this->getSearchFields($builder);
        
        // 如果是权重字段（如 title^2, description^1）
        if (method_exists($builder->model, 'searchableFields')) {
            $modelFields = $builder->model->searchableFields();
            
            if (array_values($modelFields) !== $modelFields) {
                $weightedFields = [];
                foreach ($modelFields as $field => $weight) {
                    $weightedFields[] = "{$field}^{$weight}";
                }
                return implode(',', $weightedFields);
            }
        }
        
        return implode(',', $fields);
    }

    /**
     * 获取包含字段
     */
    protected function getIncludeFields(AdvancedScoutBuilder $builder): ?string
    {
        if ($includes = $builder->options['includes'] ?? $builder->options['include_fields'] ?? null) {
            return is_array($includes) ? implode(',', $includes) : $includes;
        }
        
        return null;
    }

    /**
     * 获取排除字段
     */
    protected function getExcludeFields(AdvancedScoutBuilder $builder): ?string
    {
        if ($excludes = $builder->options['excludes'] ?? $builder->options['exclude_fields'] ?? null) {
            return is_array($excludes) ? implode(',', $excludes) : $excludes;
        }
        
        return null;
    }

    /**
     * 获取高亮字段
     */
    protected function getHighlightFields(AdvancedScoutBuilder $builder): ?string
    {
        if ($highlightFields = $builder->options['highlight_fields'] ?? null) {
            return is_array($highlightFields) ? implode(',', $highlightFields) : $highlightFields;
        }
        
        return null;
    }

    /**
     * 获取滚动 ID（用于深度分页）
     */
    protected function getScrollId(): ?string
    {
        return request()->header('X-Scroll-Id') ?? null;
    }

    /**
     * 获取偏好参数（用于路由和缓存）
     */
    protected function getPreference(AdvancedScoutBuilder $builder): ?string
    {
        return $builder->options['preference'] ?? null;
    }

    /**
     * 获取路由参数
     */
    protected function getRouting(AdvancedScoutBuilder $builder): ?string
    {
        return $builder->options['routing'] ?? null;
    }

    /**
     * 获取搜索类型
     */
    protected function getSearchType(AdvancedScoutBuilder $builder): string
    {
        return $builder->options['search_type'] ?? 'query_then_fetch';
    }

    /**
     * 获取请求缓存设置
     */
    protected function getRequestCache(AdvancedScoutBuilder $builder): ?bool
    {
        return $builder->options['request_cache'] ?? null;
    }

    /**
     * 获取允许部分搜索结果
     */
    protected function getAllowPartialSearchResults(AdvancedScoutBuilder $builder): ?bool
    {
        return $builder->options['allow_partial_search_results'] ?? null;
    }

    /**
     * 获取批次减少大小
     */
    protected function getBatchedReduceSize(AdvancedScoutBuilder $builder): ?int
    {
        return $builder->options['batched_reduce_size'] ?? null;
    }

    /**
     * 获取最大并发分片请求数
     */
    protected function getMaxConcurrentShardRequests(AdvancedScoutBuilder $builder): ?int
    {
        return $builder->options['max_concurrent_shard_requests'] ?? null;
    }

    /**
     * 获取预过滤器分片大小
     */
    protected function getPreFilterShardSize(AdvancedScoutBuilder $builder): ?int
    {
        return $builder->options['pre_filter_shard_size'] ?? null;
    }

    /**
     * 获取是否启用统计信息
     */
    protected function getStats(AdvancedScoutBuilder $builder): ?array
    {
        return $builder->options['stats'] ?? null;
    }

    /**
     * 获取超时设置
     */
    protected function getTimeout(AdvancedScoutBuilder $builder): ?string
    {
        return $builder->options['timeout'] ?? null;
    }

    /**
     * 获取终止后查询
     */
    protected function getTerminateAfter(AdvancedScoutBuilder $builder): ?int
    {
        return $builder->options['terminate_after'] ?? null;
    }

    /**
     * 获取版本设置
     */
    protected function getVersion(AdvancedScoutBuilder $builder): ?bool
    {
        return $builder->options['version'] ?? null;
    }

    /**
     * 获取序列得分器模式
     */
    protected function getSeqNoPrimaryTerm(AdvancedScoutBuilder $builder): ?bool
    {
        return $builder->options['seq_no_primary_term'] ?? null;
    }

    /**
     * 获取存储字段
     */
    protected function getStoredFields(AdvancedScoutBuilder $builder): ?array
    {
        return $builder->options['stored_fields'] ?? null;
    }

    /**
     * 获取文档值字段
     */
    protected function getDocValueFields(AdvancedScoutBuilder $builder): ?array
    {
        return $builder->options['docvalue_fields'] ?? null;
    }

    /**
     * 获取解释设置
     */
    protected function getExplain(AdvancedScoutBuilder $builder): ?bool
    {
        return $builder->options['explain'] ?? null;
    }

    /**
     * 获取字段数据字段
     */
    protected function getFieldDataFields(AdvancedScoutBuilder $builder): ?array
    {
        return $builder->options['fielddata_fields'] ?? null;
    }

    /**
     * 获取源字段过滤
     */
    protected function getSourceFilter(AdvancedScoutBuilder $builder): ?array
    {
        return $builder->options['_source'] ?? null;
    }

    /**
     * 获取索引提升设置
     */
    protected function getIndicesBoost(AdvancedScoutBuilder $builder): ?array
    {
        return $builder->options['indices_boost'] ?? null;
    }

    /**
     * 获取最小分数设置
     */
    protected function getMinScore(AdvancedScoutBuilder $builder): ?float
    {
        return $builder->options['min_score'] ?? null;
    }

    /**
     * 获取命名查询设置
     */
    protected function getQueryName(AdvancedScoutBuilder $builder): ?string
    {
        return $builder->options['query_name'] ?? null;
    }
}