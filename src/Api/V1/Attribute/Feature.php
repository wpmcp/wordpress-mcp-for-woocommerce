<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A readme.txt feature bullet (literal English; the readme is not
 * translated).
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class Feature
{
    public function __construct(
        public readonly string $text,
    ) {
    }
}
