<?php

declare(strict_types=1);

namespace M6Web\Bundle\DaemonBundle\Tests\Unit\EventListener;

use M6Web\Bundle\DaemonBundle\EventListener\ConsoleCommandListener;
use M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command\DaemonCommandConcrete;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ConsoleCommandListenerTest extends TestCase
{
    private ConsoleCommandListener $listener;

    protected function setUp(): void
    {
        $this->listener = new ConsoleCommandListener();
    }

    public function testOnConsoleSignalWithSigtermAndDaemonCommand(): void
    {
        $command = new DaemonCommandConcrete('test:daemon');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $event = new ConsoleSignalEvent($command, $input, $output, SIGTERM);

        $this->assertFalse($command->isShutdownRequested(), 'Shutdown should not be requested initially');

        $this->listener->onConsoleSignal($event);

        $this->assertTrue($command->isShutdownRequested(), 'Shutdown should be requested after SIGTERM');
    }

    public function testOnConsoleSignalWithSigintAndDaemonCommand(): void
    {
        $command = new DaemonCommandConcrete('test:daemon');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $event = new ConsoleSignalEvent($command, $input, $output, SIGINT);

        $this->assertFalse($command->isShutdownRequested(), 'Shutdown should not be requested initially');

        $this->listener->onConsoleSignal($event);

        $this->assertTrue($command->isShutdownRequested(), 'Shutdown should be requested after SIGINT');
    }

    public function testOnConsoleSignalWithSigtermAndNonDaemonCommand(): void
    {
        $command = new Command('test:regular');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $event = new ConsoleSignalEvent($command, $input, $output, SIGTERM);

        // Should not throw exception, just return early
        $this->listener->onConsoleSignal($event);

        // If we reach here, the test passes (no exception thrown)
        $this->assertTrue(true);
    }

    public function testOnConsoleSignalWithSigintAndNonDaemonCommand(): void
    {
        $command = new Command('test:regular');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $event = new ConsoleSignalEvent($command, $input, $output, SIGINT);

        // Should not throw exception, just return early
        $this->listener->onConsoleSignal($event);

        // If we reach here, the test passes (no exception thrown)
        $this->assertTrue(true);
    }

    public function testOnConsoleSignalWithOtherSignalAndDaemonCommand(): void
    {
        $command = new DaemonCommandConcrete('test:daemon');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        // Test with SIGHUP (signal 1) which is not handled
        $event = new ConsoleSignalEvent($command, $input, $output, SIGHUP);

        $this->assertFalse($command->isShutdownRequested(), 'Shutdown should not be requested initially');

        $this->listener->onConsoleSignal($event);

        $this->assertFalse($command->isShutdownRequested(), 'Shutdown should not be requested for unhandled signal');
    }

    public function testOnConsoleSignalWithOtherSignalAndNonDaemonCommand(): void
    {
        $command = new Command('test:regular');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        // Test with SIGUSR1 (signal 10) which is not handled
        $event = new ConsoleSignalEvent($command, $input, $output, SIGUSR1);

        // Should not throw exception
        $this->listener->onConsoleSignal($event);

        // If we reach here, the test passes
        $this->assertTrue(true);
    }

    /**
     * Test that only SIGTERM and SIGINT signals trigger shutdown
     */
    public function testOnlyShutdownSignalsTriggerShutdown(): void
    {
        $command = new DaemonCommandConcrete('test:daemon');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        // Test non-shutdown signals
        $nonShutdownSignals = [SIGHUP, SIGUSR1, SIGUSR2];

        foreach ($nonShutdownSignals as $signal) {
            $event = new ConsoleSignalEvent($command, $input, $output, $signal);
            $this->listener->onConsoleSignal($event);
            $this->assertFalse(
                $command->isShutdownRequested(),
                sprintf('Signal %d should not trigger shutdown', $signal)
            );
        }
    }
}
