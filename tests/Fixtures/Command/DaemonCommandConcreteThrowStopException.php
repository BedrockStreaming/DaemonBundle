<?php

namespace M6Web\Bundle\DaemonBundle\Tests\Fixtures\Command;

use M6Web\Bundle\DaemonBundle\Command\DaemonCommand;
use M6Web\Bundle\DaemonBundle\Command\StopLoopException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DaemonCommandConcreteThrowStopException extends DaemonCommand
{
    /** @var int */
    public const MAX_ITERATION = 7;

    /** @var string */
    public const EXCEPTION_MESSAGE = 'Stop loop exception';

    /** @var int */
    private int $count = 0;

    protected function configure(): void
    {
        $this
            ->setName('test:daemontest')
            ->setDescription('command for unit test');
    }

    /**
     * @throws StopLoopException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (++$this->count >= static::MAX_ITERATION) {
            throw new StopLoopException(static::EXCEPTION_MESSAGE);
        }
        return Command::FAILURE;
    }
}
