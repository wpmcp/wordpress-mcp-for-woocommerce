<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Which modules a flavor ships: all of them (full), every free-tier module
 * not excluded (wporg), or only the modules that name the flavor (vertical
 * builds such as woocommerce and elementor).
 */
enum Ships: string
{
    case All = 'all';
    case Free = 'free';
    case Selected = 'selected';
}
