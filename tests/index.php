<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using

use Davislar\daemon\DaemonController;

$app = new DaemonController(new \Davislar\objects\ConfigObject([
    'loop' => 20
]));
$app->run();