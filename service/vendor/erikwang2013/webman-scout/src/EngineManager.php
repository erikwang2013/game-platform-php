<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

namespace Erikwang2013\WebmanScout;

use Algolia\AlgoliaSearch\Algolia;
use Algolia\AlgoliaSearch\Support\AlgoliaAgent as Algolia4UserAgent;
use Algolia\AlgoliaSearch\Support\UserAgent as Algolia3UserAgent;
use Exception;
use Erikwang2013\WebmanScout\Engines\Algolia3Engine;
use Erikwang2013\WebmanScout\Engines\Algolia4Engine;
use Erikwang2013\WebmanScout\Engines\CollectionEngine;
use Erikwang2013\WebmanScout\Engines\DatabaseEngine;
use Erikwang2013\WebmanScout\Engines\MeilisearchEngine;
use Erikwang2013\WebmanScout\Engines\NullEngine;
use Erikwang2013\WebmanScout\Engines\TypesenseEngine;
use Meilisearch\Client as MeilisearchClient;
use Meilisearch\Meilisearch;
use Typesense\Client as Typesense;

use Erikwang2013\WebmanScout\Engines\ElasticSearchEngine;
use Erikwang2013\WebmanScout\Engines\AdvancedOpenSearchEngine as OpenSearchEngine;
use Elastic\Elasticsearch\Client as ElasticSearch;
use Elastic\Elasticsearch\ClientBuilder;
use Erikwang2013\WebmanScout\Engines\XunSearchEngine;
use OpenSearch\Client as OpenSearch;
use OpenSearch\GuzzleClientFactory;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;

class EngineManager extends Manager
{
    /**
     * Get a driver instance.
     *
     * @param  string|null  $name
     * @return \Erikwang2013\WebmanScout\Engines\Engine
     */
    public function engine($name = null)
    {
        return $this->driver($name);
    }

    /**
     * Create an Algolia engine instance.
     *
     * @return \Erikwang2013\WebmanScout\Engines\AlgoliaEngine
     */
    public function createAlgoliaDriver()
    {
        $this->ensureAlgoliaClientIsInstalled();

        return version_compare(Algolia::VERSION, '4.0.0', '>=')
            ? $this->configureAlgolia4Driver()
            : $this->configureAlgolia3Driver();
    }

    /**
     * Create an Algolia v3 engine instance.
     *
     * @return \Erikwang2013\WebmanScout\Engines\Algolia3Engine
     */
    protected function configureAlgolia3Driver()
    {
        Algolia3UserAgent::addCustomUserAgent('Laravel Scout', Scout::VERSION); // @phpstan-ignore class.notFound

        return Algolia3Engine::make(
            config: scout_config('algolia'),
            headers: $this->defaultAlgoliaHeaders(),
            softDelete: scout_config('soft_delete')
        );
    }

    /**
     * Create an Algolia v4 engine instance.
     *
     * @return \Erikwang2013\WebmanScout\Engines\Algolia4Engine
     */
    protected function configureAlgolia4Driver()
    {
        Algolia4UserAgent::addAlgoliaAgent('Laravel Scout', 'Laravel Scout', Scout::VERSION);

        return Algolia4Engine::make(
            config: scout_config('algolia'),
            headers: $this->defaultAlgoliaHeaders(),
            softDelete: scout_config('soft_delete')
        );
    }

