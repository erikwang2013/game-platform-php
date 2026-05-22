<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\Encryptable\Composer;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Erikwang2013\Encryptable\Support\PackagePluginPaths;

/**
 * Publishes default config files following each supported framework's official layout.
 *
 * Detection order (merged):
 * 1) {@code Composer::getRepositoryManager()->getLocalRepository()} — installed packages via Composer API
 * 2) {@code composer.lock} — full resolved graph
 * 3) Root {@code composer.json} {@code require} / {@code require-dev} keys
 * 4) Project filesystem heuristics (Webman {@code support/bootstrap.php}, Laravel {@code artisan}, Hyperf {@code bin/hyperf.php}, ThinkPHP {@code think}, …)
 *
 * @see https://laravel.com/docs/configuration
 * @see https://webman.workerman.net/doc/en/plugin/create.html
 * @see https://doc.thinkphp.cn/v8_0/config_file.html
 * @see https://hyperf.wiki/en/config.html
 */
final class Plugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageEvent',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPostPackageEvent',
        ];
    }

    public function onPostPackageEvent(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        $package = null;
        if ($operation instanceof InstallOperation) {
            $package = $operation->getPackage();
        } elseif ($operation instanceof UpdateOperation) {
            $package = $operation->getTargetPackage();
        }

        if ($package === null || $package->getName() !== PackagePluginPaths::COMPOSER_NAME) {
            return;
        }

        $composer = $event->getComposer();
        $io = $event->getIO();
        $installationManager = $composer->getInstallationManager();
        $installPath = $installationManager->getInstallPath($package);

        if ($installPath === null || $installPath === '' || ! is_dir($installPath)) {
            return;
        }

        $vendorDir = $composer->getConfig()->get('vendor-dir');
        if (! is_string($vendorDir) || $vendorDir === '') {
            return;
        }

        $vendorReal = realpath($vendorDir);
        if ($vendorReal === false) {
            return;
        }

        $projectRoot = dirname($vendorReal);
        $pluginAppStub = $installPath.'/config/stubs/plugin-app.php';
        $hyperfPluginStub = $installPath.'/config/stubs/hyperf-plugin-autoload.php';

        $names = $this->collectPackageNamesLowercase($composer, $projectRoot);
        $fs = $this->inferFrameworkFromFilesystem($projectRoot);

        $laravel = isset($names['laravel/framework']) || $fs['laravel'];
        $lumen = isset($names['laravel/lumen-framework']);
        $webman = isset($names['workerman/webman'])
            || $this->hasComposerPackagePrefix($names, 'webman/')
            || $fs['webman'];
        $hyperf = isset($names['hyperf/framework'])
            || isset($names['hyperf/hyperf'])
            || isset($names['hyperf/http-server'])
            || $fs['hyperf'];
        $thinkphp = isset($names['topthink/framework'])
            || isset($names['topthink/think'])
            || $fs['thinkphp'];

        $publishPluginApp = $webman || $laravel || $lumen || $thinkphp;
        $publishHyperfPlugin = $hyperf;

        if (! $publishPluginApp && ! $publishHyperfPlugin) {
            $io->write(sprintf(
                '<comment>[%s]</comment> Skipped auto config: no supported framework detected (checked Composer local repository, composer.lock, composer.json, and project layout). Copy files manually (see README).',
                PackagePluginPaths::COMPOSER_NAME
            ));

            return;
        }

        if ($publishHyperfPlugin && is_readable($hyperfPluginStub)) {
            $this->publishHyperfPluginAutoloadConfig($projectRoot, $hyperfPluginStub, $io);
        }

        if ($publishPluginApp && is_readable($pluginAppStub)) {
            $created = $this->ensurePluginAppPhp($projectRoot, $pluginAppStub, $io);
            if ($created) {
                $labels = [];
                if ($webman) {
                    $labels[] = 'Webman';
                }
                if ($laravel) {
                    $labels[] = 'Laravel';
                }
                if ($lumen) {
                    $labels[] = 'Lumen';
                }
                if ($thinkphp) {
                    $labels[] = 'ThinkPHP';
                }
                [$vendor, $plugin] = PackagePluginPaths::splitVendorPackage();
                $io->write(sprintf(
                    '<info>[%s]</info> Installed plugin config <comment>config/plugin/%s/%s/app.php</comment> (%s — same layout as Webman plugins; see README)',
                    PackagePluginPaths::COMPOSER_NAME,
                    $vendor,
                    $plugin,
                    $labels !== [] ? implode(' + ', $labels) : 'PHP'
                ));
            }
        }
    }

    /**
     * @return array<string, true> Lowercased Composer package names
     */
    private function collectPackageNamesLowercase(Composer $composer, string $projectRoot): array
    {
        $names = [];

        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        foreach ($localRepo->getPackages() as $package) {
            $names[strtolower($package->getName())] = true;
        }

        $lockPath = $projectRoot.'/composer.lock';
        if (is_readable($lockPath)) {
            $lock = json_decode((string) file_get_contents($lockPath), true);
            if (is_array($lock)) {
                foreach (['packages', 'packages-dev'] as $section) {
                    foreach ($lock[$section] ?? [] as $pkg) {
                        if (is_array($pkg) && isset($pkg['name']) && is_string($pkg['name'])) {
                            $names[strtolower($pkg['name'])] = true;
                        }
                    }
                }
            }
        }

        $composerJson = $projectRoot.'/composer.json';
        if (is_readable($composerJson)) {
            $rootPkg = json_decode((string) file_get_contents($composerJson), true);
            if (is_array($rootPkg)) {
                foreach (['require', 'require-dev'] as $section) {
                    foreach (array_keys($rootPkg[$section] ?? []) as $pkgName) {
                        if (is_string($pkgName)) {
                            $names[strtolower($pkgName)] = true;
                        }
                    }
                }
            }
        }

        return $names;
    }

    /**
     * @param array<string, true> $names
     */
    private function hasComposerPackagePrefix(array $names, string $prefix): bool
    {
        $prefix = strtolower($prefix);
        foreach (array_keys($names) as $n) {
            if (str_starts_with($n, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filesystem hints when composer.json / lock omit framework metapackages (common for Webman plugins).
     *
     * @return array{laravel: bool, webman: bool, hyperf: bool, thinkphp: bool}
     */
    private function inferFrameworkFromFilesystem(string $root): array
    {
        $webman = is_dir($root.'/config')
            && (
                is_file($root.'/start.php')
                || is_file($root.'/windows.php')
                || (is_dir($root.'/support') && is_file($root.'/support/bootstrap.php'))
            );

        $laravel = is_file($root.'/artisan')
            && (
                is_file($root.'/bootstrap/app.php')
                || is_file($root.'/app/Http/Kernel.php')
                || is_dir($root.'/bootstrap/cache')
            );

        $hyperf = is_file($root.'/bin/hyperf.php')
            || (
                is_dir($root.'/config/autoload')
                && (
                    is_file($root.'/config/autoload/server.php')
                    || is_file($root.'/config/autoload/dependencies.php')
                )
            );

        $thinkphp = file_exists($root.'/think') && ! is_dir($root.'/think');

        return [
            'laravel' => $laravel,
            'webman' => $webman,
            'hyperf' => $hyperf,
            'thinkphp' => $thinkphp,
        ];
    }

    /**
     * Hyperf：{@code config/autoload} 下相对路径生成点号键；新装使用 {@code plugins/{vendor}/{package}.php}。
     * 若项目已存在旧版 {@code config/autoload/encryptable.php}，则不再写入新路径以免重复配置。
     */
    private function publishHyperfPluginAutoloadConfig(string $projectRoot, string $hyperfPluginStub, IOInterface $io): void
    {
        [$vendor, $plugin] = PackagePluginPaths::splitVendorPackage();
        $autoloadDir = $projectRoot.'/config/autoload';
        $pluginDir = $autoloadDir.'/plugins/'.$vendor;
        $newTarget = $pluginDir.'/'.$plugin.'.php';
        $legacyTarget = $autoloadDir.'/encryptable.php';

        if (file_exists($newTarget) || file_exists($legacyTarget)) {
            return;
        }

        if (! is_dir($pluginDir) && ! @mkdir($pluginDir, 0775, true) && ! is_dir($pluginDir)) {
            $io->writeError(sprintf('<error>[%s]</error> Could not create Hyperf plugin config directory: %s', PackagePluginPaths::COMPOSER_NAME, $pluginDir));

            return;
        }

        if (@copy($hyperfPluginStub, $newTarget)) {
            $io->write(sprintf(
                '<info>[%s]</info> Installed Hyperf plugin config: <comment>config/autoload/plugins/%s/%s.php</comment> (see https://hyperf.wiki/en/config.html)',
                PackagePluginPaths::COMPOSER_NAME,
                $vendor,
                $plugin
            ));
        } else {
            $io->writeError(sprintf('<error>[%s]</error> Failed to copy Hyperf plugin config to %s', PackagePluginPaths::COMPOSER_NAME, $newTarget));
        }
    }

    /**
     * @return bool true if the file was created in this run
     */
    private function ensurePluginAppPhp(string $projectRoot, string $sourceStub, IOInterface $io): bool
    {
        [$vendor, $plugin] = PackagePluginPaths::splitVendorPackage();
        $targetDir = $projectRoot.'/config/plugin/'.$vendor.'/'.$plugin;
        if (! is_dir($targetDir) && ! @mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
            $io->writeError(sprintf('<error>[%s]</error> Could not create plugin config directory: %s', PackagePluginPaths::COMPOSER_NAME, $targetDir));

            return false;
        }

        $target = $targetDir.'/app.php';
        if (file_exists($target)) {
            return false;
        }

        if (! @copy($sourceStub, $target)) {
            $io->writeError(sprintf('<error>[%s]</error> Failed to copy plugin config to %s', PackagePluginPaths::COMPOSER_NAME, $target));

            return false;
        }

        return true;
    }
}
