<?php

namespace M6Web\Bundle\DaemonBundle\Command;

use M6Web\Bundle\DaemonBundle\Event\AbstractDaemonEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopBeginEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopEndEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopExceptionGeneralEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopExceptionStopEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopIterationEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonLoopMaxMemoryReachedEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonStartEvent;
use M6Web\Bundle\DaemonBundle\Event\DaemonStopEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DaemonCommand
 * Abstract class for build daemon commands.
 */
abstract class DaemonCommand extends Command
{
    /** @var bool tells if shutdown is requested */
    protected $shutdownRequested = false;

    /** @var int allows the concrete command to setup an exit code */
    protected $returnCode = 0;

    /** @var int loop count */
    protected $loopCount = 0;

    /** @var int store max loop option value */
    protected $loopMax;

    /** @var int store max memory option value */
    protected $memoryMax = 0;

    /** @var bool store shutdown on exception option value */
    protected $shutdownOnException;

    /** @var bool display or not exception on command output */
    protected $showExceptions;

    /** @var ?EventDispatcherInterface */
    protected $dispatcher;

    /** @var LoopInterface */
    protected $loop;

    /** @var callable */
    protected $loopCallback;

    /** @var \Exception */
    protected $lastException;

    /** @var float */
    protected $startTime;

    /** @var float time in seconds */
    protected $nextIterationSleepingTime = 0.0;

    /** @var array */
    protected $iterationsEvents = [];

    /** @var array */
    protected $iterationsIntervalCallbacks = [];

