<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */


namespace Erikwang2013\ClickHouse\Transport;

use Erikwang2013\ClickHouse\Exceptions\ConnectionException;
use Erikwang2013\ClickHouse\Exceptions\QueryException;
use Erikwang2013\ClickHouse\Support\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

class HttpTransport implements TransportInterface
{
    private Client $httpClient;

    public function __construct(
        private readonly Config $config,
    ) {
        $this->httpClient = new Client([
            'base_uri' => sprintf(
                'http://%s:%d/',
                $this->config->get('host', 'localhost'),
                $this->config->get('port', 8123),
            ),
            'headers' => [
                'X-ClickHouse-User' => $this->config->get('username', 'default'),
                'X-ClickHouse-Key' => $this->config->get('password', ''),
                'X-ClickHouse-Database' => $this->config->get('database', 'default'),
                'Content-Type' => 'text/plain',
            ],
            'timeout' => $this->config->get('timeout', 30),
            'http_errors' => false,
        ]);
    }

    public function send(string $sql, array $bindings = []): mixed
    {
        $sql = $this->bindParams($sql, $bindings);

        try {
            $response = $this->httpClient->post('', ['body' => $sql . ' FORMAT JSON']);
        } catch (ConnectException $e) {
            throw new ConnectionException(
                sprintf('ClickHouse connection failed: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode !== 200) {
            throw new QueryException(
                sprintf('ClickHouse query error [%d]: %s', $statusCode, $body),
                $sql,
                $bindings,
                $statusCode,
            );
        }

        $decoded = json_decode($body, true);
        return $decoded['data'] ?? $decoded;
    }

    public function close(): void
    {
    }

    private function bindParams(string $sql, array $bindings): string
    {
        if (empty($bindings)) {
            return $sql;
        }

        $index = 0;
        return preg_replace_callback('/\?/', function () use (&$index, $bindings) {
            if (!array_key_exists($index, $bindings)) {
                return '';
            }
            $value = $bindings[$index++];
            return $this->quoteValue($value);
        }, $sql);
    }

    private function quoteValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return "'" . addcslashes((string) $value, "\\'") . "'";
    }
}