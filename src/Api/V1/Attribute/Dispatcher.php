<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Declares the read/write dispatcher pair a class implements. Contributors
 * in other modules name only $slug, never the class.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Dispatcher
{
    public function __construct(
        public readonly string $slug,
        public readonly bool $registers_only_when_available = false,
    ) {
    }
}
