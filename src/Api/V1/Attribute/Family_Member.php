<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

use WPMCP\Api\V1\Value\Requires;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One member of an adapter family read from the registry (lms, crm,
 * builder-content, woo-domain and others) instead of a hand-kept list.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Family_Member
{
    public function __construct(
        public readonly string $family,
        public readonly string $key,
        public readonly string $label,
        public readonly int $order = 0,
        public readonly ?Requires $requires = null,
    ) {
    }
}
