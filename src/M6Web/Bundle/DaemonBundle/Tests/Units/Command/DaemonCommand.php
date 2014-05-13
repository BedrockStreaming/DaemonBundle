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
                        '--shutdown-on-exception' => true
                    ]))
            ->mock($eventDispatcher)
            ->call('dispatch')
            ->withArguments(DaemonEvents::DAEMON_LOOP_ITERATION)->exactly(DaemonCommandConcreteThrowException::MAX_ITERATION);
    }

    public function testGetSetMaxMemory()
    {
        $command = $this->getCommand();
        $memory  = rand(100, 128000000);

        $command->setMemoryMax($memory);

        $this
            ->integer($command->getMemoryMax())
                ->isIdenticalTo($command->getmemorymax())
        ;
    }

    public function testMaxMemory()
    {
        $eventDispatcher = new \mock\Symfony\Component\EventDispatcher\EventDispatcher();
        $eventDispatcher->getMockController()->dispatch = function() { return true; };

        $command = $this->getCommand($eventDispatcher, 'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcreteMaxMemory');

        $this
            ->if($commandTester = new CommandTester($command))
                ->then($commandTester->execute([
                            'command' => $command->getName(),
                            '--memory-max' => 10000000
                        ]))
                ->mock($eventDispatcher)
                    ->call('dispatch')
                        ->withArguments(DaemonEvents::DAEMON_LOOP_MAX_MEMORY_REACHED)
                            ->once()
        ;
    }

    public function testSetCodeException()
    {
        $command = $this->getCommand();

        $this
            ->exception(function() use($command) {
                $command->setCode(null);
            })
                ->isInstanceOf('\InvalidArgumentException')
                ->hasMessage('Invalid callable provided to Command::setCode.')
        ;
    }


    /**
     * @dataProvider signalProvider
     */
    public function testHandleSignal($signal)
    {
        $command = $this->getCommand();

        $this
            ->boolean($command->isShutdownRequested())
                ->isFalse()
        ;

        $command->handleSignal($signal);

        $this
            ->boolean($command->isShutdownRequested())
                ->isTrue()
        ;
    }

    public function signalProvider()
    {
        return [SIGINT, SIGTERM];
    }

    public function testGetSetShutdownOnException()
    {
        $command  = $this->getCommand();
        $shutdown = (bool) rand(0 ,1);

        $command->setShutdownOnException($shutdown);

        $this
            ->boolean($command->getShutdownOnException())
                ->isIdenticalTo($shutdown)
        ;
    }
}