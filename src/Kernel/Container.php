<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The compiled container. The generator reflects constructors at build time
 * and writes class => list of dependency class-strings; interfaces resolve
 * through the bindings from #[Provides]. Nothing is reflected at runtime.
 * Instances are memoized for the request. Outside strict mode a class
 * missing from the map is built with no arguments, as every handler is
 * today; tests run strict so a missing entry fails there first.
 */
final class Container
{
    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $building = [];

    /**
     * @param array<string, list<class-string>> $factories class => constructor dependency ids, in order
     * @param array<string, class-string>       $bindings  interface => implementing class
     */
    public function __construct(
        private readonly array $factories = [],
        private readonly array $bindings = [],
        private readonly bool $strict = false,
    ) {
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[ $id ])) {
            /** @var T */
            return $this->instances[ $id ];
        }
        if (isset($this->building[ $id ])) {
            throw new Container_Error(sprintf('Dependency cycle while building %s.', $id)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- an exception message for developers, never echoed.
        }

        $this->building[ $id ] = true;
        try {
            $instance = $this->build($id);
        } finally {
            unset($this->building[ $id ]);
        }

        $this->instances[ $id ] = $instance;
        /** @var T */
        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[ $id ]) || isset($this->factories[ $id ]) || isset($this->bindings[ $id ]);
    }

    /** Presets an instance (the kernel's own services, or a fake in a test). */
    public function set(string $id, object $instance): void
    {
        $this->instances[ $id ] = $instance;
    }

    private function build(string $id): object
    {
        if (isset($this->bindings[ $id ])) {
            return $this->get($this->bindings[ $id ]);
        }
        if (isset($this->factories[ $id ])) {
            $args = [];
            foreach ($this->factories[ $id ] as $dependency) {
                $args[] = $this->get($dependency);
            }
            return new $id(...$args);
        }
        if (interface_exists($id)) {
            throw new Container_Error(sprintf('No binding for interface %s.', $id)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- an exception message for developers, never echoed.
        }
        if ($this->strict) {
            throw new Container_Error(sprintf('%s is not in the compiled factory map; run composer registry:build.', $id)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- an exception message for developers, never echoed.
        }
        if (! class_exists($id)) {
            throw new Container_Error(sprintf('Class %s does not exist.', $id)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- an exception message for developers, never echoed.
        }

        return new $id();
    }
}
