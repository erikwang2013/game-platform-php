<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\admin\v1\controller\DocsController;
use app\admin\v1\controller\UploadController;
use erikwang2013\apidoc\annotation\Param as ApidocParam;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * /api/docs 的 OpenAPI 契约得真能构建出来（2026-10-01）。
 *
 * 钉两颗：
 * 1. 它不再因 DocsController::path() 第 3 参（`array $notes`，非 nullable）传 null 而抛 TypeError。
 *    该缺陷让 `/api/docs` 对任何过鉴权的调用者恒 500 —— 而冒烟只打了未登录的 401，控制器从没被执行过，
 *    所以藏了很久没人发现。
 * 2. paths 里不再有匿名注册条目（随 config/route.php 摘除）—— 有人把路由加回来，这里也红。
 *
 * 第三颗（2026-10-02）：/admin/v1/upload 的扩展名白名单有三份手写副本，必须逐字同集同序。
 * 第四颗（2026-10-02）：同一端点的**体积上限**也是手抄副本（真值字节数 / 契约串 / apidoc desc 的 `10MB`）。
 * 第五颗（2026-10-02）：该上限还**不能超过上游真闸**（workerman 的 max_package_size）。
 */
class DocsOpenApiSpecTest extends TestCase
{
    #[Test]
    public function openApiSpecBuildsAndHasNoGhostRegisterEndpoint(): void
    {
        $controller = new DocsController();
        /** @var array<string, mixed> $spec */
        $spec = (new ReflectionMethod($controller, 'buildSpec'))->invoke($controller);

        $paths = $spec['paths'] ?? null;
        $this->assertIsArray($paths, 'buildSpec() 应当返回带 paths 的规范数组');
        $this->assertNotEmpty($paths, 'paths 不能为空 —— 空了说明构建其实已经坏了');

        // 正控制：认一条活着的路由，防「整体变空也算过」
        $this->assertArrayHasKey('/api/v1/auth/login', $paths);

        $this->assertArrayNotHasKey('/api/v1/auth/register', $paths, '匿名注册路由已摘除，契约里不该再有这个幽灵端点');
    }

    /**
     * /admin/v1/upload 的扩展名白名单是**同一事实的三份手写副本**：
     *   ① 真值 UploadController::$allowExts（控制流真正读的那个）
     *   ② DocsController 契约里那条 `file(...)` 串（经 buildSpec() 取运行时值，不扫源码文本）
     *   ③ UploadController::upload() 的 #[Apidoc\Param(desc: …)]
     * 改其中任何一份而不改其余 ⇒ 这条红。三份在 2026-10-02 被手工对齐过一次，这里是防止再漂。
     */
    #[Test]
    public function uploadExtensionWhitelistIsIdenticalInAllThreeCopies(): void
    {
        // 私有属性自 PHP 8.1 起反射即可直读；别加 setAccessible()（8.5 起 deprecated，且本来就没作用）。
        /** @var array<int, string> $allowExts */
        $allowExts = (new ReflectionProperty(UploadController::class, 'allowExts'))->getValue(new UploadController());
        $this->assertNotEmpty($allowExts, '反射没读到 $allowExts —— 空数组会让下面比什么都是绿的');

        $docs = new DocsController();
        /** @var array<string, mixed> $spec */
        $spec = (new ReflectionMethod($docs, 'buildSpec'))->invoke($docs);
        $contractDesc = $spec['paths']['/admin/v1/upload']['post']['requestBody']['content']['application/json']['schema']['properties']['file']['description'] ?? null;
        $this->assertIsString($contractDesc, 'DocsController 里取不到 /admin/v1/upload 的 file 描述（形状变了？本钉子需同步）');

        // 按 name 找而不是按下标取：本端点日后**合法**地多一个参数时，这条不该跟着红。
        // apidoc 自己也是按具名实参读的（vendor/erikwang2013/apidoc-php/src/parses/ParseAnnotation.php:73
        // 调 getArguments()；AbstractAnnotation 的 formatParams() 会把位置实参整个丢掉）——
        // 所以这里也读具名键；改成位置实参则 desc 读不到，这条跟着一起红。
        $apidocDesc = null;
        foreach ((new ReflectionMethod(UploadController::class, 'upload'))->getAttributes(ApidocParam::class) as $attr) {
            $args = $attr->getArguments();
            if (($args['name'] ?? null) === 'file') {
                $apidocDesc = $args['desc'] ?? null;
                break;
            }
        }
        $this->assertIsString($apidocDesc, 'upload() 上找不到 file 参数的 #[Apidoc\Param(desc: …)]（须写成具名实参 desc:）');

        $this->assertSame($allowExts, $this->extList($contractDesc), 'DocsController 契约里的 file(...) 与 UploadController::$allowExts 不一致');
        $this->assertSame($allowExts, $this->extList($apidocDesc), '#[Apidoc\Param] 的 desc 与 UploadController::$allowExts 不一致');
    }

