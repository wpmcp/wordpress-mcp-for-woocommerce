<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

use WPMCP\Admin\Connection_Page;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The Connection screen's two AJAX actions. Both are registered through
 * Management_Access::add_handler(), so the management guard refuses first,
 * and both check the screen's nonce.
 *
 *  - wpmcp_connection_create runs the screen's own provision action and
 *    returns the four steps drawn with the new password. The plaintext is in
 *    that one response, sent with no-cache headers, and nowhere else.
 *  - wpmcp_connection_check returns the verdict, whether the check is done
 *    and the result HTML. It never carries a password or a header.
 */
final class Connection_Ajax
{
    public const CREATE = Connection_Page::AJAX_CREATE;
    public const CHECK  = Connection_Page::AJAX_CHECK;

    public function __construct(
        private readonly Connection_Page $page,
        private readonly Connect_View $steps,
        private readonly Check_View $checks,
    ) {
    }

    public function create(): void
    {
        nocache_headers();
        $post   = $this->post();
        $result = $this->page->handle_request([ 'wpmcp_connection_action' => 'provision' ] + $post);
        if (null === $result || ! isset($result['uuid'])) {
            wp_send_json_error([ 'message' => is_string($result['error'] ?? null) ? $result['error'] : __('The connection could not be created.', 'safe-mcp-for-woocommerce') ], 400);
        }

        ob_start();
        $this->steps->render($this->page->connect_screen($post, $result));
        $html = (string) ob_get_clean();

        wp_send_json_success([ 'html' => $html, 'uuid' => is_string($result['uuid']) ? $result['uuid'] : '' ]);
    }

    public function check(): void
    {
        nocache_headers();
        $post = $this->post();
        if (false === wp_verify_nonce($post['_wpnonce'] ?? '', Connection_Page::NONCE_ACTION)) {
            wp_send_json_error([ 'message' => __('Security check failed. Reload the page and try again.', 'safe-mcp-for-woocommerce') ], 403);
        }

        $app = Client_App::from_request($post['client'] ?? '');
        if (null === $app) {
            wp_send_json_error([ 'message' => __('Unknown app.', 'safe-mcp-for-woocommerce') ], 400);
        }

        $connector = 'connector' === ($post['route'] ?? '');
        $checked   = $this->page->check($app, $connector, $post['uuid'] ?? '', '1' === ($post['selftest'] ?? ''));
        if (null === $checked) {
            wp_send_json_error([ 'message' => __('That connection was not made on this screen.', 'safe-mcp-for-woocommerce') ], 404);
        }

        ob_start();
        $this->checks->render($checked['result'], $app, $connector, $checked['self_test']);
        $html = (string) ob_get_clean();

        wp_send_json_success([
            'verdict' => $checked['result']->verdict->value,
            'done'    => $checked['result']->is_done(),
            'html'    => $html,
        ]);
    }

    /**
     * The posted strings, unslashed. Anything that is not a plain string,
     * such as ?client[]=x, is left out.
     *
     * @return array<string, string>
     */
    private function post(): array
    {
        $post = [];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- each action verifies the screen's nonce: create inside Connection_Page::handle_request(), check above.
        foreach (wp_unslash($_POST) as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $post[ $key ] = $value;
            }
        }

        return $post;
    }
}
