<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Refreshes derived state after a rollback restores options (the default
 * hook) or another rollback action. Replaces a ROLLBACK_REFRESHERS row.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class On_Rollback
{
    public function __construct(
        public readonly string $hook = 'wpmcp_rollback_options_restored',
        public readonly int $priority = 10,
        public readonly int $args = 2,
    ) {
    }
}