    public function setEventDispatcher(EventDispatcherInterface $dispatcher = null): DaemonCommand
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }

    public function setLoop(LoopInterface $loop): DaemonCommand
    {
        $this->loop = $loop;

        return $this;
    }

    public function setIterationsEvents(array $iterationsEvents): DaemonCommand
    {
        $this->iterationsEvents = $iterationsEvents;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function __construct($name = null)
    {
        // Construct parent context (also calls configure)
        parent::__construct($name);

        $this->configureDaemonDefinition();

        // Set the parent code, for launch the daemon loop.
        parent::setCode([$this, 'daemon']);

        // Set the code execute for every iterations.
        $this->setCode([$this, 'execute']);
    }

    /**
     * Add daemon options to the command definition.
     */
    private function configureDaemonDefinition(): void
    {
        $this->addOption('run-once', null, InputOption::VALUE_NONE, 'Run the command just once.');
        $this->addOption('run-max', null, InputOption::VALUE_OPTIONAL, 'Run the command x time.');
        $this->addOption('memory-max', null, InputOption::VALUE_OPTIONAL, 'Gracefully stop running command when given memory volume, in bytes, is reached.', 0);
        $this->addOption('shutdown-on-exception', null, InputOption::VALUE_NONE, 'Ask for shutdown if an exception is thrown.');
        $this->addOption('show-exceptions', null, InputOption::VALUE_NONE, 'Display exception on command output.');
    }

    /**
     * Define command code callback.
     *
     * @param callable $callback
     * @return $this
     */
    public function setCode(callable $callback): static
    {
        $this->loopCallback = $callback;

        return $this;
    }

    /**
     * Get the daemon react loop.
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    /**
     * @see \Symfony\Component\Console\Command\Command::run()
     *
     * @throws \Exception
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        // Force the creation of the synopsis before the merge with the app definition
        $this->getSynopsis();

        // Enable ticks for fast signal processing
        declare(ticks=1);

        // Add the signal handler
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);

        // And now run the command
        return parent::run($input, $output);
    }

    /**
     * @see Command::run()
     */
    public function daemon(InputInterface $input, OutputInterface $output): int
    {
        // Options
        $this->setShutdownOnException($input->hasOption('shutdown-on-exception') ? $input->getOption('shutdown-on-exception') : false);
        $this->setMemoryMax($input->hasOption('memory-max') ? $input->getOption('memory-max') : -1);
        $this->setShowExceptions($input->hasOption('show-exceptions') ? $input->getOption('show-exceptions') : false);

        if ($input->hasOption('run-once') && (bool) $input->getOption('run-once')) {
            $this->setLoopMax(1);
        } else {
            if ($input->hasOption('run-max')) {
                $this->setLoopMax($input->getOption('run-max'));
            }
        }

        // Ok starting...
        $this->dispatchEvent(DaemonStartEvent::class);

        // Setup
        $this->setup($input, $output);

        // General loop
        $this->dispatchEvent(DaemonLoopBeginEvent::class);

        // First tick
        $this->loop->futureTick(function () use ($input, $output) {
            $this->loop($input, $output);
        });

        // Go !
        $this->loop->run();

        $this->dispatchEvent(DaemonLoopEndEvent::class);

        // Prepare for shutdown
        $this->tearDown($input, $output);
        $this->dispatchEvent(DaemonStopEvent::class);

        return $this->returnCode;
    }

    /**
     * Dispatch a daemon event.
     */
    protected function dispatchEvent(string $eventName): DaemonCommand
    {
        if (null !== $this->dispatcher) {
            $time = null !== $this->startTime ? microtime(true) - $this->startTime : 0;

            /** @var AbstractDaemonEvent $event */
            $event = new $eventName($this);
            $event->setExecutionTime($time);

            $this->dispatcher->dispatch($event);
        }

        return $this;
    }

    /**
     * Get the EventDispatcher.
     */
    public function getEventDispatcher(): ?EventDispatcherInterface
    {
        return $this->dispatcher;
    }

    protected function setup(InputInterface $input, OutputInterface $output): void
    {
    }

    protected function loop(InputInterface $input, OutputInterface $output): void
    {
        $this->startTime = microtime(true);

        try {
            // Loop Callback
            call_user_func($this->loopCallback, $input, $output);

            // Loop interval callback
            $this->callIterationsIntervalCallbacks($input, $output);
        } catch (StopLoopException $e) {
            $this->setLastException($e);
            $this->dispatchEvent(DaemonLoopExceptionStopEvent::class);

            $this->returnCode = $e->getCode();
            $this->requestShutdown();
        } catch (\Exception $e) {
            $this->setLastException($e);
            $this->dispatchEvent(DaemonLoopExceptionGeneralEvent::class);

            if ($this->getShowExceptions()) {
                $this->getApplication()->renderThrowable($e, $output);
            }

            if ($this->getShutdownOnException()) {
                $this->returnCode = !is_null($e->getCode()) ? $e->getCode() : -1;
                $this->requestShutdown();
            }
        }

        $this->incrLoopCount();
        $this->dispatchEvent(DaemonLoopIterationEvent::class);
        $this->dispatchConfigurationEvents();

        // Last loop, no more action needed.
        if ($this->isLastLoop()) {
            return;
        }

        // Not the last loop
        if ($this->nextIterationSleepingTime) {
            // We will wake up later but the event loop is still active for background operations
            $this->loop->addTimer($this->nextIterationSleepingTime, function () use ($input, $output) {
                $this->loop($input, $output);
            });

            $this->nextIterationSleepingTime = 0.0;
        } else {
            // No pause
            $this->loop->futureTick(function () use ($input, $output) {
                $this->loop($input, $output);
            });
        }
    }

    /**
     * Execute callbacks after every iteration interval.
     */
    protected function callIterationsIntervalCallbacks(InputInterface $input, OutputInterface $output): void
    {
        foreach ($this->iterationsIntervalCallbacks as $iterationsIntervalCallback) {
            if (($this->getLoopCount() + 1) % $iterationsIntervalCallback['interval'] === 0) {
                call_user_func($iterationsIntervalCallback['callable'], $input, $output);
            }
        }
    }

    public function getLoopCount(): int
    {
        return $this->loopCount;
    }

    public function setLoopCount(int $loopCount): DaemonCommand
    {
        $this->loopCount = $loopCount;

        return $this;
    }

    /**
     * Instruct the command to end the endless loop gracefully.
     *
     * This will finish the current iteration and give the command a chance
     * to cleanup.
     */
    public function requestShutdown(): DaemonCommand
    {
        $this->shutdownRequested = true;

        return $this;
    }

    /**
     * Get showExceptions option value.
     */
    public function getShowExceptions(): bool
    {
        return $this->showExceptions;
    }

    /**
     * Set showExceptions option value.
     */
    public function setShowExceptions(bool $show): DaemonCommand
    {
        $this->showExceptions = $show;

        return $this;
    }

    public function getShutdownOnException(): bool
    {
        return $this->shutdownOnException;
    }

    /**
     * @param bool $v value
     */
    public function setShutdownOnException(bool $shutdownOnException): DaemonCommand
    {
        $this->shutdownOnException = $shutdownOnException;

        return $this;
    }

    public function incrLoopCount(): int
    {
        return $this->loopCount++;
    }

    /**
     * Dispatch configured events.
     */
    protected function dispatchConfigurationEvents(): DaemonCommand
    {
        foreach ($this->iterationsEvents as $event) {
            if (!($this->loopCount % $event['count'])) {
                $this->dispatchEvent($event['name']);
            }
        }

        return $this;
    }

    /**
     * Return true after the last loop.
     */
    protected function isLastLoop(): bool
    {
        // Count loop
        if (null !== $this->getLoopMax() && ($this->getLoopCount() >= $this->getLoopMax())) {
            $this->requestShutdown();
        }

        // Memory
        if ($this->memoryMax > 0 && memory_get_peak_usage(true) >= $this->memoryMax) {
            $this->dispatchEvent(DaemonLoopMaxMemoryReachedEvent::class);
            $this->requestShutdown();
        }

        return $this->isShutdownRequested();
    }

    public function getLoopMax(): ?int
    {
        return $this->loopMax;
    }

    public function setLoopMax(int $loopMax = null): DaemonCommand
    {
        $this->loopMax = $loopMax;

        return $this;
    }

    /**
     * Is shutdown requested.
     */
    public function isShutdownRequested(): bool
    {
        return $this->shutdownRequested;
    }

    protected function tearDown(InputInterface $input, OutputInterface $output): void
    {
    }

    /**
     * Handle proces signals.
     */
    public function handleSignal(int $signal): void
    {
        switch ($signal) {
            // Shutdown signals
            case SIGTERM:
            case SIGINT:
                $this->requestShutdown();
                break;
        }
    }

    /**
     * Get memory max option value.
     */
    public function getMemoryMax(): int
    {
        return $this->memoryMax;
    }

    /**
     * Define memory max option value.
     */
    public function setMemoryMax(int $memory): DaemonCommand
    {
        $this->memoryMax = $memory;

        return $this;
    }

    /**
     * Return the last exception.
     */
    public function getLastException(): ?\Exception
    {
        return $this->lastException;
    }

    /**
     * Set the last exception.
     */
    protected function setLastException(\Exception $exception): DaemonCommand
    {
        $this->lastException = $exception;

        return $this;
    }

    /**
     * Add your own callback after every iteration interval.
     * @param callable $onIterationsInterval
     */
    public function addIterationsIntervalCallback(int $iterationsInterval, callable $onIterationsInterval): void
    {
        if ($iterationsInterval <= 0) {
            throw new \InvalidArgumentException('Iteration interval must be a positive integer');
        }

        $this->iterationsIntervalCallbacks[] = ['interval' => $iterationsInterval, 'callable' => $onIterationsInterval];
    }

    /**
     * Return the command name.
     */
    public function __toString(): string
    {
        return str_replace(':', '-', $this->getName());
    }

    /**
     * Set the sleeping time (used between two loops).
     */
    protected function setNextIterationSleepingTime(int $useconds): void
    {
        $this->nextIterationSleepingTime = $useconds / 1e6;
    }
}
