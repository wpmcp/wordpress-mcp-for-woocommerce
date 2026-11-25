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
 * Where a classic Oxygen (4.x) tree keeps code. Oxygen's save signs the
 * tree's shortcode copy with the site's key, and WPMCP has Oxygen sign
 * what it writes, so whatever reaches the tree runs as if Oxygen's editor
 * had saved it:
 *
 * - a Code Block (ct_code_block): every option, its PHP (code-php, run as
 *   a template), JavaScript (code-js) and CSS (code-css) included;
 * - an element's own JavaScript (custom-js) and any code-php, code-js or
 *   code-css option, wherever it sits in an element's options;
 * - dynamic data shortcodes ([oxygen ...]), which can call a PHP function
 *   by name, in any value. The signature attribute is left out, since the
 *   signer adds it to a new shortcode.
 */
final class Oxygen_Classic_Code_Fields implements Code_Fields
{
    /** Options Oxygen runs or prints as code, wherever they sit. */
    private const CODE_OPTIONS = ['code-php', 'code-js', 'code-css', 'custom-js'];

    /** @return list<string> */
    public function code(mixed $value): array
    {
        $out = [];
        foreach (Decoded_Tree::nodes($value) as $node) {
            if ('ct_code_block' === ($node['name'] ?? null) && array_key_exists('options', $node)) {
                foreach (Decoded_Tree::strings($node['options']) as $code) {
                    $out[] = Code_Values::of('oxygen code block', $code);
                }
            }
            foreach (self::CODE_OPTIONS as $key) {
                $code = $node[ $key ] ?? null;
                if (is_string($code) && '' !== $code) {
                    $out[] = Code_Values::of("oxygen {$key}", $code);
                }
            }
        }
        foreach (Decoded_Tree::strings($value) as $string) {
            if (false === stripos($string, '[oxygen') || 0 === (int) preg_match_all('/\[oxygen\s[^\]]*\]/i', $string, $tags)) {
                continue;
            }
            foreach ($tags[0] as $tag) {
                $out[] = Code_Values::of('oxygen dynamic data', (string) preg_replace('/\s*ct_sign_sha256=(["\'])[^"\']*\1/i', '', $tag));
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function markup(mixed $value): array
    {
        return [];
    }
}
