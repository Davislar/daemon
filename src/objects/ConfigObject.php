<?php

namespace Davislar\objects;


use Davislar\daemon\DaemonController;

class ConfigObject
{
    public $name = 'daemon';

    /**
     * Base controller
     * @var string
     */
    public $controller = DaemonController::class;

    /**
     * Worker list
     * @var array
     */
    public $workers = [];

    /**
     * Waiting time for the next cycle
     * Default 5 second
     * @var int
     */
    public $loop = 5;

    /**
     * @var int Memory limit for daemon, must bee less than php memory_limit
     * @default 32M
     */
    public $memoryLimit = 268435456;

    public $pidDir = null;

    public $logDir = null;


    public function __construct(array $config)
    {
        foreach ($config as $key => $value){
            $this->$key = $value;
        }
        print_r($this->loop . "\n");
    }


    /**
     * @return string
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param string $controller
     */
    public function setController($controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getWorkers()
    {
        if (count($this->workers) === 0){
            throw new \Exception('Workers was not set', 5000);
        }
        return $this->workers;
    }

    /**
     * @param array $workers
     */
    public function setWorkers(array $workers)
    {
        foreach ($workers as $worker){
            $this->workers[$worker['name']] = new WorkerConfigObject($worker);
        }
    }


}