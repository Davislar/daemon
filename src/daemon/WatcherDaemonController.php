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
            ConsoleHelper::consolePrint(-1, 'Start WatcherDaemon. PID: ' . $pid, ConsoleHelper::FG_GREEN);
            if ($pid == -1) {
                ConsoleHelper::consolePrint(self::EXIT_CODE_ERROR, 'pcntl_fork() rise error');
            } elseif ($pid) {
                ConsoleHelper::consolePrint(self::EXIT_CODE_NORMAL);
            } else {
                posix_setsid();
                $this->closeStdStreams();
            }
        }
        $this->changeProcessName();
        pcntl_signal_dispatch();

        $this->loop();

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
        $this->trigger(self::EVENT_BEFORE_JOB);
        $status = $this->doJob($job);
        $this->trigger(self::EVENT_AFTER_JOB);

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
        if ($this->checkOnProcess($job)) {
            return true;
        }

        ConsoleHelper::consolePrint(0, 'Daemon ' . $job['name'] . ' not found', ConsoleHelper::BG_RED);
        if ($job['enabled']) {

            $this->startDaemonProcess($job);

        }
        ConsoleHelper::consolePrint(0, 'Daemon ' . $job['name'] . ' is checked.');
        return true;
    }

    protected function startDaemonProcess($job)
    {
        ConsoleHelper::consolePrint(0, 'Try to run daemon ' . $job['name'] . '.', ConsoleHelper::BG_GREY);
        //run daemon
        $pid = pcntl_fork();

        if ($pid === -1) {
            ConsoleHelper::consolePrint(self::EXIT_CODE_ERROR, 'pcntl_fork() returned error', ConsoleHelper::BG_RED);
        } elseif ($pid === 0) {
            $pidJob = file_get_contents($this->getPidPath($job['name'], true));
            ConsoleHelper::consolePrint(0, "Job {$job['name']} start with PID: " . $pid, ConsoleHelper::BG_GREY);
            $this->runJob($job, $pidJob);
        } else {
            try {
                if (file_put_contents($this->getPidPath($job['name'], true), $pid)) {
                    ConsoleHelper::consolePrint(0, 'Daemon ' . $job['name'] . ' is running with pid ' . $pid);
                } else {
                    $this->stopProcessByPID($pid, SIGKILL);
                }
            } catch (\Exception $exception) {
                ConsoleHelper::consolePrint(5000, $exception->getMessage(), ConsoleHelper::BG_RED);
                $this->stopProcessByPID($pid, SIGKILL);
            }

        }
    }

    /**
     * Check process of job
     *
     * @param $job
     * @return bool
     */
    protected function checkOnProcess($job)
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

                    $this->stopDaemonProcess($job, $pid);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Stop process by PID
     *
     * @param $job
     * @param $pid
     */
    protected function stopDaemonProcess($job, $pid)
    {
        ConsoleHelper::consolePrint(0, 'Daemon ' . $job['name'] . ' running, but disabled in config. Send SIGTERM signal.', ConsoleHelper::BG_RED);
        if (isset($job['hardKill']) && $job['hardKill']) {
            $this->stopProcessByPID($pid, SIGKILL);
        } else {
            $this->stopProcessByPID($pid, SIGTERM);
        }

    }

    /**
     * @param $job
     * @param $pidJob
     */
    protected function runJob($job, $pidJob)
    {
        $object = new $job['class']([
            'pidJob' => $pidJob,
            'pidName' => $job['name']
        ]);
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
     * Stop process by system PID
     *
     * @param $pid
     * @param $signal
     */
    protected function stopProcessByPID($pid, $signal)
    {
        posix_kill($pid, $signal);
        pcntl_signal_dispatch();
    }
}