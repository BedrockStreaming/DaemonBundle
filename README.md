<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->
**Table of Contents**

- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Run command](#run-command)
- [Command events](#command-events)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

# DaemonBundle [![Build Status](https://travis-ci.org/M6Web/DaemonBundle.svg?branch=master)](https://travis-ci.org/M6Web/DaemonBundle)  
  
Allows you to create daemonized commands with the [React event-loop component](https://github.com/reactphp/event-loop).  


## Installation  

Via composer :  
  
```bash
composer require m6web/daemon-bundle
```
  
Then enable the bundle in your `config/bundles.php`:  
  
```php
<?php
  
return [  
# ... Other bundles declarations
M6Web\Bundle\DaemonBundle\M6WebDaemonBundle::class => ['all' => true],
];
```  
  
Note:   
- If you are using a symfony version `>= 4.3` use the lastest version
- For symfony versions between `2.3` and `3.0`, you can use `m6web/daemon-bundle:^1.4`
- For PHP versions `>=5.5.9` and `<7.0` support, you can use `m6web/daemon-bundle:^3.0`

*For more information about installation of plugin refers the documentation of symfony for your version.*
     
## Configuration  
  
You can optionally define events which are triggered each X iterations :  
  
```yaml
m6_web_daemon:  
    iterations_events: 
        - 
            count: 10 
            name: Path\From\Your\Project\Event\EachTenEvent
        -
            count: 5 
            name: Path\From\Your\Project\Event\EachFiveEvent
```

Your event need to extends the AbstractDaemonEvent like following:
```php
<?php

namespace Path\From\Your\Project\Event;

use M6Web\Bundle\DaemonBundle\Event\AbstractDaemonEvent;

class EachFiveEvent extends AbstractDaemonEvent
{
}
```

This bundle use the PSR-14 implementation for event dispatcher so you need to refer the symfony event dispatcher in your `config/services.yaml` like this:
```yaml
# config/services.yaml
seervices:
    # ... others declarations

    Psr\EventDispatcher\EventDispatcherInterface: "@event_dispatcher"
```

## Usage

This command use the [event-loop component](https://github.com/reactphp/event-loop#usage) which [ReactPHP](https://reactphp.org) uses to run loops and other asynchronous tasks.

```php
<?php

use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DaemonizedCommand extends DaemonCommand
{
    protected function configure()
    {
        $this
            ->setName('daemon:command')
            ->setDescription('My daemonized command');
    }

    protected function setup(InputInterface $input, OutputInterface $output)
    {
        // Set up your daemon here

        // Add your own optional callback : called every 10 iterations
        $this->addIterationsIntervalCallback(10, [$this, 'executeEveryTenLoops']);
        
        // You can add your own Periodic Timer,
        // Here this timer will be called every 0.5s
        $daemon = $this;
        $this->loop->addPeriodicTimer(0.5, function ($timer) use ($daemon) {
            // It's the last loop, cancel the timer.
            if ($daemon->isLastLoop()) {
                $daemon->loop->cancelTimer($timer);
            }
        });
    }

    /**
     * Execute is called at every loop
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Iteration");
        
        // This method helps to give back the CPU to the react-loop.
        // So you can wait between two iterations if your workers has nothing to do.
        
        $this->setNextIterationSleepingTime(1000000); // Every second
    }

    /**
     * executeEveryTenLoops is called every 10 loops
     */
    protected function executeEveryTenLoops(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Iteration " . $this->getLoopCount());
    }
}
```

You also need to declare your command under the services:

```yaml
# config/services
services:
    # ... others declarations

    App\Command\AcmeCommand:
        parent: M6Web\Bundle\DaemonBundle\Command\DaemonCommand
        tags:
            - console.command
```

*For information, you need to declare the `autowire` and `autoconfigure` parameters (to `false`) only if you have defaults parameters for services (under `_default`)*

## Run command

You can run a daemonized command as any other Symfony command with `bin/console`. DaemonCommand parent class provide additional options :

- `--run-once` - Run the command just once
- `--run-max` - Run the command x time
- `--memory-max` - Gracefully stop running command when given memory volume, in bytes, is reached
- `--shutdown-on-exception` - Ask for shutdown if an exception is thrown
- `--show-exceptions` - Display exceptions on command output stream

## Command events

Daemonized command trigger the following events :

- `DaemonEvents::DAEMON_START`
- `DaemonEvents::DAEMON_LOOP_BEGIN`
- `DaemonEvents::DAEMON_LOOP_EXCEPTION_STOP`
- `DaemonEvents::DAEMON_LOOP_EXCEPTION_GENERAL`
- `DaemonEvents::DAEMON_LOOP_MAX_MEMORY_REACHED`
- `DaemonEvents::DAEMON_LOOP_ITERATION`
- `DaemonEvents::DAEMON_LOOP_END`
- `DaemonEvents::DAEMON_STOP`
