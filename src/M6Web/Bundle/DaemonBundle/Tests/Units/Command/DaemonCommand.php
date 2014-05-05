<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Units\Command;

require_once __DIR__.'/../../../../../../../vendor/autoload.php';

use mageekguy\atoum\test;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DaemonCommand extends test
{

    protected function getCommand()
    {
        $application = new Application();
        $application->add(new DaemonCommandConcrete());

        return $application->find('test:daemontest');

//        $commandTester = new CommandTester($command);
//        $commandTester->execute(array('command' => $command->getName()));
    }

    public function testLoopCount()
    {
        $command = $this->getCommand();

        $this->if($command->incrLoopCount())
            ->then()
            ->integer($command->getLoopCount())
            ->isEqualTo(1);

        $this->if($command->setLoopCount(3))
            ->then()
            ->integer($command->getLoopCount())
            ->isEqualTo(3);
    }

    public function testShutdownRequest()
    {
        $command = $this->getCommand();

        $this->if()
            ->then()
            ->boolean($command->isShutdownRequested())
            ->isEqualTo(false);

        $this->if($command->requestShutdown())
            ->then()
            ->boolean($command->isShutdownRequested())
            ->isEqualTo(true);
    }

} 