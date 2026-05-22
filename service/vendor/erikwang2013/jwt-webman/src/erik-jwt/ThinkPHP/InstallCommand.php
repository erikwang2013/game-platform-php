<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\ThinkPHP;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class InstallCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('jwt:install')
             ->setDescription('Install erik JWT: publish config and generate secret key');
    }

    protected function execute(Input $input, Output $output): int
    {
        $source = __DIR__ . '/config/jwt.php';
        $dest   = app()->getConfigPath() . 'jwt.php';

        if (!file_exists($dest)) {
            copy($source, $dest);
            $output->info("Config published to: {$dest}");
        } else {
            $output->warning("Config already exists at: {$dest}");
        }

        $secretKey = bin2hex(random_bytes(32));
        $envPath   = app()->getRootPath() . '.env';

        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (strpos($envContent, 'JWT.SECRET_KEY=') !== false) {
                $envContent = preg_replace(
                    '/^JWT\.SECRET_KEY=.*$/m',
                    'JWT.SECRET_KEY=' . $secretKey,
                    $envContent
                );
            } else {
                $envContent .= "\nJWT.SECRET_KEY={$secretKey}\n";
            }
            file_put_contents($envPath, $envContent);
        }

        $output->info('JWT plugin installed successfully!');
        $output->info("JWT.SECRET_KEY: {$secretKey}");

        return 0;
    }
}
