<?php

declare(strict_types=1);

namespace WPMCP\Safety;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Walks decoded builder data (JSON trees, unserialized arrays, objects) for
 * the write checks: every string in it, and every array or object in it.
 * Pure functions of the argument.
 */
final class Decoded_Tree
{
    /**
     * Every string in a decoded value, objects and arrays walked.
     *
     * @param mixed $value Decoded data (open data: trees and arrays of any shape).
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }
        $out = [];
        foreach (self::fields($value) as $item) {
            array_push($out, ...self::strings($item));
        }

        return $out;
    }

    /**
     * Every array or object in a decoded value, the value itself first and
     * then depth first, objects read as arrays of their properties.
     *
     * @param mixed $value Decoded data (open data: trees and arrays of any shape).
     * @return list<array<mixed>>
     */
    public static function nodes(mixed $value): array
    {
        if (! is_array($value) && ! is_object($value)) {
            return [];
        }
        $items = self::fields($value);
        $out   = [$items];
        foreach ($items as $item) {
            array_push($out, ...self::nodes($item));
        }

        return $out;
    }

    /**
     * A decoded node's fields: an array as is, an object's properties,
     * anything else nothing.
     *
     * @param mixed $value Decoded data.
     * @return array<mixed>
     */
    public static function fields(mixed $value): array
    {
        if (is_object($value)) {
            return get_object_vars($value);
        }

        return is_array($value) ? $value : [];
    }
}