    /**
     * 第四颗：/admin/v1/upload 的体积上限是**同一事实的手抄副本**，形态与扩展名那颗同族，
     * 只是抄的是渲染后的容量串而非扩展名列表：
     *   ① 真值 UploadController::$maxSize（控制流真正读的那个，字节数）
     *   ② DocsController 契约里 `file(…, max 10MB)`
     *   ③ UploadController::upload() 的 #[Apidoc\Param] desc 里的 `最大10MB`
     * 只改 $maxSize 而不改两处描述（或反之）⇒ 这条红。
     *
     * ⚠ 两侧的串是**渲染过的单位**（`10MB`），真值是字节数（`10 * 1024 * 1024`）——
     * 所以比的是 sizeInBytes() 换算出来的字节数，不是字符串本身。
     * 认得的写法写在 sizeInBytes() 的注释里；表外写法（`10.5MB`、`10 兆`…）**红并回显原文**，不静默跳过。
     */
    #[Test]
    public function uploadSizeLimitIsIdenticalInAllCopies(): void
    {
        /** @var int $maxSize */
        $maxSize = (new ReflectionProperty(UploadController::class, 'maxSize'))->getValue(new UploadController());
        // 读到了才算数：读到 null/0 的话，下面比什么都是绿的
        $this->assertIsInt($maxSize, '反射没读到 $maxSize —— 形状变了？本钉子需同步');
        $this->assertGreaterThan(0, $maxSize, '$maxSize 非正数 —— 下面比什么都会是绿的');

        [$contractDesc, $apidocDesc] = $this->uploadFileDescriptions();

        $this->assertSame($maxSize, $this->sizeInBytes($contractDesc), 'DocsController 契约里的容量与 UploadController::$maxSize 不一致');
        $this->assertSame($maxSize, $this->sizeInBytes($apidocDesc), '#[Apidoc\Param] desc 里的容量与 UploadController::$maxSize 不一致');
    }

    /**
     * 第五颗：`$maxSize` 不是这个端点的天花板，它上面还有一道**真闸**——
     * `config/server.php` 的 `max_package_size`。它在启动时被写进
     * TcpConnection::$defaultMaxPackageSize（vendor/workerman/webman-framework/src/support/App.php:90，
     * 读的就是 `config('server')`），随后由 vendor/workerman/workerman/src/Protocols/Http.php:195
     * 在**协议解析层**（中间件与控制器之前）把「请求头长度 + Content-Length」超过它的请求
     * 直接 413 并断开。故把 $maxSize 抬到 ① 之上时，端点就成了「描述 20MB、实际到不了 10MB」的
     * 骗子 —— 上面那颗只比三份**描述**彼此是否一致（它们会一起改成 20MB，照样全绿），抓不到这个。
     *
     * ⚠ 刻意**不钉**「安全插件 body_size.max_size」那一层（照对称看该有三层）。它不是约束，是死值：
     *   1. `config/plugin/erikwang2013/security-php/app.php` **从未被加载**。webman 的
     *      Config::loadFromDir() 要求嵌套目录的兄弟 app.php 里有 `enable` 键，而该文件只有
     *      `enabled`（多一个 d）⇒ 实测解析出的配置树里 `plugin.erikwang2013` 有 9 个插件，
     *      独缺 security-php 与 encryptable —— 正是仅有的两个没有 `enable` 的文件。
     *   2. 于是 support/bootstrap.php:5 的
     *      `SecurityGuard::init(config('plugin.erikwang2013.security-php.app', config('security', [])))`
     *      实收 `[]`（config/security.php 也不存在），而 SecurityGuard::init() 在构造检测链**之前**
     *      就 `return`；SecurityGuard::guard() 又对 `empty($config['enabled']) || $chain === null`
     *      恒返 `[]` ⇒ 整套 30 个检测器（含 body_size）在真实启动配置下全关。同一条请求实测：
     *      真实启动配置 → 200 透传；手工按 app.php 内容 init 后 → 403「Request blocked by security policy」。
     *   3. 即便它被加载也仍不生效：BodySizeDetector 读 `$data['_server.CONTENT_LENGTH']`，该键由
     *      SecurityGuard.php:155 从 `$meta['content_length'] ?? $_SERVER['CONTENT_LENGTH'] ?? ''`
     *      合成，而 SecurityFilter::collectInputs() 不传 content_length、webman 也不按请求填
     *      `$_SERVER` ⇒ 恒为 '' ⇒ 检测器恒早退。
     * 钉一个没有运行时效果的值只会给出「绿灯照样全绿」的假保证，故这里只钉真闸 ①。
     *
     * 口径取 `≤` 而非 `<`：两道闸都是**严格大于**才拒（Http.php:195 与 UploadController:68 的
     * `$file->getSize() > $this->maxSize`）⇒ 恰好等于上限时两边都放行，取等号合法。
     * 本条**不保证**「正好 $maxSize 字节的文件一定送得到」：multipart 的 boundary 与分段头会叠加进
     * Content-Length，实际可达尺寸略小于 $maxSize。不为这点留余量断言，是因为余量取决于 boundary
     * 长度与表单字段数、没有可写死的常量，写死一个猜出来的数只会随无关改动乱红。
     */
    #[Test]
    public function uploadSizeLimitDoesNotExceedUpstreamGate(): void
    {
        /** @var int $maxSize */
        $maxSize = (new ReflectionProperty(UploadController::class, 'maxSize'))->getValue(new UploadController());
        $this->assertIsInt($maxSize, '反射没读到 $maxSize —— 形状变了？本钉子需同步');
        $this->assertGreaterThan(0, $maxSize, '$maxSize 非正数 —— 下面比什么都会是绿的');

        // 读到了才算数：读不出来时先红在这一步，不许静默跳过然后全绿。
        // （webman 那边对缺键有 `?? 10 * 1024 * 1024` 兜底，所以删键并不改变运行时行为 ——
        //   这里仍判红，红的是「应用显式声明的天花板没了」这件事本身。）
        $gate = config('server.max_package_size');
        $this->assertIsInt($gate, 'config/server.php 的 max_package_size 读不出来（键被删或改名？）—— 它才是上传真正的天花板');
        $this->assertGreaterThan(0, $gate, 'max_package_size 非正数 —— 比什么都会是绿的');

        // assertLessThanOrEqual($expected, $actual) 断言的是 $actual <= $expected，即 $maxSize <= $gate。
        $this->assertLessThanOrEqual(
            $gate,
            $maxSize,
            'UploadController::$maxSize 超过了 workerman 的 max_package_size（' . $gate . ' 字节）—— 再大的文件在进控制器之前就被 413 拒掉，端点描述了一个到不了的容量'
        );
    }

