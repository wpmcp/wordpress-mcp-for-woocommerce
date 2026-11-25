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
 * Where a document on the Breakdance engine (Breakdance, and Oxygen 6 under
 * its own element set) keeps code, recorded from the Oxygen 6.1 source,
 * which ships the engine. Its save stores the tree as sent and its renderer
 * prints element properties raw, so:
 *
 * - code elements (PHP Code, JavaScript Code, CSS Code, HTML Code, and
 *   Breakdance's Code Block): every property value, since the element runs
 *   or prints what it holds;
 * - PHP the engine evaluates from a property: a loop's PHP query
 *   (query.php, with whether it is active), a term query (term_query),
 *   a custom PHP display condition (ruleSlug custom-php), and dynamic data
 *   shortcodes that return PHP or process a value ([breakdance_dynamic ...
 *   phpreturn, process_value or code]) in any value;
 * - interactions that call a JavaScript function or set an attribute
 *   (javascript_function, set_attribute);
 * - attributes (advanced.attributes, name and value) and the element tag
 *   (advanced.tag), printed as given: judged as the markup they become.
 */
final class Breakdance_Code_Fields implements Code_Fields
{
    /** Element types whose content runs or is printed as code. */
    private const CODE_ELEMENTS = [
        'OxygenElements\\PhpCode', 'OxygenElements\\JavaScriptCode', 'OxygenElements\\CssCode', 'OxygenElements\\HtmlCode',
        'EssentialElements\\CodeBlock', 'EssentialElements\\PhpCode', 'EssentialElements\\JavaScriptCode', 'EssentialElements\\CssCode', 'EssentialElements\\HtmlCode',
    ];

    /** Interaction actions that run script in the browser. */
    private const SCRIPT_ACTIONS = ['javascript_function', 'set_attribute'];

    /** @param string $builder 'breakdance' or 'oxygen', named in each code value's kind. */
    public function __construct(private readonly string $builder = 'breakdance')
    {
    }

    /** @return list<string> */
    public function code(mixed $value): array
    {
        $out = [];
        foreach (Decoded_Tree::nodes($value) as $node) {
            array_push($out, ...$this->element_code($node), ...$this->query_code($node), ...$this->action_code($node));
        }
        foreach (Decoded_Tree::strings($value) as $string) {
            if (false === stripos($string, '[breakdance_dynamic') || 0 === (int) preg_match_all('/\[breakdance_dynamic\s[^\]]*\]/i', $string, $tags)) {
                continue;
            }
            foreach ($tags[0] as $tag) {
                if (1 === preg_match('/phpreturn|process_value|code/i', $tag)) {
                    $out[] = Code_Values::of("{$this->builder} dynamic data", $tag);
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function markup(mixed $value): array
    {
        $out = [];
        foreach (Decoded_Tree::nodes($value) as $node) {
            $tag = $node['tag'] ?? null;
            if (is_string($tag) && '' !== $tag) {
                $out[] = Code_Values::element($tag);
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

    /**
     * @param array<mixed> $node
     * @return list<string>
     */
    private function element_code(array $node): array
    {
        $type = $node['type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::CODE_ELEMENTS, true) || ! array_key_exists('properties', $node)) {
            return [];
        }
        $out = [];
        foreach (Decoded_Tree::strings($node['properties']) as $code) {
            $out[] = Code_Values::of("{$this->builder} {$type}", $code);
        }

        return $out;
    }

    /**
     * @param array<mixed> $node
     * @return list<string>
     */
    private function query_code(array $node): array
    {
        $out = [];
        $php = $node['php'] ?? null;
        if (is_string($php) && '' !== trim($php) && array_key_exists('active', $node)) {
            $out[] = Code_Values::of('php' === $node['active'] ? "{$this->builder} php query (on)" : "{$this->builder} php query", $php);
        }
        $terms = $node['term_query'] ?? null;
        if (is_string($terms) && '' !== trim($terms)) {
            $out[] = Code_Values::of("{$this->builder} term query", $terms);
        }
        if ('custom-php' === ($node['ruleSlug'] ?? null)) {
            $rule  = $node['value'] ?? null;
            $out[] = Code_Values::of("{$this->builder} php condition", is_string($rule) ? $rule : (string) wp_json_encode($rule));
        }

        return $out;
    }

    /**
     * @param array<mixed> $node
     * @return list<string>
     */
    private function action_code(array $node): array
    {
        $name = $node['name'] ?? null;
        if (! is_string($name) || ! in_array($name, self::SCRIPT_ACTIONS, true)) {
            return [];
        }

        return [Code_Values::of("{$this->builder} interaction {$name}", (string) wp_json_encode($node))];
    }
}
