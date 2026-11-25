<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

use WPMCP\Api\V1\Version;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The compiled registry: one or more roots (free, then Pro, then add-ons),
 * each a directory of plain `return [...]` files written by
 * bin/build-registry.php. boot.php is read on construction; abilities.php
 * and ops/<slug>.php only when asked. Roots are read in sequence, never
 * array-merged: a later root that reuses a module id or an ability name is
 * rejected for that entry, and the reason is kept in rejections().
 *
 * The kernel never names a module class; it only hands out class-strings.
 */
final class Registry
{
    private const LIST_KEYS = ['hooks', 'admin_pages', 'ajax', 'admin_post'];
    private const MAP_KEYS = ['opt_in_gates', 'factories', 'bindings', 'snapshot_types', 'table_kinds', 'dispatchers'];

    /** @var array<string, array<string, mixed>> */
    private array $modules = [];

    /** @var array<string, array<int, mixed>> */
    private array $lists = [];

    /** @var array<string, array<string, mixed>> */
    private array $maps = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $providers = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $families = [];

    /** @var array<string, string> namespace prefix => module id, longest first */
    private array $namespaces = [];

    /** @var list<string> */
    private array $rejections = [];

    private string $hash = '';
    private string $flavor = 'full';

    /** @var list<array{dir: ?string, rejected: list<string>, abilities: ?array<int, array<string, mixed>>, ops: array<string, list<array<string, mixed>>>}> */
    private array $roots = [];

    /** @var list<array<string, mixed>>|null */
    private ?array $abilities = null;

    /** @var array<string, list<array<string, mixed>>> */
    private array $ops = [];

    private function __construct()
    {
    }

    /** @param list<string> $dirs */
    public static function from_dirs(array $dirs): self
    {
        $registry = new self();
        foreach ($dirs as $dir) {
            $file = rtrim($dir, '/') . '/boot.php';
            if (! is_file($file)) {
                continue;
            }
            $boot = require $file;
            if (is_array($boot)) {
                $registry->add_root($boot, rtrim($dir, '/'), null, []);
            }
        }
        return $registry;
    }

