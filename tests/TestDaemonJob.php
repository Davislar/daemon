<?php

namespace Davislar\tests;

use Davislar\console\ConsoleHelper;
use Davislar\daemon\BaseDaemonJob;

/**
 * Class TestDaemonJob
 * @package Davislar\daemon
 */
class TestDaemonJob extends BaseDaemonJob
{

    protected $pidName;

    /**
     * TestDaemonJob constructor.
     * @param $config
     */
    public function __construct(array $config)
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }


    public function run()
    {
        $this->changeProcessName();
        $message = 'pidJob: ' . $this->pidJob . '. TestDaemonJob: ' . "\n";
        $message = ConsoleHelper::ansiFormat($message, [ConsoleHelper::BG_BLUE]) . "\n";
        ConsoleHelper::stdout($message);
    }


}