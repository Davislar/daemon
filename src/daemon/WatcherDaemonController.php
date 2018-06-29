<?php

namespace Davislar\daemon;


use Davislar\console\ConsoleHelper;
use Davislar\objects\ConfigObject;
use Davislar\src\interfaces\WatcherControllerConfigInterface;
use Davislar\src\interfaces\WatcherControllerInterface;

class WatcherDaemonController extends DaemonController implements WatcherControllerInterface
{

    /**
     * @var WatcherControllerConfigInterface
     */
    protected $config;

    /**
     * DaemonController constructor.
     * @param $config
     * @param bool $demonize
     */
    public function __construct(WatcherControllerConfigInterface $config, $demonize = false)
    {
        $this->config = $config;
        $this->demonize = ($demonize) ? $demonize : false;
    }

    /**
     * @var $currentJobs [] array of running instances
     */
    protected static $currentJobs = [];

    /**
     * Start daemons
     */
    public function run()
    {
        if ($this->demonize) {
            $pid = pcntl_fork();
            ConsoleHelper::consolePrint(-1, 'Start Daemon. PID: ' . $pid, ConsoleHelper::FG_GREEN);
            if ($pid == -1) {
                ConsoleHelper::consolePrint(self::EXIT_CODE_ERROR, 'pcntl_fork() rise error');
            } elseif ($pid) {
//                $this->cleanLog();
                ConsoleHelper::consolePrint(self::EXIT_CODE_NORMAL);
            } else {
                posix_setsid();
                $this->closeStdStreams();
            }
        }
        $this->changeProcessName();
        if (!self::$stopFlag){
            $this->loop();
        }
        return true;
    }

    /**
     * Tasks runner
     *
     * @param string $job
     *
     * @return boolean
     */
    public function runDaemon($job)
    {
//            $this->trigger(self::EVENT_BEFORE_JOB);
        $status = $this->doJob($job);
//            $this->trigger(self::EVENT_AFTER_JOB);

        return $status;
    }

    /**
     * Job processing body
     *
     * @param $job array
     *
     * @return boolean
     */
    protected function doJob($job)
    {
        $pid_file = $this->getPidPath($job['name'], true);

        ConsoleHelper::consolePrint(0, 'Check daemon ' . $job['name']);
        if (file_exists($pid_file)) {
            ConsoleHelper::consolePrint(0, 'file_exists ' . $pid_file);
            $pid = file_get_contents($pid_file);
            if ($this->isProcessRunning($pid)) {
                if ($job['enabled']) {
                    ConsoleHelper::consolePrint(0, 'Daemon ' . $job['name'] . ' running and working fine');
                    return true;
                } else {
                    ConsoleHelper::consolePrint(0, 'Daemon ' . $job['name'] . ' running, but disabled in config. Send SIGTERM signal.', ConsoleHelper::BG_RED);
                    if (isset($job['hardKill']) && $job['hardKill']) {
                        posix_kill($pid, SIGKILL);
                    } else {
                        posix_kill($pid, SIGTERM);
                    }

                    return true;
                }
            }
        }
        ConsoleHelper::consolePrint(0, 'Daemon ' . $job['name'] . ' not found', ConsoleHelper::BG_RED);
        if ($job['enabled']) {
            ConsoleHelper::consolePrint(0, 'Try to run daemon ' . $job['name'] . '.', ConsoleHelper::BG_GREY);
            $command_name = $job['name'] . DIRECTORY_SEPARATOR . 'index';
            //run daemon
            $pid = pcntl_fork();
            ConsoleHelper::consolePrint(0, '$pid: ' . $pid);
            if ($pid === -1) {
                ConsoleHelper::consolePrint(self::EXIT_CODE_ERROR, 'pcntl_fork() returned error', ConsoleHelper::BG_RED);
            } elseif ($pid === 0) {
//                $this->cleanLog();
                $pidJob = file_get_contents($this->getPidPath($job['name'], true));
                $this->runJob($job, $pidJob);
                ConsoleHelper::consolePrint(0, 'Start action');
                ConsoleHelper::consolePrint(0, 'Class: ' . $job['class']);
            } else {
//                $this->initLogger();
                try{
                    if (file_put_contents($this->getPidPath($job['name'], true), $pid)) {
                        ConsoleHelper::consolePrint(0, 'Daemon ' . $job['name'] . ' is running with pid ' . $pid);
                    }else{
                        posix_kill($pid, SIGKILL);
                    }
                }catch (\Exception $exception){
                    ConsoleHelper::consolePrint(5000, $exception->getMessage(), ConsoleHelper::BG_RED);
                    posix_kill($pid, SIGKILL);
                }

            }
        }
        ConsoleHelper::consolePrint(0, 'Daemon ' . $job['name'] . ' is checked.');
        return true;
    }

    protected function runJob($job, $pidJob){
        $object = new $job['class'](array(
            'pidJob' => $pidJob,
            'pidName' => $job['name']
                ));
        $object->run();
    }

    /**
     * @return array
     */
    protected function getDaemonsList()
    {
        return $this->config->workers;
    }

    /**
     * Fetch one task from array of tasks
     *
     * @param Array
     *
     * @return mixed one task
     */
    protected function defineJobExtractor(&$jobs)
    {
        return array_shift($jobs);
    }
}