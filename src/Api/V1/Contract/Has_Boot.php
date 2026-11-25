<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Contract;

use WPMCP\Api\V1\Value\Boot_Context;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The imperative escape hatch: called once per request, only when the
 * module is shipped, its requirements hold and the request context matches.
 */
interface Has_Boot
{
    public function boot(Boot_Context $context): void;
}
