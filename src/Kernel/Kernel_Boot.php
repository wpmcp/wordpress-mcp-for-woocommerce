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

    /**
     * attach_rollback_hooks(), but only once a rollback runs. Every
     * #[On_Rollback] row today names a wpmcp_rollback_* hook, which only
     * Rollback_Service fires, so a page load that rolls nothing back never
     * loads Hook_Loader, the container, Presence or Request_Context. A
     * stand-in on each of those hooks at PHP_INT_MIN attaches the rows the
     * first time one fires, ahead of their own priority, so they still run
     * in that same firing. A row on any other hook attaches them at once,
     * as before.
     */
    public function attach_rollback_hooks_on_first_rollback(): void
    {
        $hooks = [];
        foreach ($this->registry()->hooks() as $row) {
            if ('rollback' !== ($row['kind'] ?? null)) {
                continue;
            }
            $hook = is_string($row['hook'] ?? null) ? $row['hook'] : '';
            // A row at PHP_INT_MIN would share the stand-in's priority, which
            // WordPress does not re-read while it runs it.
            if (! str_starts_with($hook, 'wpmcp_rollback_') || PHP_INT_MIN === ($row['priority'] ?? 10)) {
                $this->attach_rollback_hooks();
                return;
            }
            $hooks[ $hook ] = true;
        }
        if ([] === $hooks) {
            return;
        }
        $names = array_keys($hooks);
        $attach = null;
        // Removes itself from every hook rather than remembering it ran, so
        // the rows are attached once, and attached again only when the hooks
        // were reset to a state from before (a test's hook backup). The rows
        // go in first: removing the only callback of a running hook makes
        // WordPress stop that run, before the rows it was about to add.
        $attach = function (mixed $value = null) use (&$attach, $names): mixed {
            $this->attach_rollback_hooks();
            foreach ($names as $name) {
                remove_filter($name, $attach, PHP_INT_MIN);
            }
            return $value; // the rollback filters (wpmcp_rollback_post_refusal) pass their value through
        };
        foreach ($names as $hook) {
            add_filter($hook, $attach, PHP_INT_MIN, 1);
        }
    }

    /**
     * Whether this build ships $class, without loading it: the file the
     * autoloader would read for it (src/ or pro/src/, src/Modules/ or
     * pro/modules/) exists. Otherwise the answer of class_exists(), which
     * autoloads, so a class a test defines elsewhere still counts. A guard
     * such as Plugin::register_rollback_refreshers() can then hook a
     * [Class, method] pair, which WordPress keeps as strings, without
     * loading every integration class on every request.
     */
    public static function ships(string $class): bool
    {
        if (class_exists($class, false)) {
            return true;
        }
        if (str_starts_with($class, 'WPMCP\\')) {
            $root = defined('WPMCP_DIR') && is_string(WPMCP_DIR) ? WPMCP_DIR : dirname(__DIR__, 2) . '/';
            $root = rtrim($root, '/') . '/';
            $relative = str_replace('\\', '/', substr($class, strlen('WPMCP\\'))) . '.php';
            $candidates = str_starts_with($relative, 'Modules/')
                ? ['src/' . $relative, 'pro/modules/' . substr($relative, strlen('Modules/'))]
                : ['src/' . $relative, 'pro/src/' . $relative];
            foreach ($candidates as $candidate) {
                if (is_file($root . $candidate)) {
                    return true;
                }
            }
        }
        return class_exists($class);
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
