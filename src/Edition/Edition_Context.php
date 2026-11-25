<?php

declare(strict_types=1);

namespace WPMCP\Edition;

use Closure;
use WPMCP\MCP\Registrar;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * What a slot may use from the plugin: the registrar of the registration
 * pass (null outside one), the ability group switch and the integration
 * pair registration. Passed in, so a contributor never reaches for the
 * plugin singleton.
 */
final class Edition_Context
{
    /**
     * @param Closure(string): bool                    $group_enabled
     * @param Closure(Registrar, list<object>): void   $register_integrations
     */
    public function __construct(
        public readonly ?Registrar $registrar,
        private readonly Closure $group_enabled,
        private readonly Closure $register_integrations,
    ) {
    }

    public function group_enabled(string $group): bool
    {
        return ($this->group_enabled)($group);
    }

    /** @param list<object> $integrations */
    public function register_integrations(Registrar $registrar, array $integrations): void
    {
        ($this->register_integrations)($registrar, $integrations);
    }
}
