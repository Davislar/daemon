<?php

namespace Davislar\tests;
use Davislar\console\ConsoleHelper;

/**
 * Class TestDaemonJob
 * @package Davislar\daemon
 */
class TestDaemonJob
{
    protected $pidJob;
    protected $pidName;

    /**
     * TestDaemonJob constructor.
     * @param $config
     */
    public function __construct(array $config)
    {
        foreach ($config as $key => $value){
            $this->$key = $value;
        }
    }

    public function run(){
        $this->changeProcessName();
        $message = 'pidJob: ' . $this->pidJob . '. TestDaemonJob: '. "\n" ;
        $message = ConsoleHelper::ansiFormat($message, [ConsoleHelper::BG_BLUE]) . "\n";
        ConsoleHelper::stdout($message);
//        posix_kill($this->pidJob, SIGKILL);
    }

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
            }
//            else {
//                $this->halt(5000, 'Can\'t find cli_set_process_title or setproctitle function', ConsoleHelper::BG_RED);
//            }
        }
    }
}