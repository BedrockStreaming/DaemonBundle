<?php

declare(strict_types=1);

namespace M6Web\Bundle\DaemonBundle\Tests\Integration\EventListener;

use M6Web\Bundle\DaemonBundle\EventListener\ConsoleCommandListener;
use M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command\DaemonCommandConcrete;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Integration tests for ConsoleCommandListener
 * These tests verify the listener works correctly with Symfony's event dispatcher
 */
class ConsoleCommandListenerIntegrationTest extends KernelTestCase
{
    private EventDispatcher $dispatcher;
    private ConsoleCommandListener $listener;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->listener = new ConsoleCommandListener();

        // Register the listener
        $this->dispatcher->addListener(ConsoleSignalEvent::class, [$this->listener, 'onConsoleSignal']);
    }

    public function testListenerIsInvokedBySigtermEvent(): void
    {
        $command = new DaemonCommandConcrete('test:daemon');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $event = new ConsoleSignalEvent($command, $input, $output, SIGTERM);

        $this->assertFalse($command->isShutdownRequested(), 'Shutdown should not be requested initially');

        // Dispatch the event
        $this->dispatcher->dispatch($event, ConsoleSignalEvent::class);

        $this->assertTrue($command->isShutdownRequested(), 'Shutdown should be requested after event dispatch');
    }

    public function testListenerIsInvokedBySigintEvent(): void
    {
        $command = new DaemonCommandConcrete('test:daemon');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $event = new ConsoleSignalEvent($command, $input, $output, SIGINT);

        $this->assertFalse($command->isShutdownRequested(), 'Shutdown should not be requested initially');

        // Dispatch the event
        $this->dispatcher->dispatch($event, ConsoleSignalEvent::class);

        $this->assertTrue($command->isShutdownRequested(), 'Shutdown should be requested after event dispatch');
    }

    public function testListenerIgnoresNonDaemonCommands(): void
    {
        $command = new Command('test:regular');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $event = new ConsoleSignalEvent($command, $input, $output, SIGTERM);

        // Should not throw any exception when dispatched
        $this->dispatcher->dispatch($event, ConsoleSignalEvent::class);

        // If we reach here, the test passes
        $this->assertTrue(true);
    }

    public function testListenerIgnoresUnhandledSignals(): void
    {
        $command = new DaemonCommandConcrete('test:daemon');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        // Test with SIGHUP (not handled by the listener)
        $event = new ConsoleSignalEvent($command, $input, $output, SIGHUP);

        $this->assertFalse($command->isShutdownRequested(), 'Shutdown should not be requested initially');

        // Dispatch the event
        $this->dispatcher->dispatch($event, ConsoleSignalEvent::class);

        $this->assertFalse($command->isShutdownRequested(), 'Shutdown should not be requested for unhandled signal');
    }

    public function testMultipleSignalsCanBeHandled(): void
    {
        $command = new DaemonCommandConcrete('test:daemon');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        // First, send an unhandled signal
        $event1 = new ConsoleSignalEvent($command, $input, $output, SIGHUP);
        $this->dispatcher->dispatch($event1, ConsoleSignalEvent::class);
        $this->assertFalse($command->isShutdownRequested(), 'Shutdown should not be requested after SIGHUP');

        // Then send SIGTERM
        $event2 = new ConsoleSignalEvent($command, $input, $output, SIGTERM);
        $this->dispatcher->dispatch($event2, ConsoleSignalEvent::class);
        $this->assertTrue($command->isShutdownRequested(), 'Shutdown should be requested after SIGTERM');
    }

    public function testListenerWorksWithMultipleListeners(): void
    {
        $called = false;

        // Add another listener to the same event
        $this->dispatcher->addListener(ConsoleSignalEvent::class, function(ConsoleSignalEvent $event) use (&$called) {
            $called = true;
        });

        $command = new DaemonCommandConcrete('test:daemon');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $event = new ConsoleSignalEvent($command, $input, $output, SIGTERM);

        // Dispatch the event
        $this->dispatcher->dispatch($event, ConsoleSignalEvent::class);

        $this->assertTrue($command->isShutdownRequested(), 'Shutdown should be requested');
        $this->assertTrue($called, 'Other listener should also be called');
    }
}

