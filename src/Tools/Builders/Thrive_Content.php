<?php

namespace WPMCP\Tools\Builders;

use WPMCP\Safety\Kses_Safety;
use WPMCP\Safety\Post_Write_Refused;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read/write access to a page's Thrive Architect layout.
 *
 * Thrive's editor save stores the layout HTML in the `tve_updated_post`
 * postmeta, the part before a Read More split in `tve_content_before_more`
 * (with `tve_content_more_found` saying whether there is one), the CSS it
 * generates for its elements in `tve_custom_css` (rules keyed by each
 * element's data-css id) and a plain-text copy of the layout in
 * post_content. A landing page stores those rows under the same keys with a
 * `_<template>` suffix, the template named by `tve_landing_page`.
 *
 * A write stores the new layout and keeps the Read More part in step. The
 * element CSS stays as it is: untouched elements keep their data-css ids,
 * so their rules still apply. Two things Thrive derives from the layout on
 * save are refreshed the way its own save does it:
 *  - the optimized assets its asset optimization keeps per page (the
 *    `_tve_lightspeed_version` flag with the page's trimmed stylesheet, and
 *    the `_tve_js_modules` list of scripts it needs) were computed for the
 *    old layout, so the flag is set to 0 and the script lists are dropped,
 *    and Thrive serves its full stylesheet and scripts until the page is
 *    optimized again;
 *  - the plain-text copy in post_content is rebuilt through Thrive itself
 *    when it is loaded.
 * Every one of these rows is postmeta or post_content, which the post
 * snapshot captures, so a rollback restores layout, CSS, Read More part,
 * optimized assets and copy together as the consistent set they were, and
 * nothing further is needed after it.
 *
 * Nothing here calls into Thrive except that one plain-text refresh.
 */
class Thrive_Content
{
    public const LAYOUT_META_KEY = 'tve_updated_post';

    public const CSS_META_KEY = 'tve_custom_css';

    public const OPTIMIZED_VERSION_META_KEY = '_tve_lightspeed_version';

    public const KSES_SUBJECT = 'Thrive Architect page';

    /** Script lists Thrive loads instead of all of its scripts when present. */
    private const JS_MODULE_META_KEYS = ['_tve_js_modules', '_tve_js_modules_woo'];

    /** Whether Thrive Architect (standalone or bundled with a Thrive product) is loaded. */
    public static function plugin_active(): bool
    {
        return (bool) apply_filters('wpmcp_thrive_active', defined('TVE_VERSION'));
    }

    /** The landing page template, or '' for a normal page. */
    public static function landing_page(int $post_id): string
    {
        return (string) get_post_meta($post_id, 'tve_landing_page', true);
    }

    /** A layout meta key as Thrive names it for this page. */
    public static function key(int $post_id, string $base): string
    {
        $template = self::landing_page($post_id);

        return '' === $template ? $base : $base . '_' . $template;
    }

    public static function get_content(int $post_id): string
    {
        $value = get_post_meta($post_id, self::key($post_id, self::LAYOUT_META_KEY), true);

        return is_string($value) ? $value : '';
    }

    /** @return array<string,mixed> */
    public static function read(int $post_id): array
    {
        $content = self::get_content($post_id);

        try {
            $tree  = Thrive_Html::tree($content);
            $error = null;
        } catch (\InvalidArgumentException $e) {
            $tree  = [];
            $error = $e->getMessage();
        }

        $out = [
            'post_id'        => $post_id,
            'builder'        => 'thrive',
            'plugin_active'  => self::plugin_active(),
            'plugin_version' => Thrive_Page::plugin_version(),
            'landing_page'   => self::landing_page($post_id),
            'hash'           => Thrive_Page::hash($content),
            'revisions'      => Thrive_Page::revisions($post_id),
            'tree'           => $tree,
            'content'        => $content,
        ];
        if (null !== $error) {
            $out['tree_error'] = $error;
        }

        return $out;
    }

    /**
     * The Read More part to store with a new layout, or null to leave that
     * row alone. Thrive stores the layout before the split, which is a
     * prefix of the layout. When the page has a split, the new part is
     * derived from where the bytes changed: an edit wholly after the split
     * leaves it as it is, an edit wholly before it shifts it, and an edit
     * across it is refused, since where the split belongs is then unknown.
     */
    public static function before_more(int $post_id, string $old, string $new): ?string
    {
        $key = self::key($post_id, 'tve_content_before_more');

        if (! get_post_meta($post_id, self::key($post_id, 'tve_content_more_found'), true)) {
            return metadata_exists('post', $post_id, $key) ? self::trim_like_thrive($new) : null;
        }

        // The part is the layout's prefix, less any leading whitespace
        // Thrive's trim removed from it.
        $part   = (string) get_post_meta($post_id, $key, true);
        $offset = null;
        foreach ([strlen($part), strlen($old) - strlen(ltrim($old)) + strlen($part)] as $candidate) {
            if ('' !== $part && $candidate <= strlen($old) && self::trim_like_thrive(substr($old, 0, $candidate)) === $part) {
                $offset = $candidate;
                break;
            }
        }
        if (null === $offset) {
            throw new \InvalidArgumentException(esc_html('This page has a Read More split that does not match its layout; save it once in Thrive Architect first.'));
        }

        $shortest = min(strlen($old), strlen($new));
        $prefix   = 0;
        while ($prefix < $shortest && $old[ $prefix ] === $new[ $prefix ]) {
            $prefix++;
        }
        if ($prefix >= $offset) {
            return null;
        }
        $suffix = 0;
        while ($suffix < $shortest - $prefix && $old[ strlen($old) - 1 - $suffix ] === $new[ strlen($new) - 1 - $suffix ]) {
            $suffix++;
        }
        if (strlen($old) - $suffix > $offset) {
            throw new \InvalidArgumentException(esc_html('This edit crosses the page\'s Read More split; make it in Thrive Architect, or edit on one side of the split at a time.'));
        }

        return self::trim_like_thrive(substr($new, 0, $offset + strlen($new) - strlen($old)));
    }

    /**
     * The layout rows a write can change, as this page stores them now, by
     * base key (the rows a Thrive revision holds).
     *
     * @return array<string,mixed>
     */
    public static function rows(int $post_id): array
    {
        $rows = [];
        foreach (Thrive_Page::REVISION_KEYS as $base) {
            $rows[ $base ] = get_post_meta($post_id, self::key($post_id, $base), true);
        }

        return $rows;
    }

    /**
     * The refusal for layout rows (base key => value) holding a new value
     * wp_kses_post would change, or new code (see Thrive_Code_Fields), or
     * null. Post meta skips kses, so a user without unfiltered_html could
     * otherwise store a script on the page. The layout is one HTML value:
     * changed anywhere, it is judged whole. Values the page's rows hold now
     * were stored by someone allowed to; a revision's rows are judged like
     * any other, since revision rows are plain post meta.
     *
     * @param array<string,mixed> $rows
     */
    public static function kses_refusal(int $post_id, array $rows): ?\WP_Error
    {
        return (new Kses_Safety())->values_refusal(self::rows($post_id), $rows, self::KSES_SUBJECT, new Thrive_Code_Fields());
    }

    /**
     * The rows save() writes, by base key, for kses_refusal().
     *
     * @return array<string,string>
     */
    public static function saved_rows(string $content, ?string $before_more, ?string $css = null): array
    {
        return array_filter([self::LAYOUT_META_KEY => $content, 'tve_content_before_more' => $before_more, self::CSS_META_KEY => $css], 'is_string');
    }

    /**
     * @param array<string,mixed> $rows
     * @throws Post_Write_Refused When kses_refusal() refuses the rows; nothing is written.
     */
    private static function assert_kses(int $post_id, array $rows): void
    {
        $refusal = self::kses_refusal($post_id, $rows);
        if (null !== $refusal) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Post_Write_Refused escapes the message it is given.
            throw new Post_Write_Refused($refusal);
        }
    }

    /**
     * Store a new layout (see before_more() for the Read More part), drop the
     * optimized assets computed for the old one, and refresh the plain-text
     * copy through Thrive when it is loaded.
     *
     * @throws Post_Write_Refused When kses_refusal() refuses the layout; nothing is written.
     */
    public static function save(int $post_id, string $content, ?string $before_more, ?string $css = null): void
    {
        self::assert_kses($post_id, self::saved_rows($content, $before_more, $css));
        update_post_meta($post_id, self::key($post_id, self::LAYOUT_META_KEY), wp_slash($content));
        if (null !== $before_more) {
            update_post_meta($post_id, self::key($post_id, 'tve_content_before_more'), wp_slash($before_more));
        }
        if (null !== $css) {
            update_post_meta($post_id, self::key($post_id, self::CSS_META_KEY), wp_slash($css));
        }

        self::refresh_derived($post_id);
    }

    /** The element CSS row Thrive generates for this page. */
    public static function get_css(int $post_id): string
    {
        $value = get_post_meta($post_id, self::key($post_id, self::CSS_META_KEY), true);

        return is_string($value) ? $value : '';
    }

    /**
     * Write rows a revision restore puts back (base key => value, each
     * stored under this page's key), then refresh what Thrive derives.
     *
     * @param array<string,mixed> $rows
     * @throws Post_Write_Refused When kses_refusal() refuses the rows; nothing is written.
     */
    public static function restore_rows(int $post_id, array $rows): void
    {
        self::assert_kses($post_id, $rows);
        foreach ($rows as $base => $value) {
            update_post_meta($post_id, self::key($post_id, $base), wp_slash($value));
        }

        self::refresh_derived($post_id);
    }

    /**
     * Drop the optimized assets computed for the old layout and refresh the
     * plain-text copy through Thrive when it is loaded (see the class
     * comment).
     */
    private static function refresh_derived(int $post_id): void
    {
        $version = get_post_meta($post_id, self::OPTIMIZED_VERSION_META_KEY, true);
        if ('' !== (string) $version && 0 !== (int) $version) {
            update_post_meta($post_id, self::OPTIMIZED_VERSION_META_KEY, 0);
        }
        foreach (self::JS_MODULE_META_KEYS as $key) {
            delete_post_meta($post_id, $key);
        }

        if (self::plugin_active() && function_exists('tcb_post')) {
            try {
                tcb_post($post_id)->update_plain_text_content();
            } catch (\Throwable $e) {
                // The copy is a convenience; it must never fail the write.
                unset($e);
            }
        }
    }

    /** The whitespace trim Thrive applies to the Read More part. */
    private static function trim_like_thrive(string $html): string
    {
        return (string) preg_replace('/^[\s]*(.*)[\s]*$/', '\\1', $html);
    }
}
