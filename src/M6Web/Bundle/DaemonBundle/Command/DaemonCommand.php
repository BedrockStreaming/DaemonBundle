<?php

namespace M6Web\Bundle\DaemonBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use M6Web\Bundle\DaemonBundle\Event\DaemonEvent;
use M6Web\Bundle\DaemonBundle\DaemonEvents;

/**
 * Class DaemonCommand
 * Abstract class for build daemon commands
 *
 * @package M6Web\Bundle\DaemonBundle\Command
 */
abstract class DaemonCommand extends ContainerAwareCommand
{
    /**
     * Tells if shutdown is requested
     *
     * @var boolean
     */
    protected $shutdownRequested = false;

    /**
     * Alows the concrete command to
     * setup an exit code
     *
     * @var integer
     */
    protected $returnCode = 0;

    /**
     * Loop count
     *
     * @var int
     */
    protected $loopCount = 0;

    /**
     * Store max loop option value
     *
     * @var integer
     */
    protected $loopMax = null;

    /**
     * Store max memory option value
     *
     * @var integer
     */
    protected $memoryMax = 0;

    /**
     * Store shutdown on exception option value
     *
     * @var boolean
     */
    protected $shutdownOnException;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher = null;

    /**
     * @var Callable
     */
    protected $loopCallback;

    /**
     * {@inheritdoc}
     */
    public function __construct($name = null)
    {
        // set indicators

        // Construct parent context (also calls configure)
        parent::__construct($name);

        // Set our runloop as the executable code
        parent::setCode(array($this, 'daemon'));

        $this->setCode(array($this, 'execute'));
    }

    /**
     * The daemon loop
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return integer The command exit code
     */
    public function daemon(InputInterface $input, OutputInterface $output)
    {
        $this->dispatchEvent(DaemonEvents::DAEMON_START);

        // options
        $this->setShutdownOnExceptions($input->getOption('shutdown-on-exceptions'));
        $this->setMemoryMax($input->getOption('memory-max'));

        if ((bool) $input->getOption('run-once')) {
            $this->setLoopMax(1);
        } else {
            if (!is_null($input->getOption('run-max'))) {
                $this->setLoopMax($input->getOption('run-max'));
            }
        }

        // Setup
        $this->dispatchEvent(DaemonEvents::DAEMON_SETUP);
        $this->setup($input, $output);


        // General loop
        $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_BEGIN);
        do {
            // Execute the inside loop code
            try {
                call_user_func($this->loopCallback, $input, $output);
            } catch (StopLoopException $e) {
                $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_EXCEPTION_STOP);

                $this->returnCode = $e->getCode();
                $this->requestShutdown();
            } catch (\Exception $e) {
                if ($this->getShutdownOnException()) {
                    $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_EXCEPTION_GENERAL);

                    $this->returnCode = 2; // with code ?
                    $this->requestShutdown();
                }
            }

            $this->incrLoopCount();
            $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_ITERATION);

        } while (!$this->isLastLoop());
        $this->dispatchEvent(DaemonEvents::DAEMON_LOOP_END);

        // Prepare for shutdown
        $this->tearDown($input, $output);
        $this->dispatchEvent(DaemonEvents::DAEMON_STOP);

        return $this->returnCode;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @see Symfony\Component\Console\Command\Command::run()
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        // Force the creation of the synopsis before the merge with the app definition
        $this->getSynopsis();

        // Merge our options
        $this->addOption('run-once', null, InputOption::VALUE_NONE, 'Run the command just once');
        $this->addOption('run-max', null, InputOption::VALUE_OPTIONAL, 'Run the command x time');
        $this->addOption('memory-max', null, InputOption::VALUE_OPTIONAL, 'Gracefully stop running command when given memory volume, in bytes, is reached', 0);
        $this->addOption('shutdown-on-exceptions', null, InputOption::VALUE_NONE, 'Ask for shutdown if an exeption is thrown');

        //$this->addOption('detect-leaks', null, InputOption::VALUE_NONE, 'Output information about memory usage');

        // Add the signal handler
        if (function_exists('pcntl_signal')) {
            // Enable ticks for fast signal processing
            declare(ticks = 1);

            pcntl_signal(SIGTERM, array($this, 'handleSignal'));
            pcntl_signal(SIGINT, array($this, 'handleSignal'));
        }

        // And now run the command
        return parent::run($input, $output);
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
     * Instruct the command to end the endless loop gracefully.
     *
     * This will finish the current iteration and give the command a chance
     * to cleanup.
     *
     * @return DaemonCommand The current instance
     */
    public function requestShutdown()
    {
        $this->shutdownRequested = true;

        return $this;
    }

    /**
     * Is shutdown requested
     *
     * @return bool
     */
    public function isShutdownRequested()
    {
        return $this->shutdownRequested;
    }

    /**
     * @param int $v setter for LoopCount
     *
     * @return $this
     */
    public function setLoopCount($v)
    {
        $this->loopCount = (int) $v;

        return $this;
    }

    /**
     * @return int
     */
    public function getLoopCount()
    {
        return $this->loopCount;
    }

    /**
     * @return int
     */
    public function incrLoopCount()
    {
        return $this->loopCount++;
    }

    /**
     * @return int
     */
    public function getLoopMax()
    {
        return $this->loopMax;
    }

    /**
     * @param int $v value
     */
    public function setLoopMax($v)
    {
        $this->loopMax = (int) $v;
    }

    /**
     * Define memory max option value
     *
     * @param integer $memory
     *
     * @return $this
     */
    public function setMemoryMax($memory)
    {
        $this->memoryMax = $memory;

        return $this;
    }

    /**
     * Get memory max option value
     *
     * @return integer
     */
    public function getMemoryMax()
    {
        return $this->memoryMax;
    }

    /**
     * Define command code callback
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function setCode($callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Invalid callable provided to Command::setCode.');
        }

        $this->loopCallback = $callback;

        return $this;
    }

    /**
     * @return int
     */
    public function getShutdownOnException()
    {
        return $this->shutdownOnException;
    }

    /**
     * @param bool $v value
     *
     * @return $this
     */
    public function setShutdownOnExceptions($v)
    {
        $this->shutdownOnException = (bool) $v;

        return $this;
    }

    /**
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     *
     * @return DaemonCommand
     */
    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }

    /**
     * get the EventDispatcher
     *
     * @return object|EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        if (!is_null($this->dispatcher)) {

            return $this->dispatcher;
        }

        return $this->getContainer()->get('event_dispatcher');
    }

    /**
     * Dispatch a daemon event
     *
     * @param string $eventName
     *
     * @return boolean
     */
    protected function dispatchEvent($eventName)
    {
        $this->getEventDispatcher()->dispatch($eventName, new DaemonEvent($this));

        return $this;
    }

    /**
     * Return true after the last loop
     *
     * @return boolean
     */
    protected function isLastLoop()
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

    protected function setup(InputInterface $input, OutputInterface $output)
    {

    }

    protected function tearDown(InputInterface $input, OutputInterface $output)
    {

    }

}