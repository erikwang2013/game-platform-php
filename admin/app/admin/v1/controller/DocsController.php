<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\v1\controller;

use erikwang2013\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

#[Apidoc\Title("API文档")]
#[Apidoc\Group("docs")]
class DocsController
{
    #[Apidoc\Title("API文档")]
    #[Apidoc\Desc("返回OpenAPI 3.0格式的API规范文档")]
    #[Apidoc\Url("/api/docs")]
    #[Apidoc\Method("GET")]
    #[Apidoc\Author("erik")]
    public function index(Request $request): Response
    {
        return json($this->buildSpec());
    }

    private function buildSpec(): array
    {
        $baseUrl = rtrim((string) config('app.url', 'http://localhost:8789'), '/');

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title'       => trans('Open Admin API'),
                'description' => trans('A full-stack admin system built on webman v2. The API version is placed in the URL path (e.g. /api/v1/*, /admin/v1/*) rather than in a request header.'),
                'version'     => '1.0.0',
                'contact'     => ['name' => 'erik', 'email' => 'erik@erik.xyz', 'url' => 'https://erik.xyz'],
            ],
            'servers' => [['url' => $baseUrl, 'description' => trans('Local development')]],
            'security' => [['bearerAuth' => []]],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
                ],
                'schemas' => [
                    'ApiResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'code'    => ['type' => 'integer', 'description' => trans('0=success, 400=bad request, 401=unauthenticated, 403=forbidden, 404=not found, 422=validation failed, 429=rate limited, 500=server error')],
                            'message' => ['type' => 'string'],
                            'data'    => ['type' => 'object'],
                        ],
                    ],
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id'         => ['type' => 'string', 'description' => trans('User ID encoded as hashid')],
                            'username'   => ['type' => 'string'],
                            'real_name'  => ['type' => 'string'],
                            'phone'      => ['type' => 'string', 'description' => trans('Masked phone number')],
                            'email'      => ['type' => 'string', 'description' => trans('Masked email')],
                            'status'     => ['type' => 'integer', 'description' => trans('1=enabled, 0=disabled')],
                            'last_login_at' => ['type' => 'string', 'format' => 'date-time'],
                            'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        ],
                    ],
                    'Role' => [
                        'type' => 'object',
                        'properties' => [
                            'id'          => ['type' => 'string'],
                            'name'        => ['type' => 'string'],
                            'slug'        => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'status'      => ['type' => 'integer'],
                        ],
                    ],
                    'Config' => [
                        'type' => 'object',
                        'properties' => [
                            'id'          => ['type' => 'string'],
                            'group'       => ['type' => 'string'],
                            'key'         => ['type' => 'string'],
                            'value'       => ['type' => 'string'],
                            'type'        => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                        ],
                    ],
                    'OperationLog' => [
                        'type' => 'object',
                        'properties' => [
                            'id'        => ['type' => 'string'],
                            'user_name' => ['type' => 'string'],
                            'method'    => ['type' => 'string', 'enum' => ['POST', 'PUT', 'DELETE']],
                            'path'      => ['type' => 'string'],
                            'ip'        => ['type' => 'string'],
                            'created_at'=> ['type' => 'string'],
                        ],
                    ],
                    'HealthData' => [
                        'type' => 'object',
                        'properties' => [
                            'app'           => ['type' => 'string', 'example' => 'open-admin'],
                            'version'       => ['type' => 'string', 'example' => '1.0'],
                            'php'           => ['type' => 'string', 'example' => '8.3.0'],
                            'database'      => ['type' => 'string', 'enum' => ['ok', 'unavailable']],
                            'redis'         => ['type' => 'string', 'enum' => ['ok', 'unavailable']],
                            'elasticsearch' => ['type' => 'string', 'enum' => ['ok', 'unavailable']],
                            'timestamp'     => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'paths' => [
                // 第 3 参是 array $notes（非 nullable），传 null 会让 buildSpec() 在第一条就抛 TypeError ⇒ 整个
                // /api/docs 恒 500（冒烟只打未登录的 401，控制器从没被执行过，所以一直没被发现）。
                '/health' => $this->path(trans('Health check'), 'GET', [], 'HealthData'),

                '/api/v1/captcha/generate' => $this->path(trans('Generate click captcha'), 'POST', [trans('Public')], 'object', ['difficulty' => 'string: easy|medium|hard']),
                '/api/v1/captcha/verify'   => $this->path(trans('Verify click captcha'), 'POST', [trans('Public')]),
                '/api/v1/auth/login'       => $this->path(trans('Login'), 'POST', [trans('Public')], 'object', ['username' => 'string', 'password' => 'string', 'captcha_key' => 'string', 'clicks' => 'array']),
                // '/api/v1/auth/register' 于 2026-10-01 摘除（匿名可建启用态管理员 + 该路由组无 OperationLog），
                // 这里同步摘掉条目 —— OpenAPI 是机器读的契约，留一条「Public」的幽灵端点会直接把调用方引到 404。
                // 留档在 config/route.php 的注记与 admin/docs/API*.md 的 §3.6 注记里，不在这里重复。
                '/api/v1/auth/refresh'     => $this->path(trans('Refresh token'), 'POST', [trans('Public')], 'object', ['refresh_token' => 'string']),

                '/admin/v1/dashboard' => $this->path(trans('Dashboard data'), 'GET', [trans('JWT authentication')]),

                '/admin/v1/user'               => $this->path(trans('User list'), 'GET', ['JWT', 'RBAC'], 'object', ['page' => 'int', 'limit' => 'int', 'keyword' => 'string?', 'status' => 'int?']),
                '/admin/v1/user/{id}'          => $this->path(trans('User detail/update/delete'), 'GET|PUT|DELETE', ['JWT', 'RBAC']),
                '/admin/v1/user/batch/destroy' => $this->path(trans('Bulk delete users'), 'POST', ['JWT', 'RBAC', trans('Password confirmation required')], null, ['ids' => 'string[]', 'password' => 'string']),
                '/admin/v1/user/batch/status'  => $this->path(trans('Bulk enable/disable users'), 'POST', ['JWT', 'RBAC'], null, ['ids' => 'string[]', 'status' => '0|1']),

                '/admin/v1/role'     => $this->path(trans('Role list/create'), 'GET|POST', ['JWT', 'RBAC']),
                '/admin/v1/role/{id}' => $this->path(trans('Role update/delete'), 'PUT|DELETE', ['JWT', 'RBAC', trans('Deletion requires password confirmation')]),

                '/admin/v1/permission'     => $this->path(trans('Permission tree/create'), 'GET|POST', ['JWT', 'RBAC']),
                '/admin/v1/permission/{id}' => $this->path(trans('Permission update/delete'), 'PUT|DELETE', ['JWT', 'RBAC', trans('Deletion requires password confirmation')]),

                '/admin/v1/config'     => $this->path(trans('Config list/create'), 'GET|POST', ['JWT', 'RBAC']),
                '/admin/v1/config/{id}' => $this->path(trans('Config update/delete'), 'PUT|DELETE', ['JWT', 'RBAC', trans('Deletion requires password confirmation')]),

                '/admin/v1/log' => $this->path(trans('Operation log query'), 'GET', ['JWT', 'RBAC'], 'array', ['user_id' => 'int?', 'action' => 'string?', 'path' => 'string?', 'start_date' => 'date?', 'end_date' => 'date?']),

                '/admin/v1/profile'          => $this->path(trans('Update profile'), 'PUT', ['JWT'], null, ['real_name' => 'string?', 'phone' => 'string?', 'email' => 'string?']),
                '/admin/v1/profile/password' => $this->path(trans('Change password'), 'PUT', ['JWT'], null, ['old_password' => 'string', 'new_password' => 'string']),
                '/admin/v1/profile/logout'   => $this->path(trans('Logout'), 'POST', ['JWT']),

                '/admin/v1/export/excel' => $this->path(trans('Export Excel'), 'POST', ['JWT', 'RBAC'], 'binary', ['table' => 'string', 'columns' => 'string[]', 'conditions' => 'object?', 'title' => 'string?']),
                '/admin/v1/export/pdf'   => $this->path(trans('Export PDF'), 'POST', ['JWT', 'RBAC'], 'binary', ['type' => 'string', 'title' => 'string?', 'data' => 'object?']),

                '/admin/v1/import/users' => $this->path(trans('Import users (Excel)'), 'POST', ['JWT', 'RBAC'], 'object', ['file' => 'file(.xlsx)']),

                '/admin/v1/upload' => $this->path(trans('File upload'), 'POST', ['JWT', 'RBAC'], 'object', ['file' => 'file(jpg/png/pdf/xlsx/docx, max 10MB)']),
            ],
        ];
    }

    private function path(string $summary, string $method, array $notes = [], ?string $responseRef = null, ?array $params = null): array
    {
        $methods = explode('|', strtoupper($method));
        $path = [];

        foreach ($methods as $m) {
            $op = [
                'summary' => $summary,
                'description' => implode(' | ', $notes),
                'responses' => ['200' => ['description' => trans('Success')]],
            ];

            if ($responseRef && $responseRef !== 'object') {
                $op['responses']['200']['content'] = [
                    'application/json' => ['schema' => ['$ref' => "#/components/schemas/{$responseRef}"]],
                ];
            }

            if ($params) {
                $op['requestBody'] = [
                    'required' => true,
                    'content' => ['application/json' => ['schema' => [
                        'type' => 'object',
                        'properties' => array_map(fn($v) => ['type' => 'string', 'description' => $v], $params),
                    ]]],
                ];
            }

            $path[strtolower($m)] = $op;
        }

        return $path;
    }
}
