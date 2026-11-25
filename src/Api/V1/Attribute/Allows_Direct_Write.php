<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Allows a method to write posts directly, with the reason next to the
 * code instead of in a central guard test list.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Allows_Direct_Write
{
    public function __construct(
        public readonly string $reason,
    ) {
    }
}
