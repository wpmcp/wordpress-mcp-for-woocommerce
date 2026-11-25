<?php

declare(strict_types=1);

namespace WPMCP\Safety;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Where a builder keeps code in its stored data, for
 * Kses_Safety::values_refusal().
 *
 * kses judges markup, so a value the builder runs as PHP or prints as a
 * script passes it unchanged when it holds no tags: a code element's PHP,
 * JavaScript, HTML and CSS, a layout's custom JavaScript, a PHP query
 * editor. WordPress lets only users with unfiltered_html store such code,
 * so a user without it may keep the code the data already holds, where it
 * holds it, and nothing more. Attributes a builder prints from a stored
 * name and value are another blind spot (each part alone is plain text),
 * so they are handed to kses as the tag they become.
 *
 * Implementations read decoded data only: each answer depends on the
 * argument alone.
 */
interface Code_Fields
{
    /**
     * The code in decoded builder data: one string per value the builder
     * runs or prints as code, each starting with the kind of place it sits
     * in and a NUL byte, so a value counts as stored only where it was
     * stored as code. A place that only shows its code as text gives
     * nothing.
     *
     * @param mixed $value Decoded builder data (open data: trees and arrays of any shape).
     * @return list<string>
     */
    public function code(mixed $value): array;

    /**
     * The markup the builder prints from separate stored values (an
     * attribute name and its value on an element), one tag per pair.
     *
     * @param mixed $value Decoded builder data (open data: trees and arrays of any shape).
     * @return list<string>
     */
    public function markup(mixed $value): array;
}
