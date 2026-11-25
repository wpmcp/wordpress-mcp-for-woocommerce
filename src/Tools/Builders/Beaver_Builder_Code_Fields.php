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
 * Where a Beaver Builder layout keeps code, recorded from Beaver Builder
 * 2.11 (Lite), whose own save drops the JavaScript fields of a user it does
 * not trust with them:
 *
 * - the layout settings' JavaScript (js) and a node's own (bb_js_code),
 *   added to the layout's script;
 * - a button's click handler (button, when click_action is button), in the
 *   button and button group modules;
 * - a box's link_target, printed inside its script, when it is not one of
 *   the link targets, and a photo's stored image sizes (data.sizes) with a
 *   quote, backslash or angle bracket, printed inside its lightbox script;
 * - custom attributes (custom_attributes, key and value) and a photo's
 *   attributes, printed on the element, the container and title tags
 *   (container_element, title_tag, a heading's tag), and the row, column and module
 *   values printed into attributes unescaped: judged as the markup they
 *   become.
 */
final class Beaver_Builder_Code_Fields implements Code_Fields
{
    /** Link targets a box may print. */
    private const LINK_TARGETS = ['', '_self', '_blank', '_parent', '_top'];

    /** Node settings printed into attributes without escaping. */
    private const RAW_ATTRIBUTES = [
        'bg_video_url_mp4', 'bg_video_url_webm', 'bg_video_fallback_src', 'bg_video_service_url', 'bg_video_mobile', 'bg_video_audio',
        'responsive_display', 'width', 'bg_type', 'content_alignment', 'bg_parallax_image_src', 'bg_parallax_speed', 'bg_parallax_offset',
        'bg_parallax_image_medium_src', 'bg_parallax_image_responsive_src', 'style', 'delay', 'duration',
    ];

    /** @return list<string> */
    public function code(mixed $value): array
    {
        $out = [];
        foreach (Decoded_Tree::nodes($value) as $node) {
            foreach (['js' => 'beaver builder js', 'bb_js_code' => 'beaver builder node js'] as $key => $kind) {
                $js = $node[ $key ] ?? null;
                if (is_string($js) && '' !== trim($js)) {
                    $out[] = Code_Values::of($kind, $js);
                }
            }
            $click = $node['button'] ?? null;
            if ('button' === ($node['click_action'] ?? null) && is_string($click) && '' !== trim($click)) {
                $out[] = Code_Values::of('beaver builder button click', $click);
            }
            $target = $node['link_target'] ?? null;
            if ('box' === ($node['type'] ?? null) && is_string($target) && ! in_array($target, self::LINK_TARGETS, true)) {
                $out[] = Code_Values::of('beaver builder box link target', $target);
            }
            if ('photo' !== ($node['type'] ?? null) || ! array_key_exists('data', $node)) {
                continue;
            }
            foreach (Decoded_Tree::strings(Decoded_Tree::fields($node['data'])['sizes'] ?? null) as $photo) {
                if (false !== strpbrk($photo, '\'"\\<>')) {
                    $out[] = Code_Values::of('beaver builder photo script value', $photo);
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
            array_push($out, ...$this->tags($node), ...$this->attributes($node));
            foreach (self::RAW_ATTRIBUTES as $key) {
                $raw = $node[ $key ] ?? null;
                if (is_string($raw) && false !== strpbrk($raw, '"<>')) {
                    $out[] = Code_Values::raw_attribute('data-beaver', $raw);
                }
            }
        }

        return $out;
    }

    /**
     * @param array<mixed> $node
     * @return list<string>
     */
    private function tags(array $node): array
    {
        $keys = 'heading' === ($node['type'] ?? null) ? ['container_element', 'title_tag', 'tag'] : ['container_element', 'title_tag'];
        $out  = [];
        foreach ($keys as $key) {
            $tag = $node[ $key ] ?? null;
            if (is_string($tag) && '' !== $tag) {
                $out[] = Code_Values::element($tag);
            }
        }

        return $out;
    }

    /**
     * @param array<mixed> $node
     * @return list<string>
     */
    private function attributes(array $node): array
    {
        $out = [];
        foreach (Decoded_Tree::fields($node['custom_attributes'] ?? null) as $attribute) {
            $attribute = Decoded_Tree::fields($attribute);
            $key       = $attribute['key'] ?? null;
            if (! is_string($key) || '' === $key) {
                continue;
            }
            $text  = $attribute['value'] ?? '';
            $out[] = Code_Values::attribute($key, is_scalar($text) ? (string) $text : '');
        }
        if ('photo' !== ($node['type'] ?? null)) {
            return $out;
        }
        foreach (Decoded_Tree::fields($node['attributes'] ?? null) as $key => $text) {
            if (is_string($key) && is_scalar($text)) {
                $out[] = Code_Values::raw_attribute($key, (string) $text);
            }
        }

        return $out;
    }
}
