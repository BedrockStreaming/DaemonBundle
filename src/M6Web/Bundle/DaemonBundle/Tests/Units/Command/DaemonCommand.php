<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Units\Command;

use mageekguy\atoum\test;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use M6Web\Bundle\DaemonBundle\DaemonEvents;

class DaemonCommand extends test
{
    protected function getCommand($eventDispatcher = null, ContainerInterface $container = null, $commandClass = 'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcrete')
    {
        $application = new Application();
        $application->add(new $commandClass());

        if (is_null($container)) {
            $container = new \mock\Symfony\Component\DependencyInjection\Container;
        }

        $command = $application->find('test:daemontest');
        $command->setContainer($container);

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

        $command = $this->getCommand($eventDispatcher, null, 'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcreteThrowStopException');

        $this
            ->if($commandTester = new CommandTester($command))
                ->then($commandTester->execute([
                            'command' => $command->getName(),
                            '--shutdown-on-exception' => true
                        ]))
                ->mock($eventDispatcher)
                    ->call('dispatch')
                        ->withArguments(DaemonEvents::DAEMON_LOOP_ITERATION)
                            ->exactly(DaemonCommandConcreteThrowStopException::MAX_ITERATION)
                ->object($command->getLastException())
                    ->isInstanceOf('M6Web\Bundle\DaemonBundle\Command\StopLoopException')
        ;
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

        $command = $this->getCommand($eventDispatcher, null, 'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcreteMaxMemory');

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

    public function testGetSetShowExceptions()
    {
        $command = $this->getCommand();
        $show    = (bool) rand(0 ,1);

        $command->setShowExceptions($show);

        $this
            ->boolean($command->getShowExceptions())
                ->isIdenticalTo($show)
        ;
    }

    public function testCommandException()
    {
        $eventDispatcher = new \mock\Symfony\Component\EventDispatcher\EventDispatcher();
        $lastEvents      = [];

        $eventDispatcher->getMockController()->dispatch = function ($name, $eventObject) use (&$lastEvents) {
            $lastEvents[$name] = $eventObject;
            return true;
        };

        $command = $this->getCommand($eventDispatcher, null,'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcreteThrowException');

        $this
            ->if($commandTester = new CommandTester($command))
                ->then($commandTester->execute([
                            'command' => $command->getName(),
                            '--shutdown-on-exception' => true,
                            '--show-exceptions' => true
                        ]))
                ->object($command->getLastException())
                    ->isInstanceOf('\Exception')
                ->output($commandTester->getDisplay())
                    ->contains(DaemonCommandConcreteThrowException::$exceptionMessage)
                    ->contains('Exception')
                ->then($commandTester->execute([
                            'command' => $command->getName(),
                            '--shutdown-on-exception' => true
                        ]))
                ->object($command->getLastException())
                    ->isInstanceOf('\Exception')
                ->output($commandTester->getDisplay())
                    ->notContains(DaemonCommandConcreteThrowException::$exceptionMessage)
                    ->notContains('Exception')
                ->object($lastEvents['console.exception'])
                    ->isInstanceOf('Symfony\Component\Console\Event\ConsoleExceptionEvent')
                ->object($lastEvents['console.exception']->getException())
                    ->isEqualTo($command->getLastException())
                ->object(end($lastEvents))
                    ->isInstanceOf('M6Web\Bundle\DaemonBundle\Event\DaemonEvent')
                ->object(end($lastEvents)->getCommand())
                    ->isInstanceOf('M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcreteThrowException')
                ->object(end($lastEvents)->getCommand()->getLastException())
                    ->isInstanceOf('Exception')
                ->string(end($lastEvents)->getCommandLastExceptionClassName())
                    ->isEqualTo('Exception')
        ;
    }

    public function testCommandEvents()
    {
        $eventDispatcher = new \mock\Symfony\Component\EventDispatcher\EventDispatcher();
        $container       = new \mock\Symfony\Component\DependencyInjection\Container;
        $command         = $this->getCommand($eventDispatcher, $container);

        $eventDispatcher
            ->getMockController()
            ->dispatch = function() { return true; };

        $container
            ->getMockController()
            ->getParameter = function() {
                return [
                    ['count' => 10, 'name' => 'event 10'],
                    ['count' => 5,  'name' => 'event 5'],
                ];
            };

        $container
            ->getMockController()
            ->hasParameter = function($id) {
                return true;
            };

        $this->if($commandTester = new CommandTester($command))
            ->then($commandTester->execute([
                        'command' => $command->getName(),
                        '--run-max' => 20
                    ]))
            ->mock($eventDispatcher)
                ->call('dispatch')
                    ->withArguments(DaemonEvents::DAEMON_LOOP_BEGIN)->once()
                    ->withArguments(DaemonEvents::DAEMON_LOOP_ITERATION)->exactly(20)
                    ->withArguments(DaemonEvents::DAEMON_LOOP_END)->once()
                    ->withArguments(DaemonEvents::DAEMON_STOP)->once()
                    ->withArguments(DaemonEvents::DAEMON_STOP)->once()
                    ->withArguments('event 10')->twice()
                    ->withArguments('event 5')->exactly(4);
    }

    public function testWithoutEventDispatcher()
    {
        $command = $this->getCommand();
        $command->setEventDispatcher(null);

        $this->if($commandTester = new CommandTester($command))
            ->then($commandTester->execute([
                'command' => $command->getName(),
                '--run-once' => true
            ]))
            ->variable($command->getEventDispatcher())
                ->isNull()
        ;
    }

    public function testInteractionCallback()
    {
        $command = $this->getCommand(null, null, 'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcreteIterationCallback');

        $this->if($commandTester = new CommandTester($command))
            ->then($commandTester->execute([
                        'command' => $command->getName(),
                        '--run-max' => 4
                    ]))
            ->integer($command->countCall)
                ->isEqualTo(0);

        $command = $this->getCommand(null, null, 'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcreteIterationCallback');

        $this->if($commandTester = new CommandTester($command))
            ->then($commandTester->execute([
                        'command' => $command->getName(),
                        '--run-max' => 19
                    ]))
            ->integer($command->countCall)
                ->isEqualTo(3);

        $command = $this->getCommand(null, null, 'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcreteIterationCallback');

        $this->if($commandTester = new CommandTester($command))
            ->then($commandTester->execute([
                        'command' => $command->getName(),
                        '--run-max' => 20
                    ]))
            ->integer($command->countCall)
                ->isEqualTo(4);

        $command = $this->getCommand(null, null, 'M6Web\Bundle\DaemonBundle\Tests\Units\Command\DaemonCommandConcreteIterationCallback');

        $this->if($commandTester = new CommandTester($command))
            ->then($commandTester->execute([
                        'command' => $command->getName(),
                        '--run-max' => 21
                    ]))
            ->integer($command->countCall)
                ->isEqualTo(4);
    }
}
