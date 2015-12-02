# DaemonBundle [![Build Status](https://travis-ci.org/M6Web/DaemonBundle.svg?branch=master)](https://travis-ci.org/M6Web/DaemonBundle)

Allows you to create daemonized commands.

## Installation

Via composer :

```json
"require": {
    "m6web/daemon-bundle":"1.1.*"
}
```

then enable the bundle in your kernel:

```php
<?php

$bundles = [
    new M6Web\Bundle\DaemonBundle\M6WebDaemonBundle
];
```

## Configuration

You can optionnaly define events which are triggered each X iterations :

```yml
m6_web_daemon:
    iterations_events:
        -
            count: 10
            name: "daemon.iteration.each.10"
        -
            count: 5
            name: "daemon.iteration.each.5"
```

## Write command

```php
<?php

use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyDaemonizedCommand extends DaemonCommand
{
    protected function configure()
    {
        $this
            ->setName('company:my-daemonized-command')
            ->setDescription('My daemonized command');
    }

    protected function setup(InputInterface $input, OutputInterface $output)
    {
        // Set up your daemon here

        // Add your own optional callback : called every 10 iterations
        $this->addIterationsIntervalCallback(10, [$this, 'executeEveryTenLoops']);
    }

    /**
     * Execute is called at every loop
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Iteration");

        usleep(100000);
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

## Run command

You can run a daemonized command as any other Symfony command. DaemonCommand parent class provide additional options :

- `--run-once` - Run the command just once
- `--run-max` - Run the command x time
- `--memory-max` - Gracefully stop running command when given memory volume, in bytes, is reached
- `--shutdown-on-exception` - Ask for shutdown if an exeption is thrown
- `--show-exceptions` - Display excepions on command output stream

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