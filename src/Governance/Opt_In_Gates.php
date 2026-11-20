<?php

namespace WPMCP\Governance;

use WPMCP\Tools\Bridge\Bridge_Guard;
use WPMCP\Tools\Cli\Wp_Cli_Guard;
use WPMCP\Tools\CustomCode\Custom_Js_Guard;
use WPMCP\Tools\Packages\Delete_Plugin;
use WPMCP\Tools\Packages\Delete_Theme;
use WPMCP\Tools\Packages\Package_Guard;
use WPMCP\Tools\Packages\Update_Plugin;
use WPMCP\Tools\Packages\Update_Theme;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read-only view of the default-off execution opt-in gates (issue #78).
 *
 * The RCE-class / destructive ability groups - exec (WP-CLI, PHP snippets),
 * database writes, and filesystem writes - each guard their own execution
 * behind a default-false opt-in filter checked inside the tool
 * (wpmcp_allow_wp_cli, wpmcp_allow_php_exec, wpmcp_enable_db_writes,
 * wpmcp_enable_fs_writes). Those filters ARE the master gates and this class
 * does not touch them: it only maps ability names to the owning tool's own
 * is_enabled() check so the ability grid can (a) mark these rows with a
 * distinct warning and (b) REFUSE to write an enabling governance toggle
 * while the gate is closed - the grid must never become a UI that appears
 * to open a gate only code can open.
 */
class Opt_In_Gates
{
    /**
     * ability name => [opt-in filter name, the owning tool's gate check].
     * The callable is the tool class's own is_enabled(), so the single
     * source of truth for "is this gate open?" stays in the tool.
     *
     * @return array<string, array{filter: string, is_open: callable}>
     */
    private static function gates(): array
    {
        return [
            'wpmcp/run-wp-cli'      => ['filter' => 'wpmcp_allow_wp_cli', 'is_open' => [Wp_Cli_Guard::class, 'is_enabled']],
            // The async dispatcher (issue #84) reaches the same executor
            // through the same guard chain, so it is the same gate. Its
            // poll/list/cancel siblings only read and transition job
            // records and are deliberately NOT listed: they cannot run a
            // command, and marking them dangerous would blunt the warning.
            'wpmcp/dispatch-cli-job' => ['filter' => 'wpmcp_allow_wp_cli', 'is_open' => [Wp_Cli_Guard::class, 'is_enabled']],
            // Stored custom JS (issue #63) is the same shape of gate: an
            // XSS-class surface that is default-off behind its own filter.
            // Its sibling wpmcp/add-scoped-css is NOT listed: CSS is
            // sanitized reject-not-clean and has no opt-in filter to open,
            // and marking it dangerous would blunt the warning.
            'wpmcp/add-custom-js'   => ['filter' => 'wpmcp_allow_js_injection', 'is_open' => [Custom_Js_Guard::class, 'is_enabled']],
            // The third-party ability bridge (issue #194): all three shells
            // share one site-level gate. The two discovery tools are listed
            // as well as the executor because the gate closes the whole
            // surface, and a grid row that could be flipped "enabled"
            // while the constant/filter is off would be exactly the UI
            // this class exists to prevent.
            'wpmcp/list-site-abilities'  => ['filter' => 'wpmcp_enable_ability_bridge', 'is_open' => [Bridge_Guard::class, 'is_enabled']],
            'wpmcp/get-site-ability'     => ['filter' => 'wpmcp_enable_ability_bridge', 'is_open' => [Bridge_Guard::class, 'is_enabled']],
            'wpmcp/execute-site-ability' => ['filter' => 'wpmcp_enable_ability_bridge', 'is_open' => [Bridge_Guard::class, 'is_enabled']],
            // Installing a plugin or theme from an uploaded ZIP (issue #282) puts
            // code from outside the directory on the site.
            'wpmcp/install-package-from-zip' => ['filter' => 'wpmcp_enable_zip_install', 'is_open' => [Package_Guard::class, 'zip_install_enabled']],
            // Deleting or updating a plugin or theme (issue #631) replaces or
            // removes code on disk, and each tool is default-off behind its
            // own filter, so each is a gate the grid must not appear to open.
            'wpmcp/delete-plugin' => ['filter' => 'wpmcp_enable_delete_plugin', 'is_open' => [Delete_Plugin::class, 'is_enabled']],
            'wpmcp/update-plugin' => ['filter' => 'wpmcp_enable_update_plugin', 'is_open' => [Update_Plugin::class, 'is_enabled']],
            'wpmcp/delete-theme'  => ['filter' => 'wpmcp_enable_delete_theme', 'is_open' => [Delete_Theme::class, 'is_enabled']],
            'wpmcp/update-theme'  => ['filter' => 'wpmcp_enable_update_theme', 'is_open' => [Update_Theme::class, 'is_enabled']],
        ];
    }

    /** Whether $ability is one of the default-off dangerous, gated abilities. */
    public static function is_gated(string $ability): bool
    {
        return isset(self::gates()[ $ability ]);
    }

    /** The opt-in filter guarding $ability, or null when it is not gated. */
    public static function filter_for(string $ability): ?string
    {
        return self::gates()[ $ability ]['filter'] ?? null;
    }

    /**
     * Whether $ability's execution gate is currently open. Ungated abilities
     * report true (there is nothing to open).
     */
    public static function is_open(string $ability): bool
    {
        $gate = self::gates()[ $ability ] ?? null;
        return null === $gate || (bool) ($gate['is_open'])();
    }
}
