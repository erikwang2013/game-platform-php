<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout\Engines;

use Erikwang2013\WebmanScout\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;
use Erikwang2013\WebmanScout\XunSearchClient;
use support\Log;


class XunSearchEngine extends Engine
{
    /**
     * XunSearch 客户端
     */
    protected XunSearchClient $xunsearch;

    /**
     * 是否启用软删除
     */
    protected bool $softDelete;

    /**
     * 批量操作大小
     */
    protected int $batchSize = 100;

    /**
     * 搜索结果缓存
     */
    protected array $searchCache = [];

    /**
     * 创建新的引擎实例
     */
    public function __construct(XunSearchClient $xunsearch, bool $softDelete = false)
    {
        $this->xunsearch = $xunsearch;
        $this->softDelete = $softDelete;
    }

    /**
     * 更新索引
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        // 处理软删除
        if ($this->usesSoftDelete($models->first()) && $this->softDelete) {
            $models->each->pushSoftDeleteMetadata();
        }

        $index = $this->getIndex($models->first()->searchableAs());
        $docs = [];

        foreach ($models as $model) {
            $searchableData = $model->toSearchableArray();
            
            if (empty($searchableData)) {
                continue;
            }

            $doc = new \XSDocument();
            
            // 确保包含主键
            if (!isset($searchableData[$model->getKeyName()])) {
                $searchableData[$model->getKeyName()] = $model->getScoutKey();
            }

            $doc->setFields($searchableData);
            $docs[] = $doc;

            // 批量提交
            if (count($docs) >= $this->batchSize) {
                $index->update($docs);
                $docs = [];
            }
        }

        // 提交剩余的文档
        if (!empty($docs)) {
            $index->update($docs);
        }

        // 刷新索引（可选，根据性能需求调整）
        if (scout_config('xunsearch.auto_flush', true)) {
            $index->flushIndex();
        }
    }

    /**
     * 删除文档
     */
    public function delete($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $index = $this->getIndex($models->first()->searchableAs());
        $ids = [];

        foreach ($models as $model) {
            $ids[] = $model->getScoutKey();

            // 批量删除
            if (count($ids) >= $this->batchSize) {
                foreach ($ids as $id) {
                    $index->del($id);
                }
                $ids = [];
            }
        }

        // 删除剩余的
        foreach ($ids as $id) {
            $index->del($id);
        }

        // 刷新索引
        $index->flushIndex();
    }