    /**
     * 取 /admin/v1/upload 的两处 file 描述：契约侧（buildSpec() 产物）+ apidoc 侧（注解具名实参）。
     * 读法与 uploadExtensionWhitelistIsIdenticalInAllThreeCopies() 里那两段同形 ——
     * 刻意不抽公共 helper，那颗钉子已验收，一个字都不再动。
     *
     * @return array{0: string, 1: string}
     */
    private function uploadFileDescriptions(): array
    {
        $docs = new DocsController();
        /** @var array<string, mixed> $spec */
        $spec = (new ReflectionMethod($docs, 'buildSpec'))->invoke($docs);
        $contractDesc = $spec['paths']['/admin/v1/upload']['post']['requestBody']['content']['application/json']['schema']['properties']['file']['description'] ?? null;
        $this->assertIsString($contractDesc, 'DocsController 里取不到 /admin/v1/upload 的 file 描述（形状变了？本钉子需同步）');

        $apidocDesc = null;
        foreach ((new ReflectionMethod(UploadController::class, 'upload'))->getAttributes(ApidocParam::class) as $attr) {
            $args = $attr->getArguments();
            if (($args['name'] ?? null) === 'file') {
                $apidocDesc = $args['desc'] ?? null;
                break;
            }
        }
        $this->assertIsString($apidocDesc, 'upload() 上找不到 file 参数的 #[Apidoc\Param(desc: …)]');

        return [$contractDesc, $apidocDesc];
    }

    /**
     * 从描述串里读出容量并换算成字节。**认得的写法**：
     *   `<整数>` + 可选空白 + 单位（大小写不敏感）：B | K | KB | M | MB | G | GB | KiB | MiB | GiB
     *   K/M/G 单独出现按 1024 进制 —— 与本仓真值 `10 * 1024 * 1024` 同口径。
     * 于是 `max 10MB`、`最大 10 MB`、`10240KB`、`10 MiB`、`10485760B` 都读成 10485760。
     * 判红（不是静默跳过）：小数（`10.5MB` —— 后视 `(?<![\d.,])` 就是为它加的：没有它，
     * 正则会在 `5MB` 处再起一头，把 `10.5MB` **静默读成 5MB**）、千分位（`10,485,760B`）、
     * 中文单位（`10 兆`）、或串里出现**不止一处**容量写法。
     */
    private function sizeInBytes(string $desc): int
    {
        $hits = preg_match_all('/(?<![\d.,])(\d+)\s*(GiB|MiB|KiB|GB|MB|KB|G|M|K|B)\b/i', $desc, $m, PREG_SET_ORDER);
        $this->assertSame(1, $hits, '描述串里读不出唯一的容量写法（认得的写法见 sizeInBytes() 注释）：' . $desc);

        $multipliers = [
            'B' => 1,
            'K' => 1024, 'KB' => 1024, 'KIB' => 1024,
            'M' => 1048576, 'MB' => 1048576, 'MIB' => 1048576,
            'G' => 1073741824, 'GB' => 1073741824, 'GIB' => 1073741824,
        ];

        return (int) $m[0][1] * $multipliers[strtoupper($m[0][2])];
    }

    /** 从「…(jpg/jpeg/…)」形态的描述串里取出扩展名列表。 */
    private function extList(string $desc): array
    {
        $hits = preg_match('/\(([a-z0-9]+(?:\/[a-z0-9]+)*)/', $desc, $m);
        $this->assertSame(1, $hits, '描述串里读不出扩展名列表（格式变了？）：' . $desc);

        return explode('/', $m[1]);
    }
}
