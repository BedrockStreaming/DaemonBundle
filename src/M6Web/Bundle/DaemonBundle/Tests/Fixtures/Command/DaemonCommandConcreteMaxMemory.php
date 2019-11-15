<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command;

use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DaemonCommandConcreteMaxMemory extends DaemonCommand
{
    /** @var array */
    protected $data;

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->setCode([$this, 'myFooFunction']);
    }

    protected function configure(): void
    {
        $this->setName('test:daemontest')
            ->setDescription('command for unit test');
    }

    protected function myFooFunction(InputInterface $input, OutputInterface $output): void
    {
        $this->data[] = str_repeat('foo', 100);
    }
}
