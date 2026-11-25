<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A wp-admin page. $title_method names a static method returning the
 * translated title; $method renders the page.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Admin_Page
{
    public function __construct(
        public readonly string $slug,
        public readonly ?string $parent = null,
        public readonly string $capability = 'manage_options',
        public readonly ?float $position = null,
        public readonly ?string $title_method = null,
        public readonly string $method = 'render',
    ) {
    }
}
