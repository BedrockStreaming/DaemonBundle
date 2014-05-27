<?php

namespace M6Web\Bundle\DaemonBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;

/**
 * DaemonEvent
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
     * Gets last exception class name
     *
     * @return string
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