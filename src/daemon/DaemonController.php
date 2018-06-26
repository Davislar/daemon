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
    const EXIT_CODE_NORMAL = 0;
    const EXIT_CODE_ERROR = 1;
    /**
     * @var ConfigObject
     */
    protected $config;

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
     * @var $currentJobs [] array of running instances
     */
    protected static $currentJobs = [];

    /**
     * DaemonController constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->demonize = !is_null(APP_DEM_ENV) ? APP_DEM_ENV : false;
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
    public function run()
    {
        if ($this->demonize) {
            $pid = pcntl_fork();
            $this->halt(-1, 'Start Daemon. PID: ' . $pid, ConsoleHelper::FG_GREEN);
            if ($pid == -1) {
                $this->halt(self::EXIT_CODE_ERROR, 'pcntl_fork() rise error');
            } elseif ($pid) {
//                $this->cleanLog();
                $this->halt(self::EXIT_CODE_NORMAL);
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

//        while (!self::$stopFlag) {
//            if (memory_get_usage() > $this->memoryLimit) {
//                break;
//            }
//            $this->halt(-1, 'Start');
//            pcntl_signal_dispatch();
//            $this->loop();
//        }
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

    /**
     * @param $code int -1|0|1
     * @param $message string
     * @param int $color
     */
    protected function halt($code, $message = null, $color = ConsoleHelper::FG_GREEN)
    {
        if ($message !== null) {
            $message = 'Code: ' . $code . "\n" . ConsoleHelper::ansiFormat($message, [$color]) . "\n";
            ConsoleHelper::stdout($message);
        }
    }

    protected function loop(){
        if (file_put_contents($this->getPidPath(), getmypid())) {
            $this->parentPID = getmypid();
//            \Yii::trace('Daemon ' . $this->getProcessName() . ' pid ' . getmypid() . ' started.');
            while (!self::$stopFlag) {
                $this->halt(0, 'Memory use: ' . memory_get_usage());
                $this->halt(0, 'Memory at system: ' . memory_get_usage(true));
                if (memory_get_usage() > $this->config->memoryLimit) {
                    $this->halt(5000, 'Daemon ' . $this->getProcessName() . ' pid ' .
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
//                        if ($this->isMultiInstance && (count(static::$currentJobs) >= $this->maxChildProcesses)) {
//                            \Yii::trace('Reached maximum number of child processes. Waiting...');
//                            while (count(static::$currentJobs) >= $this->maxChildProcesses) {
//                                sleep(1);
//                                pcntl_signal_dispatch();
//                            }
//                            \Yii::trace(
//                                'Free workers found: ' .
//                                ($this->maxChildProcesses - count(static::$currentJobs)) .
//                                ' worker(s). Delegate tasks.'
//                            );
//                        }
                        pcntl_signal_dispatch();
                        $this->runDaemon($job);
                    }
                } else {
                    sleep($this->config->loop);
                }
                pcntl_signal_dispatch();
//                $this->trigger(self::EVENT_AFTER_ITERATION);
            }

//            \Yii::info('Daemon ' . $this->getProcessName() . ' pid ' . getmypid() . ' is stopped.');

            return self::EXIT_CODE_NORMAL;
        }
        $this->halt(self::EXIT_CODE_ERROR, 'Can\'t create pid file ' . $this->getPidPath(), ConsoleHelper::BG_RED);
//        sleep($this->config->loop);
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

    /**
     * Tasks runner
     *
     * @param string $job
     *
     * @return boolean
     */
    final public function runDaemon($job)
    {
//        if ($this->isMultiInstance) {
//            $this->flushLog();
//            $pid = pcntl_fork();
//            if ($pid == -1) {
//                return false;
//            } elseif ($pid !== 0) {
//                static::$currentJobs[$pid] = true;
//
//                return true;
//            } else {
//                $this->cleanLog();
//                $this->renewConnections();
//                //child process must die
//                $this->trigger(self::EVENT_BEFORE_JOB);
//                $status = $this->doJob($job);
//                $this->trigger(self::EVENT_AFTER_JOB);
//                if ($status) {
//                    $this->halt(self::EXIT_CODE_NORMAL);
//                } else {
//                    $this->halt(self::EXIT_CODE_ERROR, 'Child process #' . $pid . ' return error.');
//                }
//            }
//        } else {
//            $this->trigger(self::EVENT_BEFORE_JOB);
            $status = $this->doJob($job);
//            $this->trigger(self::EVENT_AFTER_JOB);

            return $status;
//        }
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

//        \Yii::trace('Check daemon ' . $job['daemon']);
        $this->halt(0, 'Check daemon ' . $job['name']);
        if (file_exists($pid_file)) {
            $this->halt(0, 'file_exists ' . $pid_file);
            $pid = file_get_contents($pid_file);
            if ($this->isProcessRunning($pid)) {
                if ($job['enabled']) {
//                    \Yii::trace('Daemon ' . $job['name'] . ' running and working fine');
                    $this->halt(0, 'Daemon ' . $job['name'] . ' running and working fine');
                    return true;
                } else {
//                    \Yii::warning('Daemon ' . $job['daemon'] . ' running, but disabled in config. Send SIGTERM signal.');
                    $this->halt(0, 'Daemon ' . $job['name'] . ' running, but disabled in config. Send SIGTERM signal.', ConsoleHelper::BG_RED);
                    if (isset($job['hardKill']) && $job['hardKill']) {
                        posix_kill($pid, SIGKILL);
                    } else {
                        posix_kill($pid, SIGTERM);
                    }

                    return true;
                }
            }
        }
//        \Yii::error('Daemon pid not found.');
        $this->halt(0, 'Daemon ' . $job['name'] . ' not found', ConsoleHelper::BG_RED);
        if ($job['enabled']) {
//            \Yii::trace('Try to run daemon ' . $job['daemon'] . '.');
            $this->halt(0, 'Try to run daemon ' . $job['name'] . '.', ConsoleHelper::BG_GREY);
            $command_name = $job['name'] . DIRECTORY_SEPARATOR . 'index';
            //flush log before fork
//            $this->flushLog(true);
            //run daemon
            $pid = pcntl_fork();
            $this->halt(0, '$pid: ' . $pid);
            if ($pid === -1) {
                $this->halt(self::EXIT_CODE_ERROR, 'pcntl_fork() returned error', ConsoleHelper::BG_RED);
            } elseif ($pid === 0) {
//                $this->cleanLog();
//                \Yii::$app->requestedRoute = $command_name;
//                \Yii::$app->runAction("$command_name", ['demonize' => 1]);
                $pidJob = file_get_contents($this->getPidPath($job['name'], true));
                $this->runJob($job, $pidJob);
                $this->halt(0, 'Start action');
                $this->halt(0, 'Class: ' . $job['class']);
            } else {
//                $this->initLogger();
//                \Yii::trace('Daemon ' . $job['daemon'] . ' is running with pid ' . $pid);
                try{
                    if (file_put_contents($this->getPidPath($job['name'], true), $pid)) {
                        $this->halt(0, 'Daemon ' . $job['name'] . ' is running with pid ' . $pid);
                    }else{
                        posix_kill($pid, SIGKILL);
                    }
                }catch (\Exception $exception){
                    $this->halt(5000, $exception->getMessage(), ConsoleHelper::BG_RED);
                    posix_kill($pid, SIGKILL);
                }

            }
        }
//        \Yii::trace('Daemon ' . $job['name'] . ' is checked.');
        $this->halt(0, 'Daemon ' . $job['name'] . ' is checked.');

        return true;
    }

    protected function runJob($job, $pidJob){
        $object = new $job['class']([
            'pidJob' => $pidJob,
            'pidName' => $job['name'],
        ]);
        $object->run();
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
                $this->halt(5000, 'Can\'t find cli_set_process_title or setproctitle function', ConsoleHelper::BG_RED);
            }
        }
    }
}