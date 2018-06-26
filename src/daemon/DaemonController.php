<?php

namespace Davislar\daemon;
use Davislar\console\ConsoleHelper;
use Davislar\objects\ConfigObject;

/**
 * Class DaemonController
 * @package Davislar\daemon
 */
class DaemonController
{
    /**
     * @var ConfigObject
     */
    protected $config;

    /**
     * Run controller as Daemon
     * @var $demonize boolean
     * @default false
     */
    public $demonize = false;

    /**
     * Main procces pid
     * @var $parentPID int
     */
    protected $parentPID;

    public $loop = 5;

    /**
     * @var int Memory limit for daemon, must bee less than php memory_limit
     * @default 32M
     */
    protected $memoryLimit = 268435456;

    protected $pidDir = "@runtime/daemons/pids";

    protected $logDir = "@runtime/daemons/logs";

    static $stopFlag = false;

    private $stdIn;
    private $stdOut;
    private $stdErr;

    /**
     * DaemonController constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Init function
     */
    public function init()
    {
        //set PCNTL signal handlers
        pcntl_signal(SIGTERM, ['Davislar\daemon\DaemonController', 'signalHandler']);
        pcntl_signal(SIGINT, ['Davislar\daemon\DaemonController', 'signalHandler']);
        pcntl_signal(SIGHUP, ['Davislar\daemon\DaemonController', 'signalHandler']);
        pcntl_signal(SIGUSR1, ['Davislar\daemon\DaemonController', 'signalHandler']);
        pcntl_signal(SIGCHLD, ['Davislar\daemon\DaemonController', 'signalHandler']);
    }


    /**
     * Start daemons
     */
    public function run(){
//        if ($this->demonize) {
//            $pid = pcntl_fork();
//            if ($pid == -1) {
//                $this->halt(self::EXIT_CODE_ERROR, 'pcntl_fork() rise error');
//            } elseif ($pid) {
//                $this->cleanLog();
//                $this->halt(self::EXIT_CODE_NORMAL);
//            } else {
//                posix_setsid();
//                $this->closeStdStreams();
//            }
//        }
//        $this->changeProcessName();
//
//        //run loop
//        return
        while (!self::$stopFlag) {
            if (memory_get_usage() > $this->memoryLimit) {
                break;
            }
            $this->halt(-1, 'Start');
            pcntl_signal_dispatch();
            $this->loop();
        }
    }

    /**
     * Delete pid file
     * @throws \Exception
     */
    protected function deletePid()
    {
        $pid = $this->getPidPath();
        if (file_exists($pid)) {
            if (file_get_contents($pid) == getmypid()) {
                unlink($this->getPidPath());
            }
        } else {
            throw new \Exception('Config was not set', 5000);
        }
    }

    /**
     * @param string $daemon
     *
     * @return string
     */
    public function getPidPath($daemon = null)
    {
        if (!file_exists($this->pidDir)) {
            mkdir($this->pidDir, 0744, true);
        }
        $daemon = $this->getProcessName($daemon);

        return $this->pidDir . DIRECTORY_SEPARATOR . $daemon;
    }

    /**
     * @return ConfigObject
     * @throws \Exception
     */
    public function getConfig()
    {
        if (is_null($this->config)){
            throw new \Exception('Config was not set', 5000);
        }
        return $this->config;
    }


    /**
     * Stop process and show or write message
     *
     * @param $code int -1|0|1
     * @param $message string
     */
    protected function halt($code, $message = null)
    {
        if ($message !== null) {
            $message = 'Code: ' . $code . "\n" . ConsoleHelper::ansiFormat($message, [ConsoleHelper::FG_GREEN]) . "\n";
            ConsoleHelper::stdout($message);
        }
    }

    protected function loop(){
        sleep($this->config->loop);
    }
}