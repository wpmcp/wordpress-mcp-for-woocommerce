<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The state of one step of the Connection screen's flow. Open is a step the
 * reader can use now that is not the one to do next (step 4 while step 3 is
 * current), so only one step is ever Current.
 */
enum Step_State: string
{
    case Locked = 'locked';
    case Current = 'current';
    case Open = 'open';
    case Done = 'done';
    case Blocked = 'blocked';
}
