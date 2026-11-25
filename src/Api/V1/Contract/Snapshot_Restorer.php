<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Contract;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Captures and restores one #[Snapshot_Type] of state.
 */
interface Snapshot_Restorer
{
    /**
     * @param array<string, mixed> $target
     * @return array<string, mixed>
     */
    public function capture(array $target): array;

    /** @param array<string, mixed> $snapshot */
    public function restore(array $snapshot): bool|\WP_Error;
}
