<?php

namespace M6Web\Bundle\DaemonBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;

/**
 * DaemonEvent.
 */
class DaemonEvent extends Event
{
    /**
     * @var DaemonCommand
     */
    protected $command;

    /**
     * @param DaemonCommand $command
     */
    public function __construct(DaemonCommand $command)
    {
        $this->command = $command;
    }

    /**
     * @return DaemonCommand
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Set the execution time.
     *
     * @param float
     *
     * @return DaemonEvent
     */
    public function setExecutionTime($v)
    {
        $this->executionTime = $v;

        return $this;
    }

    /**
     * Return the execution time.
     *
     * @return float
     */
    public function getExecutionTime()
    {
        return $this->executionTime;
    }

    /**
     * Alias of getExecutionTime for statsd.
     * Return execution in ms.
     *
     * @return float
     */
    public function getTiming()
    {
        return $this->getExecutionTime() * 1000;
    }

    /**
     * Return the current memory usage.
     *
     * @return number
     */
    public function getMemory()
    {
        return memory_get_usage();
    }

    /**
     * Gets last exception class name.
     *
     * @return string|null
     */
    public function getCommandLastExceptionClassName()
    {
        $exception = $this->command->getLastException();

        if (!is_null($exception)) {
            return get_class($exception);
        }

        return null;
    }
}