    /**
     * 执行搜索
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, [
            'hitsPerPage' => $builder->limit,
        ]);
    }

    /**
     * 分页搜索
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = $this->performSearch($builder, [
            'hitsPerPage' => (int) $perPage,
            'page' => max(0, ($page ?? 1) - 1), // 修正页码计算
        ]);

        // 获取总记录数
        $search = $this->xunsearch->getSearch();
        $total = $search->getLastCount() ?? 0;
        
        $result['total'] = $total;
        $result['per_page'] = $perPage;
        $result['current_page'] = $page;
        $result['last_page'] = $total > 0 ? ceil($total / $perPage) : 1;

        return $result;
    }

    /**
     * 执行搜索
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $indexName = $builder->index ?: $builder->model->searchableAs();
        $search = $this->xunsearch->refresh($indexName)->getSearch();

        // 自定义回调
        if ($builder->callback) {
            return call_user_func($builder->callback, $search, $builder->query, $options);
        }

        // 设置查询
        if ($builder->query) {
            $search->setQuery($builder->query);
            
            // 设置模糊搜索
            if ($builder->options['fuzzy'] ?? true) {
                $search->setFuzzy(true);
            }
            
            // 设置搜索字段
            if ($fields = $builder->options['fields'] ?? null) {
                if (is_array($fields)) {
                    $fields = implode(',', $fields);
                }
                $search->setQuery($builder->query, $fields);
            }
        } else {
            $search->setQuery('');
        }

        // 处理 where 条件
        $this->applyWheres($search, $builder);

        // 处理排序
        $this->applySorts($search, $builder);

        // 设置分页
        $perPage = $options['hitsPerPage'] ?? 20;
        $offset = $options['page'] ?? 0;
        $limit = $perPage;
        
        // 计算偏移量
        if ($offset > 0) {
            $offset = $offset * $perPage;
        }

        // 执行搜索
        $results = $search->setLimit($limit, $offset)->search();

        // 处理搜索结果
        return $this->processSearchResults($results, $search, $options);
    }

    /**
     * 应用 where 条件
     */
    protected function applyWheres(\XSSearch $search, Builder $builder): void
    {
        // 处理基本 where 条件
        foreach ($builder->wheres as $field => $value) {
            if (is_array($value)) {
                // 数组条件：范围查询
                if (count($value) === 2 && isset($value[0], $value[1])) {
                    $search->addRange($field, $value[0], $value[1]);
                } else {
                    // IN 查询：XunSearch 不支持 IN，需要特殊处理
                    $this->applyInCondition($search, $field, $value);
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
     * 应用 IN 条件
     */
    protected function applyInCondition(\XSSearch $search, string $field, array $values): void
    {
        // XunSearch 不支持直接的 IN 查询，可以转换为 OR 查询
        if (!empty($values)) {
            $conditions = [];
            foreach ($values as $value) {
                $conditions[] = "{$field}:{$value}";
            }
            
            $currentQuery = $search->getQuery();
            $orQuery = '(' . implode(' OR ', $conditions) . ')';
            
            if ($currentQuery) {
                $search->setQuery("({$currentQuery}) AND {$orQuery}");
            } else {
                $search->setQuery($orQuery);
            }
        }
    }

    /**
     * 应用 NOT IN 条件
     */
    protected function applyNotInCondition(\XSSearch $search, string $field, array $values): void
    {
        // XunSearch 不支持 NOT IN，可以转换为 NOT 查询
        if (!empty($values)) {
            $conditions = [];
            foreach ($values as $value) {
                $conditions[] = "{$field}:{$value}";
            }
            
            $currentQuery = $search->getQuery();
            $notQuery = 'NOT (' . implode(' OR ', $conditions) . ')';
            
            if ($currentQuery) {
                $search->setQuery("({$currentQuery}) AND {$notQuery}");
            } else {
                $search->setQuery($notQuery);
            }
        }
    }

    /**
     * 应用排序
     */
    protected function applySorts(\XSSearch $search, Builder $builder): void
    {
        if (!empty($builder->orders)) {
            // XunSearch 只支持单字段排序，取第一个排序条件
            $order = $builder->orders[0];
            $field = $order['column'];
            $direction = $order['direction'];
            
            // XunSearch 使用 setSort() 方法，第二个参数为 true 表示降序
            $search->setSort($field, $direction === 'desc');
        }
    }

    /**
     * 处理搜索结果
     */
    protected function processSearchResults(array $results, \XSSearch $search, array $options): array
    {
        $processed = [];

        foreach ($results as $doc) {
            $processed[] = [
                'data' => $doc->getFields(),
                'score' => $doc->score(), // 相关度得分
                'percent' => $doc->percent(), // 匹配百分比
                'terms' => $doc->terms(), // 匹配的词
                'matched' => $doc->matched(), // 是否匹配
            ];
        }

        return [
            'hits' => $processed,
            'total' => $search->getLastCount(),
            'search_time' => $search->getLastTime(),
            'search_cost' => $search->getLastCost(),
        ];
    }

    /**
     * 映射 ID
     */
    public function mapIds($results)
    {
        if (empty($results['hits'])) {
            return Collection::make();
        }

        $modelKeyName = null;
        $ids = [];

        foreach ($results['hits'] as $hit) {
            if (isset($hit['data'])) {
                // 尝试从数据中提取主键
                foreach ($hit['data'] as $key => $value) {
                    if (strpos($key, '_id') !== false || $key === 'id') {
                        $ids[] = $value;
                        if (!$modelKeyName) {
                            $modelKeyName = $key;
                        }
                        break;
                    }
                }
            }
        }

        return Collection::make($ids)->values();
    }

    /**
     * 映射结果到模型
     */
    public function map(Builder $builder, $results, $model)
    {
        if (empty($results['hits'])) {
            return $model->newCollection();
        }

        $objectIds = $this->mapIds($results)->all();
        
        if (empty($objectIds)) {
            return $model->newCollection();
        }

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds($builder, $objectIds)
            ->filter(fn($model) => in_array($model->getScoutKey(), $objectIds))
            ->sortBy(fn($model) => $objectIdPositions[$model->getScoutKey()])
            ->values();
    }

    /**
     * 懒加载映射
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if (empty($results['hits'])) {
            return LazyCollection::make();
        }

        $objectIds = $this->mapIds($results)->all();

        if (empty($objectIds)) {
            return LazyCollection::make();
        }

        return LazyCollection::make(function () use ($builder, $model, $objectIds) {
            $models = $model->getScoutModelsByIds($builder, $objectIds);
            
            foreach ($models as $model) {
                if (in_array($model->getScoutKey(), $objectIds)) {
                    yield $model;
                }
            }
        });
    }

    /**
     * 获取总数
     */
    public function getTotalCount($results)
    {
        return $results['total'] ?? 0;
    }

    /**
     * 清空索引
     */
    public function flush($model)
    {
        $index = $this->getIndex($model->searchableAs());
        $index->clean();
        $index->flushIndex();
        
        Log::info('XunSearch index flushed', ['index' => $model->searchableAs()]);
    }

    /**
     * 创建索引
     */
    public function createIndex($name, array $options = [])
    {
        try {
            return $this->xunsearch->createIndex($name, $options);
        } catch (\Exception $e) {
            Log::error('Failed to create XunSearch index', [
                'index' => $name,
                'error' => $e->getMessage(),
            ]);
            
            if (config('app.debug')) {
                throw $e;
            }
        }
    }

    /**
     * 删除索引
     */
    public function deleteIndex($name)
    {
        try {
            $index = $this->getIndex($name);
            $index->clean();
            $index->flushIndex();
            
            Log::info('XunSearch index deleted', ['index' => $name]);
        } catch (\Exception $e) {
            Log::error('Failed to delete XunSearch index', [
                'index' => $name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取索引信息
     */
    public function getIndexInfo(string $name): array
    {
        try {
            $index = $this->getIndex($name);
            $search = $this->xunsearch->getSearch();
            
            return [
                'name' => $name,
                'doc_count' => $index->getDocCount(),
                'total_terms' => $index->getTotalTerms(),
                'last_error' => $index->getLastError(),
                'custom_data' => $index->getCustomData(),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 获取索引实例
     */
    protected function getIndex(string $name): \XSIndex
    {
        return $this->xunsearch->task($name)->getIndex();
    }

    /**
     * 获取搜索实例
     */
    protected function getSearch(string $name): \XSSearch
    {
        return $this->xunsearch->refresh($name)->getSearch();
    }

    /**
     * 设置批量操作大小
     */
    public function setBatchSize(int $size): self
    {
        $this->batchSize = $size;
        return $this;
    }

    /**
     * 获取相关搜索建议
     */
    public function getRelatedQuery(string $query, string $index, int $limit = 10): array
    {
        $search = $this->getSearch($index);
        return $search->getRelatedQuery($query, $limit);
    }

    /**
     * 获取热门搜索
     */
    public function getHotQuery(string $index, int $limit = 10, string $type = 'total'): array
    {
        $search = $this->getSearch($index);
        return $search->getHotQuery($limit, $type);
    }

    /**
     * 获取搜索日志
     */
    public function getSearchLog(string $index, int $limit = 10): array
    {
        $search = $this->getSearch($index);
        return $search->getSearchLog($limit);
    }

    /**
     * 添加同义词
     */
    public function addSynonym(string $index, string $word, string $synonym): bool
    {
        try {
            $xsIndex = $this->getIndex($index);
            $xsIndex->addSynonym($word, $synonym);
            $xsIndex->flushIndex();
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to add synonym to XunSearch', [
                'index' => $index,
                'word' => $word,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 设置搜索缓存
     */
    public function setSearchCache(string $key, $data, int $ttl = 3600): void
    {
        $this->searchCache[$key] = [
            'data' => $data,
            'expire' => time() + $ttl,
        ];
    }

    /**
     * 获取搜索缓存
     */
    public function getSearchCache(string $key)
    {
        if (isset($this->searchCache[$key])) {
            $cache = $this->searchCache[$key];
            if ($cache['expire'] > time()) {
                return $cache['data'];
            }
            unset($this->searchCache[$key]);
        }
        return null;
    }

    /**
     * 检查是否使用软删除
     */
    protected function usesSoftDelete($model): bool
    {
        return in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * 动态调用 XunSearch 客户端方法
     */
    public function __call($method, $parameters)
    {
        return $this->xunsearch->$method(...$parameters);
    }
}