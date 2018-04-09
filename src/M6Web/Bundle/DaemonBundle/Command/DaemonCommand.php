<?php

namespace M6Web\Bundle\DaemonBundle\Command;

use M6Web\Bundle\DaemonBundle\DaemonEvents;
use M6Web\Bundle\DaemonBundle\Event\DaemonEvent;
use React\EventLoop\LoopInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class DaemonCommand
 * Abstract class for build daemon commands.
 */
abstract class DaemonCommand extends ContainerAwareCommand
{
    /**
     * Tells if shutdown is requested.
     *
     * @var bool
     */
    protected $shutdownRequested = false;

    /**
     * Alows the concrete command to
     * setup an exit code.
     *
     * @var int
     */
    protected $returnCode = 0;

    /**
     * Loop count.
     *
     * @var int
     */
    protected $loopCount = 0;

    /**
     * Store max loop option value.
     *
     * @var int
     */
    protected $loopMax = null;

    /**
     * Store max memory option value.
     *
     * @var int
     */
    protected $memoryMax = 0;

    /**
     * Store shutdown on exception option value.
     *
     * @var bool
     */
    protected $shutdownOnException;

    /**
     * Display or not exception on command output.
     *
     * @var bool
     */
    protected $showExceptions;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher = null;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var callable
     */
    protected $loopCallback;

    /**
     * @var \Exception
     */
    protected $lastException = null;

    /**
     * @var float
     */
    protected $startTime = null;

    /**
     * In seconds
     *
     * @var float
     */
    protected $nextIterationSleepingTime = 0.0;

    /**
     * @var array
     */
    protected $iterationsEvents = [];

    /**
     * @var array
     */
    protected $iterationsIntervalCallbacks = [];

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
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function setCode(callable $callback): self
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
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @see \Symfony\Component\Console\Command\Command::run()
     * @throws \Exception
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        // Force the creation of the synopsis before the merge with the app definition
        $this->getSynopsis();

        // Enable ticks for fast signal processing
        declare(ticks=1);

        // Add the signal handler
        pcntl_signal(SIGTERM, array($this, 'handleSignal'));
        pcntl_signal(SIGINT, array($this, 'handleSignal'));

        // And now run the command
        return parent::run($input, $output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @see Command::run()
     *
     */
    public function daemon(InputInterface $input, OutputInterface $output)
    {
        // Init services
        $container = $this->getContainer();

        if (is_null($this->dispatcher) && $container->has('event_dispatcher')) {
            $this->dispatcher = $this->getContainer()->get('event_dispatcher');
        }

        $this->loop = $container->get('m6_daemon_bundle.event_loop');

        // Last configuration
        $this->configureEvents();

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
        $this->dispatchEvent(DaemonEvents::DAEMON_START);

        // Setup
        $this->setup($input, $output);

        // General loop
        $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_BEGIN);

        // First tick
        $this->loop->futureTick(function () use ($input, $output) {
            $this->loop($input, $output);
        });

        // Go !
        $this->loop->run();

        $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_END);

        // Prepare for shutdown
        $this->tearDown($input, $output);
        $this->dispatchEvent(DaemonEvents::DAEMON_STOP);

