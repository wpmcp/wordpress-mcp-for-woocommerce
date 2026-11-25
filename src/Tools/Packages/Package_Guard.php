<?php

namespace WPMCP\Tools\Packages;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Shared guardrails for the plugin/theme package tools.
 *
 * Protected packages (this plugin itself, and Elementor free/pro) can never
 * be deactivated or deleted through these tools: an agent that disables its
 * own delivery mechanism, or the site's page builder, would strand the site
 * with no way to recover other than direct server access.
 */
class Package_Guard
{
    /** Plugin file paths (relative to the plugins dir) that may never be deactivated or deleted. */
    private const PROTECTED_PLUGINS = [
        'wpmcp/wpmcp.php',
        // The licensed build installs to its own folder (the licensing SDK slug).
        'wpmcp-pro/wpmcp.php',
        'elementor/elementor.php',
        'elementor-pro/elementor-pro.php',
    ];

    public static function is_protected_plugin(string $plugin_file): bool
    {
        if (in_array($plugin_file, self::PROTECTED_PLUGINS, true)) {
            return true;
        }

        // The running copy, whatever folder it was installed in.
        $self = defined('WPMCP_FILE') ? constant('WPMCP_FILE') : null;
        if (is_string($self) && plugin_basename($self) === $plugin_file) {
            return true;
        }

        // Any other WPMCP copy, in whatever folder it sits: the licensing
        // service installs the premium plugin in a folder of its own, and
        // every build's main file declares a WPMCP Flavor header. Only a
        // folder/file.php inside the plugins folder is read.
        if (0 !== validate_file($plugin_file) || ! str_contains($plugin_file, '/') || ! function_exists('wpmcp_flavor_of')) {
            return false;
        }

        return '' !== wpmcp_flavor_of(WP_PLUGIN_DIR . '/' . $plugin_file);
    }

    /**
     * The refusal for a protected plugin (issue #631): which plugin, why it
     * is protected, and how a person can still do it. The list is fixed in
     * code, with no setting or filter to change it, so the way round it is
     * the Plugins screen in wp-admin, where a person does it by hand.
     *
     * @param string $action The verb, such as "deactivate", "delete" or "update".
     */
    public static function protected_plugin_refusal(string $action, string $plugin_file): string
    {
        $why = str_starts_with($plugin_file, 'elementor')
            ? 'Elementor is protected because it is the site\'s page builder: if it stops working, every page built with it stops rendering, and an agent could not put it back.'
            : 'WPMCP is protected because it is the connection this agent works through: if it stops working, the agent loses access to the site and cannot undo the change.';

        return sprintf(
            'Refusing to %1$s protected plugin "%2$s". %3$s WPMCP never lets a tool %1$s it, and no setting changes that; to %1$s it anyway, a site administrator can do it from Plugins in wp-admin.',
            $action,
            $plugin_file,
            $why
        );
    }

    /**
     * Whether a plugin DIRECTORY belongs to a protected package: the
     * directory of any protected plugin file, or the directory this plugin
     * is actually running from (a renamed install still protects itself).
     * install-package-from-zip checks this before it can overwrite anything.
     */
    public static function is_protected_plugin_dir(string $dir): bool
    {
        $dirs = array_map('dirname', self::PROTECTED_PLUGINS);
        if (defined('WPMCP_FILE')) {
            $dirs[] = dirname(plugin_basename(WPMCP_FILE));
        }
        return in_array(strtolower($dir), array_map('strtolower', $dirs), true);
    }

    /**
     * The default-off opt-in gate for installing packages from an uploaded
     * ZIP (issue #282). A ZIP from outside the directory is arbitrary code,
     * so the same constant-plus-filter pattern as the other execution-class
     * gates applies, and Governance\Opt_In_Gates reports it.
     */
    public static function zip_install_enabled(): bool
    {
        $default = defined('WPMCP_ENABLE_ZIP_INSTALL') && WPMCP_ENABLE_ZIP_INSTALL;

        return (bool) apply_filters('wpmcp_enable_zip_install', $default);
    }

    /**
     * Package installs/updates/deletes require WordPress to be able to write
     * to the filesystem directly (no FTP/SSH credential prompt). If the
     * detected method is anything other than 'direct', these tools refuse
     * rather than attempting a credential-based filesystem connection.
     */
    public static function filesystem_ready(): bool
    {
        if (! function_exists('get_filesystem_method')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        return 'direct' === get_filesystem_method();
    }
}
