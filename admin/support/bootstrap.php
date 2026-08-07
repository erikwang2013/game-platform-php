<?php

require_once __DIR__ . '/../vendor/workerman/webman-framework/src/support/bootstrap.php';

\Erikwang2013\Security\SecurityGuard::init(config('plugin.erikwang2013.security-php.app', config('security', [])));
