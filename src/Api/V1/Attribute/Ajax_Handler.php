<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles wp_ajax_<action> (and wp_ajax_nopriv_<action> when $nopriv).
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Ajax_Handler
{
    public function __construct(
        public readonly string $action,
        public readonly bool $nopriv = false,
    ) {
    }
}
