<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Units\Command;

use mageekguy\atoum\test;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use M6Web\Bundle\DaemonBundle\DaemonEvents;

class DaemonCommand extends test
{

    protected function getCommand($eventDispatcher = null, $commandClass = 'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcrete')
    {
        $application = new Application();
        $application->add(new $commandClass());
        $command = $application->find('test:daemontest');

        if (!is_null($eventDispatcher)) {
            $command->setEventDispatcher($eventDispatcher);
        }

        return $command;
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

    /**
     * @tags events
     */
    public function testRunOnce()
    {
        $eventDispatcher = new \mock\Symfony\Component\EventDispatcher\EventDispatcher();
        $eventDispatcher->getMockController()->dispatch = function() { return true; };
        $command = $this->getCommand($eventDispatcher);

        $this->if($commandTester = new CommandTester($command))
            ->then($commandTester->execute([
                        'command' => $command->getName(),
                        '--run-once' => true
                    ]))
            ->mock($eventDispatcher)
                ->call('dispatch')
                    ->withArguments(DaemonEvents::DAEMON_SETUP)->once()
                    ->withArguments(DaemonEvents::DAEMON_LOOP_BEGIN)->once()
                    ->withArguments(DaemonEvents::DAEMON_LOOP_ITERATION)->once()
                    ->withArguments(DaemonEvents::DAEMON_LOOP_END)->once()
                    ->withArguments(DaemonEvents::DAEMON_STOP)->once();
    }

    /**
     * @tags events
     */
    public function testMaxLoop()
    {
        $eventDispatcher = new \mock\Symfony\Component\EventDispatcher\EventDispatcher();
        $eventDispatcher->getMockController()->dispatch = function() { return true; };
        $command = $this->getCommand($eventDispatcher);

        $this->if($commandTester = new CommandTester($command))
            ->then($commandTester->execute([
                        'command' => $command->getName(),
                        '--run-max' => 7
                    ]))
            ->mock($eventDispatcher)
            ->call('dispatch')
            ->withArguments(DaemonEvents::DAEMON_LOOP_ITERATION)->exactly(7);

        $eventDispatcher = new \mock\Symfony\Component\EventDispatcher\EventDispatcher();
        $eventDispatcher->getMockController()->dispatch = function() { return true; };
        $command = $this->getCommand($eventDispatcher);
        $this->if($commandTester = new CommandTester($command))
            ->then($commandTester->execute([
                        'command' => $command->getName(),
                        '--run-max' => 1
                    ]))
            ->mock($eventDispatcher)
            ->call('dispatch')
            ->withArguments(DaemonEvents::DAEMON_LOOP_ITERATION)->exactly(1);
    }

    /**
     * @tags exception
     */
    public function testStopLoopException()
    {
        $eventDispatcher = new \mock\Symfony\Component\EventDispatcher\EventDispatcher();
        $eventDispatcher->getMockController()->dispatch = function() { return true; };
        $command = $this->getCommand($eventDispatcher, 'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcreteThrowException');
        $this->if($commandTester = new CommandTester($command))
            ->then($commandTester->execute([
                        'command' => $command->getName(),
                        '--shutdown-on-exceptions' => true
                    ]))
            ->mock($eventDispatcher)
            ->call('dispatch')
            ->withArguments(DaemonEvents::DAEMON_LOOP_ITERATION)->exactly(DaemonCommandConcreteThrowException::MAX_ITERATION);
    }

} 