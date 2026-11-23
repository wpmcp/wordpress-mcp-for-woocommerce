<?php

declare(strict_types=1);

namespace WPMCP\Safety;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Whether two pieces of markup parse to the same HTML (issue #1243).
 *
 * kses rewrites some markup in ways a browser never sees: a bare "&" becomes
 * "&amp;", "<hr/>" becomes "<hr />", a lone "<" in text becomes "&lt;".
 * Refusing those would refuse almost every block page (core's separator
 * and image blocks save "/>"), so a kses check compares what the markup
 * means instead: the same tags with the same attribute values, the same
 * text once decoded, the same comments byte for byte, and block delimiters
 * with the same block name and the same attributes once their JSON is
 * decoded. kses re-serializes block delimiters: an empty block's opener and
 * closer become one void delimiter, and a string attribute is compared the
 * same way as markup, so the entities it re-encodes in a block's HTML
 * value (Divi 5 keeps text as HTML in its attributes) count as the same.
 *
 * A pure function of its arguments, built on core's HTML tokenizer.
 */
final class Markup_Equivalence
{
    public static function same(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $left = self::tokens($a);

        return null !== $left && $left === self::tokens($b);
    }

    /**
     * A short, readable list of what $after lost against $before: elements
     * ("<script> element"), attributes ("onclick attribute on <button>"),
     * and, when neither explains the change, changed attribute values or
     * text. At most ten entries. Empty when the two parse the same.
     *
     * @return list<string>
     */
    public static function removed(string $before, string $after): array
    {
        if (self::same($before, $after)) {
            return [];
        }
        $old = self::tokens($before) ?? [];
        $new = self::tokens($after) ?? [];
        $out  = [];
        $gone = self::lost(self::openers($old), self::openers($new));
        foreach ($gone as $tag => $count) {
            $out[] = sprintf('<%s> element%s', strtolower($tag), $count > 1 ? " ({$count})" : '');
        }
        foreach (self::lost(self::attributes($old), self::attributes($new)) as $pair => $count) {
            [$tag, $name] = explode(' ', $pair, 2);
            if (isset($gone[ $tag ])) {
                continue;
            }
            $out[] = sprintf('%s attribute on <%s>%s', $name, strtolower($tag), $count > 1 ? " ({$count})" : '');
        }
        if ([] === $out) {
            $out[] = 'changed attribute values or text (such as a disallowed URL protocol)';
        }

        return array_slice($out, 0, 10);
    }

    /**
     * @param list<array{0: string, 1: string, 2?: bool, 3?: array<string, string|true>}> $tokens
     * @return array<string, int> tag name => openers
     */
    private static function openers(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $token) {
            if ('#tag' === $token[0] && false === ($token[2] ?? true)) {
                $out[ $token[1] ] = ($out[ $token[1] ] ?? 0) + 1;
            }
        }

        return $out;
    }

    /**
     * @param list<array{0: string, 1: string, 2?: bool, 3?: array<string, string|true>}> $tokens
     * @return array<string, int> "tag attribute" => count
     */
    private static function attributes(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $token) {
            if ('#tag' !== $token[0] || true === ($token[2] ?? false)) {
                continue;
            }
            foreach (array_keys($token[3] ?? []) as $name) {
                $key         = $token[1] . ' ' . $name;
                $out[ $key ] = ($out[ $key ] ?? 0) + 1;
            }
        }

        return $out;
    }

    /**
     * @param array<string, int> $before
     * @param array<string, int> $after
     * @return array<string, int> what $after has fewer of, and how many fewer
     */
    private static function lost(array $before, array $after): array
    {
        $out = [];
        foreach ($before as $key => $count) {
            $missing = $count - ($after[ $key ] ?? 0);
            if ($missing > 0) {
                $out[ $key ] = $missing;
            }
        }

        return $out;
    }

    /**
     * The markup as a list of tokens, adjacent text merged, or null when it
     * does not tokenize to the end.
     *
     * @return list<array{0: string, 1: string, 2?: bool, 3?: array<string, string|true>}>|null
     */
    private static function tokens(string $html): ?array
    {
        $processor = new \WP_HTML_Tag_Processor($html);
        $out       = [];
        $text      = null;
        while ($processor->next_token()) {
            $type = (string) $processor->get_token_type();
            if ('#text' === $type) {
                $text = ($text ?? '') . $processor->get_modifiable_text();
                continue;
            }
            if (null !== $text) {
                $out[] = ['#text', $text];
                $text  = null;
            }
            if ('#comment' === $type) {
                $out[] = self::comment($processor->get_modifiable_text());
                continue;
            }
            if ('#tag' !== $type) {
                $out[] = [$type, $processor->get_modifiable_text()];
                continue;
            }
            $attrs = [];
            foreach ((array) $processor->get_attribute_names_with_prefix('') as $name) {
                $value          = $processor->get_attribute($name);
                $attrs[ $name ] = is_string($value) ? $value : true;
            }
            ksort($attrs);
            $out[] = ['#tag', (string) $processor->get_tag(), $processor->is_tag_closer(), $attrs];
            // The text inside script, style, textarea and title.
            $inner = $processor->get_modifiable_text();
            if ('' !== $inner) {
                $out[] = ['#inner', $inner];
            }
        }
        if (null !== $text) {
            $out[] = ['#text', $text];
        }
        if ($processor->paused_at_incomplete_token()) {
            return null;
        }

        return self::void_empty_blocks($out);
    }

    /**
     * A comment token; a block delimiter is reduced to its name and decoded
     * attributes, so re-serialized JSON compares equal.
     *
     * @return array{0: string, 1: string, 2?: bool, 3?: array<string, string|true>}
     */
    private static function comment(string $text): array
    {
        if (1 !== preg_match('#^\s*(/)?wp:([a-z][a-z0-9_-]*(?:/[a-z][a-z0-9_-]*)?)\s+(\{.*\})?\s*(/)?\s*$#s', $text, $m)) {
            return ['#comment', $text];
        }
        $attrs = isset($m[3]) && '' !== $m[3] ? json_decode($m[3], true) : [];
        if (! is_array($attrs)) {
            return ['#comment', $text];
        }

        return ['#block', $m[1] . $m[2] . (isset($m[4]) ? $m[4] : ''), true, ['json' => (string) wp_json_encode(self::canonical($attrs))]];
    }

    /**
     * An empty block written as an opener and a closer is the same block as
     * its void delimiter.
     *
     * @param list<array{0: string, 1: string, 2?: bool, 3?: array<string, string|true>}> $tokens
     * @return list<array{0: string, 1: string, 2?: bool, 3?: array<string, string|true>}>
     */
    private static function void_empty_blocks(array $tokens): array
    {
        $out   = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[ $i ];
            $next  = $tokens[ $i + 1 ] ?? null;
            $name  = $token[1];
            if ('#block' === $token[0] && null !== $next && '#block' === $next[0] && ! str_starts_with($name, '/') && ! str_ends_with($name, '/') && '/' . $name === $next[1]) {
                $token[1] = $name . '/';
                $i++;
            }
            $out[] = $token;
        }

        return $out;
    }

    /**
     * Decoded block attributes with object keys sorted and every string
     * replaced by its tokens, so values that parse the same compare equal.
     *
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private static function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[ $key ] = self::canonical($item);
            } elseif (is_string($item)) {
                $value[ $key ] = self::tokens($item) ?? $item;
            }
        }

        return $value;
    }
}
