<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command;

use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DaemonCommandConcreteIterationCallback extends DaemonCommand
{
    public int $countCall = 0;
    public int $iterationInterval = 5;

    protected function configure(): void
    {
        $this
            ->setName('test:daemontest')
            ->setDescription('command for unit test');
    }

    protected function setup(InputInterface $input, OutputInterface $output): void
    {
        $this->addIterationsIntervalCallback($this->iterationInterval, [$this, 'myCallback']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }

    protected function myCallback(InputInterface $input, OutputInterface $output): void
    {
        $this->countCall++;
    }
}
