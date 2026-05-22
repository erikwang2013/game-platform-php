<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;

/**
 * OpenAPI 3.0 文档端点
 * @Apidoc\Title("API文档")
 * @Apidoc\Group("docs")
 */
class DocsController
{
    /**
     * API文档
     * @Apidoc\Title("API文档")
     * @Apidoc\Url("/api/docs")
     * @Apidoc\Method("GET")
     */
    public function index(Request $request): Response
    {
        return json($this->buildSpec());
    }

    private function buildSpec(): array
    {
        $baseUrl = rtrim((string) config('app.url', 'http://localhost:8787'), '/');

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title'       => '开放管理后台 API',
                'description' => '基于 webman v2 的全栈管理后台系统。API 版本通过请求头 API-Version 控制，不在 URL 中体现。',
                'version'     => '1.0.0',
                'contact'     => ['name' => 'erik', 'email' => 'erik@erik.xyz', 'url' => 'https://erik.xyz'],
            ],
            'servers' => [['url' => $baseUrl, 'description' => '本地开发']],
            'security' => [['bearerAuth' => []]],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
                    'apiVersion' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'API-Version', 'description' => 'API 版本号，默认 v1'],
                ],
                'schemas' => [
                    'ApiResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'code'    => ['type' => 'integer', 'description' => '0=成功, 400=参数错误, 401=未认证, 403=无权限, 404=不存在, 422=验证失败, 429=限流, 500=服务器错误'],
                            'message' => ['type' => 'string'],
                            'data'    => ['type' => 'object'],
                        ],
                    ],
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id'         => ['type' => 'string', 'description' => 'hashid 加密的用户ID'],
                            'username'   => ['type' => 'string'],
                            'real_name'  => ['type' => 'string'],
                            'phone'      => ['type' => 'string', 'description' => '脱敏手机号'],
                            'email'      => ['type' => 'string', 'description' => '脱敏邮箱'],
                            'status'     => ['type' => 'integer', 'description' => '1=启用, 0=禁用'],
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
                '/health' => $this->path('健康检查', 'GET', null, 'HealthData'),

                '/api/captcha/generate' => $this->path('生成点击验证码', 'POST', ['API-Version 头必须'], 'object', ['difficulty' => 'string: easy|medium|hard']),
                '/api/captcha/verify'   => $this->path('校验点击验证码', 'POST', ['API-Version 头必须']),
                '/api/auth/login'       => $this->path('登录', 'POST', ['API-Version 头必须'], 'object', ['username' => 'string', 'password' => 'string', 'captcha_key' => 'string', 'clicks' => 'array']),
                '/api/auth/register'    => $this->path('注册', 'POST', ['API-Version 头必须'], 'object', ['username' => 'string', 'password' => 'string', 'real_name' => 'string', 'captcha_key' => 'string', 'clicks' => 'array']),
                '/api/auth/refresh'     => $this->path('刷新令牌', 'POST', ['API-Version 头必须'], 'object', ['refresh_token' => 'string']),

                '/admin/dashboard' => $this->path('仪表盘数据', 'GET', ['JWT 认证']),

                '/admin/user'               => $this->path('用户列表', 'GET', ['JWT', 'RBAC'], 'object', ['page' => 'int', 'limit' => 'int', 'keyword' => 'string?', 'status' => 'int?']),
                '/admin/user/{id}'          => $this->path('用户详情/更新/删除', 'GET|PUT|DELETE', ['JWT', 'RBAC']),
                '/admin/user/batch/destroy' => $this->path('批量删除用户', 'POST', ['JWT', 'RBAC', '需要密码确认'], null, ['ids' => 'string[]', 'password' => 'string']),
                '/admin/user/batch/status'  => $this->path('批量启用/禁用用户', 'POST', ['JWT', 'RBAC'], null, ['ids' => 'string[]', 'status' => '0|1']),

                '/admin/role'     => $this->path('角色列表/创建', 'GET|POST', ['JWT', 'RBAC']),
                '/admin/role/{id}' => $this->path('角色更新/删除', 'PUT|DELETE', ['JWT', 'RBAC', '删除需密码确认']),

                '/admin/permission'     => $this->path('权限树/创建', 'GET|POST', ['JWT', 'RBAC']),
                '/admin/permission/{id}' => $this->path('权限更新/删除', 'PUT|DELETE', ['JWT', 'RBAC', '删除需密码确认']),

                '/admin/config'     => $this->path('配置列表/创建', 'GET|POST', ['JWT', 'RBAC']),
                '/admin/config/{id}' => $this->path('配置更新/删除', 'PUT|DELETE', ['JWT', 'RBAC', '删除需密码确认']),

                '/admin/log' => $this->path('操作日志查询', 'GET', ['JWT', 'RBAC'], 'array', ['user_id' => 'int?', 'action' => 'string?', 'path' => 'string?', 'start_date' => 'date?', 'end_date' => 'date?']),

                '/admin/profile'          => $this->path('更新个人信息', 'PUT', ['JWT'], null, ['real_name' => 'string?', 'phone' => 'string?', 'email' => 'string?']),
                '/admin/profile/password' => $this->path('修改密码', 'PUT', ['JWT'], null, ['old_password' => 'string', 'new_password' => 'string']),
                '/admin/profile/logout'   => $this->path('登出', 'POST', ['JWT']),

                '/admin/export/excel' => $this->path('导出Excel', 'POST', ['JWT', 'RBAC'], 'binary', ['table' => 'string', 'columns' => 'string[]', 'conditions' => 'object?', 'title' => 'string?']),
                '/admin/export/pdf'   => $this->path('导出PDF', 'POST', ['JWT', 'RBAC'], 'binary', ['type' => 'string', 'title' => 'string?', 'data' => 'object?']),

                '/admin/import/users' => $this->path('导入用户(Excel)', 'POST', ['JWT', 'RBAC'], 'object', ['file' => 'file(.xlsx)']),

                '/admin/upload' => $this->path('文件上传', 'POST', ['JWT', 'RBAC'], 'object', ['file' => 'file(jpg/png/pdf/xlsx/docx, max 10MB)']),
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
                'responses' => ['200' => ['description' => '成功']],
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
