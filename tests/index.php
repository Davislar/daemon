<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload files using

use Davislar\daemon\DaemonController;

$app = new DaemonController();
$app->run();