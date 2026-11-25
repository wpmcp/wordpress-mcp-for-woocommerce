<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Value;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * What Has_Boot::boot() is told about the request it runs in.
 */
final class Boot_Context
{
    public function __construct(
        public readonly string $module,
        public readonly Context $context,
        public readonly string $flavor,
    ) {
    }
}
