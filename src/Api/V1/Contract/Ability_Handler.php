<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Contract;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * An #[Ability] handler. The kernel resolves it lazily through the
 * compiled container the first time the ability is called.
 */
interface Ability_Handler
{
    /** @param array<string, mixed> $input */
    public function handle(array $input): mixed;
}
