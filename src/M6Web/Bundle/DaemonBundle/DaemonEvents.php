<?php

namespace M6Web\Bundle\DaemonBundle;

/**
 * DaemonEvents
 */
final class DaemonEvents
{
    const DAEMON_START                  = 'daemon.start';
    const DAEMON_SETUP                  = 'daemon.setup';
    const DAEMON_LOOP_BEGIN             = 'daemon.loop.begin';
    const DAEMON_LOOP_EXCEPTION_STOP    = 'daemon.loop.exception.stop';
    const DAEMON_LOOP_EXCEPTION_GENERAL = 'daemon.loop.exception.general';
    const DAEMON_LOOP_END               = 'daemon.loop.begin';
    const DAEMON_STOP                   = 'daemon.stop';
}