<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Code blocks and copy rows on the Connection screen. A block that carries
 * the application password wraps it in a span, so the script can hide it on
 * screen by default and show it on request. Each Copy button carries the
 * text it copies and the sentence it announces once it did; the script
 * never copies what the block shows, because while the password is hidden
 * the block also holds the screen-reader label "password hidden" (#1260).
 * Without JavaScript the password stays visible, because the reader needs
 * it, and the Copy and Show buttons stay hidden, because they would do
 * nothing.
 */
final class Snippet_View
{
    public function code(string $id, string $file, string $text, string $secret, string $copy_label, string $copied, bool $wrap = false): void
    {
        $has_secret = '' !== $secret && str_contains($text, $secret);
        ?>
        <div class="wpmcp-code" data-wpmcp-code>
            <div class="wpmcp-code__bar">
                <span class="wpmcp-code__file"><?php echo esc_html($file); ?></span>
                <span class="wpmcp-code__tools">
                    <?php if ($has_secret) : ?>
                        <button type="button" class="button button-small wpmcp-reveal" data-wpmcp-reveal aria-pressed="false" hidden><?php echo esc_html__('Show password', 'safe-mcp-for-woocommerce'); ?></button>
                    <?php endif; ?>
                    <?php $this->copy_button($id, $text, $copy_label, $copied); ?>
                </span>
            </div>
            <pre class="<?php echo esc_attr($wrap ? 'is-wrapped' : ''); ?>" id="<?php echo esc_attr($id); ?>" tabindex="0" role="region" aria-label="<?php echo esc_attr($file); ?>"><?php echo wp_kses($this->marked($text, $secret), [ 'span' => [ 'class' => true ] ]); ?></pre>
        </div>
        <?php if ($has_secret) : ?>
            <p class="wpmcp-code__foot" data-wpmcp-secret-note hidden><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php echo esc_html__('The password is hidden on screen. Copy still copies all of it.', 'safe-mcp-for-woocommerce'); ?></p>
        <?php endif; ?>
        <?php
    }

    public function copy_row(string $id, string $text, string $copy_label, string $copied): void
    {
        ?>
        <div class="wpmcp-copyrow">
            <code id="<?php echo esc_attr($id); ?>"><?php echo esc_html($text); ?></code>
            <?php $this->copy_button($id, $text, $copy_label, $copied); ?>
        </div>
        <?php
    }

    /**
     * The Copy button of the element $id: the text it copies is in
     * data-wpmcp-copy-text and the sentence it announces in
     * data-wpmcp-copied. Data attributes are neither shown nor read by
     * screen readers; the button's accessible name stays $copy_label.
     */
    private function copy_button(string $id, string $text, string $copy_label, string $copied): void
    {
        printf(
            '<button type="button" class="button button-small wpmcp-copy" data-copy-target="%1$s" data-wpmcp-copy-text="%2$s" data-wpmcp-copied="%3$s" aria-label="%4$s" hidden><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><span class="wpmcp-copy__label">%5$s</span></button>',
            esc_attr($id),
            esc_attr($text),
            esc_attr($copied),
            esc_attr($copy_label),
            esc_html__('Copy', 'safe-mcp-for-woocommerce')
        );
    }

    /** The escaped text with each occurrence of the secret wrapped for masking. */
    private function marked(string $text, string $secret): string
    {
        $html = esc_html($text);
        if ('' === $secret) {
            return $html;
        }

        $escaped = esc_html($secret);

        return str_replace($escaped, '<span class="wpmcp-secret">' . $escaped . '</span>', $html);
    }
}
