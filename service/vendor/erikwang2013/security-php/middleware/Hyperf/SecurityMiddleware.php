<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Security\Middleware\Hyperf;

use Erikwang2013\Security\SecurityGuard;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SecurityMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = array_merge(
            $request->getCookieParams() ?? [],
            $request->getParsedBody() ?? [],
            $request->getQueryParams() ?? [],
        );

        $uploadedFiles = $request->getUploadedFiles();
        foreach ($uploadedFiles as $key => $file) {
            if ($file instanceof \Hyperf\HttpMessage\Upload\UploadedFile) {
                $data[$key] = [
                    'name'     => $file->getClientFilename() ?? '',
                    'tmp_name' => $file->getStream()->getMetadata('uri') ?? '',
                ];
            }
        }

        $serverParams = $request->getServerParams();

        $threats = SecurityGuard::guard($data, [
            'ip'     => $serverParams['remote_addr'] ?? '0.0.0.0',
            'method' => $request->getMethod(),
            'uri'    => $request->getUri()->getPath(),
        ]);

        if (!empty($threats) && SecurityGuard::shouldBlock($threats)) {
            $statusCode = SecurityGuard::blockStatusCode();
            $message = SecurityGuard::blockMessage();

            $response = new \Hyperf\HttpMessage\Server\Response();
            return $response
                ->withStatus($statusCode)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withBody(new \Hyperf\HttpMessage\Stream\SwooleStream($message));
        }

        return $handler->handle($request);
    }
}
