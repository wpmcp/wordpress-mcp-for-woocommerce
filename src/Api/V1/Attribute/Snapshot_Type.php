<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A snapshot type on its Snapshot_Restorer. Rollback_Service asks the
 * registry whether a type is restorable and which capability it needs.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Snapshot_Type
{
    public function __construct(
        public readonly string $type,
        public readonly string $capability = 'manage_options',
    ) {
    }
}
