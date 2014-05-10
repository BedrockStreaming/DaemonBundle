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
}