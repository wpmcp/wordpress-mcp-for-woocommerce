<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

use WPMCP\Api\V1\Value\Requires;
use WPMCP\Api\V1\Value\Tier;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * An operation contributor to the dispatcher pair $dispatcher. $method is
 * the static method returning the operation array (repeatable, so a class
 * with weglot_operations() and gtranslate_operations() declares both).
 * $summary is this contributor's fragment of the pair description, joined
 * with ', ' in $summary_order (defaults to $order); a contributor whose
 * fragment another one already covers passes null. $hosts_method names a
 * pure static method returning [op => host label] for packs with several
 * hosts. $availability names a grandfathered static predicate (phase A
 * parity, for example 'active'); otherwise $requires is checked.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Operations
{
    /**
     * @param array<string, string> $not_objects op:args_key => why it is not a WordPress object
     */
    public function __construct(
        public readonly string $dispatcher,
        public readonly ?string $host = null,
        public readonly ?string $summary = null,
        public readonly int $order = 0,
        public readonly ?int $summary_order = null,
        public readonly Tier|string $tier = Tier::Free,
        public readonly ?string $availability = null,
        public readonly ?Requires $requires = null,
        public readonly ?string $variant = null,
        public readonly ?string $hosts_method = null,
        public readonly bool $several = false,
        public readonly array $not_objects = [],
        public readonly string $method = 'operations',
        public readonly ?string $prefix = null,
    ) {
    }
}
