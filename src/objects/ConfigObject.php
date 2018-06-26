<?php

namespace Davislar\objects;


use Davislar\daemon\DaemonController;

class ConfigObject
{
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