    /**
     * Test seam: one root built from arrays.
     *
     * @param array<string, mixed>                       $boot
     * @param list<array<string, mixed>>                 $abilities
     * @param array<string, list<array<string, mixed>>>  $ops
     */
    public static function from_arrays(array $boot, array $abilities = [], array $ops = []): self
    {
        $registry = new self();
        $registry->add_root($boot + ['format' => Version::REGISTRY_FORMAT], null, $abilities, $ops);
        return $registry;
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function flavor(): string
    {
        return $this->flavor;
    }

    /** @return array<string, array<string, mixed>> */
    public function modules(): array
    {
        return $this->modules;
    }

    /** @return array<string, mixed>|null */
    public function module(string $id): ?array
    {
        return $this->modules[ $id ] ?? null;
    }

    public function has_module(string $id): bool
    {
        return isset($this->modules[ $id ]);
    }

    /** @return list<array<string, mixed>> */
    public function hooks(): array
    {
        return array_values($this->lists['hooks'] ?? []);
    }

    /** @return list<array<string, mixed>> */
    public function list(string $key): array
    {
        return array_values($this->lists[ $key ] ?? []);
    }

    /** @return array<string, mixed> */
    public function map(string $key): array
    {
        return $this->maps[ $key ] ?? [];
    }

    /** @return array<string, array<string, mixed>> */
    public function opt_in_gates(): array
    {
        return $this->map('opt_in_gates');
    }

    /** @return array<string, list<string>> */
    public function factories(): array
    {
        return $this->map('factories');
    }

    /** @return array<string, string> */
    public function bindings(): array
    {
        return $this->map('bindings');
    }

    /** @return list<array<string, mixed>> providers of one interface, in priority order */
    public function providers(string $interface): array
    {
        return $this->providers[ $interface ] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function family(string $family): array
    {
        return $this->families[ $family ] ?? [];
    }

    /** The module owning a class, by namespace, or null for kernel, platform and foreign classes. */
    public function owner_of_class(string $class): ?string
    {
        $class = ltrim($class, '\\');
        foreach ($this->namespaces as $prefix => $module) {
            if (str_starts_with($class, $prefix)) {
                return $module;
            }
        }
        return null;
    }

    /** @return list<string> */
    public function rejections(): array
    {
        return $this->rejections;
    }

    /**
     * Ability descriptors of every root, in order. Loaded on first call.
     *
     * @return list<array<string, mixed>>
     */
    public function abilities(): array
    {
        if (null !== $this->abilities) {
            return $this->abilities;
        }
        $seen = [];
        $out = [];
        foreach ($this->roots as $root) {
            $rows = $root['abilities'] ?? self::read_file($root['dir'], 'abilities.php');
            foreach ($rows as $row) {
                $name = (string) ($row['name'] ?? '');
                if (in_array((string) ($row['module'] ?? ''), $root['rejected'], true)) {
                    continue;
                }
                if (isset($seen[ $name ])) {
                    $this->rejections[] = sprintf('Ability %s from module %s was rejected: already declared by %s.', $name, (string) ($row['module'] ?? ''), $seen[ $name ]);
                    continue;
                }
                $seen[ $name ] = (string) ($row['module'] ?? '');
                $out[] = $row;
            }
        }
        return $this->abilities = $out;
    }

    /**
     * Contributors of one dispatcher pair from every root, in order.
     *
     * @return list<array<string, mixed>>
     */
    public function operations(string $dispatcher): array
    {
        if (isset($this->ops[ $dispatcher ])) {
            return $this->ops[ $dispatcher ];
        }
        if (1 !== preg_match('/^[a-z0-9-]+$/', $dispatcher)) {
            return [];
        }
        $out = [];
        foreach ($this->roots as $root) {
            $rows = $root['ops'][ $dispatcher ] ?? (null === $root['dir'] ? [] : self::read_file($root['dir'], 'ops/' . $dispatcher . '.php'));
            foreach ($rows as $row) {
                if (! in_array((string) ($row['module'] ?? ''), $root['rejected'], true)) {
                    $out[] = $row;
                }
            }
        }
        return $this->ops[ $dispatcher ] = $out;
    }

    /**
     * A per-root data file (data.php, refusal-plugins.php, skills.php), keyed
     * entries of earlier roots winning.
     *
     * @return array<int|string, mixed>
     */
    public function data_file(string $name): array
    {
        $out = [];
        foreach ($this->roots as $root) {
            foreach (self::read_file($root['dir'], $name . '.php') as $key => $row) {
                if (is_int($key)) {
                    $out[] = $row;
                } elseif (! array_key_exists($key, $out)) {
                    $out[ $key ] = $row;
                }
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed>                       $boot
     * @param list<array<string, mixed>>|null            $abilities
     * @param array<string, list<array<string, mixed>>>  $ops
     */
    private function add_root(array $boot, ?string $dir, ?array $abilities, array $ops): void
    {
        $label = $dir ?? 'array root';
        if (Version::REGISTRY_FORMAT !== (int) ($boot['format'] ?? 0)) {
            $this->rejections[] = sprintf('Registry %s was rejected: format %s, expected %d.', $label, (string) json_encode($boot['format'] ?? null), Version::REGISTRY_FORMAT);
            return;
        }

        $accepted = [];
        $rejected = [];
        foreach ((array) ($boot['modules'] ?? []) as $id => $module) {
            $id = (string) $id;
            if (isset($this->modules[ $id ])) {
                $this->rejections[] = sprintf('Module %s from %s was rejected: the id is already registered.', $id, $label);
                $rejected[] = $id;
                continue;
            }
            $this->modules[ $id ] = (array) $module;
            $accepted[] = $id;
        }
        if ('' === $this->hash) {
            $this->hash = (string) ($boot['hash'] ?? '');
            $this->flavor = (string) ($boot['flavor'] ?? 'full');
        }

        $mine = static fn (mixed $row): bool => is_array($row) && ! in_array((string) ($row['module'] ?? ''), $rejected, true);
        foreach (self::LIST_KEYS as $key) {
            foreach ((array) ($boot[ $key ] ?? []) as $row) {
                if ($mine($row)) {
                    $this->lists[ $key ][] = $row;
                }
            }
        }
        foreach (self::MAP_KEYS as $key) {
            foreach ((array) ($boot[ $key ] ?? []) as $k => $row) {
                if (isset($this->maps[ $key ][ $k ])) {
                    $this->rejections[] = sprintf('%s entry %s from %s was rejected: already registered.', $key, (string) $k, $label);
                    continue;
                }
                if (is_array($row) && isset($row['module']) && ! $mine($row)) {
                    continue;
                }
                $this->maps[ $key ][ (string) $k ] = $row;
            }
        }
        foreach ((array) ($boot['providers'] ?? []) as $interface => $rows) {
            foreach ((array) $rows as $row) {
                if ($mine($row)) {
                    $this->providers[ (string) $interface ][] = $row;
                }
            }
            usort($this->providers[ (string) $interface ], static fn (array $a, array $b): int => ((int) ($a['priority'] ?? 10)) <=> ((int) ($b['priority'] ?? 10)));
        }
        foreach ((array) ($boot['families'] ?? []) as $family => $rows) {
            foreach ((array) $rows as $row) {
                if ($mine($row)) {
                    $this->families[ (string) $family ][] = $row;
                }
            }
        }
        foreach ((array) (($boot['ownership'] ?? [])['namespaces'] ?? []) as $prefix => $module) {
            if (! in_array((string) $module, $rejected, true)) {
                $this->namespaces[ (string) $prefix ] = (string) $module;
            }
        }
        uksort($this->namespaces, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));

        $this->roots[] = ['dir' => $dir, 'rejected' => $rejected, 'abilities' => $abilities, 'ops' => $ops];
    }

    /** @return array<int|string, array<string, mixed>> */
    private static function read_file(?string $dir, string $name): array
    {
        if (null === $dir) {
            return [];
        }
        $file = $dir . '/' . $name;
        if (! is_file($file)) {
            return [];
        }
        $data = require $file;
        return is_array($data) ? $data : [];
    }
}
