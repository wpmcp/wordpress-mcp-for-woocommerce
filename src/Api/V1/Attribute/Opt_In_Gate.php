<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A default-off switch: $filter is the opt-in filter name, $labels names a
 * static method on the class returning [filter => translated label], called
 * only when the settings screen renders (never __() in an attribute).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Opt_In_Gate
{
    public function __construct(
        public readonly string $filter,
        public readonly bool $destructive = false,
        public readonly ?string $labels = null,
        public readonly ?string $group = null,
    ) {
    }
}
