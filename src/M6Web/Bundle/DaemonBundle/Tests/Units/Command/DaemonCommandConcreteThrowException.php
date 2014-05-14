<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Units\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use M6Web\Bundle\DaemonBundle\Command\DaemonCommand as Base;
use M6Web\Bundle\DaemonBundle\Command\StopLoopException as StopLoopExceptionBase;

class DaemonCommandConcreteThrowException extends Base
{

    public static $exceptionMessage = null;

    protected function configure()
    {
        $this->setName('test:daemontest')
            ->setDescription('command for unit test');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::$exceptionMessage = (string) uniqid();

        throw new \Exception(self::$exceptionMessage);
    }
}