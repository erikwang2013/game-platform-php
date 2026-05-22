#!/usr/bin/env php
<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

ini_set('display_errors', 'on');
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

support\App::load();
support\bootstrap\Session::class;
support\bootstrap\LaravelDb::class;

Worker::runAll();
