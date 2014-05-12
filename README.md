# DaemonBundle

Allows you to create daemonized commands.

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
            ->setDescription('M6 Voting Worker Status');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Iteration");

        usleep(100000);
    }
}
```

## Run command

You can run a daemonized command as any other Symfony command. DaemonCommand parent class provide additionals options :

- `--run-once` - Run the command just once
- `--loop-max` - Run the command x time
- `--memory-max` - Gracefully stop running command when given memory volume, in bytes, is reached
- `--shutdown-on-exceptions` - Ask for shutdown if an exeption is thrown

## Command events

Daemonized command trigger the following events :

- `DaemonEvents::DAEMON_START`
- `DaemonEvents::DAEMON_SETUP`
- `DaemonEvents::DAEMON_LOOP_BEGIN`
- `DaemonEvents::DAEMON_LOOP_EXCEPTION_STOP`
- `DaemonEvents::DAEMON_LOOP_EXCEPTION_GENERAL`
- `DaemonEvents::DAEMON_LOOP_MAX_MEMORY_REACHED`
- `DaemonEvents::DAEMON_LOOP_ITERATION`
- `DaemonEvents::DAEMON_LOOP_END`
- `DaemonEvents::DAEMON_STOP`