    /**
     * Ensure the Algolia API client is installed.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function ensureAlgoliaClientIsInstalled()
    {
        if (class_exists(Algolia::class)) {
            return;
        }

        throw new Exception('Please install the suggested Algolia client: algolia/algoliasearch-client-php.');
    }

    /**
     * Set the default Algolia configuration headers.
     *
     * @return array
     */
    protected function defaultAlgoliaHeaders()
    {
        if (! scout_config('identify')) {
            return [];
        }

        $headers = [];

        try {
            $request = function_exists('request') ? request() : null;
            if (
                $request && ! config('app.debug')
                && filter_var($ip = $request->ip(), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            ) {
                $headers['X-Forwarded-For'] = $ip;
            }
            if ($request && ($user = $request->user()) && method_exists($user, 'getKey')) {
                $headers['X-Algolia-UserToken'] = $user->getKey();
            }
        } catch (\Throwable $e) {
            // CLI 或非 HTTP 环境下无 request，忽略
        }

        return $headers;
    }

    /**
     * Create a Meilisearch engine instance.
     *
     * @return \Erikwang2013\WebmanScout\Engines\MeilisearchEngine
     */
    public function createMeilisearchDriver()
    {
        $this->ensureMeilisearchClientIsInstalled();

        return new MeilisearchEngine(
            $this->container->make(MeilisearchClient::class),
            scout_config('soft_delete', false)
        );
    }

    /**
     * Ensure the Meilisearch client is installed.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function ensureMeilisearchClientIsInstalled()
    {
        if (class_exists(Meilisearch::class) && version_compare(Meilisearch::VERSION, '1.0.0') >= 0) {
            return;
        }

        throw new Exception('Please install the suggested Meilisearch client: meilisearch/meilisearch-php.');
    }

    /**
     * Create an ElasticSearch engine instance.
     *
     * @return \Erikwang2013\WebmanScout\Engines\ElasticSearchEngine
     */
    public function createElasticsearchDriver()
    {
        $config = scout_config('elasticsearch');

        $this->ensureElasticSearchClientIsInstalled();
        $clientBuilder = ClientBuilder::create()->setHosts($config['hosts'] ?? ['http://127.0.0.1:9200']);

        if (!empty($config['auth'])) {
            if (
                !empty($config['auth']['user']) && $config['auth']['user'] !== null
                && !empty($config['auth']['pass']) && $config['auth']['pass'] !== null
            ) {
                $clientBuilder->setBasicAuthentication($config['auth']['user'], $config['auth']['pass']);
            }
            if (!empty($config['auth']['api_key']) && $config['auth']['api_key'] !== null) {
                $clientBuilder->setApiKey($config['auth']['api_key'], $config['auth']['api_id'] ?? null);
            }
            if (!empty($config['auth']['cloud_id']) && $config['auth']['cloud_id'] !== null) {
                $clientBuilder->setElasticCloudId($config['auth']['cloud_id']);
            }
        }

        return new ElasticSearchEngine(
            $clientBuilder->build(),
            scout_config('soft_delete', false)
        );
    }

    /**
     * Ensure the MeiliSearch client is installed.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function ensureElasticSearchClientIsInstalled()
    {
        if (class_exists(ElasticSearch::class)) {
            return;
        }

        throw new Exception('Please install the ElasticSearch client: elasticsearch/elasticsearch.');
    }

    public function createOpensearchDriver()
    {
        $config = scout_config('opensearch');
        // 构建客户端
        $handlerStack = HandlerStack::create(new CurlHandler());
        $clientFactory = new GuzzleClientFactory();
        $setConfig = [
            'base_uri' => $config['host'],
            'timeout'  => $config['timeout'],
            'connect_timeout' => $config['connection_timeout'],
            'handler' => $handlerStack, // 使用我们创建的标准堆栈
            // 认证配置
            'auth' => [
                $config['username'],
                $config['password']
            ],
            // SSL 验证（生产环境应为 true 并配置证书）
            'verify' => $config['ssl_verification'],
            // 禁用 Expect 头以避免大请求时的 100-continue 问题
            'expect' => false
        ];

        if (true == $config['ssl_verification']) {
            if (!empty($config['ssl_cert'])) {
                $setConfig['cert'] = str_starts_with($config['ssl_cert'], '/') ? $config['ssl_cert'] : base_path() . $config['ssl_cert'];
            }
            if (!empty($config['ssl_key'])) {
                $setConfig['ssl_key'] = str_starts_with($config['ssl_key'], '/') ? $config['ssl_key'] : base_path() . $config['ssl_key'];
            }
        }
        return new OpenSearchEngine(
            $clientFactory->create($setConfig),
            scout_config('soft_delete', false)
        );
    }

    /**
     * Ensure the MeiliSearch client is installed.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function ensureOpenSearchClientIsInstalled()
    {
        if (class_exists(OpenSearch::class)) {
            return;
        }

        throw new Exception('Please install the ElasticSearch client: opensearch-project/opensearch-php.');
    }

    /**
     * Create an ElasticSearch engine instance.
     *
     * @return \Erikwang2013\WebmanScout\Engines\XunSearchEngine
     * @throws \Elastic\Elasticsearch\Exception\AuthenticationException
     */
    public function createXunsearchDriver()
    {

        $this->ensureXunSearchClientIsInstalled();
        return new XunSearchEngine(
            new XunSearchClient(),
            scout_config('soft_delete', false)
        );
    }

    /**
     * Ensure the MeiliSearch client is installed.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function ensureXunSearchClientIsInstalled()
    {
        if (class_exists(\XS::class)) {
            return;
        }

        throw new Exception('Please install the ElasticSearch client: elasticsearch/elasticsearch.');
    }
    /**
     * Create a Typesense engine instance.
     *
     * @return \Erikwang2013\WebmanScout\Engines\TypesenseEngine
     *
     * @throws \Typesense\Exceptions\ConfigError
     */
    public function createTypesenseDriver()
    {
        $config = scout_config('typesense');
        $this->ensureTypesenseClientIsInstalled();

        return new TypesenseEngine(new Typesense($config['client-settings']), $config['max_total_results'] ?? 1000);
    }

    /**
     * Ensure the Typesense client is installed.
     *
     * @return void
     *
     * @throws Exception
     */
    protected function ensureTypesenseClientIsInstalled()
    {
        if (! class_exists(Typesense::class)) {
            throw new Exception('Please install the suggested Typesense client: typesense/typesense-php.');
        }
    }

    /**
     * Create a database engine instance.
     *
     * @return \Erikwang2013\WebmanScout\Engines\DatabaseEngine
     */
    public function createDatabaseDriver()
    {
        return new DatabaseEngine;
    }

    /**
     * Create a collection engine instance.
     *
     * @return \Erikwang2013\WebmanScout\Engines\CollectionEngine
     */
    public function createCollectionDriver()
    {
        return new CollectionEngine;
    }

    /**
     * Create a null engine instance.
     *
     * @return \Erikwang2013\WebmanScout\Engines\NullEngine
     */
    public function createNullDriver()
    {
        return new NullEngine;
    }

    /**
     * Forget all of the resolved engine instances.
     *
     * @return $this
     */
    public function forgetEngines()
    {
        $this->drivers = [];

        return $this;
    }

    /**
     * Get the default Scout driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        $driver = scout_config('driver');
        if ($driver === null) {
            return 'null';
        }
        // getenv 误用等会导致 false/空串，避免进入 Str::studly 与 createDriver 冲突
        if ($driver === false || $driver === '') {
            return 'opensearch';
        }

        return $driver;
    }
}
