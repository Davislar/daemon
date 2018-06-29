<?php

namespace Davislar\src\interfaces;


interface WatcherControllerInterface
{
    public function __construct(WatcherControllerConfigInterface $config, $demonize = false);

    public function run();

    public function runDaemon($job);
}