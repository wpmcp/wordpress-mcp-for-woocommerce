<?php

declare(strict_types=1);

namespace WPMCP\Kernel;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * How a legacy central list takes in the compiled registry's rows: the
 * literal rows first, in their order, then the registry's. A key in both is
 * a mistake (an integration listed by hand and declared by a module), so it
 * throws instead of letting one silently win. Pure and stateless.
 */
final class Registry_Rows
{
    /**
     * @template K of array-key
     * @template T
     * @param array<K, T> $literal  the central list's own rows
     * @param array<K, T> $registry rows compiled from module attributes
     * @param string      $what     what a row is, for the message ("opt-in group")
     * @return array<K, T>
     */
    public static function merge(array $literal, array $registry, string $what): array
    {
        $duplicates = array_intersect_key($registry, $literal);
        if ([] !== $duplicates) {
            $message = sprintf(
                '%s %s is declared both in a central list and by a module. Keep the module declaration and remove the literal row (docs/CODING-STANDARD.md, How to add an integration).',
                $what,
                implode(', ', array_map('strval', array_keys($duplicates))),
            );
            throw new \LogicException($message); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- a developer error, never echoed.
        }
        return $literal + $registry;
    }
}
