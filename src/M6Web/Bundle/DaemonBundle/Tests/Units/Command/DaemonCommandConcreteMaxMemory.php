<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Units\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use M6Web\Bundle\DaemonBundle\Command\DaemonCommand as Base;

class DaemonCommandConcreteMaxMemory extends Base
{
    protected $data;

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->setCode([$this, 'myFooFunction']);
    }

    protected function configure()
    {
        $this->setName('test:daemontest')
            ->setDescription('command for unit test');
    }

    protected function myFooFunction(InputInterface $input, OutputInterface $output)
    {
        $this->data[] = str_repeat('foo', 100);

        return true;
    }
}
