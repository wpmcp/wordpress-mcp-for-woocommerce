<?php

declare(strict_types=1);

namespace WPMCP\Safety;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The strings a Code_Fields implementation lists. Pure functions.
 */
final class Code_Values
{
    /** A code value as Code_Fields::code() lists it: the kind of place, a NUL byte, the code. */
    public static function of(string $kind, string $code): string
    {
        return $kind . "\0" . $code;
    }

    /**
     * The tag an attribute pair prints as, for Code_Fields::markup(). An
     * anchor, so kses judges a URL value's protocol as well as the name.
     */
    public static function attribute(string $name, string $value): string
    {
        return strtr('<a NAME="VALUE"></a>', ['NAME' => $name, 'VALUE' => esc_attr($value)]);
    }

    /**
     * The tag an attribute prints as when the builder prints its value
     * without escaping it, for Code_Fields::markup(): a quote in the value
     * then closes the attribute and opens another.
     */
    public static function raw_attribute(string $name, string $value): string
    {
        return strtr('<a NAME="VALUE"></a>', ['NAME' => $name, 'VALUE' => $value]);
    }

    /**
     * The element a stored tag name opens, for Code_Fields::markup(): a
     * builder that prints the name as given turns its element into a
     * script with 'script', or adds attributes with a name that holds them.
     */
    public static function element(string $tag): string
    {
        return '<' . $tag . '></' . $tag . '>';
    }
}
