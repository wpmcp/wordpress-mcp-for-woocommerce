<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

use WPMCP\MCP\Ability;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Turns compiled ability descriptors (abilities.php) into WPMCP\MCP\Ability
 * objects whose handler is a Lazy_Handler, so registering every ability
 * loads no handler class. objects and op_capability become closures that
 * resolve the handler only when the permission check calls them. Order is
 * the compiled order (D5). A descriptor's condition is a static callable
 * run here, at the same point today's registration-time probes run.
 */
final class Ability_Loader
{
    private readonly ?\Closure $tier_for;

    /**
     * @param (callable(string, string): string)|null $tier_for ability name, compiled tier => tier (flavor overrides)
     */
    public function __construct(
        private readonly Container $container,
        ?callable $tier_for = null,
    ) {
        $this->tier_for = null === $tier_for ? null : \Closure::fromCallable($tier_for);
    }

    /**
     * @param iterable<array<string, mixed>>               $descriptors
     * @param (callable(array<string, mixed>): bool)|null $include
     * @return list<Ability>
     */
    public function load(iterable $descriptors, ?callable $include = null): array
    {
        $out = [];
        foreach ($descriptors as $d) {
            if (null !== $include && ! $include($d)) {
                continue;
            }
            if (isset($d['condition']) && is_array($d['condition'])) {
                /** @var callable-array $condition a [class, static method] pair the generator validated */
                $condition = $d['condition'];
                if (! (bool) call_user_func($condition)) {
                    continue;
                }
            }
            $out[] = $this->ability($d);
        }
        return $out;
    }

    /** @param array<string, mixed> $d */
    public function ability(array $d): Ability
    {
        $name = (string) $d['name'];
        $class = (string) $d['class'];
        $tier = (string) ($d['tier'] ?? 'free');
        if (null !== $this->tier_for) {
            $tier = ($this->tier_for)($name, $tier);
        }

        return new Ability(
            $name,
            $tier,
            (string) ($d['description'] ?? ''),
            (array) ($d['input'] ?? ['type' => 'object']),
            new Lazy_Handler($this->container, $class, (string) ($d['method'] ?? 'handle')),
            (string) ($d['capability'] ?? 'edit_posts'),
            (string) ($d['domain'] ?? 'content'),
            (string) ($d['operation'] ?? 'read'),
            isset($d['read_only']) ? (bool) $d['read_only'] : null,
            isset($d['destructive']) ? (bool) $d['destructive'] : null,
            isset($d['idempotent']) ? (bool) $d['idempotent'] : null,
            array_values(array_map('strval', (array) ($d['read_keys'] ?? []))),
            $this->method_closure($class, $d['objects'] ?? null),
            $this->method_closure($class, $d['op_capability'] ?? null),
        );
    }

    private function method_closure(string $class, mixed $method): ?\Closure
    {
        if (! is_string($method) || '' === $method) {
            return null;
        }
        $container = $this->container;
        return static function (array $input) use ($container, $class, $method): mixed {
            /** @var class-string $class */
            return $container->get($class)->{$method}($input);
        };
    }
}
