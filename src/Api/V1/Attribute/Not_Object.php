<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Declares that an op's args key names something that is not a WordPress
 * object (its own table row, for example), with the reason.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Not_Object
{
    /**
     * @param string $key op:args_key
     */
    public function __construct(
        public readonly string $key,
        public readonly string $reason,
    ) {
    }
}
