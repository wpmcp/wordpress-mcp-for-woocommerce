<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Binds the class as an implementation of a kernel extension point
 * (edition seams, Entitlement_Source). $key distinguishes several providers
 * of one interface (an add-on tier slug).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Provides
{
    /**
     * @param class-string $interface
     */
    public function __construct(
        public readonly string $interface,
        public readonly ?string $key = null,
        public readonly int $priority = 10,
    ) {
    }
}
