<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * An ability handler that resolves its class through the container only
 * when called. It is invokable, so ($ability->handler)(...) in
 * Registrar::throttled() and call_user_func() keep working; target() gives
 * tests and Tool_Search the [class, method] pair today's arrays carry.
 */
final class Lazy_Handler
{
    public function __construct(
        private readonly Container $container,
        private readonly string $class,
        private readonly string $method,
    ) {
    }

    public function __invoke(mixed ...$args): mixed
    {
        /** @var class-string $class */
        $class = $this->class;
        return $this->container->get($class)->{$this->method}(...$args);
    }

    /** @return array{0: string, 1: string} */
    public function target(): array
    {
        return [$this->class, $this->method];
    }
}
