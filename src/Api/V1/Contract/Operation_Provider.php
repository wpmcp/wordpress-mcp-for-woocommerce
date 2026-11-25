<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Contract;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * An #[Operations] contributor to a dispatcher pair. The generator calls
 * operations() at build time for scalar metadata only (closures dropped);
 * at runtime it is called on the first dispatch or catalog read of the pair.
 */
interface Operation_Provider
{
    /** @return array<string, array<string, mixed>> */
    public static function operations(): array;
}
