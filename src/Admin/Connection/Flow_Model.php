<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The state of each of the four steps, why step 2 is blocked when it is, and
 * how the chosen app signs in (null before an app is picked).
 */
final class Flow_Model
{
    public function __construct(
        public readonly Step_State $pick,
        public readonly Step_State $create,
        public readonly Step_State $add,
        public readonly Step_State $check,
        public readonly ?Blocker $blocker,
        public readonly ?Sign_In $sign_in,
    ) {
    }
}
