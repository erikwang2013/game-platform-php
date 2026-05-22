<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Hyperf;

use Erikwang2013\Jwt\JWT as JWTInstance;
use Erikwang2013\Jwt\JWTException;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Middleware implements MiddlewareInterface
{
    protected JWTInstance $jwt;
    protected ConfigInterface $config;
    protected HttpResponseInterface $responseFactory;

    public function __construct(JWTInstance $jwt, ConfigInterface $config, HttpResponseInterface $responseFactory)
    {
        $this->jwt    = $jwt;
        $this->config = $config;
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config = $this->config->get('jwt', []);

        $except = $config['middleware']['except'] ?? [];
        $path   = $request->getUri()->getPath();
        foreach ($except as $pattern) {
            if (preg_match('#^' . $pattern . '$#', $path)) {
                return $handler->handle($request);
            }
        }

        $token = $request->getHeaderLine('Authorization');
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return $this->responseFactory->json([
                'code' => 401,
                'msg'  => 'Token not provided',
                'data' => null,
            ])->withStatus(401);
        }

        try {
            $payload = $this->jwt->decode($token);
            $request = $request->withAttribute('jwt_payload', $payload);
        } catch (JWTException $e) {
            return $this->responseFactory->json([
                'code' => 401,
                'msg'  => $e->getMessage(),
                'data' => null,
            ])->withStatus(401);
        }

        return $handler->handle($request);
    }
}
