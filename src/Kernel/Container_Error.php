<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A class the compiled container cannot build.
 */
final class Container_Error extends \RuntimeException
{
}
