<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The operation catalog of one dispatcher pair, read from the compiled
 * ops/<slug>.php. Summary, hosts, op names and has_destructive come from
 * compiled metadata and run no contributor code. operations() calls each
 * permitted, available contributor's method on first use, in order, and
 * merges them with today's array_merge semantics (a later contributor's op
 * replaces an earlier one of the same name; the generator reports those).
 */
final class Operation_Registry
{
    /**
     * Definition keys whose value may be the name of an instance method on
     * the contributor instead of a callable. Metadata stays static and pure
     * (the generator evaluates it); the work runs on an injected instance.
     */
    private const METHOD_KEYS = ['handler', 'validate', 'snapshot', 'confirm_preview', 'requires'];

    private readonly \Closure $tier_permitted;
    private readonly ?\Closure $variant;

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $memo = [];

    /**
     * @param (callable(string): bool)|null      $tier_permitted defaults to every tier
     * @param (callable(string): ?string)|null   $variant        dispatcher slug => active variant (theme framework detection)
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly Presence $presence,
        private readonly Container $container,
        ?callable $tier_permitted = null,
        ?callable $variant = null,
    ) {
        $this->tier_permitted = \Closure::fromCallable($tier_permitted ?? static fn (string $tier): bool => true);
        $this->variant = null === $variant ? null : \Closure::fromCallable($variant);
    }

    /**
     * Permitted contributors of the pair, in order.
     *
     * @return list<array<string, mixed>>
     */
    public function contributors(string $dispatcher): array
    {
        $out = [];
        foreach ($this->registry->operations($dispatcher) as $row) {
            if ($this->permitted((string) ($row['tier'] ?? 'free')) && $this->variant_matches($dispatcher, $row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    public function any_available(string $dispatcher): bool
    {
        foreach ($this->contributors($dispatcher) as $row) {
            if ($this->available($row)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $row */
    public function available(array $row): bool
    {
        $predicate = $row['availability'] ?? null;
        if (is_string($predicate) && '' !== $predicate) {
            /** @var callable(): mixed $check a static method the generator validated */
            $check = [(string) $row['class'], $predicate];
            return (bool) call_user_func($check);
        }
        return $this->presence->satisfies(is_array($row['requires'] ?? null) ? $row['requires'] : null);
    }

    /**
     * The pair's summary: every shipped contributor's fragment, joined with
     * ', ' in summary_order. Tier does not narrow it, as today's literal
     * summary() strings are not narrowed either.
     */
    public function summary(string $dispatcher): string
    {
        $rows = array_filter(
            $this->registry->operations($dispatcher),
            static fn (array $row): bool => is_string($row['summary'] ?? null) && '' !== $row['summary']
        );
        usort($rows, static fn (array $a, array $b): int => ((int) ($a['summary_order'] ?? $a['order'] ?? 0)) <=> ((int) ($b['summary_order'] ?? $b['order'] ?? 0)));

        return implode(', ', array_map(static fn (array $row): string => (string) $row['summary'], $rows));
    }

    /**
     * Op name => the plugin it needs, for refusals while no host is active
     * (today's operation_hosts()). Ops this install's tier may not run are
     * left out; a later contributor's row replaces an earlier one.
     *
     * @return array<string, array{name: string, several: bool}>
     */
    public function hosts(string $dispatcher): array
    {
        $hosts = [];
        foreach ($this->contributors($dispatcher) as $row) {
            foreach ($this->permitted_ops($row) as $op => $meta) {
                $name = is_array($row['hosts'] ?? null) && isset($row['hosts'][ $op ]) ? (string) $row['hosts'][ $op ] : (string) ($row['host'] ?? '');
                if ('' === $name) {
                    continue;
                }
                $hosts[ $op ] = ['name' => $name, 'several' => (bool) ($row['several'] ?? false)];
            }
        }
        return $hosts;
    }

    /** @return list<string> op names in merge order, duplicates kept at their first position */
    public function operation_names(string $dispatcher): array
    {
        $names = [];
        foreach ($this->contributors($dispatcher) as $row) {
            foreach (array_keys($this->permitted_ops($row)) as $op) {
                $names[ (string) $op ] = true;
            }
        }
        return array_keys($names);
    }

    public function has_destructive(string $dispatcher): bool
    {
        foreach ($this->contributors($dispatcher) as $row) {
            foreach ($this->permitted_ops($row) as $meta) {
                if ('destructive' === ($meta['mode'] ?? '')) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * The full operation definitions (closures included), from every
     * permitted and available contributor, memoized for the request. An op
     * with an opt_in_filter is off until that filter opens it, and its
     * enabled_by_default is read from the filter on every call, so a switch
     * turned on after the first read is not hidden by the memo.
     *
     * @return array<string, array<string, mixed>>
     */
    public function operations(string $dispatcher): array
    {
        return array_map(self::with_live_opt_in(...), $this->definitions($dispatcher));
    }

    /**
     * @param array<string, mixed> $def
     * @return array<string, mixed>
     */
    private static function with_live_opt_in(array $def): array
    {
        $filter = $def['opt_in_filter'] ?? null;
        if (! is_string($filter) || '' === $filter) {
            return $def;
        }
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the op's own declared wpmcp_* opt-in filter, as in MCP\Availability.
        $def['enabled_by_default'] = (bool) apply_filters($filter, false);
        return $def;
    }

    /**
     * The memoized definitions behind operations().
     *
     * @return array<string, array<string, mixed>>
     */
    private function definitions(string $dispatcher): array
    {
        if (isset($this->memo[ $dispatcher ])) {
            return $this->memo[ $dispatcher ];
        }
        $ops = [];
        foreach ($this->contributors($dispatcher) as $row) {
            if (! $this->available($row)) {
                continue;
            }
            $class = (string) $row['class'];
            $method = (string) ($row['method'] ?? 'operations');
            /**
             * @var class-string $class
             * @var callable(): mixed $static a static method the generator validated
             */
            $static = [$class, $method];
            $defs = (bool) ($row['static'] ?? true)
                ? (array) call_user_func($static)
                : (array) $this->container->get($class)->{$method}();
            foreach ($defs as $name => $def) {
                if ($this->permitted((string) ($def['tier'] ?? 'free'))) {
                    $ops[ (string) $name ] = $this->bind_methods($class, (array) $def);
                }
            }
        }
        return $this->memo[ $dispatcher ] = $ops;
    }

    /**
     * @param array<string, mixed> $def
     * @return array<string, mixed>
     */
    private function bind_methods(string $class, array $def): array
    {
        foreach (self::METHOD_KEYS as $key) {
            $method = $def[ $key ] ?? null;
            if (! is_string($method) || '' === $method || ! method_exists($class, $method)) {
                continue;
            }
            $container = $this->container;
            $def[ $key ] = static function (mixed ...$args) use ($container, $class, $method): mixed {
                /** @var class-string $class */
                return $container->get($class)->{$method}(...$args);
            };
        }
        return $def;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, array<string, mixed>>
     */
    private function permitted_ops(array $row): array
    {
        $out = [];
        foreach ((array) ($row['ops'] ?? []) as $op => $meta) {
            $meta = (array) $meta;
            if ($this->permitted((string) ($meta['tier'] ?? 'free'))) {
                $out[ (string) $op ] = $meta;
            }
        }
        return $out;
    }

    private function permitted(string $tier): bool
    {
        return (bool) ($this->tier_permitted)($tier);
    }

    /** @param array<string, mixed> $row */
    private function variant_matches(string $dispatcher, array $row): bool
    {
        $variant = $row['variant'] ?? null;
        if (! is_string($variant) || '' === $variant) {
            return true;
        }
        return null !== $this->variant && ($this->variant)($dispatcher) === $variant;
    }
}
