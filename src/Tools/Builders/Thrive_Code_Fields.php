<?php

declare(strict_types=1);

namespace WPMCP\Tools\Builders;

use WPMCP\Safety\Code_Fields;
use WPMCP\Safety\Decoded_Tree;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Where a Thrive Architect layout keeps code it hides from kses, recorded
 * from Thrive Architect 11.3: a Custom HTML element's scripts, stored as
 * [tcb-script ATTRS]...[/tcb-script] (and tcb-noscript) with the body's
 * HTML entities encoded, which Thrive prints as a script element with the
 * body decoded; and a WordPress Content element's markup, stored between
 * ___TVE_SHORTCODE_RAW__ markers with its entities encoded, which Thrive
 * decodes into the page. Both are handed to kses as the markup Thrive
 * prints. The rest of a layout is HTML, which kses judges as it is.
 */
final class Thrive_Code_Fields implements Code_Fields
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
            array_push($out, ...$this->scripts($string), ...$this->raw_content($string));
        }

        return $out;
    }

    /** @return list<string> the script elements Thrive prints for the [tcb-script] shortcodes in $string */
    private function scripts(string $string): array
    {
        if (false === stripos($string, '[tcb-') || 0 === (int) preg_match_all('/\[(tcb-script|tcb-noscript)([^\]]*)\](.*?)\[\/\1\]/is', $string, $scripts, PREG_SET_ORDER)) {
            return [];
        }
        $out = [];
        foreach ($scripts as $script) {
            $tag   = 'tcb-script' === strtolower($script[1]) ? 'script' : 'noscript';
            $out[] = "<{$tag}{$script[2]}>" . html_entity_decode($script[3], ENT_QUOTES | ENT_HTML5) . "</{$tag}>";
        }

        return $out;
    }

    /** @return list<string> the markup Thrive decodes from the raw content markers in $string */
    private function raw_content(string $string): array
    {
        if (false === strpos($string, '___TVE_SHORTCODE_RAW__') || 0 === (int) preg_match_all('/___TVE_SHORTCODE_RAW__(.*?)__TVE_SHORTCODE_RAW___/s', $string, $raws)) {
            return [];
        }

        return array_map(static fn (string $raw): string => html_entity_decode($raw, ENT_QUOTES | ENT_HTML5), $raws[1]);
    }
}
