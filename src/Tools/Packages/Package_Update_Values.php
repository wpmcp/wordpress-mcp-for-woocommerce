<?php

declare(strict_types=1);

namespace WPMCP\Tools\Packages;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Typed reads of the loosely typed values safe package updates keep in an
 * option or receive as input (issue #843): each returns the expected type
 * or the default, so a damaged stored run never reaches the update code as
 * the wrong type. Pure functions, no state.
 */
final class Package_Update_Values
{
    private function __construct()
    {
    }

    public static function str(mixed $value, string $default = ''): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : $default;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_int($value) || (is_string($value) && is_numeric($value)) || is_float($value) ? (int) $value : $default;
    }

    /** True for a value PHP's empty() would call non-empty. */
    public static function flag(mixed $value): bool
    {
        return null !== $value && false !== $value && 0 !== $value && '' !== $value && '0' !== $value && [] !== $value && 0.0 !== $value;
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $key => $item) {
            $out[ (string) $key ] = $item;
        }
        return $out;
    }

    /** @return list<string> */
    public static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item) || is_int($item)) {
                $out[] = (string) $item;
            }
        }
        return $out;
    }
}
