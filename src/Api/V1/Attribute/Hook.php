<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Attaches the method to a WordPress action or filter. Static methods are
 * attached as [Class, method]; instance methods through a trampoline, so
 * nothing autoloads until the hook fires.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Hook
{
    /**
     * @param list<\WPMCP\Api\V1\Value\Context|string> $contexts
     */
    public function __construct(
        public readonly string $hook,
        public readonly int $priority = 10,
        public readonly int $args = 1,
        public readonly bool $filter = false,
        public readonly array $contexts = [],
    ) {
    }
}
