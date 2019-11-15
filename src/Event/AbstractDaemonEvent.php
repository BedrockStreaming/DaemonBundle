<?php

namespace M6Web\Bundle\DaemonBundle\Event;

use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractDaemonEvent extends Event
{
    /** @var DaemonCommand */
    protected $command;

    /** @var float */
    protected $executionTime;

    public function __construct(DaemonCommand $command)
    {
        $this->command = $command;
    }

    public function getCommand(): DaemonCommand
    {
        return $this->command;
    }

    /**
     * Set the execution time.
     */
    public function setExecutionTime(float $executionTime): AbstractDaemonEvent
    {
        $this->executionTime = $executionTime;

        return $this;
    }

    /**
     * Return the execution time.
     */
    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    /**
     * Alias of getExecutionTime for statsd.
     * Return execution in ms.
     */
    public function getTiming(): float
    {
        return $this->getExecutionTime() * 1000;
    }

    /**
     * Return the current memory usage.
     */
    public function getMemory(): int
    {
        return memory_get_usage();
    }

    /**
     * Get last exception class name.
     */
    public function getCommandLastExceptionClassName(): ?string
    {
        $exception = $this->command->getLastException();

        if ($exception !== null) {
            return \get_class($exception);
        }

        return null;
    }
}
