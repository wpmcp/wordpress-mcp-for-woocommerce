<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Runs the method on a cron hook (attached in every context, since cron
 * and WP-CLI both fire it).
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Cron
{
    public function __construct(
        public readonly string $hook,
        public readonly int $args = 1,
    ) {
    }
}
