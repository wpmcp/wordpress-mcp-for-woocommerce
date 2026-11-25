<?php

declare(strict_types=1);

namespace WPMCP\Safety;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Makes the save's kses visible (issue #1243).
 *
 * A user without unfiltered_html (every administrator on a network who is
 * not a super admin, and any role the capability was removed from) has kses
 * run over post_content on save. A write could report success while kses
 * dropped scripts, mangled attributes or left tags unbalanced on the live
 * page. The checks here sanitize the planned value the way the save will:
 *
 * - builder markup, shortcode layouts and JSON payloads, where sanitizing
 *   corrupts the page, are refused before anything is written
 *   (content_refusal(), values_refusal());
 * - plain classic and block content is sanitized as WordPress's editor
 *   sanitizes it (the #440 decision), and the write reports that it was and
 *   what was removed (sanitization());
 * - code a builder runs or prints as is, which kses leaves alone (see
 *   Code_Fields), may only be kept as stored (values_refusal()).
 *
 * Stateless: every answer depends on the arguments, the current user and
 * the save filters registered at the time of the call, so one instance can
 * be shared or injected.
 */
final class Kses_Safety
{
    public const CODE = 'unfiltered_html_required';

    /**
     * The refusal for post content the post_content save would not store as
     * planned, or null when it would be stored as planned: byte for byte, or
     * changed only in ways that parse to the same HTML (Markup_Equivalence).
     * $post_id is 0 for a post that does not exist yet.
     *
     * @param string $subject What the content belongs to, in the message: "page", "popup", "Visual Composer page".
     */
    public function content_refusal(int $post_id, string $planned, string $subject): ?\WP_Error
    {
        if (current_user_can('unfiltered_html')) {
            return null;
        }
        $stored = sanitize_post_field('post_content', wp_slash($planned), $post_id, 'db');
        if (is_string($stored) && Markup_Equivalence::same($planned, wp_unslash($stored))) {
            return null;
        }

        return $this->refusal($subject);
    }

    /**
     * What the post_content save will do to plain classic or block content
     * for this user, reported instead of refused (issue #440 kept WordPress's
     * sanitizing of the content a user without unfiltered_html writes, as
     * the editor does it): an empty array when the content is stored as
     * planned, otherwise sanitized:true and a short list of what kses
     * removes, for the write's response. Builder markup, shortcode layouts
     * and JSON payloads use content_refusal() instead, where sanitizing
     * corrupts the page.
     *
     * @return array{sanitized?: true, removed?: list<string>}
     */
    public function sanitization(int $post_id, string $planned): array
    {
        if (current_user_can('unfiltered_html')) {
            return [];
        }
        $stored = sanitize_post_field('post_content', wp_slash($planned), $post_id, 'db');
        $stored = is_string($stored) ? wp_unslash($stored) : '';
        if (Markup_Equivalence::same($planned, $stored)) {
            return [];
        }

        return ['sanitized' => true, 'removed' => Markup_Equivalence::removed($planned, $stored)];
    }

    /**
     * The refusal for data a post type keeps in post_content as text, not
     * markup (JSON settings, say), or null when the save stores it byte for
     * byte. Unlike content_refusal() nothing kses re-encodes is let through:
     * an entity it adds is a changed value in the data.
     */
    public function exact_refusal(int $post_id, string $planned, string $subject): ?\WP_Error
    {
        if (current_user_can('unfiltered_html')) {
            return null;
        }
        $stored = sanitize_post_field('post_content', wp_slash($planned), $post_id, 'db');
        if (is_string($stored) && wp_unslash($stored) === $planned) {
            return null;
        }

        return $this->refusal($subject);
    }

    /**
     * Throw the content refusal, so a write deep in a call stack stops before
     * anything is stored. The Registrar returns the carried WP_Error.
     *
     * @throws Post_Write_Refused When the save would not store $planned as is.
     */
    public function assert_content(int $post_id, string $planned, string $subject): void
    {
        $refusal = $this->content_refusal($post_id, $planned, $subject);
        if (null !== $refusal) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Post_Write_Refused escapes the message it is given.
            throw new Post_Write_Refused($refusal);
        }
    }

    /**
     * The refusal for structured data (a builder's element tree in post meta)
     * holding a new string that wp_kses_post would change (beyond what parses
     * the same, see Markup_Equivalence), the filter the builder's own save
     * runs for a user without unfiltered_html. Strings
     * already in $before were stored by someone allowed to, so they are not
     * judged again. Null when every new string survives as is.
     *
     * With the builder's $code fields, code kses cannot judge is refused
     * unless $before holds it in the same kind of place (code_refusal()),
     * and the attributes the builder assembles are judged as markup too.
     *
     * @param mixed $before The stored value, decoded (open data: JSON trees and arrays of any shape).
     * @param mixed $after  The value about to be stored, decoded.
     */
    public function values_refusal(mixed $before, mixed $after, string $subject, ?Code_Fields $code = null): ?\WP_Error
    {
        if (current_user_can('unfiltered_html')) {
            return null;
        }
        if (null !== $code) {
            $stored = array_fill_keys($code->code($before), true);
            foreach ($code->code($after) as $value) {
                if (! isset($stored[ $value ])) {
                    return $this->code_refusal($subject);
                }
            }
        }
        $markup = null === $code ? [[], []] : [$code->markup($before), $code->markup($after)];
        $known  = array_fill_keys([...Decoded_Tree::strings($before), ...$markup[0]], true);
        foreach ([...Decoded_Tree::strings($after), ...$markup[1]] as $value) {
            if (! isset($known[ $value ]) && ! Markup_Equivalence::same($value, wp_kses_post($value))) {
                return $this->refusal($subject);
            }
        }

        return null;
    }

    /**
     * Throw the values refusal.
     *
     * @param mixed $before The stored value, decoded.
     * @param mixed $after  The value about to be stored, decoded.
     * @throws Post_Write_Refused When a new string would not survive wp_kses_post, or new code would be stored.
     */
    public function assert_values(mixed $before, mixed $after, string $subject, ?Code_Fields $code = null): void
    {
        $refusal = $this->values_refusal($before, $after, $subject, $code);
        if (null !== $refusal) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Post_Write_Refused escapes the message it is given.
            throw new Post_Write_Refused($refusal);
        }
    }

    /**
     * A post type's name for the message: its singular label, lower-cased
     * unless it holds a capital past the first letter (an acronym such as
     * "FAQ"), and "post" when it has none.
     */
    public function subject(string $post_type): string
    {
        $object = get_post_type_object($post_type);
        $name   = null !== $object && isset($object->labels->singular_name) ? $object->labels->singular_name : '';
        $label  = is_string($name) ? trim($name) : '';

        if ('' === $label) {
            return 'post';
        }

        return 1 === preg_match('/^.[^A-Z]*$/u', $label) ? strtolower($label) : $label;
    }

    /** The refusal itself, the same for every writer. */
    public function refusal(string $subject): \WP_Error
    {
        return new \WP_Error(
            self::CODE,
            "This {$subject} holds markup WordPress saves only for users with the unfiltered_html capability (on a network, super admins), such as scripts or attributes kses removes. Saving it as you would rewrite the live page, so nothing was written. Ask a user with unfiltered_html to make this change.",
            ['status' => 403]
        );
    }

    /**
     * The refusal for a meta value that would store PHP serialized data (an
     * array or object in PHP's storage format) the stored value did not
     * already hold in the same shape, or null. WordPress unserializes such a
     * string when the value is read, so it is rebuilt as whatever it
     * describes; arrays sent as arrays go through update_post_meta(), which
     * serializes them itself, and are not judged here.
     *
     * @param mixed $before The stored value: raw, or as get_post_meta() returns it.
     * @param mixed $after  The value about to be written.
     */
    public function serialized_refusal(mixed $before, mixed $after, string $subject): ?\WP_Error
    {
        if (current_user_can('unfiltered_html')) {
            return null;
        }
        $known = array_fill_keys(array_map([Serialized_Values::class, 'shape'], Serialized_Values::in($before)), true);
        foreach (Serialized_Values::in($after) as $serialized) {
            if (! isset($known[ Serialized_Values::shape($serialized) ])) {
                return new \WP_Error(
                    self::CODE,
                    "This {$subject} would store PHP serialized data (an array or object in PHP's storage format) that only users with the unfiltered_html capability (on a network, super admins) may write as raw meta, so nothing was written. Send an array as an array, or ask a user with unfiltered_html to make this change.",
                    ['status' => 403]
                );
            }
        }

        return null;
    }

    /** The refusal for new code (see Code_Fields), with the same code and status. */
    public function code_refusal(string $subject): \WP_Error
    {
        return new \WP_Error(
            self::CODE,
            "This {$subject} holds new code, such as a script, PHP or a code element, that only users with the unfiltered_html capability (on a network, super admins) may add or change, so nothing was written. Ask a user with unfiltered_html to make this change.",
            ['status' => 403]
        );
    }
}
