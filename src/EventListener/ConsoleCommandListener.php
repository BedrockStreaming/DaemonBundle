<?php

declare(strict_types=1);

namespace M6Web\Bundle\DaemonBundle\EventListener;

use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;
use Symfony\Component\Console\Event\ConsoleSignalEvent;

class ConsoleCommandListener
{
    public function onConsoleSignal(ConsoleSignalEvent $event): void
    {
        switch ($event->getHandlingSignal()) {
            // Shutdown signals
            case SIGTERM:
            case SIGINT:
                $command = $event->getCommand();
                if (!$command instanceof DaemonCommand) {
                    return;
                }
                $command->requestShutdown();
                break;
            default:
                break;
        }
    }
}
