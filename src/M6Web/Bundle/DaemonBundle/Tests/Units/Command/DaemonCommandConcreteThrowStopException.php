<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Units\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use M6Web\Bundle\DaemonBundle\Command\DaemonCommand as Base;
use M6Web\Bundle\DaemonBundle\Command\StopLoopException as StopLoopExceptionBase;

class DaemonCommandConcreteThrowStopException extends Base
{

    public static $count = 0;

    const MAX_ITERATION = 7;

    protected function configure()
    {
        $this->setName('test:daemontest')
            ->setDescription('command for unit test');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::$count++;
        if (self::$count >= self::MAX_ITERATION) {
            throw new StopLoopExceptionBase();
        }
        return true;
    }
}