        return $this->returnCode;
    }

    /**
     * Retrieve configured events.
     *
     * @return DaemonCommand
     */
    protected function configureEvents(): self
    {
        $container = $this->getContainer();
        $key = 'm6web_daemon.iterations_events';

        if ($container->hasParameter($key)) {
            $this->iterationsEvents = $container->getParameter($key);
        }

        return $this;
    }

    /**
     * Dispatch a daemon event.
     *
     * @param string $eventName
     * @return DaemonCommand
     */
    protected function dispatchEvent(string $eventName): self
    {
        $dispatcher = $this->getEventDispatcher();
        if (!is_null($dispatcher)) {
            $time = !is_null($this->startTime) ? microtime(true) - $this->startTime : 0;

            $event = new DaemonEvent($this);
            $event->setExecutionTime($time);

            $dispatcher->dispatch($eventName, $event);
        }

        return $this;
    }

    /**
     * get the EventDispatcher.
     *
     * @return object|EventDispatcherInterface
     */
    public function getEventDispatcher(): ?EventDispatcherInterface
    {
        return $this->dispatcher;
    }

    protected function setup(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function loop(InputInterface $input, OutputInterface $output)
    {
        $this->startTime = microtime(true);

        try {
            // Loop Callback
            call_user_func($this->loopCallback, $input, $output);

            // Loop interval callback
            $this->callIterationsIntervalCallbacks($input, $output);
        } catch (StopLoopException $e) {
            $this->setLastException($e);
            $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_EXCEPTION_STOP);

            $this->returnCode = $e->getCode();
            $this->requestShutdown();
        } catch (\Exception $e) {
            $this->setLastException($e);
            $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_EXCEPTION_GENERAL);

            if ($this->getShowExceptions()) {
                $this->getApplication()->renderException($e, $output);
            }

            if ($this->getShutdownOnException()) {
                $this->returnCode = !is_null($e->getCode()) ? $e->getCode() : -1;
                $this->requestShutdown();
            }
        }

        $this->incrLoopCount();
        $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_ITERATION);
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
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function callIterationsIntervalCallbacks(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->iterationsIntervalCallbacks as $iterationsIntervalCallback) {
            if (($this->getLoopCount() + 1) % $iterationsIntervalCallback['interval'] === 0) {
                call_user_func($iterationsIntervalCallback['callable'], $input, $output);
            }
        }
    }

    /**
     * @return int
     */
    public function getLoopCount(): int
    {
        return $this->loopCount;
    }

    /**
     * @param int $v setter for LoopCount
     *
     * @return $this
     */
    public function setLoopCount(int $v): self
    {
        $this->loopCount = $v;

        return $this;
    }

    /**
     * Instruct the command to end the endless loop gracefully.
     *
     * This will finish the current iteration and give the command a chance
     * to cleanup.
     *
     * @return DaemonCommand The current instance
     */
    public function requestShutdown(): self
    {
        $this->shutdownRequested = true;

        return $this;
    }

    /**
     * Get showExceptions option value.
     *
     * @return bool
     */
    public function getShowExceptions(): bool
    {
        return $this->showExceptions;
    }

    /**
     * Set showExceptions option value.
     *
     * @param bool $show
     * @return $this
     */
    public function setShowExceptions(bool $show): self
    {
        $this->showExceptions = $show;

        return $this;
    }

    /**
     * @return bool
     */
    public function getShutdownOnException(): bool
    {
        return $this->shutdownOnException;
    }

    /**
     * @param bool $v value
     *
     * @return $this
     */
    public function setShutdownOnException(bool $v): self
    {
        $this->shutdownOnException = $v;

        return $this;
    }

    /**
     * @return int
     */
    public function incrLoopCount(): int
    {
        return $this->loopCount++;
    }

    /**
     * Dispatch configured events.
     *
     * @return DaemonCommand
     */
    protected function dispatchConfigurationEvents(): self
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
     *
     * @return bool
     */
    protected function isLastLoop(): bool
    {
        // Count loop
        if (!is_null($this->getLoopMax()) && ($this->getLoopCount() >= $this->getLoopMax())) {
            $this->requestShutdown();
        }

       // Memory
        if ($this->memoryMax > 0 && memory_get_peak_usage(true) >= $this->memoryMax) {
            $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_MAX_MEMORY_REACHED);
            $this->requestShutdown();
        }

        return $this->isShutdownRequested();
    }

    /**
     * @return int
     */
    public function getLoopMax(): ?int
    {
        return $this->loopMax;
    }

    /**
     * @param int $v value
     * @return DaemonCommand
     */
    public function setLoopMax(int $v = null): self
    {
        $this->loopMax = $v;

        return $this;
    }

    /**
     * Is shutdown requested.
     *
     * @return bool
     */
    public function isShutdownRequested(): bool
    {
        return $this->shutdownRequested;
    }

    protected function tearDown(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * Handle proces signals.
     *
     * @param int $signal The signalcode to handle
     */
    public function handleSignal($signal)
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
     *
     * @return int
     */
    public function getMemoryMax(): int
    {
        return $this->memoryMax;
    }

    /**
     * Define memory max option value.
     *
     * @param int $memory
     * @return $this
     */
    public function setMemoryMax(int $memory): self
    {
        $this->memoryMax = $memory;

        return $this;
    }

    /**
     *
     * @param EventDispatcherInterface|null $dispatcher
     * @return DaemonCommand
     */
    public function setEventDispatcher(EventDispatcherInterface $dispatcher = null): self
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }

    /**
     * Return the last exception.
     *
     * @return \Exception|null
     */
    public function getLastException(): ?\Exception
    {
        return $this->lastException;
    }

    /**
     * Set the last exception.
     *
     * @param \Exception $e
     *
     * @return \M6Web\Bundle\DaemonBundle\Command\DaemonCommand
     */
    protected function setLastException(\Exception $e): self
    {
        $this->lastException = $e;

        return $this;
    }

    /**
     * Add your own callback after every iteration interval.
     *
     * @param int $iterationsInterval
     * @param callable $onIterationsInterval
     */
    public function addIterationsIntervalCallback($iterationsInterval, callable $onIterationsInterval)
    {
        if (!is_int($iterationsInterval) || ($iterationsInterval <= 0)) {
            throw new \InvalidArgumentException('Iteration interval must be a positive integer');
        }

        $this->iterationsIntervalCallbacks[] = ['interval' => $iterationsInterval, 'callable' => $onIterationsInterval,];
    }

    /**
     * Return the command name.
     *
     * @return string
     */
    public function __toString(): string
    {
        return str_replace(':', '-', $this->getName());
    }

    /**
     * Set the sleeping time (used between two loops).
     * @param int $µseconds
     */
    protected function setNextIterationSleepingTime(int $µseconds): void
    {
        $this->nextIterationSleepingTime = $µseconds / 1e6;
    }
}
