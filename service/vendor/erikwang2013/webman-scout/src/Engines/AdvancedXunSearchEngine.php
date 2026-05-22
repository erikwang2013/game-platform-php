<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Engines;

use Erikwang2013\WebmanScout\Builder as AdvancedScoutBuilder;
use support\Log;
use support\Cache;


class AdvancedXunSearchEngine extends XunSearchEngine
{
    /**
     * 高级查询条件处理器
     */
    protected array $conditionHandlers = [];

    /**
     * 高级搜索缓存键前缀
     */
    protected string $cachePrefix = 'xunsearch_advanced:';

    /**
     * 执行高级搜索
     */
    public function advancedSearch(AdvancedScoutBuilder $builder): array
    {
        // 检查缓存
        $cacheKey = $this->getCacheKey($builder);
        $cacheEnabled = $builder->options['cache'] ?? scout_config('xunsearch.cache.enabled', true);
        $cacheTtl = $builder->options['cache_ttl'] ?? scout_config('xunsearch.cache.ttl', 300);
        
        if ($cacheEnabled && $cached = $this->getAdvancedSearchCache($cacheKey)) {
            Log::debug('Advanced XunSearch cache hit', ['key' => $cacheKey]);
            return $cached;
        }

        $result = $this->performAdvancedSearch($builder);
        
        // 应用结果处理器
        if (method_exists($builder, 'getResultProcessors')) {
            $processors = $builder->getResultProcessors();
            foreach ($processors as $processor) {
                $result = $processor($result);
            }
        }

        // 缓存结果
        if ($cacheEnabled && $cacheTtl > 0) {
            $this->setAdvancedSearchCache($cacheKey, $result, $cacheTtl);
        }

        return $result;
    }

    /**
     * 执行高级搜索
     */
    protected function performAdvancedSearch(AdvancedScoutBuilder $builder): array
    {
        $indexName = $builder->index ?: $builder->model->searchableAs();
        $search = $this->xunsearch->refresh($indexName)->getSearch();

        // 构建查询
        $query = $this->buildAdvancedQuery($builder);
        $search->setQuery($query);

        // 设置搜索选项
        $this->applySearchOptions($search, $builder);

        // 应用高级条件
        $this->applyAdvancedConditions($search, $builder);

        // 应用排序
        $this->applyAdvancedSorts($search, $builder);

        // 设置分页
        $limit = $builder->limit ?: 20;
        $offset = $builder->offset ?: 0;
        
        // 执行搜索
        $rawResults = $search->setLimit($limit, $offset)->search();

        // 处理结果
        return $this->processAdvancedResults($rawResults, $search, $builder);
    }

    /**
     * 构建高级查询
     */
    protected function buildAdvancedQuery(AdvancedScoutBuilder $builder): string
    {
        $queryParts = [];

        // 添加主查询
        if ($builder->query) {
            // 检查是否为布尔查询
            if ($this->isBooleanQuery($builder->query)) {
                $queryParts[] = $builder->query;
            } else {
                // 添加字段限制
                $fields = $this->getQueryFields($builder);
                if ($fields) {
                    $queryParts[] = "{$fields}:{$builder->query}";
                } else {
                    $queryParts[] = $builder->query;
                }
            }
        } else {
            // 如果没有查询词，使用通配符查询所有
            $queryParts[] = '*';
        }

        // 添加基础 where 条件
        foreach ($builder->wheres as $field => $value) {
            if (is_array($value)) {
                // 范围查询
                if (count($value) === 2) {
                    $queryParts[] = "{$field}:[{$value[0]} TO {$value[1]}]";
                }
            } else {
                // 精确匹配
                $queryParts[] = "{$field}:{$value}";
            }
        }

        // 添加高级 where 条件
        if (method_exists($builder, 'getAdvancedWheres')) {
            foreach ($builder->getAdvancedWheres() as $condition) {
                $queryParts[] = $this->buildConditionQuery($condition);
            }
        }

        // 组合查询
        $query = implode(' ', array_filter($queryParts));
        
        return $query;
    }

