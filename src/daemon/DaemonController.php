<?php

namespace Davislar\daemon;
use Davislar\console\ConsoleHelper;
use Davislar\objects\ConfigObject;
use Davislar\src\interfaces\WatcherControllerConfigInterface;
use Davislar\src\interfaces\WatcherControllerInterface;

/**
 * Class DaemonController
 * @package Davislar\daemon
 */
class DaemonController
{
    const EXIT_CODE_NORMAL = 0;
    const EXIT_CODE_ERROR = 1;


    /**
     * Run controller as Daemon
     * @var $demonize boolean
     * @default false
     */
    public $demonize;

    /**
     * Main procces pid
     * @var $parentPID int
     */
    protected $parentPID;

    public $loop = 5;

    public $firstIteration = true;



    static $stopFlag = false;

    private $stdIn;
    private $stdOut;
    private $stdErr;

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
     * @param bool $worker
     *
     * @return string
     */
    public function getPidPath($daemon = null, $worker = false)
    {
        if (!file_exists($this->config->pidDir)) {
            mkdir($this->config->pidDir, 0744, true);
        }
        if (!$worker){
            $daemon = $this->getProcessName($daemon);
        }

        return $this->config->pidDir . DIRECTORY_SEPARATOR . $daemon;
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


    protected function loop(){
        if (file_put_contents($this->getPidPath(), getmypid())) {
            $this->parentPID = getmypid();
//            \Yii::trace('Daemon ' . $this->getProcessName() . ' pid ' . getmypid() . ' started.');
            while (!self::$stopFlag) {
                ConsoleHelper::consolePrint(0, 'Memory use: ' . memory_get_usage());
                ConsoleHelper::consolePrint(0, 'Memory at system: ' . memory_get_usage(true));
                if (memory_get_usage() > $this->config->memoryLimit) {
                    ConsoleHelper::consolePrint(5000, 'Daemon ' . $this->getProcessName() . ' pid ' .
                        getmypid() . ' used ' . memory_get_usage() . ' bytes on ' . $this->config->memoryLimit .
                        ' bytes allowed by memory limit', ConsoleHelper::BG_RED);
                    break;
                }
//                $this->trigger(self::EVENT_BEFORE_ITERATION);
//                $this->renewConnections();
                $jobs = $this->defineJobs();
                if ($jobs && !empty($jobs)) {
                    while (($job = $this->defineJobExtractor($jobs)) !== null) {
                        //if no free workers, wait
                        pcntl_signal_dispatch();
                        $this->runDaemon($job);
                    }
                } else {
                    sleep($this->config->loop);
                }
                pcntl_signal_dispatch();
//                $this->trigger(self::EVENT_AFTER_ITERATION);
            }
            return self::EXIT_CODE_NORMAL;
        }
        ConsoleHelper::consolePrint(self::EXIT_CODE_ERROR, 'Can\'t create pid file ' . $this->getPidPath(), ConsoleHelper::BG_RED);
    }

    /**
     * @return array
     */
    protected function defineJobs()
    {
        if ($this->firstIteration) {
            $this->firstIteration = false;
        } else {
            sleep($this->config->loop);
        }

        return $this->getDaemonsList();
    }

    /**
     * @param $pid
     *
     * @return bool
     */
    public function isProcessRunning($pid)
    {
        return file_exists("/proc/$pid");
    }


    /**
     * Close std streams and open to /dev/null
     * need some class properties
     */
    protected function closeStdStreams()
    {
        print_r(APP_DEV_ENV);
        if (APP_DEV_ENV) {
            if (is_resource(STDIN)) {
                fclose(STDIN);
                $this->stdIn = fopen('/dev/null', 'r');
            }
            if (is_resource(STDOUT)) {
                fclose(STDOUT);
                $this->stdOut = fopen('/dev/null', 'ab');
            }
            if (is_resource(STDERR)) {
                fclose(STDERR);
                $this->stdErr = fopen('/dev/null', 'ab');
            }
        }
    }

    /**
     * @return string
     */
    public function getProcessName($route = null)
    {
        return $this->config->name;
    }

    /**
     * Set new process name
     */
    protected function changeProcessName()
    {
        //rename process
        if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
            cli_set_process_title($this->config->name);
        } else {
            if (function_exists('setproctitle')) {
                setproctitle($this->config->name);
            } else {
                ConsoleHelper::consolePrint(5000, 'Can\'t find cli_set_process_title or setproctitle function', ConsoleHelper::BG_RED);
            }
        }
    }

    /**
     * PCNTL signals handler
     *
     * @param $signo
     * @param null $pid
     * @param null $status
     */
    final static function signalHandler($signo, $pid = null, $status = null)
    {
        switch ($signo) {
            case SIGINT:
            case SIGTERM:
                //shutdown
                self::$stopFlag = true;
                break;
            case SIGHUP:
                //restart, not implemented
                break;
            case SIGUSR1:
                //user signal, not implemented
                break;
            case SIGCHLD:
                if (!$pid) {
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                while ($pid > 0) {
                    if ($pid && isset(static::$currentJobs[$pid])) {
                        unset(static::$currentJobs[$pid]);
                    }
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                break;
        }
    }
}