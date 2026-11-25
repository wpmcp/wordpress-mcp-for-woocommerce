<?php

namespace WPMCP\Admin;

use WPMCP\MCP\Request_Log;
use WPMCP\Safety\Mutation_Failed;
use WPMCP\Safety\Snapshot_Store;
use WPMCP\Tools\Rollback_Operation;
use WPMCP\Tools\Rollback_Session;

if (! defined('ABSPATH')) {
    exit;
}

class Restore_Controller
{
    /** AJAX action and nonce action of the Audit Log's Undo session button (issue #1150). */
    public const SESSION_ACTION = 'wpmcp_undo_session';

    /** @return array|\WP_Error rollback-operation's result, or its error for an unknown id */
    public function restore(string $operation_id)
    {
        return (new Rollback_Operation())->handle(['operation_id' => $operation_id]);
    }

    public function handle(): void
    {
        if (! current_user_can(Management_Access::CAPABILITY) || ! check_ajax_referer('wpmcp_restore', 'nonce', false)) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        // One Audit Log row can stand for a call that wrote several
        // operations (issue #1126): their ids come comma-separated, newest
        // first, and are undone in that order. The first refusal stops the
        // rest and is reported with the tool's own message.
        $ids = array_values(array_filter(array_map('trim', explode(',', sanitize_text_field(wp_unslash($_POST['operation_id'] ?? '')))), 'strlen'));
        if ([] === $ids) {
            wp_send_json_error(['message' => __('No change was named to restore.', 'safe-mcp-for-woocommerce')], 400);
        }

        $restored = 0;
        $warnings = [];
        $last     = [];
        foreach ($ids as $operation_id) {
            try {
                $out = $this->restore($operation_id);
            } catch (Mutation_Failed $e) {
                wp_send_json_error(['message' => $e->getMessage(), 'restored_count' => $restored], 409);
            }
            if (is_wp_error($out)) {
                wp_send_json_error(['message' => $out->get_error_message(), 'restored_count' => $restored], 409);
            }
            if (empty($out['restored'])) {
                $why = implode(' ', array_map('strval', (array) ($out['warnings'] ?? [])));
                wp_send_json_error([
                    'message'        => '' !== $why ? $why : __('Nothing was restored.', 'safe-mcp-for-woocommerce'),
                    'restored_count' => $restored,
                ], 409);
            }
            $restored++;
            $warnings = array_merge($warnings, array_map('strval', (array) ($out['warnings'] ?? [])));
            $last     = $out;
        }

        wp_send_json_success(array_merge($last, ['restored' => true, 'restored_count' => $restored, 'warnings' => $warnings]));
    }

    /**
     * Undo a whole session from wp-admin (issue #1150) through the same
     * Rollback_Session handler the wpmcp/rollback-session ability runs, so
     * the count, the warnings and every refusal (pruned, erased, edit lock,
     * theme pair, another user's session) are the tool's own. A refusal is
     * all or nothing inside Rollback_Service, so nothing is written then.
     *
     * The catch-all session ('' and 'default') is refused here: it collects
     * unrelated writes made without a session_id, so undoing it in one click
     * would undo far more than one run.
     */
    public function handle_session(): void
    {
        if (! current_user_can(Management_Access::CAPABILITY) || ! check_ajax_referer(self::SESSION_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        $session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
        if (Snapshot_Store::is_loose_session($session_id)) {
            wp_send_json_error([
                'message' => __('These writes were made without a session, so they cannot be undone together. Restore them one at a time.', 'safe-mcp-for-woocommerce'),
            ], 400);
        }

        $started = microtime(true);
        try {
            $result = (new Rollback_Session())->handle(['session_id' => $session_id]);
        } catch (Mutation_Failed $e) {
            $this->log_session_undo($session_id, $started, $e);
            wp_send_json_error(['message' => $e->getMessage()], 409);
        }

        $this->log_session_undo($session_id, $started, null);
        wp_send_json_success($result);
    }

    /**
     * The undo shows on the Audit Log's Requests tab like an agent's call,
     * marked as coming from wp-admin.
     */
    private function log_session_undo(string $session_id, float $started, ?\Throwable $error): void
    {
        $parts = null === $error ? [] : explode('\\', get_class($error));
        Request_Log::record([
            'tool'          => 'wpmcp/rollback-session',
            'client'        => 'wp-admin',
            'ok'            => null === $error,
            'error_code'    => null === $error ? '' : 'exception:' . end($parts),
            'error_message' => null === $error ? '' : $error->getMessage(),
            'duration_ms'   => (int) round((microtime(true) - $started) * 1000),
            'args'          => ['session_id' => $session_id],
        ]);
    }
}
