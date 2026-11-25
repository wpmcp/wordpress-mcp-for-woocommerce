<?php

namespace WPMCP\Governance;

use WPMCP\Kernel\Registry_Rows;
use WPMCP\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Admin toggles for the operations that are off by default behind an
 * opt-in filter (issue #823 part 4), so a site owner without code access
 * can switch a group on.
 *
 * Each group is one opt-in filter (wpmcp_enable_memberpress_write,
 * wpmcp_woo_op_enabled, ...). The toggle works by hooking that same filter
 * at the earliest priority: while the toggle is on and nothing else hooks
 * the filter, it answers true. The rules:
 *
 *  - Every toggle defaults off, and nothing but the admin screen writes it.
 *  - Code wins. When any other callback hooks the filter (an mu-plugin, a
 *    theme, a test), the toggle stands down entirely and the screen shows
 *    the group as set in code, so the stored state never leaks into a site
 *    that decides in code.
 *  - Stored per site (OPTION) and, on multisite, network-wide
 *    (NETWORK_OPTION, set by a network admin). Either one switches the group
 *    on for a site.
 *  - Both options are locked in Option_Guard, so update-option, get-option
 *    and settings sync cannot touch them: an agent cannot enable its own
 *    permissions.
 *  - The code-only gates Opt_In_Gates lists (exec, database and file writes,
 *    migrations, package changes) are not groups here and never will be.
 *
 * Owners are named by file path, never by class: the directory builds drop
 * some of these files, and a group shows only when its owner ships in the
 * build. tests/free/Governance/OptInSettingsTest pins this list to every
 * opt_in_filter declared under src/.
 */
final class Opt_In_Settings
{
    /** Per-site toggles: filter name => 1. */
    public const OPTION = 'wpmcp_opt_in_groups';

    /** Network-wide toggles on multisite (sitemeta): filter name => 1. */
    public const NETWORK_OPTION = 'wpmcp_opt_in_groups_network';

    /**
     * Groups that are a behaviour, not operations an agent runs, so no
     * opt_in_filter declares them. Each is named by its owner file.
     */
    public const BEHAVIOUR_GROUPS = [ 'wpmcp_enable_user_credential_restore' ];

    /**
     * Groups a network decides as a whole: on multisite only the network
     * toggle counts, so only a network administrator can switch them on.
     * Restoring login sessions and application passwords with a user is
     * one, since usermeta is shared by every site of the network.
     */
    public const NETWORK_ONLY_GROUPS = [ 'wpmcp_enable_user_credential_restore' ];

    /** @var array<string, array{owner: string, destructive: bool}>|null */
    private static ?array $shipped = null;

    /**
     * filter => [owner file relative to the plugin root, destructive].
     * A destructive group includes an operation that deletes data or whose
     * effect undo cannot reverse (mail sent, a backup started).
     *
     * @return array<string, array{owner: string, destructive: bool}>
     */
    public static function all_groups(): array
    {
        $i = 'src/Integrations/';
        return Registry_Rows::merge([
            'wpmcp_woo_op_enabled'                 => [ 'owner' => 'src/Tools/WooCommerce/Catalog/Woo_Write.php', 'destructive' => true ],
            'wpmcp_enable_memberpress_write'       => [ 'owner' => $i . 'MemberPress_Pack.php', 'destructive' => false ],
            'wpmcp_enable_gamipress_write'         => [ 'owner' => $i . 'Gamipress_Pack.php', 'destructive' => true ],
            'wpmcp_enable_thrive_optimize_write'   => [ 'owner' => $i . 'Thrive_Optimize_Pack.php', 'destructive' => true ],
            'wpmcp_enable_forms_write'             => [ 'owner' => $i . 'Forms_Integration.php', 'destructive' => false ],
            'wpmcp_enable_ws_form_write'           => [ 'owner' => $i . 'WS_Form_Pack.php', 'destructive' => true ],
            'wpmcp_enable_everest_forms_write'     => [ 'owner' => $i . 'Everest_Forms_Pack.php', 'destructive' => true ],
            'wpmcp_enable_elementor_pro_write'     => [ 'owner' => $i . 'Elementor_Pro_Forms_Pack.php', 'destructive' => true ],
            'wpmcp_enable_theme_write'             => [ 'owner' => $i . 'Theme_Integration.php', 'destructive' => true ],
            'wpmcp_enable_seo_redirect_write'      => [ 'owner' => $i . 'Rank_Math_Redirects_Pack.php', 'destructive' => true ],
            'wpmcp_enable_acf_write'               => [ 'owner' => $i . 'ACF_Integration.php', 'destructive' => false ],
            'wpmcp_enable_metabox_write'           => [ 'owner' => $i . 'Meta_Box_Integration.php', 'destructive' => false ],
            'wpmcp_enable_jetengine_write'         => [ 'owner' => $i . 'JetEngine_Pack.php', 'destructive' => false ],
            'wpmcp_enable_pods_write'              => [ 'owner' => $i . 'Pods_Pack.php', 'destructive' => false ],
            'wpmcp_enable_core_site_write'         => [ 'owner' => $i . 'Core_Site_Pack.php', 'destructive' => true ],
            'wpmcp_enable_core_patterns_write'     => [ 'owner' => $i . 'Core_Patterns_Pack.php', 'destructive' => false ],
            'wpmcp_enable_core_privacy_write'      => [ 'owner' => $i . 'Core_Privacy_Pack.php', 'destructive' => true ],
            'wpmcp_enable_font_library_write'      => [ 'owner' => $i . 'Core_Fonts_Pack.php', 'destructive' => true ],
            'wpmcp_enable_cache_settings_write'    => [ 'owner' => $i . 'Cache_Settings_Pack.php', 'destructive' => false ],
            'wpmcp_enable_site_gate_write'         => [ 'owner' => $i . 'Site_Gate_Pack.php', 'destructive' => true ],
            'wpmcp_enable_redux_write'             => [ 'owner' => $i . 'Redux_Pack.php', 'destructive' => false ],
            'wpmcp_enable_login_url_write'         => [ 'owner' => $i . 'Login_Url_Pack.php', 'destructive' => true ],
            'wpmcp_enable_consent_write'           => [ 'owner' => $i . 'Consent_Pack.php', 'destructive' => true ],
            'wpmcp_enable_action_scheduler_write'  => [ 'owner' => $i . 'Action_Scheduler_Pack.php', 'destructive' => true ],
            'wpmcp_enable_crm_send'                => [ 'owner' => $i . 'CRM_Adapter.php', 'destructive' => true ],
            'wpmcp_enable_funnelkit_automations_write' => [ 'owner' => $i . 'CRM_FunnelKit.php', 'destructive' => false ],
            'wpmcp_enable_helpdesk_write'          => [ 'owner' => $i . 'Helpdesk_Adapter.php', 'destructive' => true ],
            'wpmcp_enable_booking_write'           => [ 'owner' => $i . 'Booking_Adapter.php', 'destructive' => true ],
            'wpmcp_enable_learndash_learner_write' => [ 'owner' => $i . 'LMS_LearnDash.php', 'destructive' => false ],
            'wpmcp_enable_thrive_apprentice_write' => [ 'owner' => $i . 'LMS_Thrive_Apprentice.php', 'destructive' => false ],
            'wpmcp_enable_smtp_write'              => [ 'owner' => $i . 'SMTP_Pack_Support.php', 'destructive' => true ],
            'wpmcp_enable_backup_plugin_run'       => [ 'owner' => $i . 'UpdraftPlus_Status.php', 'destructive' => true ],
            'wpmcp_enable_pretty_links_write'      => [ 'owner' => $i . 'Pretty_Links_Pack.php', 'destructive' => false ],
            'wpmcp_enable_instagram_feed_write'    => [ 'owner' => $i . 'Instagram_Feed_Pack.php', 'destructive' => false ],
            'wpmcp_enable_thirstyaffiliates_write' => [ 'owner' => $i . 'Thirsty_Affiliates_Pack.php', 'destructive' => false ],
            'wpmcp_enable_site_reviews_write'      => [ 'owner' => $i . 'Site_Reviews_Pack.php', 'destructive' => false ],
            'wpmcp_enable_thrive_ovation_write'    => [ 'owner' => $i . 'Thrive_Ovation_Pack.php', 'destructive' => false ],
            'wpmcp_enable_publishpress_write'      => [ 'owner' => $i . 'PublishPress_Pack.php', 'destructive' => false ],
            'wpmcp_enable_wpforo_write'            => [ 'owner' => $i . 'WpForo_Pack.php', 'destructive' => false ],
            'wpmcp_enable_asgaros_write'           => [ 'owner' => $i . 'Asgaros_Forum_Pack.php', 'destructive' => true ],
            'wpmcp_enable_block_suite_settings_write' => [ 'owner' => $i . 'Block_Suite_Settings.php', 'destructive' => false ],
            'wpmcp_enable_otter_settings_write'    => [ 'owner' => $i . 'Block_Suite_Otter.php', 'destructive' => false ],
            'wpmcp_enable_restrict_content_write'  => [ 'owner' => $i . 'Restrict_Content_Pack.php', 'destructive' => false ],
            'wpmcp_enable_ultimate_member_write'   => [ 'owner' => $i . 'Ultimate_Member_Pack.php', 'destructive' => false ],
            'wpmcp_enable_edd_write'               => [ 'owner' => $i . 'EDD_Pack.php', 'destructive' => false ],
            'wpmcp_enable_translation_settings_write' => [ 'owner' => $i . 'Translation_Settings_Pack.php', 'destructive' => false ],
            'wpmcp_enable_toc_write'               => [ 'owner' => $i . 'Easy_TOC_Pack.php', 'destructive' => false ],
            'wpmcp_enable_surecart_write'          => [ 'owner' => $i . 'SureCart_Pack.php', 'destructive' => false ],
            'wpmcp_enable_string_translation_write' => [ 'owner' => $i . 'I18n_Strings.php', 'destructive' => false ],
            'wpmcp_enable_cartflows_write'         => [ 'owner' => $i . 'CartFlows_Pack.php', 'destructive' => false ],
            'wpmcp_enable_advanced_ads_write'      => [ 'owner' => $i . 'Advanced_Ads_Pack.php', 'destructive' => false ],
            'wpmcp_enable_maps_write'              => [ 'owner' => $i . 'Maps_Pack.php', 'destructive' => false ],
            'wpmcp_enable_megamenu_write'          => [ 'owner' => $i . 'Max_Mega_Menu_Pack.php', 'destructive' => false ],
            'wpmcp_enable_discount_rules_write'    => [ 'owner' => $i . 'Discount_Rules_Pack.php', 'destructive' => false ],
            'wpmcp_enable_popup_builder_write'     => [ 'owner' => $i . 'Popup_Builder_Pack.php', 'destructive' => false ],
            'wpmcp_enable_wpfunnels_write'         => [ 'owner' => $i . 'WPFunnels_Pack.php', 'destructive' => false ],
            'wpmcp_enable_thrive_automator_write'  => [ 'owner' => $i . 'Thrive_Automator_Pack.php', 'destructive' => true ],
            'wpmcp_enable_thrive_leads_write'      => [ 'owner' => $i . 'Thrive_Leads_Pack.php', 'destructive' => false ],
            'wpmcp_enable_listings_write'          => [ 'owner' => $i . 'Listings_Pack.php', 'destructive' => false ],
            'wpmcp_enable_jetpack_crm_write'       => [ 'owner' => $i . 'Jetpack_Crm_Pack.php', 'destructive' => false ],
            'wpmcp_enable_popup_maker_write'       => [ 'owner' => $i . 'Popup_Maker_Pack.php', 'destructive' => false ],
            'wpmcp_enable_optinmonster_write'      => [ 'owner' => $i . 'OptinMonster_Pack.php', 'destructive' => false ],
            'wpmcp_enable_events_manager_write'    => [ 'owner' => $i . 'Events_Manager_Pack.php', 'destructive' => false ],
            'wpmcp_enable_tec_write'               => [ 'owner' => $i . 'The_Events_Calendar_Writes.php', 'destructive' => false ],
            'wpmcp_enable_mec_write'               => [ 'owner' => $i . 'Modern_Events_Calendar_Writes.php', 'destructive' => false ],
            'wpmcp_enable_image_optimizer_write'   => [ 'owner' => $i . 'Image_Optimizer_Pack.php', 'destructive' => true ],
            'wpmcp_enable_data_tables_write'       => [ 'owner' => $i . 'Data_Tables_Pack.php', 'destructive' => false ],
            'wpmcp_enable_foogallery_write'        => [ 'owner' => $i . 'FooGallery_Pack.php', 'destructive' => false ],
            'wpmcp_enable_filebird_write'          => [ 'owner' => $i . 'FileBird_Pack.php', 'destructive' => true ],
            'wpmcp_enable_jetformbuilder_write'    => [ 'owner' => $i . 'JetFormBuilder_Operations.php', 'destructive' => true ],
            'wpmcp_enable_kadence_forms_write'     => [ 'owner' => $i . 'Kadence_Forms_Operations.php', 'destructive' => false ],
            'wpmcp_enable_jetpack_forms_write'     => [ 'owner' => $i . 'Jetpack_Forms_Operations.php', 'destructive' => true ],
            'wpmcp_enable_newsletter_write'        => [ 'owner' => $i . 'Mailing_List_Newsletter.php', 'destructive' => false ],
            'wpmcp_enable_wpcode_write'            => [ 'owner' => $i . 'WPCode_Pack.php', 'destructive' => false ],
            'wpmcp_enable_loco_write'              => [ 'owner' => $i . 'Loco_Translate_Pack.php', 'destructive' => false ],
            'wpmcp_enable_give_write'              => [ 'owner' => $i . 'Give_Integration.php', 'destructive' => true ],
            'wpmcp_enable_search_write'            => [ 'owner' => $i . 'Search_Plugin_Support.php', 'destructive' => false ],
            'wpmcp_enable_email_marketing_write'   => [ 'owner' => $i . 'Email_Marketing_Pack.php', 'destructive' => false ],
            'wpmcp_enable_tracking_write'          => [ 'owner' => $i . 'Tracking_Pack.php', 'destructive' => false ],
            'wpmcp_enable_search_replace_write'    => [ 'owner' => $i . 'Search_Replace_Pack.php', 'destructive' => false ],
            'wpmcp_enable_thrive_ultimatum_write'  => [ 'owner' => $i . 'Thrive_Ultimatum_Pack.php', 'destructive' => false ],
            'wpmcp_enable_cpt_builder_write'       => [ 'owner' => $i . 'Cpt_Builder_Pack.php', 'destructive' => false ],
            'wpmcp_enable_hardening_write'         => [ 'owner' => $i . 'Hardening_Pack.php', 'destructive' => false ],
            'wpmcp_enable_checkout_field_editor_write' => [ 'owner' => $i . 'Checkout_Field_Editor_Pack.php', 'destructive' => false ],
            'wpmcp_enable_pmpro_write'             => [ 'owner' => $i . 'Paid_Memberships_Pro_Pack.php', 'destructive' => true ],
            'wpmcp_enable_user_credential_restore' => [ 'owner' => 'src/Safety/Rollback_Service.php', 'destructive' => false ], // central-list-ok: a safety-core undo behaviour, not an integration's opt-in
        ], self::module_groups(), 'Opt-in group');
    }

    /**
     * The groups modules declare with #[Opt_In_Gate], from the compiled
     * registry. The owner is the declaring class's file, so the same
     * is_file() check decides whether it ships.
     *
     * @return array<string, array{owner: string, destructive: bool}>
     */
    private static function module_groups(): array
    {
        $registry = Plugin::instance()->kernel()->registry();
        $groups = [];
        foreach ($registry->opt_in_gates() as $filter => $gate) {
            $module = $registry->module(self::text($gate['module'] ?? null)) ?? [];
            $namespace = self::text($module['namespace'] ?? null);
            $class = self::text($gate['class'] ?? null);
            $relative = str_starts_with($class, $namespace) ? substr($class, strlen($namespace)) : $class;
            $groups[ $filter ] = [
                'owner' => rtrim(self::text($module['dir'] ?? null), '/') . '/' . str_replace('\\', '/', $relative) . '.php',
                'destructive' => true === ($gate['destructive'] ?? false),
            ];
        }
        return $groups;
    }

    /** A registry value as a string ('' when it is not one). */
    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /**
     * Screen labels of the module groups: each gate names a static method
     * on its class returning filter => label.
     *
     * @return array<string, string>
     */
    private static function module_labels(): array
    {
        $labels = [];
        foreach (Plugin::instance()->kernel()->registry()->opt_in_gates() as $filter => $gate) {
            $method = self::text($gate['labels'] ?? null);
            $callback = [ self::text($gate['class'] ?? null), $method ];
            if ('' === $method || ! is_callable($callback)) {
                continue;
            }
            $declared = (array) call_user_func($callback);
            $labels[ $filter ] = is_string($declared[ $filter ] ?? null) ? $declared[ $filter ] : $filter;
        }
        return $labels;
    }

    /**
     * Screen labels, kept apart from all_groups() so the boot-time hook
     * registration never loads translations early.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return Registry_Rows::merge([
            'wpmcp_woo_op_enabled'                 => __('WooCommerce: destructive operations (delete, cancel, refund)', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_memberpress_write'       => __('MemberPress: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_gamipress_write'         => __('GamiPress: points, achievement and rank writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_thrive_optimize_write'   => __('Thrive Optimize: traffic splits, stopping tests and picking winners', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_forms_write'             => __('Form plugins: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_ws_form_write'           => __('WS Form: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_everest_forms_write'     => __('Everest Forms: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_elementor_pro_write'     => __('Elementor Pro forms: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_theme_write'             => __('Themes: settings, layouts and child themes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_seo_redirect_write'      => __('Rank Math: redirect writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_acf_write'               => __('ACF: field and field group writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_metabox_write'           => __('Meta Box: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_jetengine_write'         => __('JetEngine: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_pods_write'              => __('Pods: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_core_site_write'         => __('Core site settings: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_core_patterns_write'     => __('Block patterns: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_font_library_write'      => __('Font Library: install and delete fonts', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_cache_settings_write'    => __('Cache plugin settings: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_site_gate_write'         => __('Maintenance and coming soon mode', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_redux_write'             => __('Redux Framework: options panel writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_login_url_write'         => __('WPS Hide Login: change the login URL', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_consent_write'           => __('Cookie consent banners: banner text writes and a Complianz scan restart', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_action_scheduler_write'  => __('Action Scheduler: run and cancel actions', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_crm_send'                => __('CRM: send email', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_funnelkit_automations_write' => __('FunnelKit Automations: contact tags and lists, automation status', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_helpdesk_write'          => __('Helpdesk: ticket writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_booking_write'           => __('Bookly, Simply Schedule Appointments, BookingPress and LatePoint: cancel bookings', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_learndash_learner_write' => __('LearnDash: learner enrolment and progress', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_thrive_apprentice_write' => __('Thrive Apprentice: course and lesson writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_smtp_write'              => __('SMTP plugins: send a test email', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_backup_plugin_run'       => __('Backup plugins (UpdraftPlus, BackWPup): start a backup', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_pretty_links_write'      => __('Pretty Links: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_instagram_feed_write'    => __('Instagram Feed: feed settings and cache clears', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_thirstyaffiliates_write' => __('ThirstyAffiliates: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_site_reviews_write'      => __('Site Reviews: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_thrive_ovation_write'    => __('Thrive Ovation: testimonial writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_publishpress_write'      => __('PublishPress Planner and Future: status changes and scheduled changes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_wpforo_write'            => __('wpForo: close, open and move topics', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_asgaros_write'           => __('Asgaros Forum: post, moderate, move and delete', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_core_privacy_write'      => __('Privacy requests: export and erase personal data', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_block_suite_settings_write' => __('Kadence Blocks and Spectra: plugin settings writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_otter_settings_write'    => __('Otter Blocks: settings writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_restrict_content_write'  => __('Restrict Content: membership level changes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_ultimate_member_write'   => __('Ultimate Member: member role changes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_edd_write'               => __('Easy Digital Downloads: download and discount writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_translation_settings_write' => __('GTranslate: language settings writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_toc_write'               => __('Easy Table of Contents: settings and per-post flags', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_surecart_write'          => __('SureCart: product edits and coupon creates', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_string_translation_write' => __('Polylang: theme and plugin string translations', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_cartflows_write'         => __('CartFlows: flow, step and checkout product writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_advanced_ads_write'      => __('Advanced Ads: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_maps_write'              => __('WP Go Maps and WP Store Locator: marker and store writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_megamenu_write'          => __('Max Mega Menu: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_discount_rules_write'    => __('Discount Rules for WooCommerce: pricing rule writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_popup_builder_write'     => __('Popup Builder: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_wpfunnels_write'         => __('WPFunnels: funnel and checkout product writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_thrive_automator_write'  => __('Thrive Automator: turn automations on and off', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_thrive_leads_write'      => __('Thrive Leads: form and display rule writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_listings_write'          => __('WP Job Manager, GeoDirectory and Directorist: listing writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_jetpack_crm_write'       => __('Jetpack CRM: contact writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_popup_maker_write'       => __('Popup Maker: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_optinmonster_write'      => __('OptinMonster: switch campaigns on or off', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_events_manager_write'    => __('Events Manager: event writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_tec_write'               => __('The Events Calendar: event, venue and organizer writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_mec_write'               => __('Modern Events Calendar: event, location and organizer writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_image_optimizer_write'   => __('Image optimization plugins: optimize an image', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_data_tables_write'       => __('wpDataTables and Ninja Tables: cell and row writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_foogallery_write'        => __('FooGallery: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_filebird_write'          => __('FileBird: folder writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_jetformbuilder_write'    => __('JetFormBuilder: entry read state and delete', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_kadence_forms_write'     => __('Kadence Blocks forms: email notification changes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_jetpack_forms_write'     => __('Jetpack Forms: response status, read state and delete', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_newsletter_write'        => __('Newsletter: contact writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_wpcode_write'            => __('WPCode: snippet and global script writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_loco_write'              => __('Loco Translate: .po string edits and .mo compiles', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_give_write'              => __('GiveWP: donation form and donation status writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_search_write'            => __('Search plugins (Relevanssi, FiboSearch): settings writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_email_marketing_write'   => __('Klaviyo and Kit: signup and form settings writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_tracking_write'          => __('Tracking plugins (GTM4WP, Microsoft Clarity): ID and event settings writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_search_replace_write'    => __('Search and replace (Better Search Replace, Search Regex): run a database search and replace', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_thrive_ultimatum_write'  => __('Thrive Ultimatum: campaign writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_cpt_builder_write'       => __('Custom Post Type UI and MB Custom Post Types: post type and taxonomy writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_hardening_write'         => __('Security hardening settings: writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_checkout_field_editor_write' => __('Checkout Field Editor: checkout field writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_pmpro_write'             => __('Paid Memberships Pro: level, member and discount code writes', 'safe-mcp-for-woocommerce'),
            'wpmcp_enable_user_credential_restore' => __('Undo of a user change: also restore their login sessions and application passwords, bringing back ones revoked after the change', 'safe-mcp-for-woocommerce'), // central-list-ok: a safety-core undo behaviour, not an integration's opt-in
        ], self::module_labels(), 'Opt-in label');
    }

    /**
     * The groups whose owner ships in this build.
     *
     * @return array<string, array{owner: string, destructive: bool}>
     */
    public static function groups(): array
    {
        if (null !== self::$shipped) {
            return self::$shipped;
        }
        $root = defined('WPMCP_DIR') ? (string) WPMCP_DIR : dirname(__DIR__, 2) . '/';
        return self::$shipped = array_filter(
            self::all_groups(),
            static fn (array $group): bool => is_file($root . $group['owner']) || is_file($root . 'pro/' . $group['owner']) // central-list-ok: owners that moved to pro/ (the Pro split)
        );
    }

    /** Hook every shipped group's filter. Idempotent. */
    public static function register(): void
    {
        foreach (array_keys(self::groups()) as $filter) {
            add_filter($filter, [ self::class, 'apply' ], PHP_INT_MIN, 1);
        }
    }

    /**
     * The filter callback: true while the current filter's toggle is on
     * and no code hooks it; otherwise the value unchanged.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function apply($value)
    {
        $filter = (string) current_filter();
        if (self::set_in_code($filter) || ! self::is_on($filter)) {
            return $value;
        }
        return true;
    }

    /** Whether anything other than this class hooks $filter. */
    public static function set_in_code(string $filter): bool
    {
        global $wp_filter;
        $hook = $wp_filter[ $filter ] ?? null;
        if (! $hook instanceof \WP_Hook) {
            return false;
        }
        foreach ($hook->callbacks as $callbacks) {
            foreach ($callbacks as $callback) {
                $fn = $callback['function'] ?? null;
                if (! (is_array($fn) && self::class === ($fn[0] ?? null) && 'apply' === ($fn[1] ?? null))) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Whether the stored toggle for $filter is on (site or network; network only for a network-only group on multisite). */
    public static function is_on(string $filter): bool
    {
        if (! isset(self::groups()[ $filter ])) {
            return false;
        }
        if (self::network_only($filter)) {
            return self::stored_on($filter, 'network');
        }
        return self::stored_on($filter, 'site') || (is_multisite() && self::stored_on($filter, 'network'));
    }

    /** Whether $filter is decided network-wide only: a network-only group, on multisite. */
    public static function network_only(string $filter): bool
    {
        return is_multisite() && in_array($filter, self::NETWORK_ONLY_GROUPS, true);
    }

    /** Whether $filter is on in one scope's store ('site' or 'network'). */
    public static function stored_on(string $filter, string $scope): bool
    {
        $stored = 'network' === $scope ? get_site_option(self::NETWORK_OPTION, []) : get_option(self::OPTION, []);
        return is_array($stored) && ! empty($stored[ $filter ]);
    }

    /**
     * Store one toggle. The caller (the admin screen) checks capability,
     * nonce and "set in code" first; this only refuses an unknown group or a
     * network write off multisite.
     */
    public static function set(string $filter, bool $enabled, string $scope): bool
    {
        if (! isset(self::groups()[ $filter ]) || ('network' === $scope && ! is_multisite())) {
            return false;
        }
        if ('network' !== $scope && self::network_only($filter)) {
            return false;
        }

        $network = 'network' === $scope;
        $stored  = $network ? get_site_option(self::NETWORK_OPTION, []) : get_option(self::OPTION, []);
        $stored  = is_array($stored) ? array_intersect_key($stored, self::all_groups()) : [];
        if ($enabled) {
            $stored[ $filter ] = 1;
        } else {
            unset($stored[ $filter ]);
        }

        if ($network) {
            update_site_option(self::NETWORK_OPTION, $stored);
        } else {
            update_option(self::OPTION, $stored);
        }
        return true;
    }
}
