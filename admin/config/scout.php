<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

/**
 * Elasticsearch 搜索引擎配置
 * 用于数据同步、全文检索和聚合查询。模型中使用 Searchable trait 启用 ES 索引
 * @link https://github.com/erikwang2013/webman-scout
 */
return [
    // ES 驱动，可选 elasticsearch|opensearch|meilisearch
    'driver' => getenv('SCOUT_DRIVER') ?: 'elasticsearch',

    // ES 服务地址数组，支持多节点集群
    'hosts' => explode(',', getenv('SCOUT_HOSTS') ?: 'http://localhost:9200'),

    // 索引名称前缀，最终索引名为: erik_表名。与数据库表前缀保持一致
    'prefix' => getenv('SCOUT_PREFIX') ?: 'erik_',

    // ES 索引分片数，生产环境建议 3
    'number_of_shards' => (int)(getenv('SCOUT_SHARDS') ?: 1),

    // ES 副本数，生产环境建议 1-2
    'number_of_replicas' => (int)(getenv('SCOUT_REPLICAS') ?: 0),

    // 批量同步的 chunk 大小，影响内存占用和同步速度
    'chunk_size' => (int)(getenv('SCOUT_CHUNK_SIZE') ?: 500),

    // 是否开启软删除数据自动从索引中移除
    'soft_delete' => (bool)(getenv('SCOUT_SOFT_DELETE') ?: true),
];
