<?php

declare(strict_types=1);

namespace WPMCP\Tools\Builders;

use WPMCP\Safety\Code_Fields;
use WPMCP\Safety\Code_Values;
use WPMCP\Safety\Decoded_Tree;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Where a Divi layout hides markup from kses, recorded from Divi 5.18: Divi
 * 5 block attributes (parsed blocks, so their JSON escapes are already
 * undone), and Divi 4 shortcodes, which Divi 5 still renders.
 *
 * - A Divi 4 code module ([et_pb_code], [et_pb_fullwidth_code]) prints its
 *   body as is, and for an older module Divi decodes the body's HTML
 *   entities first, so the decoded body is judged as markup. A Divi 5 code
 *   module's value is judged as it is, like any decoded value.
 * - Attributes (module.decoration.attributes, name and value), the
 *   element tag (module.advanced.html elementType, printed after
 *   tag_escape() only) and interactions that set an attribute
 *   (attributeName, attributeValue): judged as the markup they become.
 *
 * Divi runs no code from its layout data, so code() lists nothing.
 */
final class Divi_Code_Fields implements Code_Fields
{
    /** @return list<string> */
    public function code(mixed $value): array
    {
        return [];
    }

    /** @return list<string> */
    public function markup(mixed $value): array
    {
        $out = [];
        foreach (Decoded_Tree::strings($value) as $string) {
            if (false === strpos($string, '_code') || 0 === (int) preg_match_all('/\[(et_pb_code|et_pb_fullwidth_code)\b[^\]]*\](.*?)\[\/\1\]/s', $string, $modules)) {
                continue;
            }
            foreach ($modules[2] as $body) {
                $out[] = html_entity_decode($body, ENT_QUOTES | ENT_HTML5);
            }
        }
        foreach (Decoded_Tree::nodes($value) as $node) {
            $tag = $node['elementType'] ?? null;
            if (is_string($tag) && '' !== $tag) {
                $out[] = Code_Values::element($tag);
            }
            $set = $node['attributeName'] ?? null;
            if (is_string($set) && '' !== $set) {
                $text  = $node['attributeValue'] ?? '';
                $out[] = Code_Values::attribute($set, is_scalar($text) ? (string) $text : '');
            }
            $attributes = $node['attributes'] ?? null;
            if (! is_array($attributes) || ! array_is_list($attributes)) {
                continue;
            }
            foreach ($attributes as $attribute) {
                $attribute = Decoded_Tree::fields($attribute);
                $name      = $attribute['name'] ?? null;
                if (! is_string($name) || '' === $name) {
                    continue;
                }
                $text  = $attribute['value'] ?? '';
                $out[] = Code_Values::attribute($name, is_scalar($text) ? (string) $text : '');
            }
        }

        return $out;
    }
}
