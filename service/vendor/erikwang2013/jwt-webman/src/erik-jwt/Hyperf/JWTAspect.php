<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Hyperf;

use Erikwang2013\Jwt\JWTException;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Container\ContainerInterface;

#[Aspect]
class JWTAspect extends AbstractAspect
{
    public array $annotations = [
        JWT::class,
    ];

    public function __construct(
        protected ContainerInterface $container,
        protected RequestInterface $request,
        protected ResponseInterface $response
    ) {
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $jwt    = $this->container->get(\Erikwang2013\Jwt\JWT::class);
        $config = $this->container->get(\Hyperf\Contract\ConfigInterface::class)->get('jwt', []);

        $except = $config['middleware']['except'] ?? [];
        $path   = $this->request->getUri()->getPath();
        foreach ($except as $pattern) {
            if (preg_match('#^' . $pattern . '$#', $path)) {
                return $proceedingJoinPoint->process();
            }
        }

        $token = $this->request->getHeaderLine('Authorization');
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return $this->response->json([
                'code' => 401, 'msg' => 'Token not provided', 'data' => null
            ])->withStatus(401);
        }

        try {
            $payload = $jwt->decode($token);
            Context::set('jwt_payload', $payload);
        } catch (JWTException $e) {
            return $this->response->json([
                'code' => 401, 'msg' => $e->getMessage(), 'data' => null
            ])->withStatus(401);
        }

        return $proceedingJoinPoint->process();
    }
}
