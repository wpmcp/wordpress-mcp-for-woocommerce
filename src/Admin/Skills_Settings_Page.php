<?php

namespace WPMCP\Admin;

use WPMCP\Plugin;
use WPMCP\Skills\Skill_Library;
use WPMCP\Skills\Skills_Module;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin screen for the agent-skills surface (issue #74): the module on/off
 * checkbox, the catalog as this site's agents actually see it, and the
 * documents that were found but refused.
 *
 * The refused list is the point of having a screen at all. A custom SKILL.md
 * with a broken header is otherwise invisible: it simply never appears in an
 * agent's catalog, with no way to tell "not installed" from "installed and
 * rejected". Here it is named, with its validation error codes.
 *
 * the management capability like the rest of the wpmcp screens: the toggle changes what
 * every connecting agent is told about this site.
 */
class Skills_Settings_Page
{
    public const GROUP = 'wpmcp_skills';
    public const SLUG  = 'wpmcp-skills';

    /** Hooked on admin_init: register the toggle with its sanitizer. */
    public static function register_setting(): void
    {
        register_setting(self::GROUP, Skills_Module::OPTION, [
            'type'              => 'string',
            'default'           => '1',
            'sanitize_callback' => [Skills_Module::class, 'sanitize'],
        ]);
    }

    public function render(): void
    {
        // The catalog is memoized per request; drop it so this screen always
        // reflects what is on disk right now, not what an earlier call read.
        Skill_Library::reset();

        $enabled = Skills_Module::is_enabled();
        $skills  = Skill_Library::all();
        $invalid = Skill_Library::invalid();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(Plugin::page_title(__('Agent Skills', 'safe-mcp-for-woocommerce'))); ?></h1>
            <p>
                <?php
                echo esc_html__(
                    'Skills are versioned markdown playbooks served to connected agents through the list-skills and get-skill tools. Turning the surface off unregisters both tools entirely, so they cost a connecting client nothing.',
                    'safe-mcp-for-woocommerce'
                );
                ?>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>
                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr(Skills_Module::OPTION); ?>"
                            value="1"
                            <?php checked($enabled); ?>
                        />
                        <?php echo esc_html__('Serve agent skills over MCP', 'safe-mcp-for-woocommerce'); ?>
                    </label>
                </p>
                <?php submit_button(); ?>
            </form>

            <h2><?php echo esc_html__('Installed skills', 'safe-mcp-for-woocommerce'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Slug', 'safe-mcp-for-woocommerce'); ?></th>
                        <th><?php echo esc_html__('Name', 'safe-mcp-for-woocommerce'); ?></th>
                        <th><?php echo esc_html__('Version', 'safe-mcp-for-woocommerce'); ?></th>
                        <th><?php echo esc_html__('Source', 'safe-mcp-for-woocommerce'); ?></th>
                        <th><?php echo esc_html__('Status', 'safe-mcp-for-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ([] === $skills) : ?>
                    <tr><td colspan="5"><?php echo esc_html__('No skills found.', 'safe-mcp-for-woocommerce'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($skills as $skill) : ?>
                    <tr>
                        <td><code><?php echo esc_html($skill['slug']); ?></code></td>
                        <td><?php echo esc_html($skill['name']); ?></td>
                        <td><?php echo esc_html($skill['version']); ?></td>
                        <td><?php echo esc_html($skill['source']); ?></td>
                        <td>
                            <?php
                            if (! $skill['available']) {
                                echo esc_html(sprintf(
                                    /* translators: %s: comma-separated ability names. */
                                    __('Hidden, requires: %s', 'safe-mcp-for-woocommerce'),
                                    implode(', ', $skill['missing_abilities'])
                                ));
                            } else {
                                echo esc_html__('Served', 'safe-mcp-for-woocommerce');
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ([] !== $invalid) : ?>
                <h2><?php echo esc_html__('Rejected documents', 'safe-mcp-for-woocommerce'); ?></h2>
                <p>
                    <?php
                    echo esc_html__(
                        'These SKILL.md files were found but failed validation, so they are not served to any agent.',
                        'safe-mcp-for-woocommerce'
                    );
                    ?>
                </p>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('File', 'safe-mcp-for-woocommerce'); ?></th>
                            <th><?php echo esc_html__('Errors', 'safe-mcp-for-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invalid as $entry) : ?>
                        <tr>
                            <td><code><?php echo esc_html($entry['path']); ?></code></td>
                            <td><?php echo esc_html(implode(', ', $entry['errors'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