    /**
     * 检查是否为布尔查询
     */
    protected function isBooleanQuery(string $query): bool
    {
        $booleanOperators = ['AND', 'OR', 'NOT', '(', ')', 'NEAR', 'SENTENCE', 'PARAGRAPH'];
        
        foreach ($booleanOperators as $operator) {
            if (stripos($query, $operator) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 获取查询字段
     */
    protected function getQueryFields(AdvancedScoutBuilder $builder): string
    {
        if (isset($builder->options['fields'])) {
            $fields = $builder->options['fields'];
            return is_array($fields) ? implode(',', $fields) : $fields;
        }

        // 使用模型定义的搜索字段
        if (method_exists($builder->model, 'searchableFields')) {
            $fields = $builder->model->searchableFields();
            
            if (is_array($fields)) {
                return implode(',', array_keys($fields));
            }
        }

        return '';
    }

    /**
     * 构建条件查询
     */
    protected function buildConditionQuery(array $condition): string
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        $options = $condition['options'] ?? [];

        // 检查是否有自定义处理器
        if (isset($this->conditionHandlers[$operator])) {
            return $this->conditionHandlers[$operator]($field, $value, $options);
        }

        return match($operator) {
            'range' => $this->buildRangeQuery($field, $value),
            'date_range' => $this->buildDateRangeQuery($field, $value, $options),
            'in' => $this->buildInQuery($field, (array)$value),
            'not_in' => $this->buildNotInQuery($field, (array)$value),
            '>' => "{$field}:>{$value}",
            '>=' => "{$field}:>={$value}",
            '<' => "{$field}:<{$value}",
            '<=' => "{$field}:<={$value}",
            '!=' => "NOT {$field}:{$value}",
            'like' => "{$field}:*{$value}*",
            'starts_with' => "{$field}:{$value}*",
            'ends_with' => "{$field}:*{$value}",
            'exists' => "{$field}:[* TO *]",
            'missing' => "NOT {$field}:[* TO *]",
            'fuzzy' => $this->buildFuzzyQuery($field, $value, $options),
            'proximity' => $this->buildProximityQuery($field, $value, $options),
            default => "{$field}:{$value}",
        };
    }

    /**
     * 构建范围查询
     */
    protected function buildRangeQuery(string $field, array $range): string
    {
        $min = $range['range'][0] ?? null;
        $max = $range['range'][1] ?? null;
        
        if ($min !== null && $max !== null) {
            return "{$field}:[{$min} TO {$max}]";
        } elseif ($min !== null) {
            return "{$field}:>={$min}";
        } elseif ($max !== null) {
            return "{$field}:<={$max}";
        }
        
        return '';
    }

    /**
     * 构建日期范围查询
     */
    protected function buildDateRangeQuery(string $field, array $range, array $options): string
    {
        $min = $range['range'][0] ?? null;
        $max = $range['range'][1] ?? null;
        $format = $options['format'] ?? 'Y-m-d';
        
        if ($min instanceof \DateTime) {
            $min = $min->format($format);
        }
        
        if ($max instanceof \DateTime) {
            $max = $max->format($format);
        }
        
        if ($min && $max) {
            return "{$field}:[{$min} TO {$max}]";
        } elseif ($min) {
            return "{$field}:>={$min}";
        } elseif ($max) {
            return "{$field}:<={$max}";
        }
        
        return '';
    }

    /**
     * 构建 IN 查询
     */
    protected function buildInQuery(string $field, array $values): string
    {
        if (empty($values)) {
            return '';
        }
        
        $conditions = [];
        foreach ($values as $value) {
            $conditions[] = "{$field}:{$value}";
        }
        
        return '(' . implode(' OR ', $conditions) . ')';
    }

    /**
     * 构建 NOT IN 查询
     */
    protected function buildNotInQuery(string $field, array $values): string
    {
        if (empty($values)) {
            return '';
        }
        
        $conditions = [];
        foreach ($values as $value) {
            $conditions[] = "{$field}:{$value}";
        }
        
        return 'NOT (' . implode(' OR ', $conditions) . ')';
    }

    /**
     * 构建模糊查询
     */
    protected function buildFuzzyQuery(string $field, $value, array $options): string
    {
        $distance = $options['distance'] ?? 1;
        return "{$field}:{$value}~{$distance}";
    }

    /**
     * 构建邻近查询
     */
    protected function buildProximityQuery(string $field, $value, array $options): string
    {
        $distance = $options['distance'] ?? 5;
        return "\"{$field}:{$value}\"~{$distance}";
    }

    /**
     * 应用搜索选项
     */
    protected function applySearchOptions(\XSSearch $search, AdvancedScoutBuilder $builder): void
    {
        // 设置模糊搜索
        if ($builder->options['fuzzy'] ?? scout_config('xunsearch.search.fuzzy', true)) {
            $search->setFuzzy(true);
        }

        // 设置自动同义词
        if ($builder->options['auto_synonym'] ?? scout_config('xunsearch.search.auto_synonym', true)) {
            if (method_exists($search, 'setAutoSynonyms')) {
                $search->setAutoSynonyms();
            }
        }

        // 设置查询方言
        if ($dialect = $builder->options['dialect'] ?? null) {
            $search->setQuery($search->getQuery(), $dialect);
        }

        // 设置搜索范围
        if ($range = $builder->options['range'] ?? null) {
            if (is_array($range) && count($range) === 2) {
                $search->setRange($range[0], $range[1]);
            }
        }

        // 设置权重
        if ($weights = $builder->options['weights'] ?? []) {
            foreach ($weights as $field => $weight) {
                $search->setWeight($field, $weight);
            }
        }

        // 设置折叠（去重）
        if ($collapse = $builder->options['collapse'] ?? null) {
            if (method_exists($search, 'setCollapse')) {
                $search->setCollapse($collapse);
            }
        }
    }

    /**
     * 应用高级条件
     */
    protected function applyAdvancedConditions(\XSSearch $search, AdvancedScoutBuilder $builder): void
    {
        // 这里可以添加更复杂的条件处理逻辑
        // XunSearch 的条件主要通过查询字符串实现，已在上面的 buildAdvancedQuery 中处理
    }

    /**
     * 应用高级排序
     */
    protected function applyAdvancedSorts(\XSSearch $search, AdvancedScoutBuilder $builder): void
    {
        $sorts = [];
        if (method_exists($builder, 'getSorts')) {
            $sorts = $builder->getSorts();
        }
        
        if (!empty($sorts)) {
            // XunSearch 只支持单字段排序，取第一个
            $sort = $sorts[0];
            $field = $sort['field'] ?? null;
            
            if ($field) {
                if ($sort['type'] === 'relevance' || $field === '_score') {
                    // 相关度排序（默认）
                    $search->setSort('_score', $sort['direction'] === 'desc');
                } elseif ($field === 'random') {
                    // 随机排序 - XunSearch 不支持，这里不做处理
                    Log::warning('XunSearch does not support random sorting');
                } else {
                    // 字段排序
                    $search->setSort($field, $sort['direction'] === 'desc');
                }
            }
        }
    }

    /**
     * 处理高级搜索结果
     */
    protected function processAdvancedResults(array $rawResults, \XSSearch $search, AdvancedScoutBuilder $builder): array
    {
        $results = [
            'hits' => [],
            'total' => $search->getLastCount(),
            'search_time' => $search->getLastTime(),
            'search_cost' => $search->getLastCost(),
            'related_queries' => [],
            'suggestions' => [],
            'facets' => [],
            'aggregations' => [],
        ];

        // 处理匹配的文档
        foreach ($rawResults as $doc) {
            $hit = [
                '_id' => $doc->id(),
                '_score' => $doc->score(),
                '_percent' => $doc->percent(),
                '_doc' => $doc->getFields(),
                '_terms' => $doc->terms(),
                '_matched' => $doc->matched(),
            ];

            // 添加高亮
            if ($builder->options['highlight'] ?? false) {
                if (method_exists($doc, 'highlight')) {
                    $hit['_highlight'] = $doc->highlight();
                }
            }

            // 添加相关性
            if ($builder->options['relevance'] ?? false) {
                if (method_exists($doc, 'relevance')) {
                    $hit['_relevance'] = $doc->relevance();
                }
            }

            $results['hits'][] = $hit;
        }

        // 获取相关查询
        if ($builder->query && ($builder->options['related'] ?? false)) {
            try {
                $results['related_queries'] = $search->getRelatedQuery($builder->query, 10);
            } catch (\Exception $e) {
                Log::warning('Failed to get related queries', ['error' => $e->getMessage()]);
            }
        }

        // 获取搜索建议
        if ($builder->options['suggest'] ?? false) {
            try {
                $results['suggestions'] = $search->getExpandedQuery($builder->query);
            } catch (\Exception $e) {
                Log::warning('Failed to get search suggestions', ['error' => $e->getMessage()]);
            }
        }

        // 添加分面信息
        if (method_exists($builder, 'getFacetConfig')) {
            $facets = $builder->getFacetConfig();
            if ($facets) {
                $results['facets'] = $this->getFacets($search, $facets);
            }
        }

        // 添加聚合信息
        if (method_exists($builder, 'getAggregationConfig')) {
            $aggregations = $builder->getAggregationConfig();
            if ($aggregations) {
                $results['aggregations'] = $this->getAggregations($search, $aggregations);
            }
        }

        return $results;
    }

    /**
     * 获取分面信息
     */
    protected function getFacets(\XSSearch $search, array $facets): array
    {
        $facetResults = [];
        
        foreach ($facets as $field => $options) {
            try {
                $limit = $options['size'] ?? 10;
                $facetResults[$field] = $search->getFacets($field, $limit);
            } catch (\Exception $e) {
                Log::warning("Failed to get facet for field {$field}", ['error' => $e->getMessage()]);
                $facetResults[$field] = [];
            }
        }
        
        return $facetResults;
    }

    /**
     * 获取聚合信息
     */
    protected function getAggregations(\XSSearch $search, array $aggregations): array
    {
        $aggResults = [];
        
        foreach ($aggregations as $name => $config) {
            try {
                $field = $config['field'];
                $type = $config['type'];
                
                switch ($type) {
                    case 'terms':
                        $size = $config['options']['size'] ?? 10;
                        $aggResults[$name] = $search->getTerms($field, $size);
                        break;
                        
                    case 'stats':
                        // XunSearch 不支持统计聚合，需要手动计算
                        $aggResults[$name] = $this->calculateStats($search, $field);
                        break;
                        
                    case 'range':
                        // XunSearch 支持范围聚合
                        $ranges = $config['options']['ranges'] ?? [];
                        $aggResults[$name] = $this->calculateRanges($search, $field, $ranges);
                        break;
                        
                    default:
                        Log::warning("Unsupported aggregation type: {$type}");
                        $aggResults[$name] = [];
                }
            } catch (\Exception $e) {
                Log::warning("Failed to get aggregation {$name}", ['error' => $e->getMessage()]);
                $aggResults[$name] = [];
            }
        }
        
        return $aggResults;
    }

    /**
     * 计算统计信息
     */
    protected function calculateStats(\XSSearch $search, string $field): array
    {
        try {
            $terms = $search->getTerms($field, 1000);
            
            if (empty($terms)) {
                return ['count' => 0, 'min' => 0, 'max' => 0, 'avg' => 0, 'sum' => 0];
            }
            
            $values = array_values($terms);
            $count = count($values);
            $sum = array_sum($values);
            $min = min($values);
            $max = max($values);
            $avg = $count > 0 ? $sum / $count : 0;
            
            return [
                'count' => $count,
                'min' => $min,
                'max' => $max,
                'avg' => $avg,
                'sum' => $sum,
            ];
        } catch (\Exception $e) {
            Log::warning("Failed to calculate stats for field {$field}", ['error' => $e->getMessage()]);
            return ['count' => 0, 'min' => 0, 'max' => 0, 'avg' => 0, 'sum' => 0];
        }
    }

    /**
     * 计算范围聚合
     */
    protected function calculateRanges(\XSSearch $search, string $field, array $ranges): array
    {
        $result = [];
        
        foreach ($ranges as $range) {
            try {
                $from = $range['from'] ?? null;
                $to = $range['to'] ?? null;
                $key = $range['key'] ?? "{$from}-{$to}";
                
                if ($from !== null && $to !== null) {
                    $query = "{$field}:[{$from} TO {$to}]";
                    $count = $search->count($query);
                    $result[$key] = $count;
                }
            } catch (\Exception $e) {
                Log::warning("Failed to calculate range for field {$field}", ['error' => $e->getMessage()]);
                $result[$range['key'] ?? 'unknown'] = 0;
            }
        }
        
        return $result;
    }

    /**
     * 获取缓存键
     */
    protected function getCacheKey(AdvancedScoutBuilder $builder): string
    {
        $params = [
            'index' => $builder->index ?: $builder->model->searchableAs(),
            'query' => $builder->query,
            'wheres' => $builder->wheres,
            'limit' => $builder->limit,
            'offset' => $builder->offset,
            'options' => $builder->options,
        ];

        // 添加高级条件
        if (method_exists($builder, 'getAdvancedWheres')) {
            $params['advanced_wheres'] = $builder->getAdvancedWheres();
        }
        
        if (method_exists($builder, 'getSorts')) {
            $params['sorts'] = $builder->getSorts();
        }

        return $this->cachePrefix . md5(serialize($params));
    }

    /**
     * 获取高级搜索缓存
     */
    protected function getAdvancedSearchCache(string $key)
    {
        $store = scout_config('xunsearch.cache.store', 'file');
        return Cache::store($store)->get($key);
    }

    /**
     * 设置高级搜索缓存
     */
    protected function setAdvancedSearchCache(string $key, array $data, int $ttl): void
    {
        $store = scout_config('xunsearch.cache.store', 'file');
        Cache::store($store)->put($key, $data, $ttl);
    }

    /**
     * 获取聚合结果
     */
    public function getAggregations(AdvancedScoutBuilder $builder): array
    {
        if (!method_exists($builder, 'getAggregationConfig')) {
            return [];
        }

        $indexName = $builder->index ?: $builder->model->searchableAs();
        $search = $this->xunsearch->refresh($indexName)->getSearch();
        
        // 设置查询
        if ($builder->query) {
            $search->setQuery($builder->query);
        }
        
        // 应用 where 条件
        $this->applyWheres($search, $builder);
        
        return $this->getAggregations($search, $builder->getAggregationConfig());
    }

    /**
     * 获取分面结果
     */
    public function getFacets(AdvancedScoutBuilder $builder): array
    {
        if (!method_exists($builder, 'getFacetConfig')) {
            return [];
        }

        $indexName = $builder->index ?: $builder->model->searchableAs();
        $search = $this->xunsearch->refresh($indexName)->getSearch();
        
        // 设置查询
        if ($builder->query) {
            $search->setQuery($builder->query);
        }
        
        // 应用 where 条件
        $this->applyWheres($search, $builder);
        
        return $this->getFacets($search, $builder->getFacetConfig());
    }

    /**
     * 应用 where 条件
     */
    protected function applyWheres(\XSSearch $search, $builder): void
    {
        // 处理基本 where 条件
        foreach ($builder->wheres as $field => $value) {
            if (is_array($value)) {
                // 数组条件：范围查询
                if (count($value) === 2 && isset($value[0], $value[1])) {
                    $search->addRange($field, $value[0], $value[1]);
                }
            } else {
                // 精确匹配：添加到查询字符串
                $currentQuery = $search->getQuery();
                $search->setQuery($currentQuery . " {$field}:{$value}");
            }
        }

        // 处理 whereIn 条件
        foreach ($builder->whereIns as $field => $values) {
            $this->applyInCondition($search, $field, $values);
        }

        // 处理 whereNotIn 条件
        foreach ($builder->whereNotIns as $field => $values) {
            $this->applyNotInCondition($search, $field, $values);
        }
    }

    /**
     * 语义搜索
     */
    public function semanticSearch(string $index, string $query, array $options = []): array
    {
        $search = $this->getSearch($index);
        
        // 设置语义搜索（XunSearch 3.0+）
        if (method_exists($search, 'setSemantic')) {
            $search->setSemantic(true);
        }
        
        $search->setQuery($query);
        
        // 设置参数
        $limit = $options['limit'] ?? 20;
        $offset = $options['offset'] ?? 0;
        $search->setLimit($limit, $offset);
        
        $results = $search->search();
        
        return $this->processAdvancedResults($results, $search, new AdvancedScoutBuilder(
            new class {
                public function searchableAs() { return 'dummy'; }
            },
            $query
        ));
    }

    /**
     * 拼音搜索
     */
    public function pinyinSearch(string $index, string $query, array $options = []): array
    {
        $search = $this->getSearch($index);
        
        // 设置拼音搜索
        if (method_exists($search, 'setPinyin')) {
            $search->setPinyin(true);
        }
        
        $search->setQuery($query);
        
        // 设置参数
        $limit = $options['limit'] ?? 20;
        $offset = $options['offset'] ?? 0;
        $search->setLimit($limit, $offset);
        
        $results = $search->search();
        
        return $this->processAdvancedResults($results, $search, new AdvancedScoutBuilder(
            new class {
                public function searchableAs() { return 'dummy'; }
            },
            $query
        ));
    }

    /**
     * 获取搜索分析报告
     */
    public function getSearchAnalysis(string $index, array $options = []): array
    {
        $search = $this->getSearch($index);
        
        try {
            return [
                'total_searches' => $search->getDbTotal(),
                'last_search_time' => $search->getLastTime(),
                'hot_queries' => $search->getHotQuery($options['hot_limit'] ?? 10),
                'related_queries' => isset($options['query']) ? $search->getRelatedQuery($options['query'], 10) : [],
                'search_logs' => $search->getSearchLog($options['log_limit'] ?? 50),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get search analysis', [
                'index' => $index,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'total_searches' => 0,
                'last_search_time' => 0,
                'hot_queries' => [],
                'related_queries' => [],
                'search_logs' => [],
            ];
        }
    }

    /**
     * 重建索引
     */
    public function rebuildIndex(string $index): bool
    {
        try {
            $xsIndex = $this->getIndex($index);
            
            // XunSearch 没有直接的 rebuild 方法，我们可以通过清空并重新索引来实现
            $xsIndex->clean();
            $xsIndex->flushIndex();
            
            Log::info('XunSearch index rebuilt', ['index' => $index]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to rebuild XunSearch index', [
                'index' => $index,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * 优化索引
     */
    public function optimizeIndex(string $index): bool
    {
        try {
            $xsIndex = $this->getIndex($index);
            
            if (method_exists($xsIndex, 'optimize')) {
                $xsIndex->optimize();
                Log::info('XunSearch index optimized', ['index' => $index]);
                return true;
            }
            
            Log::warning('XunSearch optimize method not available');
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to optimize XunSearch index', [
                'index' => $index,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * 获取引擎信息
     */
    public function getEngineInfo(): array
    {
        try {
            $version = 'Unknown';
            
            // 尝试获取版本信息
            if (defined('XS_APP_VERSION')) {
                $version = XS_APP_VERSION;
            } elseif (function_exists('xs_version')) {
                $version = xs_version();
            }
            
            return [
                'type' => 'xunsearch',
                'version' => $version,
                'supports_advanced' => true,
                'supports_semantic' => method_exists(\XSSearch::class, 'setSemantic'),
                'supports_pinyin' => method_exists(\XSSearch::class, 'setPinyin'),
                'supports_facets' => method_exists(\XSSearch::class, 'getFacets'),
                'supports_highlight' => true,
                'supports_cache' => true,
            ];
        } catch (\Exception $e) {
            return [
                'type' => 'xunsearch',
                'error' => $e->getMessage(),
                'version' => 'Unknown',
                'supports_advanced' => false,
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
     * 注册条件处理器
     */
    public function registerConditionHandler(string $operator, callable $handler): self
    {
        $this->conditionHandlers[$operator] = $handler;
        return $this;
    }

    /**
     * 获取搜索实例
     */
    protected function getSearch(string $name): \XSSearch
    {
        return $this->xunsearch->refresh($name)->getSearch();
    }

    /**
     * 获取索引实例
     */
    protected function getIndex(string $name): \XSIndex
    {
        return $this->xunsearch->task($name)->getIndex();
    }
}