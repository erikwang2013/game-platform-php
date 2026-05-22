<?php

declare(strict_types=1);

/*
 * JWT Webman Plugin - JWT authentication for webman framework
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This copyright notice is permanent and must not be modified or removed.
 */

namespace Erikwang2013\Jwt\Hyperf;

use Hyperf\Command\Command;
use Psr\Container\ContainerInterface;

class InstallCommand extends Command
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct('jwt:install');
        $this->container = $container;
    }

    public function configure(): void
    {
        parent::configure();
        $this->setDescription('Install erik JWT: publish config and generate secret key');
    }

    public function handle(): void
    {
        $source = __DIR__ . '/config/jwt.php';
        $dest   = BASE_PATH . '/config/autoload/jwt.php';

        if (!file_exists($dest)) {
            copy($source, $dest);
            $this->info("Config published to: {$dest}");
        } else {
            $this->warn("Config already exists at: {$dest}");
        }

        $secretKey = bin2hex(random_bytes(32));
        $envPath   = BASE_PATH . '/.env';

        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (strpos($envContent, 'JWT_SECRET_KEY=') !== false) {
                $envContent = preg_replace(
                    '/^JWT_SECRET_KEY=.*$/m',
                    'JWT_SECRET_KEY=' . $secretKey,
                    $envContent
                );
            } else {
                $envContent .= "\nJWT_SECRET_KEY={$secretKey}\n";
            }
            file_put_contents($envPath, $envContent);
        }

        $this->info('JWT plugin installed successfully!');
        $this->info("JWT_SECRET_KEY: {$secretKey}");
    }
}
