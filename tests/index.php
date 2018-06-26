<?php

defined('APP_DEV_ENV') or define('APP_DEV_ENV', true);
defined('APP_DEM_ENV') or define('APP_DEM_ENV', false);

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using

use Davislar\daemon\DaemonController;

$app = new DaemonController(new \Davislar\objects\ConfigObject([
    'loop' => 20,
    'pidDir' => __DIR__ . '/../runtime/daemon',
    'logDir' => __DIR__ . '/../runtime/logs',
    'workers' => [
        [
            'name' => 'test',
            'class' => Davislar\tests\TestDaemonJob::class,
            'enabled' => true
        ]
    ]
]));
$app->run();