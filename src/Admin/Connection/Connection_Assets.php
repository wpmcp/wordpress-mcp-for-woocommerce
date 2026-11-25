<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

use WPMCP\Admin\Connection_Page;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Loads the Connection screen's stylesheet and script, on that screen only.
 *
 * The screen's hook name ends in _page_wpmcp-connection, but WordPress
 * builds its first part from the WPMCP menu title, which carries the Memory
 * pending badge, so the name is wpmcp-1_page_wpmcp-connection while a memory
 * is pending. The suffix is what is matched.
 *
 * The files live beside this class because the wordpress.org and
 * WooCommerce builds ship src/ and not the top-level assets/ directory.
 */
final class Connection_Assets
{
    public const HANDLE = 'wpmcp-connection';

    public function enqueue(string $hook_suffix): void
    {
        if (! str_ends_with($hook_suffix, '_page_' . Connection_Page::SLUG)) {
            return;
        }

        $version = defined('WPMCP_VERSION') ? constant('WPMCP_VERSION') : '';
        $version = is_string($version) && '' !== $version ? $version : false;

        wp_enqueue_style(self::HANDLE, plugins_url('assets/connection.css', __FILE__), [ 'dashicons' ], $version);
        wp_enqueue_script(self::HANDLE, plugins_url('assets/connection.js', __FILE__), [], $version, true);
        wp_add_inline_script(self::HANDLE, 'window.wpmcpConnection = ' . wp_json_encode($this->settings()) . ';', 'before');
    }

    /**
     * What the script needs: where to post, and every string it shows.
     *
     * @return array{ajaxUrl: string, actions: array{create: string, check: string}, watch: array{every: int, for: int}, i18n: array<string, string>}
     */
    private function settings(): array
    {
        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'actions' => [ 'create' => Connection_Ajax::CREATE, 'check' => Connection_Ajax::CHECK ],
            'watch'   => [ 'every' => 5, 'for' => 120 ],
            'i18n'    => [
                'copied'        => __('Copied', 'safe-mcp-for-woocommerce'),
                'copiedLong'    => __('Copied to the clipboard.', 'safe-mcp-for-woocommerce'),
                'copyFailed'    => __('Copy failed: select the text and copy it yourself.', 'safe-mcp-for-woocommerce'),
                'passwordShown' => __('The password is visible. Hide it before you share your screen.', 'safe-mcp-for-woocommerce'),
                'passwordHidden' => __('The password is hidden on screen. Copy still copies all of it.', 'safe-mcp-for-woocommerce'),
                'hidden'        => __('password hidden', 'safe-mcp-for-woocommerce'),
                'creating'      => __('Creating...', 'safe-mcp-for-woocommerce'),
                'checking'      => __('Checking...', 'safe-mcp-for-woocommerce'),
                'checkAgain'    => __('Check again', 'safe-mcp-for-woocommerce'),
                /* translators: %s: app name */
                'watching'      => __('Watching for %s: checks every 5 seconds for 2 minutes.', 'safe-mcp-for-woocommerce'),
                'watchEnded'    => __('Stopped watching. Use Check again when you are ready.', 'safe-mcp-for-woocommerce'),
                'created'       => __('Connection created. The password is shown once.', 'safe-mcp-for-woocommerce'),
                'failed'        => __('Something went wrong. Reload the page and try again.', 'safe-mcp-for-woocommerce'),
            ],
        ];
    }
}
