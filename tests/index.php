<?php

defined('APP_DEV_ENV') or define('APP_DEV_ENV', true);
defined('APP_DEM_ENV') or define('APP_DEM_ENV', false);

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using

use Davislar\daemon\WatcherDaemonController;
use Davislar\objects\ConfigObject;

$config = [
    'loop' => 5,
    'name' => 'WatcherDaemon',
    'pidDir' => __DIR__ . '/../runtime/daemon',
    'logDir' => __DIR__ . '/../runtime/logs',
    'workers' => [
        [
            'name' => 'TestDaemonJob',
            'class' => Davislar\tests\TestDaemonJob::class,
            'enabled' => true
        ]
    ]
];
$app = new WatcherDaemonController(new ConfigObject($config));
$app->run();