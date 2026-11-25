<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The kernel boot (D11), held by the legacy Plugin (Plugin::kernel()): it
 * loads the compiled registry (build/registry, written by
 * bin/build-registry.php and shipped by every release build) once per
 * request and exposes it, so the legacy central lists can take in what
 * modules declare, and attaches the #[On_Rollback] rows next to the legacy
 * rollback refreshers. Nothing else is wired yet: other hooks and abilities
 * still register the legacy way.
 */
final class Kernel_Boot
{
    private ?Registry $kernel_registry = null;

    private ?Operation_Registry $kernel_operations = null;

    private ?Container $kernel_container = null;

    /** @var array<string, ?string> dispatcher => active variant, for this request */
    private array $kernel_variants = [];

    /**
     * The compiled registry; empty (no modules) when build/registry is absent,
     * which a development checkout reports (Registry_Health).
     */
    public function registry(): Registry
    {
        if (null === $this->kernel_registry) {
            (new Registry_Health(dirname(self::registry_dir(), 2)))->report();
        }
        return $this->kernel_registry ??= Registry::from_dirs([self::registry_dir(), ...\WPMCP\Edition\Edition::registry_roots()]);
    }

    /**
     * The operations modules contribute to a legacy dispatcher pair
     * ('theme', 'plugin-data', ...), already permitted and available.
     *
     * @param ?string $variant the pair's active variant (the detected theme framework)
     * @return array<string, array<string, mixed>>
     */
    public function module_operations(string $dispatcher, ?string $variant = null): array
    {
        $this->kernel_variants[ $dispatcher ] = $variant;
        $variants = &$this->kernel_variants;
        $this->kernel_operations ??= new Operation_Registry(
            $this->registry(),
            new Presence(),
            $this->container(),
            static fn (string $tier): bool => self::tier_permitted($tier),
            static function (string $pair) use (&$variants): ?string {
                return $variants[ $pair ] ?? null;
            },
        );
        return $this->kernel_operations->operations($dispatcher);
    }

    /**
     * Attaches the rollback refreshers modules declare with #[On_Rollback],
     * as Plugin::register_rollback_refreshers() wires its own list: only for
     * the modules this build ships, whose tier this install may run and
     * whose Requires the site meets. An instance method is resolved from the
     * container when the rollback fires, so no module class loads before.
     *
     * @param (callable(string, array{0: string, 1: string}|\Closure, int, int, bool): mixed)|null $adder test seam; defaults to add_action()
     * @return int how many were attached
     */
    public function attach_rollback_hooks(?callable $adder = null): int
    {
        $registry = $this->registry();
        $presence = new Presence();
        $rows = array_filter($registry->hooks(), static fn (array $row): bool => 'rollback' === ($row['kind'] ?? null));
        $active = static function (string $id) use ($registry, $presence): bool {
            $module = $registry->module($id);
            if (null === $module || ! self::tier_permitted(is_string($module['tier'] ?? null) ? $module['tier'] : 'free')) {
                return false;
            }
            /** @var array<string, mixed>|null $requires the generator writes a Requires as its named fields */
            $requires = is_array($module['requires'] ?? null) ? $module['requires'] : null;
            return $presence->satisfies($requires);
        };
        return (new Hook_Loader($this->container(), new Request_Context(), $adder))->attach($rows, $active);
    }

    /** The container over the compiled factories and bindings, one per request. */
    private function container(): Container
    {
        /**
         * The generator writes only class-strings it reflected.
         *
         * @var array<string, list<class-string>> $factories
         * @var array<string, class-string> $bindings
         */
        [$factories, $bindings] = [$this->registry()->factories(), $this->registry()->bindings()];
        return $this->kernel_container ??= new Container($factories, $bindings);
    }

    private static function tier_permitted(string $tier): bool
    {
        return \WPMCP\MCP\Registrar::tier_permitted($tier);
    }

    /** Test seam: a registry built from arrays, or null to load build/registry again. Guarded by WPMCP_TESTING. */
    public function use_registry_for_tests(?Registry $registry): void
    {
        if (! defined('WPMCP_TESTING') || ! WPMCP_TESTING) {
            return;
        }
        $this->kernel_registry = $registry;
        $this->kernel_operations = null;
        $this->kernel_container = null;
        $this->kernel_variants = [];
    }

    private static function registry_dir(): string
    {
        $root = defined('WPMCP_DIR') && is_string(WPMCP_DIR) ? WPMCP_DIR : dirname(__DIR__, 2) . '/';
        return rtrim($root, '/') . '/build/registry';
    }
}
