<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Contract;

use WPMCP\Api\V1\Value\Requires;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read-only view of what is active on the site, answered once per request.
 */
interface Plugin_Presence
{
    public function plugin(string $file): bool;

    /** @param Requires|array<string, mixed>|null $requires */
    public function satisfies(Requires|array|null $requires): bool;
}
