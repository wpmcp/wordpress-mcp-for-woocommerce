<?php

declare(strict_types=1);

namespace WPMCP\Tools\Packages;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Whether install-plugin's activate:true step may run in the same call
 * (issue #1201). The activation is left for a separate activate-plugin call
 * when it could outlast the request or the client's tool timeout:
 *
 *  - less than Install_Plugin::ACTIVATION_TIME_NEEDED seconds of the PHP
 *    time limit are left (issue #728);
 *  - the install already used most of the client's budget (default 60 s,
 *    filter wpmcp_install_plugin_client_budget), so the activation would
 *    end after the client stopped waiting;
 *  - the plugin is large (default 40 MB on disk, filter
 *    wpmcp_install_plugin_heavy_bytes): a large plugin's activation, such
 *    as WooCommerce creating its tables, can take longer than a client
 *    waits on one call.
 *
 * The time since the request started is filterable through
 * wpmcp_install_plugin_elapsed, which is also the test seam.
 */
final class Activation_Budget
{
    /** Seconds an MCP client is assumed to wait for one tool call. */
    public const CLIENT_BUDGET = 60;

    /** On-disk size from which a plugin's activation goes to its own call. */
    public const HEAVY_BYTES = 40 * 1024 * 1024;

    private function __construct()
    {
    }

    /** Why the activation should be a separate call, or null to run it now. */
    public static function deferral_reason(string $plugin_file): ?string
    {
        if (! Install_Plugin::time_left_for_activation()) {
            return 'The install used most of the PHP time limit, so the activation did not run.';
        }

        $budget = (float) apply_filters('wpmcp_install_plugin_client_budget', self::CLIENT_BUDGET);
        if ($budget > 0 && $budget - self::elapsed() < Install_Plugin::ACTIVATION_TIME_NEEDED) {
            return 'The install took most of the time a client waits for one call, so the activation did not run, to keep the call from timing out.';
        }

        $heavy = (int) apply_filters('wpmcp_install_plugin_heavy_bytes', self::HEAVY_BYTES);
        if ($heavy > 0 && self::plugin_bytes($plugin_file) >= $heavy) {
            return 'This is a large plugin whose activation can take longer than a client waits for one call, so it was installed but not activated.';
        }

        return null;
    }

    /** Seconds since the request started. */
    public static function elapsed(): float
    {
        $started = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);
        return (float) apply_filters('wpmcp_install_plugin_elapsed', max(0.0, microtime(true) - $started));
    }

    /** Bytes on disk of the plugin's folder (or its single file), symlinks skipped. */
    public static function plugin_bytes(string $plugin_file): int
    {
        $path = WP_PLUGIN_DIR . '/' . $plugin_file;
        $dir  = dirname($path);
        if (rtrim(WP_PLUGIN_DIR, '/') === rtrim($dir, '/')) {
            return is_file($path) ? (int) filesize($path) : 0;
        }
        if (! is_dir($dir)) {
            return 0;
        }

        $bytes = 0;
        try {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && ! $file->isLink()) {
                    $bytes += (int) $file->getSize();
                }
            }
        } catch (\UnexpectedValueException $e) {
            unset($e);
        }
        return $bytes;
    }
}
