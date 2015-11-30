<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Units\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use M6Web\Bundle\DaemonBundle\Command\DaemonCommand as Base;

class DaemonCommandConcreteIterationCallback extends Base
{
    public $countCall         = 0;
    public $iterationInterval = 5;

    protected function configure()
    {
        $this->setName('test:daemontest')
             ->setDescription('command for unit test');
    }

    protected function setup(InputInterface $input, OutputInterface $output)
    {
        $this->addIterationsIntervalCallback($this->iterationInterval, [$this, 'myCallback']);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return true;
    }

    protected function myCallback(InputInterface $input, OutputInterface $output)
    {
        $this->countCall++;
    }
}