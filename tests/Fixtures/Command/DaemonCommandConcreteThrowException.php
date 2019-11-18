<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command;

use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DaemonCommandConcreteThrowException extends DaemonCommand
{
    public static $exceptionMessage = null;

    protected function configure(): void
    {
        $this
            ->setName('test:daemontest')
            ->setDescription('command for unit test');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        self::$exceptionMessage = (string) uniqid(mt_rand(), true);

        throw new \Exception(self::$exceptionMessage);
    }
}
