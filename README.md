# daemon

### Install

    composer require davislar/daemon
    
### Config

         [
             'loop' => 5,
             'name' => 'WatcherDaemon',
             'pidDir' => __DIR__ . '/../runtime/daemon',
             'logDir' => __DIR__ . '/../runtime/logs',
             'workers' => [
                 [
                     'name' => 'TestDaemonJob',
                     'class' => Davislar\tests\TestDaemonJob::class,
                     'enabled' => true
                 ]
             ]
         ]

## Exceptions

Exception codes:

5xxx(Error codes):

5000 - Not set data

