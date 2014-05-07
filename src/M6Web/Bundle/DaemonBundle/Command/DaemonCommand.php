<?php

namespace M6Web\Bundle\DaemonBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class DaemonCommand
 * Abstract class for build daemon commands
 *
 * @package M6Web\Bundle\DaemonBundle\Command
 */
abstract class DaemonCommand extends Command
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
    protected $loopMax;

    /**
     * Store max memory option value
     *
     * @var integer
     */
    protected $memoryMax;

    /**
     * Store shutdown on exception option value
     *
     * @var boolean
     */
    protected $shutdownOnException;

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
        // options
        $this->shutdownOnExceptions = (bool) $input->getOption('shutdown-on-exceptions');

        if ((bool) $input->getOption('run-once')) {
            $this->loopMax = 1;
        } else {
            $this->loopMax = (int) $input->getOption('loop-max');
        }

        $this->setup($input, $output);
        // TODO : fire an event

        do {

            // execute the inside loop code
            try {
                $this->execute($input, $output);
            } catch (StopLoopException $e) {
                // TODO : fire an event
                $this->returnCode = $e->getCode();
                $this->requestShutdown();
            } catch (\Exception $e) {
                if ($shutdownOnExceptions) {
                    // TODO : fire an event
                    $this->returnCode = 2; // with code ?
                    $this->requestShutdown();
                }

            }

            // Request shutdown if we only should run once
            if ( true ===  $runOnce) {
                // TODO : fire an event
                $this->requestShutdown();
            }

            // count loop
            if ($this->incrLoopCount() >= $runMax) {
                // TODO : fire an event
                $this->requestShutdown();
            }

            // test memory

        } while ($this->isShutdownRequested());

        // Prepare for shutdown
        $this->tearDown($input, $output);
        // TODO : fire an event

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
     * @return Command The current instance
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


    protected function setup(InputInterface $input, OutputInterface $output)
    {

    }


    protected function tearDown(InputInterface $input, OutputInterface $output)
    {

    }

}