<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Attaches compiled hooks (#[Hook], #[Cron], #[On_Rollback] rows of
 * boot.php) without loading any module class: static methods as
 * [Class, method], instance methods through a trampoline that resolves the
 * instance from the container when the hook fires. A row is skipped when
 * its module is not active in this request or its contexts do not match.
 */
final class Hook_Loader
{
    private readonly \Closure $adder;

    /**
     * @param (callable(string, array{0: string, 1: string}|\Closure, int, int, bool): mixed)|null $adder defaults to add_filter() / add_action()
     */
    public function __construct(
        private readonly Container $container,
        private readonly Request_Context $context,
        ?callable $adder = null,
    ) {
        $this->adder = \Closure::fromCallable($adder ?? [self::class, 'wordpress_adder']);
    }

    /**
     * @param iterable<array<string, mixed>> $hooks compiled rows
     * @param callable(string): bool         $module_active
     * @return int how many were attached
     */
    public function attach(iterable $hooks, callable $module_active): int
    {
        $attached = 0;
        foreach ($hooks as $row) {
            if (! $module_active((string) ($row['module'] ?? ''))) {
                continue;
            }
            /** @var list<string> $contexts */
            $contexts = (array) ($row['contexts'] ?? []);
            if (! $this->context->matches($contexts)) {
                continue;
            }
            $added = ($this->adder)(
                (string) $row['hook'],
                $this->callback((string) $row['class'], (string) $row['method'], (bool) ($row['static'] ?? false)),
                (int) ($row['priority'] ?? 10),
                (int) ($row['args'] ?? 1),
                (bool) ($row['filter'] ?? false)
            );
            if (false !== $added) {
                ++$attached;
            }
        }
        return $attached;
    }

    /**
     * Not typed callable on purpose: checking [Class, method] for
     * callability autoloads Class, which is exactly what this avoids. The
     * docblock type says callable only for static analysis.
     *
     * @return callable-array|\Closure
     */
    private function callback(string $class, string $method, bool $static): array|\Closure
    {
        if ($static) {
            /** @var callable-array $pair a static method the generator validated */
            $pair = [$class, $method];
            return $pair;
        }
        $container = $this->container;
        return static function (mixed ...$args) use ($container, $class, $method): mixed {
            /** @var class-string $class */
            return $container->get($class)->{$method}(...$args);
        };
    }

    /** @param callable-array|\Closure $callback */
    private static function wordpress_adder(string $hook, array|\Closure $callback, int $priority, int $args, bool $filter): bool
    {
        if (! function_exists('add_filter')) {
            return false;
        }
        if ($filter || ! function_exists('add_action')) {
            add_filter($hook, $callback, $priority, $args);
        } else {
            add_action($hook, $callback, $priority, $args);
        }
        return true;
    }
}
