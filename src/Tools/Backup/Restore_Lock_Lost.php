<?php

namespace WPMCP\Tools\Backup;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Thrown by a restore that no longer holds the restore lock (issue #1124):
 * its database connection dropped, the server freed the lock with it, and
 * wpdb reconnected without it. Another restore may hold the lock now, so
 * the worker stops at once and writes nothing more: no statement, no
 * progress file, no rollback, no job history, no maintenance change.
 */
class Restore_Lock_Lost extends \RuntimeException
{
}
