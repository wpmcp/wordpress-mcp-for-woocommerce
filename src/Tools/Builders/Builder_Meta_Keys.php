<?php

declare(strict_types=1);

namespace WPMCP\Tools\Builders;

use WPMCP\Safety\Kses_Safety;
use WPMCP\Safety\Post_Write_Refused;
use WPMCP\Tools\Content\Content_Guard;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The post meta rows page builders render a page from or keep code in that
 * WordPress does not protect (no leading underscore), so the generic meta
 * writers (set-post-meta, the meta argument of create-post and update-post,
 * find-replace-content) could store any value in them. A user without
 * unfiltered_html changes these rows through update-builder-content, which
 * checks the values, and is refused here; a user with it writes them as
 * before.
 *
 * Recorded from each builder's storage (see its *_Content class):
 * BeTheme (mfn-page-items and the other mfn- rows), Brizy (brizy and its
 * brizy- and brizy_ rows), SiteOrigin Page Builder (panels_data), Kirki
 * (kirki and its kirki_ rows), Thrive (tve_, tcb_, tcb2_, thrive_ and thrv_
 * rows, revisions included), Breakdance 1.x (breakdance_data and its
 * breakdance_ rows), classic Oxygen before 4.8.3 (ct_builder_ rows and
 * ct_page_settings) and Visual Composer (vcv- rows).
 */
final class Builder_Meta_Keys
{
    /** Keys a builder owns outright. */
    private const KEYS = ['panels_data', 'brizy', 'kirki', 'ct_page_settings'];

    /** Prefixes of the rows a builder owns. */
    private const PREFIXES = ['mfn-', 'brizy-', 'brizy_', 'kirki_', 'tve_', 'tcb_', 'tcb2_', 'thrive_', 'thrv_', 'breakdance_', 'ct_builder_', 'vcv-'];

    /** Whether a builder renders a page from, or keeps code in, the row this key may be stored as. */
    public function owns(string $key): bool
    {
        foreach (Content_Guard::stored_keys($key) as $stored) {
            $stored = strtolower($stored);
            if (in_array($stored, self::KEYS, true)) {
                return true;
            }
            foreach (self::PREFIXES as $prefix) {
                if (str_starts_with($stored, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Refuse a user without unfiltered_html a builder's row, with the same
     * code and status as the builder writes' own refusals.
     *
     * @param list<string> $keys
     * @throws Post_Write_Refused When a key names a builder's row; nothing is written.
     */
    public function assert_writable(array $keys, int $post_id = 0): void
    {
        if (current_user_can('unfiltered_html')) {
            return;
        }
        foreach ($keys as $key) {
            if (! $this->owns($key)) {
                continue;
            }
            $message = sprintf(
                'Meta key "%s" holds a page builder\'s layout or code, which only users with the unfiltered_html capability (on a network, super admins) may write as raw meta, so nothing was written. Change the page with update-builder-content, which checks the values, or ask a user with unfiltered_html.',
                $key
            );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Post_Write_Refused escapes the message it is given.
            throw new Post_Write_Refused(new \WP_Error(Kses_Safety::CODE, $message, ['status' => 403, 'post_id' => $post_id]));
        }
    }
}
