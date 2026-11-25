<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The stored data a module owns, for uninstall, deactivation and privacy
 * export.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Owns_Data
{
    /**
     * @param list<string> $options
     * @param list<string> $tables
     * @param list<string> $cron
     * @param list<string> $post_types
     * @param list<string> $user_meta
     */
    public function __construct(
        public readonly array $options = [],
        public readonly array $tables = [],
        public readonly array $cron = [],
        public readonly array $post_types = [],
        public readonly array $user_meta = [],
    ) {
    }
}
