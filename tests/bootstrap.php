<?php
/**
 * PHPUnit bootstrap: load the app (env, helpers, App\ autoloader) without a web request.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

$_SESSION = ['lang' => 'en'];
