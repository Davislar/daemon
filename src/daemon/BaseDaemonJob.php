<?php

namespace Davislar\daemon;

use Davislar\console\ConsoleHelper;

/**
 * Class BaseDaemonJob
 * @package Davislar\daemon
 */
abstract class BaseDaemonJob
{
    protected $pidJob;

    abstract public function __construct(array $config);

    abstract public function run();

    /**
     * Set new process name
     */
    protected function changeProcessName()
    {
        //rename process
        if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
            cli_set_process_title($this->pidName);
        } else {
            if (function_exists('setproctitle')) {
                setproctitle($this->pidName);
            } else {
                ConsoleHelper::consolePrint(WatcherDaemonController::EXIT_CODE_ERROR, 'Can\'t find cli_set_process_title or setproctitle function');
            }
        }
    }

    /**
     * @return mixed
     */
    public function getPidJob()
    {
        return $this->pidJob;
    }

    /**
     * @param mixed $pidJob
     */
    public function setPidJob($pidJob)
    {
        $this->pidJob = $pidJob;
    }


}