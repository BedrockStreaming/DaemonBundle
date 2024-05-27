<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Unit\Command;

use Exception;
use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;
use M6Web\Bundle\DaemonBundle\Command\StopLoopException;
use M6Web\Bundle\DaemonBundle\Event\AbstractDaemonEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopBeginEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopEndEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopExceptionStopEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopIterationEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopMaxMemoryReachedEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonStartEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonStopEvent;
use M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command\DaemonCommandConcrete;
use M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command\DaemonCommandConcreteIterationCallback;
use M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command\DaemonCommandConcreteMaxMemory;
use M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command\DaemonCommandConcreteThrowException;
use M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command\DaemonCommandConcreteThrowStopException;
use M6Web\Bundle\DaemonBundle\Tests\Fixtures\Event\EachFiveEvent;
use M6Web\Bundle\DaemonBundle\Tests\Fixtures\Event\EachTenEvent;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DaemonCommandTest extends TestCase
{
    protected ?EventDispatcherInterface $eventDispatcher;

    protected ?LoopInterface $loop;

    public function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->loop = Loop::get();
    }

    public function tearDown(): void
    {
        $this->eventDispatcher = null;
        $this->loop = null;
    }

    private function createDaemonCommand(
        string $class = DaemonCommandConcrete::class,
        ?EventDispatcherInterface $eventDispatcher = null,
        array $iterationEvents = []
    ): DaemonCommand {
        /** @var DaemonCommand $command */
        $command = new $class();

        $command
            ->setEventDispatcher($eventDispatcher ?? $this->eventDispatcher)
            ->setLoop($this->loop)
            ->setIterationsEvents($iterationEvents);

        return $command;
    }

    public function testLoopCountWithIncrLoopCount(): void
    {
        // Given
        $command = $this->createDaemonCommand();

        // When
        $command->incrLoopCount();

        // Then
        $this->assertIsInt($command->getLoopCount());
        $this->assertEquals(1, $command->getLoopCount());
    }

    public function testLoopCountWithSetLoopCount(): void
    {
        // Given
        $command = $this->createDaemonCommand();

        // When
        $command->setLoopCount(3);

        // Then
        $this->assertIsInt($command->getLoopCount());
        $this->assertEquals(3, $command->getLoopCount());
    }

    public function testShutdownRequest(): void
    {
        // Given
        $command = $this->createDaemonCommand();

        // With
        $this->assertIsBool($command->isShutdownRequested());
        $this->assertFalse($command->isShutdownRequested());

        // When
        $command->requestShutdown();

        // Then
        $this->assertIsBool($command->isShutdownRequested());
        $this->assertTrue($command->isShutdownRequested());
    }

    /**
     * @group events
     */
    public function testRunOnce(): void
    {
        // Given
        $command = $this->createDaemonCommand();
        $commandTester = new CommandTester($command);

        // Expect
        $this->eventDispatcher
            ->expects($this->exactly(5))
            ->method('dispatch')
            ->withConsecutive(
                [
                    $this->isInstanceOf(DaemonStartEvent::class),
                ],
                [
                    $this->isInstanceOf(DaemonLoopBeginEvent::class),
                ],
                [
                    $this->isInstanceOf(DaemonLoopIterationEvent::class),
                ],
                [
                    $this->isInstanceOf(DaemonLoopEndEvent::class),
                ],
                [
                    $this->isInstanceOf(DaemonStopEvent::class),
                ]
            );

        // When
        $commandTester->execute(
            [
                '--run-once' => true,
            ]
        );
    }

    /**
     * @group events
     */
    public function testMaxLoop(): void
    {
        // Given
        $command = $this->createDaemonCommand();
        $commandTester = new CommandTester($command);

        // Expect
        $this->eventDispatcher
            ->expects($this->atLeast(7))
            ->method('dispatch');

        $this->eventDispatcher
            ->expects($this->at(2))
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(DaemonLoopIterationEvent::class)
            );

        $this->eventDispatcher
            ->expects($this->at(8))
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(DaemonLoopIterationEvent::class)
            );

        // When
        $commandTester->execute(
            [
                '--run-max' => 7,
            ]
        );
    }

    /**
     * @group exception
     */
    public function testStopLoopException(): void
    {
        // Given
        $command = $this->createDaemonCommand(DaemonCommandConcreteThrowStopException::class);
        $commandTester = new CommandTester($command);

        // Expect
        $this->eventDispatcher
            ->expects($this->atLeast(DaemonCommandConcreteThrowStopException::MAX_ITERATION))
            ->method('dispatch');

        $this->eventDispatcher
            ->expects($this->at(DaemonCommandConcreteThrowStopException::MAX_ITERATION + 1))
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(DaemonLoopExceptionStopEvent::class)
            );

        // When
        $commandTester->execute(
            [
                '--shutdown-on-exception' => true,
            ]
        );

        // Then
        $this->assertInstanceOf(StopLoopException::class, $exception = $command->getLastException());
        $this->assertEquals(DaemonCommandConcreteThrowStopException::EXCEPTION_MESSAGE, $exception->getMessage());
    }

    /**
     * @throws Exception
     */
    public function testGetSetMaxMemory(): void
    {
        // Given
        $command = $this->createDaemonCommand(DaemonCommandConcreteThrowStopException::class);
        $memory = random_int(100, 128000000);
        $command->setMemoryMax($memory);

        // Then
        $this->assertEquals($memory, $command->getMemoryMax());
    }

    public function testMaxMemory(): void
    {
        // Given
        $command = $this->createDaemonCommand(DaemonCommandConcreteMaxMemory::class);
        $commandTester = new CommandTester($command);
        $eventTypes = [];

        // Expect
        $this->eventDispatcher
            ->expects($this->atLeast(1))
            ->method('dispatch')
            ->with(
                $this->callback(
                    static function (AbstractDaemonEvent $event) use (&$eventTypes) {
                        $eventTypes[get_class($event)] = get_class($event);

                        return true;
                    })
            );

        // When
        $commandTester->execute(
            [
                //                '--memory-max' => 10000000
                '--memory-max' => 1,
            ]
        );

        // Then
        $this->assertArrayHasKey(DaemonLoopMaxMemoryReachedEvent::class, $eventTypes);
    }

    /**
     * @dataProvider getTestHandleSignalProvider
     */
    public function testHandleSignal(int $signal): void
    {
        // Given
        $command = $this->createDaemonCommand();
        $this->assertFalse($command->isShutdownRequested());

        // When
        $command->handleSignal($signal);

        // Then
        $this->assertTrue($command->isShutdownRequested());
    }

    public function getTestHandleSignalProvider(): array
    {
        return [
            [SIGINT],
            [SIGTERM],
        ];
    }

    /**
     * @throws Exception
     */
    public function testGetSetShutdownOnException(): void
    {
        // Given
        $command = $this->createDaemonCommand();
        $shutdown = (bool) random_int(0, 1);

        // When
        $command->setShutdownOnException($shutdown);

        // Then
        $this->assertIsBool($command->getShutdownOnException());
        $this->assertEquals($shutdown, $command->getShutdownOnException());
    }

    /**
     * @throws Exception
     */
    public function testGetSetShowExceptions(): void
    {
        // Given
        $command = $this->createDaemonCommand();
        $show = (bool) random_int(0, 1);

        // When
        $command->setShowExceptions($show);

        // Then
        $this->assertIsBool($command->getShowExceptions());
        $this->assertEquals($show, $command->getShowExceptions());
    }

    public function testCommandExceptionWithShowException(): void
    {
        // Given
        $command = $this->createDaemonCommand(DaemonCommandConcreteThrowException::class);
        $command->setApplication(new Application());
        $commandTester = new CommandTester($command);

        // Expect
        $this->eventDispatcher
            ->expects($this->atLeast(1))
            ->method('dispatch');

        // When
        $commandTester->execute(
            [
                '--shutdown-on-exception' => true,
                '--show-exceptions' => true,
            ]
        );

        // Then
        $this->assertInstanceOf(\Exception::class, $command->getLastException());
        $this->assertStringContainsString(DaemonCommandConcreteThrowException::$exceptionMessage, $commandTester->getDisplay());
        $this->assertStringContainsString('Exception', $commandTester->getDisplay());
    }

    public function testCommandExceptionWithoutShowException(): void
    {
        // Given
        $command = $this->createDaemonCommand(DaemonCommandConcreteThrowException::class);
        $command->setApplication(new Application());
        $commandTester = new CommandTester($command);
        /** @var AbstractDaemonEvent|null $lastEvent */
        $lastEvent = null;

        // Expect
        $this->eventDispatcher
            ->expects($this->atLeast(1))
            ->method('dispatch')
            ->willReturnCallback(
                static function ($eventObject) use (&$lastEvent) {
                    $lastEvent = $eventObject;

                    return true;
                }
            );

        // When
        $commandTester->execute(
            [
                '--shutdown-on-exception' => true,
            ]
        );

        // Then
        $this->assertInstanceOf(\Exception::class, $command->getLastException());
        $this->assertStringNotContainsString(DaemonCommandConcreteThrowException::$exceptionMessage, $commandTester->getDisplay());
        $this->assertStringNotContainsString('Exception', $commandTester->getDisplay());

        $this->assertInstanceOf(AbstractDaemonEvent::class, $lastEvent);
        $this->assertInstanceOf(DaemonCommandConcreteThrowException::class, $lastEvent->getCommand());
        $this->assertInstanceOf(\Exception::class, $lastEvent->getCommand()->getLastException());
        $this->assertEquals('Exception', $lastEvent->getCommandLastExceptionClassName());
    }

    public function testCommandEvents(): void
    {
        // Given
        $command = $this->createDaemonCommand(
            DaemonCommandConcrete::class,
            null,
            [
                ['count' => 10, 'name' => EachTenEvent::class],
                ['count' => 5, 'name' => EachFiveEvent::class],
            ]
        );
        $commandTester = new CommandTester($command);
        $stack = [];

        // Expect
        $this->eventDispatcher
            ->expects($this->atLeast(1))
            ->method('dispatch')
            ->with(
                $this->callback(
                    static function (AbstractDaemonEvent $event) use (&$stack) {
                        $stack[] = get_class($event);

                        return true;
                    })
            );

        // When
        $commandTester->execute(
            [
                '--run-max' => 20,
            ]
        );

        // Then
        $events = array_count_values($stack);
        $this->assertArrayHasKey(DaemonLoopBeginEvent::class, $events);

        $this->assertArrayHasKey(DaemonLoopIterationEvent::class, $events);
        $this->assertEquals(20, $events[DaemonLoopIterationEvent::class]);

        $this->assertArrayHasKey(DaemonLoopEndEvent::class, $events);
        $this->assertArrayHasKey(DaemonStopEvent::class, $events);

        $this->assertArrayHasKey(EachFiveEvent::class, $events);
        $this->assertEquals(4, $events[EachFiveEvent::class]);

        $this->assertArrayHasKey(EachTenEvent::class, $events);
        $this->assertEquals(2, $events[EachTenEvent::class]);
    }

    public function testWithoutEventDispatcher(): void
    {
        // Given
        $command = $this->createDaemonCommand();
        $command->setEventDispatcher(null);
        $commandTester = new CommandTester($command);

        // When
        $commandTester->execute(
            [
                '--run-once' => true,
            ]
        );

        // Then
        $this->assertNull($command->getEventDispatcher());
    }

    /** @dataProvider getTestInteractionCallbackProvider */
    public function testInteractionCallback(int $runMax, int $countCall): void
    {
        // Given
        /** @var DaemonCommandConcreteIterationCallback $command */
        $command = $this->createDaemonCommand(DaemonCommandConcreteIterationCallback::class);
        $command->setEventDispatcher(null);
        $commandTester = new CommandTester($command);

        // When
        $commandTester->execute(
            [
                '--run-max' => $runMax,
            ]
        );

        // Then
        $this->assertIsInt($command->countCall);
        $this->assertEquals($countCall, $command->countCall);
    }

    public function getTestInteractionCallbackProvider(): array
    {
        return [
            [
                'runMax' => 4,
                'countCall' => 0,
            ],
            [
                'runMax' => 19,
                'countCall' => 3,
            ],
            [
                'runMax' => 20,
                'countCall' => 4,
            ],
            [
                'runMax' => 21,
                'countCall' => 4,
            ],
        ];
    }
}
