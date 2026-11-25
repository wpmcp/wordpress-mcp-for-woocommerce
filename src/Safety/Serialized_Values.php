<?php

declare(strict_types=1);

namespace WPMCP\Safety;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * PHP serialized data in stored values, for
 * Kses_Safety::serialized_refusal(). WordPress unserializes a meta value
 * that is serialized data when it reads it, so such a string is rebuilt as
 * whatever it describes. Decoding here never builds an object of a real
 * class (allowed_classes is false). Pure functions of the argument.
 */
final class Serialized_Values
{
    /** How deep serialized data inside serialized data is followed. */
    private const MAX_DEPTH = 8;

    /**
     * Every string in a value that is serialized data, and every one inside
     * those once decoded.
     *
     * @param mixed $value Decoded data (open data: strings, arrays, objects).
     * @return list<string>
     */
    public static function in(mixed $value, int $depth = 0): array
    {
        $out = [];
        foreach (Decoded_Tree::strings($value) as $string) {
            if (! is_serialized($string)) {
                continue;
            }
            $out[] = $string;
            if ($depth < self::MAX_DEPTH) {
                array_push($out, ...self::in(self::decode($string), $depth + 1));
            }
        }

        return $out;
    }

    /**
     * What serialized data describes without its scalar values: the type of
     * each value, array keys, and object classes.
     */
    public static function shape(string $serialized): string
    {
        return (string) wp_json_encode(self::skeleton(self::decode($serialized)));
    }

    /** @return mixed The decoded data, objects as incomplete ones. */
    private static function decode(string $serialized): mixed
    {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged -- reads what a value describes; no object of a real class is built.
        return @unserialize($serialized, ['allowed_classes' => false]);
    }

    private static function skeleton(mixed $value): mixed
    {
        if (is_object($value)) {
            $fields = (array) $value;
            $class  = $fields['__PHP_Incomplete_Class_Name'] ?? get_class($value);
            unset($fields['__PHP_Incomplete_Class_Name']);

            return ['object' => is_string($class) ? $class : '', 'fields' => array_map([self::class, 'skeleton'], $fields)];
        }
        if (is_array($value)) {
            return ['array' => array_map([self::class, 'skeleton'], $value)];
        }

        return gettype($value);
    }
}
