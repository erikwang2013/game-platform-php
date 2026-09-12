<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

use erikwang2013\apidoc\Controller;
use erikwang2013\apidoc\exception\HttpException;
use erikwang2013\apidoc\middleware\WebmanMiddleware;
use Webman\Http\Request;
use Webman\Route;

// 插件常量（原由 WebmanService::register() 定义；下面改为自注册回调，故在此显式定义）
defined('APIDOC_ROOT_PATH') || define('APIDOC_ROOT_PATH', WebmanMiddleware::getRootPath());
defined('APIDOC_STORAGE_PATH') || define('APIDOC_STORAGE_PATH', WebmanMiddleware::getRuntimePath());

// 17 个 JSON 端点。回调内 try/catch 把门禁异常转成信封：
// 插件异常继承 HttpException 且未实现 render()，debug 模式下会被框架渲染成 HTTP 500 + 完整堆栈；
// 而 App::onMessage() 对每一层（含最内层控制器调用）都做了 catch-all（vendor App.php:443-470），
// 异常在那层已渲染成 Response，路由中间件永远 catch 不到 ⇒ 唯一可行的位置就是路由回调内部。
// 不能先调 WebmanService::register() 再注册同 URI：Route::addRoute 对重复 method+path 直接抛
// "Route conflict"（vendor Route.php:530-540）。副作用：插件的 auto_register_routes 分支未复刻
// （WebmanService.php:22-41），当前配置该项为 false，若日后开启需在此补回。
WebmanMiddleware::registerApidocRoutes(function ($item) {
    Route::any($item['uri'], function (Request $request) use ($item) {
        try {
            return (new Controller())->{$item['route']}();
        } catch (Throwable $e) {
            // 本项目约定：业务码随 HTTP 200 返回（对齐 /admin/v1/dashboard 未登录）。
            // 必须 catch Throwable 而非仅 HttpException：入参为数组时（如 password[]=x）
            // Auth::verifyAuth(string $password) 抛 TypeError，它属于 \Error 而非 HttpException，
            // 漏出回调就会被框架渲染成 HTTP 500 + 完整堆栈（含绝对路径与 vendor 行号）。
            // 非业务异常不回显 getMessage()——其文本本身可能带绝对路径。
            $isBiz = $e instanceof HttpException;
            return json([
                // 插件业务码：4001 Token 缺失/非法、4002 密码错误、4004 文档关闭…，
                // apidoc SPA 只认这组码弹密码框，不能换成字面量 401；4000 为通用参数错误。
                'code' => $isBiz ? $e->getCode() : 4000,
                'message' => $isBiz ? $e->getMessage() : '请求参数不合法',
                'data' => [],
            ]);
        }
    })->middleware([WebmanMiddleware::class]);
});

// 插件只注册 17 个 JSON 端点，没有 UI 路由；而 webman 静态服务只认精确文件路径，
// /apidoc/ 是目录所以 404。这里把文档首页指向 public/apidoc/index.html（前端产物，不改写 UI）。
$apidocIndex = function () {
    return response(file_get_contents(public_path('apidoc/index.html')), 200, ['Content-Type' => 'text/html; charset=utf-8']);
};
Route::get('/apidoc', $apidocIndex);
Route::get('/apidoc/', $apidocIndex);
