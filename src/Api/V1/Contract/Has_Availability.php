<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Contract;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Only for a module whose availability a Requires value cannot express.
 */
interface Has_Availability
{
    public function is_available(Plugin_Presence $presence): bool;
}
