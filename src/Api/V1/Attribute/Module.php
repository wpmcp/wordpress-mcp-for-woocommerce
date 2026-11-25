<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Attribute;

use WPMCP\Api\V1\Value\Requires;
use WPMCP\Api\V1\Value\Tier;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Declares a module on its Module class. The class itself carries no logic.
 * $order spaces modules by 100 in today's registration order; $flavors names
 * the vertical flavors that ship it ('full' always does, 'wporg' does for
 * free modules unless excluded). $owns lists legacy paths the module owns
 * during phase A (globs relative to the repository root). $contexts limits
 * Has_Boot and the module's hooks to those request contexts (empty: all).
 * $availability names a static method on the Module class implementing
 * Has_Availability's question when Requires cannot express it.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class Module
{
    /**
     * @param list<string> $flavors
     * @param list<string> $exclude_flavors
     * @param list<string> $owns
     * @param list<string> $test_plugins
     * @param list<\WPMCP\Api\V1\Value\Context|string> $contexts
     */
    public function __construct(
        public readonly string $id,
        public readonly string $group = '',
        public readonly int $order = 0,
        public readonly Tier|string $tier = Tier::Free,
        public readonly array $flavors = [],
        public readonly array $exclude_flavors = [],
        public readonly ?Requires $requires = null,
        public readonly array $owns = [],
        public readonly array $test_plugins = [],
        public readonly array $contexts = [],
        public readonly ?string $availability = null,
    ) {
    }
}
