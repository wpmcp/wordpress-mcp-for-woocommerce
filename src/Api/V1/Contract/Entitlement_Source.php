<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Contract;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Answers whether an addon:<slug> tier is permitted on this site. An add-on
 * provides one with #[Provides(Entitlement_Source::class, key: '<slug>')].
 */
interface Entitlement_Source
{
    public function permits(): bool;
}
