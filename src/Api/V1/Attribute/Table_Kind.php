<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One plugin table kind for Plugin_Table_Rows_Snapshot: the table, its key
 * and order columns, suffixes, binary columns, companions, the restore
 * capability and an optional after_restore method on the class.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Table_Kind
{
    /**
     * @param list<string> $key
     * @param list<string> $order
     * @param list<string> $suffixes
     * @param list<string> $binary
     * @param list<string> $companions
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $table,
        public readonly array $key,
        public readonly array $order = [],
        public readonly array $suffixes = [],
        public readonly array $binary = [],
        public readonly array $companions = [],
        public readonly string $capability = 'manage_options',
        public readonly ?string $after_restore = null,
    ) {
    }
}
