<?php

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- ABSPATH guard is an intentional side effect.

namespace WPMCP;

// Plugin Check's Direct_File_Access_Check only accepts the bare defined()
// test; an extra conjunct makes it report the file as unprotected. The test
// bootstrap defines ABSPATH itself, so the guard needs no test escape hatch.
// It sits above the use block because the check's namespace-blind fallback
// only scans the first 50 lines of the file.
if (! defined('ABSPATH')) {
    exit;
}

use WPMCP\Admin\Audit_Log_Page;
use WPMCP\Admin\Management_Access;
use WPMCP\Admin\History_Page;
use WPMCP\Admin\Restore_Controller;
use WPMCP\Maintenance\Maintenance_Guard;
use WPMCP\Tools\Maintenance\Get_Maintenance_Status;
use WPMCP\Tools\Maintenance\Enable_Maintenance;
use WPMCP\Tools\Maintenance\Disable_Maintenance;
use WPMCP\Tools\Context\Get_Site_Context;
use WPMCP\Tools\Context\Get_Page_Snapshot;
use WPMCP\Tools\Context\Get_Rendered_Html;
use WPMCP\Tools\Rest\List_Rest_Routes;
use WPMCP\Tools\Rest\Call_Rest;
use WPMCP\Tools\Blocks\List_Block_Types;
use WPMCP\Tools\Blocks\Get_Block_Type;
use WPMCP\Tools\Blocks\Parse_Blocks;
use WPMCP\Tools\Blocks\Serialize_Blocks;
use WPMCP\Tools\Blocks\Convert_Html_To_Blocks;
use WPMCP\Tools\Blocks\Add_Block;
use WPMCP\Tools\Blocks\Update_Block;
use WPMCP\Tools\Blocks\Remove_Block;
use WPMCP\Tools\Blocks\Move_Block;
use WPMCP\Tools\Blocks\Duplicate_Block;
use WPMCP\Tools\Blocks\List_Patterns;
use WPMCP\Tools\Blocks\Insert_Pattern;
use WPMCP\Tools\Structure\List_Shortcodes;
use WPMCP\Tools\Structure\Render_Shortcode;
use WPMCP\Tools\Structure\List_Sidebars;
use WPMCP\Tools\Structure\List_Sidebar_Widgets;
use WPMCP\Tools\Structure\Create_Sidebar_Widget;
use WPMCP\Tools\Structure\Update_Sidebar_Widget;
use WPMCP\Tools\Structure\Move_Sidebar_Widget;
use WPMCP\Tools\Structure\Delete_Sidebar_Widget;
use WPMCP\Tools\Export\Export_Content;
use WPMCP\Tools\Export\List_Exports;
use WPMCP\Tools\Export\Import_Content;
use WPMCP\Admin\Handshake_Settings_Page;
use WPMCP\Admin\Connection_Page;
use WPMCP\Admin\Snapshot_Retention_Notice;
use WPMCP\Admin\Backup_Dir_Notice;
use WPMCP\Admin\New_Tools_Notice;
use WPMCP\Admin\Ability_Grid_Page;
use WPMCP\Admin\Memory_Page;
use WPMCP\Admin\Redirect_Suggestion_Controller;
use WPMCP\Admin\Redirects_Page;
use WPMCP\Admin\Skills_Settings_Page;
use WPMCP\Memory\Memory_Store;
use WPMCP\Privacy\Personal_Data;
use WPMCP\Skills\Skills_Module;
use WPMCP\Tools\Skills\Get_Skill;
use WPMCP\Tools\Skills\List_Skills;
use WPMCP\Governance\Default_Seeder;
use WPMCP\Connect\Exposure;
use WPMCP\MCP\Ability;
use WPMCP\MCP\Handshake_Instructions;
use WPMCP\MCP\Tool_Exposure;
use WPMCP\Tools\Bridge\Execute_Site_Ability;
use WPMCP\Tools\Bridge\Get_Site_Ability;
use WPMCP\Tools\Bridge\List_Site_Abilities;
use WPMCP\Tools\Dispatch\Call_Tool;
use WPMCP\Tools\Dispatch\Get_Tool_Schema;
use WPMCP\Tools\Dispatch\List_Tools;
use WPMCP\MCP\Registrar;
use WPMCP\Tools\Get_Page;
use WPMCP\Tools\Update_Blocks;
use WPMCP\Tools\List_Operations;
use WPMCP\Tools\Rollback_Operation;
use WPMCP\Tools\Rollback_Session;
use WPMCP\Tools\ACF\List_Field_Groups;
use WPMCP\Tools\ACF\Get_Fields;
use WPMCP\Tools\ACF\Update_Fields;
use WPMCP\Tools\Meta\Get_Post_Meta;
use WPMCP\Tools\Meta\Set_Post_Meta;
use WPMCP\Tools\Meta\Get_Option;
use WPMCP\Tools\Meta\Update_Option;
use WPMCP\Tools\SEO\Get_SEO_Status;
use WPMCP\Tools\SEO\Crawler_Files;
use WPMCP\Tools\SEO\Get_Crawler_Files;
use WPMCP\Tools\SEO\Update_Crawler_Files;
use WPMCP\Tools\SEO\Get_SEO_Meta;
use WPMCP\Tools\SEO\Update_SEO_Meta;
use WPMCP\Tools\SEO\SEO_Adapter;
use WPMCP\Tools\I18n\I18n_Adapter;
use WPMCP\Tools\I18n\List_Languages;
use WPMCP\Tools\I18n\Get_Post_Translations;
use WPMCP\Tools\I18n\Set_Post_Language;
use WPMCP\Tools\I18n\Link_Post_Translations;
use WPMCP\Tools\Linking\Find_Orphan_Posts;
use WPMCP\Tools\Linking\Suggest_Internal_Links;
use WPMCP\Tools\Linking\Get_Link_Map;
use WPMCP\Tools\Redirects\Create_Redirect;
use WPMCP\Tools\Redirects\Delete_Redirect;
use WPMCP\Tools\Redirects\Find_Broken_Links;
use WPMCP\Tools\Redirects\List_Redirects;
use WPMCP\Tools\Redirects\Redirect_Router;
use WPMCP\Tools\Redirects\Redirect_Handler;
use WPMCP\Tools\Redirects\Redirect_Store;
use WPMCP\Tools\Redirects\Run_Broken_Link_Scan;
use WPMCP\Tools\Redirects\Update_Redirect;
use WPMCP\Tools\Connect\Get_Connection_Info;
use WPMCP\Tools\Connect\List_Tool_Catalog;
use WPMCP\Tools\Content\List_Post_Types;
use WPMCP\Tools\Content\List_Taxonomies;
use WPMCP\Tools\Content\Create_Post;
use WPMCP\Tools\Content\Get_Post;
use WPMCP\Tools\Content\Get_Preview_Link;
use WPMCP\Tools\Content\Update_Post;
use WPMCP\Tools\Content\Delete_Post;
use WPMCP\Tools\Content\List_Posts;
use WPMCP\Tools\Content\Set_Post_Terms;
use WPMCP\Tools\Content\Duplicate_Post;
use WPMCP\Tools\Content\Diff_Revisions;
use WPMCP\Tools\Content\Count_Content;
use WPMCP\Tools\Terms\List_Terms;
use WPMCP\Tools\Terms\Get_Term;
use WPMCP\Tools\Terms\Create_Term;
use WPMCP\Tools\Terms\Update_Term;
use WPMCP\Tools\Terms\Delete_Term;
use WPMCP\Tools\Terms\Set_Term_Meta;
use WPMCP\Tools\Revisions\List_Revisions;
use WPMCP\Tools\Revisions\Get_Revision;
use WPMCP\Tools\Revisions\Restore_Revision;
use WPMCP\Tools\Media\Get_Media;
use WPMCP\Tools\Media\Update_Media;
use WPMCP\Tools\Media\Delete_Media;
use WPMCP\Tools\Media\Sideload_Image;
use WPMCP\Tools\Media\Upload_Grant;
use WPMCP\Tools\Media\Upload_Media;
use WPMCP\Tools\Media\List_Media;
use WPMCP\Tools\Media\Find_Unused_Media;
use WPMCP\Tools\Media\Resize_Media;
use WPMCP\Tools\Media\Upload_Svg;
use WPMCP\Tools\Media\Stock\Set_Stock_Key;
use WPMCP\Tools\Media\Stock\Search_Stock_Images;
use WPMCP\Tools\Media\Stock\Import_Stock_Image;
use WPMCP\Tools\Settings\Get_Settings;
use WPMCP\Tools\Settings\Update_Settings;
use WPMCP\Tools\Search\Search_Content;
use WPMCP\Tools\Search\Reindex_Search;
use WPMCP\Tools\Search\Index_Hooks;
use WPMCP\Tools\Users\List_Users;
use WPMCP\Tools\Users\Get_User;
use WPMCP\Tools\Users\Create_User;
use WPMCP\Tools\Users\Update_User;
use WPMCP\Tools\Comments\List_Comments;
use WPMCP\Tools\Comments\Get_Comment;
use WPMCP\Tools\Comments\Moderate_Comment;
use WPMCP\Tools\Comments\Create_Comment;
use WPMCP\Tools\Comments\Reply_To_Comment;
use WPMCP\Tools\Comments\Edit_Comment;
use WPMCP\Tools\Comments\Delete_Comment;
use WPMCP\Tools\Packages\List_Plugins;
use WPMCP\Tools\Packages\Activate_Plugin;
use WPMCP\Tools\Packages\Deactivate_Plugin;
use WPMCP\Tools\Packages\Install_Plugin;
use WPMCP\Tools\Packages\Update_Plugin;
use WPMCP\Tools\Packages\Manage_Updates;
use WPMCP\Tools\Packages\Safe_Package_Update;
use WPMCP\Tools\Packages\Delete_Plugin;
use WPMCP\Tools\Packages\List_Themes;
use WPMCP\Tools\Packages\Switch_Theme;
use WPMCP\Tools\Packages\Install_Theme;
use WPMCP\Tools\Packages\Update_Theme;
use WPMCP\Tools\Packages\Delete_Theme;
use WPMCP\Tools\Packages\Search_Plugins;
use WPMCP\Tools\Packages\Search_Themes;
use WPMCP\Tools\Packages\Get_Plugin_Info;
use WPMCP\Tools\Database\List_Tables;
use WPMCP\Tools\Database\Describe_Table;
use WPMCP\Tools\Filesystem\Filesystem_Guard;
use WPMCP\Tools\Performance\Analyze_Performance;
use WPMCP\Tools\Security\Scan_Security;
use WPMCP\Tools\Security\Incident_Response;
use WPMCP\Tools\Cache\Get_Cache_Status;
use WPMCP\Tools\Cache\Clear_Cache;
use WPMCP\Tools\Diagnostics\Get_Debug_Config;
use WPMCP\Tools\Diagnostics\Get_Debug_Log;
use WPMCP\Tools\Diagnostics\List_Transients;
use WPMCP\Tools\Diagnostics\Delete_Transient;
use WPMCP\Tools\Diagnostics\Get_Site_Health;
use WPMCP\Tools\SiteEditor\Site_Templates_Read;
use WPMCP\Tools\SiteEditor\Site_Templates_Write;
use WPMCP\Tools\Cron\List_Cron_Events;
use WPMCP\Tools\Cron\Schedule_Event;
use WPMCP\Tools\Cron\Unschedule_Event;
use WPMCP\Tools\Cron\Run_Event;
use WPMCP\Tools\Backup\Trigger_Backup;
use WPMCP\Tools\Backup\Network_Scope;
use WPMCP\Tools\Backup\Get_Backup_Status;
use WPMCP\Tools\Backup\List_Backup_Jobs;
use WPMCP\Tools\Backup\Cancel_Backup_Job;
use WPMCP\Tools\Backup\Run_Backup_Job;
use WPMCP\Tools\Backup\Backup_Schedule;
use WPMCP\Tools\Backup\Get_Backup_Manifest;
use WPMCP\Tools\Backup\Delete_Backup_Archive;
use WPMCP\Tools\Sync\Apply_Change_Set;
use WPMCP\Tools\Sync\Build_Change_Set;
use WPMCP\Tools\Sync\Get_Change_Set;
use WPMCP\Tools\Governance\Get_Governance_Settings;
use WPMCP\Tools\Governance\Update_Governance_Settings;
use WPMCP\Tools\Governance\List_Governance_Audit_Log;
use WPMCP\Tools\Multisite\Is_Multisite;
use WPMCP\Tools\Multisite\Get_Network_Info;
use WPMCP\Tools\Multisite\List_Network_Sites;
use WPMCP\Tools\Multisite\Get_Site_Details;
use WPMCP\Tools\Analytics\Get_Analytics_Connection_Status;
use WPMCP\Tools\Analytics\Get_Analytics_Summary;
use WPMCP\Tools\Analytics\Get_Top_Pages;
use WPMCP\Tools\Analytics\Get_Search_Console_Summary;
use WPMCP\Tools\Analytics\Get_Search_Console_Queries;
use WPMCP\Tools\Identity\Create_Identity;
use WPMCP\Tools\Identity\List_Identities;
use WPMCP\Tools\Identity\Delete_Identity;
use WPMCP\Tools\Elementor\List_Widgets;
use WPMCP\Tools\Elementor\Get_Widget_Schema;
use WPMCP\Tools\Elementor\Regenerate_Elementor_Css;
use WPMCP\Tools\Elementor\Get_Global_Settings;
use WPMCP\Tools\WooCommerce\List_Products;
use WPMCP\Tools\WooCommerce\Get_Product;
use WPMCP\Tools\WooCommerce\Create_Product;
use WPMCP\Tools\WooCommerce\Update_Product;
use WPMCP\Tools\WooCommerce\Delete_Product;
use WPMCP\Tools\WooCommerce\List_Product_Categories;
use WPMCP\Tools\WooCommerce\List_Orders;
use WPMCP\Tools\WooCommerce\Get_Order;
use WPMCP\Tools\WooCommerce\Update_Order_Status;
use WPMCP\Tools\WooCommerce\Add_Order_Note;
use WPMCP\Tools\WooCommerce\Get_Sales_Report;
use WPMCP\Tools\WooCommerce\List_Variations;
use WPMCP\Tools\WooCommerce\Update_Variation;
use WPMCP\Tools\WooCommerce\List_Low_Stock_Products;
use WPMCP\Tools\WooCommerce\Create_Variation;
use WPMCP\Tools\WooCommerce\Delete_Variation;
use WPMCP\Tools\WooCommerce\Bulk_Update_Products;
use WPMCP\Tools\WooCommerce\List_Coupons;
use WPMCP\Tools\WooCommerce\Get_Coupon;
use WPMCP\Tools\WooCommerce\Create_Coupon;
use WPMCP\Tools\WooCommerce\Update_Coupon;
use WPMCP\Tools\WooCommerce\Delete_Coupon;
use WPMCP\Tools\WooCommerce\Validate_Coupon;
use WPMCP\Tools\WooCommerce\List_Tax_Rates;
use WPMCP\Tools\WooCommerce\Create_Tax_Rate;
use WPMCP\Tools\WooCommerce\Update_Tax_Rate;
use WPMCP\Tools\WooCommerce\Delete_Tax_Rate;
use WPMCP\Tools\WooCommerce\Plan_Product_Import;
use WPMCP\Tools\WooCommerce\Apply_Product_Import;
use WPMCP\Tools\Menus\List_Menus;
use WPMCP\Tools\Menus\Get_Menu;
use WPMCP\Tools\Menus\List_Menu_Locations;
use WPMCP\Tools\Menus\Create_Menu;
use WPMCP\Tools\Menus\Add_Menu_Item;
use WPMCP\Tools\Menus\Update_Menu_Item;
use WPMCP\Tools\Menus\Remove_Menu_Item;
use WPMCP\Tools\Menus\Assign_Menu_To_Location;
use WPMCP\Tools\Menus\Delete_Menu;
use WPMCP\Auth\Endpoints as OAuth_Endpoints;
use WPMCP\Auth\Bearer_Auth;
use WPMCP\Auth\OAuth_Config;
use WPMCP\Auth\Oauth_Gc;
use WPMCP\MCP\Structured_Result;
use WPMCP\MCP\Server as Mcp_Server;
use WPMCP\MCP\Stdio_Transport;
use WPMCP\MCP\Transport_Guard;

final class Plugin
{
    /**
     * The product name as it appears in the admin menu. A brand, not a
     * sentence: it is deliberately NOT wrapped in __(), because a msgid
     * identical to the text domain gives translators no context, and a brand
     * inside a msgid is one more literal for a flavor build to chase. This
     * constant is the single place such a build would rewrite; today neither
     * scripts/build-woo-release.sh nor build-wporg-release.sh does, so every
     * flavor renders "WPMCP" here. Menu and page titles compose it with
     * a translated tail through page_title() instead of baking it into a
     * msgid.
     */
    public const BRAND = 'WPMCP';

    /**
     * The product name in page titles and H1s (issues #1127, #1128):
     * "WPMCP: Abilities". The admin menu keeps BRAND.
     */
    public const PRODUCT_NAME = 'WPMCP';

    /**
     * "<brand>: <screen>" for a page title or an H1. One translatable
     * pattern owns the separator so a locale can reorder the two parts or
     * use its own punctuation (French " : ", a full-width colon in CJK), and
     * the brand never enters a msgid. $screen is already translated by the
     * caller.
     */
    public static function page_title(string $screen): string
    {
        return sprintf(
            /* translators: 1: product name (not translated), 2: admin screen name */
            __('%1$s: %2$s', 'safe-mcp-for-woocommerce'),
            self::PRODUCT_NAME,
            $screen
        );
    }

    private static ?Plugin $instance = null;
    private ?Registrar $registrar = null;
    private ?\WPMCP\Edition\Edition $edition = null;

    public static function instance(): Plugin
    {
        return self::$instance ??= new self();
    }
    private function __construct()
    {
    }

    /**
     * The shared Registrar instance every ability is registered into. Exposed
     * so admin screens (e.g. the audit log's tool_name filter) and tests can
     * enumerate the abilities that are actually registered, without each
     * caller building its own throwaway Registrar.
     */
    public function registrar(): Registrar
    {
        return $this->registrar ??= new Registrar();
    }

    /**
     * The edition slots: Pro registers its part of the surface through the
     * wpmcp_edition_slots filter (src/Edition/Edition.php). Built once per
     * instance, on first use, so the free build pays for one filter call.
     */
    private function edition(): \WPMCP\Edition\Edition
    {
        return $this->edition ??= \WPMCP\Edition\Edition::from_hooks();
    }

    /** Run one edition slot outside the boot sequence (tests replay a slot this way). */
    public function run_edition_slot(string $slot, ?Registrar $registrar = null): void
    {
        $this->edition()->run($slot, $this->edition_context($registrar));
    }

    private function edition_context(?Registrar $registrar): \WPMCP\Edition\Edition_Context
    {
        return new \WPMCP\Edition\Edition_Context(
            $registrar,
            fn (string $group): bool => $this->group_enabled($group),
            function (Registrar $registrar, array $integrations): void {
                $this->register_integrations($registrar, array_values(array_filter($integrations, static fn (object $i): bool => $i instanceof \WPMCP\Integrations\Integration_Dispatcher)));
            }
        );
    }

    /**
     * Test seam: forced build flavor. Guarded by WPMCP_TESTING.
     */
    private static ?string $flavor_override = null;

    /**
     * Ability groups per vertical build flavor. A flavor listed here
     * registers ONLY these groups (the inline core content/safety abilities
     * in register_abilities() are common to every flavor); any other flavor
     * value, including the default 'full', registers everything. The
     * corresponding build script (scripts/build-woo-release.sh) prunes the
     * excluded domains' files from that flavor's zip, so the gate and the
     * artifact must stay in sync.
     */
    private const FLAVOR_GROUPS = [
        'woocommerce' => [
            'compose', 'woocommerce', 'menu', 'seo', 'linking', 'redirects',
            'meta', 'diagnostics', 'cron', 'maintenance', 'context', 'block',
            'structure', 'taxonomy', 'export', 'backup', 'migration', 'analysis',
            'connect', 'governance', 'skills', 'gateway',
        ],
    ];

    /**
     * Listeners on wpmcp_rollback_options_restored, which every option and
     * option_set rollback fires. Each rebuilds state an integration derives
     * from options, the same way the forward write did.
     *
     * These classes live in src/Integrations, which the WooCommerce build
     * removes, so they are wired through register_rollback_refreshers() and
     * never named in a bare add_action(): a listener naming a class the build
     * does not ship fatals every option rollback on that build.
     */
    private const ROLLBACK_REFRESHERS = [
        // A theme framework pack write rebuilt the active theme's generated
        // CSS; its rollback does too (issue #316).
        [\WPMCP\Integrations\Theme_Framework_Pack::class, 'refresh_after_restore'],
        // A translation made by create-translation leaves its source's group
        // when its creation is undone, and a page text write that held the
        // multilingual plugin's sync off is undone with it held off too
        // (issue #498).
        // An undo of set-permalink-structure rebuilds the rewrite rules for
        // the restored structure and bases, as the write did (issue #785).
        [\WPMCP\Integrations\Core_Site_Pack::class, 'after_options_restored'],
        // An undo that would change a synced pattern other people's posts
        // place is refused for a user who cannot edit those posts (#711).
        [\WPMCP\Integrations\Core_Patterns_Pack::class, 'restore_refusal', 'wpmcp_rollback_post_refusal'],
        // An undo of a PublishPress Future schedule or cancel puts the post's
        // meta back; the plugin's scheduled action follows it (issue #709).
        [\WPMCP\Integrations\PublishPress_Pack::class, 'after_post_restored', 'wpmcp_rollback_post_restored'],
        // An undo of a GeoDirectory listing write brings the listing's
        // detail row mirror of its title, status and terms back in step
        // (issue #707).
        [\WPMCP\Integrations\Listings_Pack::class, 'after_post_restored', 'wpmcp_rollback_post_restored'],
        // An undo of a Max Mega Menu settings, theme or menu item write
        // rebuilds its generated CSS, as the write did (issue #908).
        [\WPMCP\Integrations\Max_Mega_Menu_Pack::class, 'after_options_restored'],
        [\WPMCP\Integrations\Max_Mega_Menu_Pack::class, 'after_post_restored', 'wpmcp_rollback_post_restored'],
        // An undo of a Redux panel write runs the panel's compiler hooks for
        // the restored values, as the write did (#897).
        [\WPMCP\Integrations\Redux_Pack::class, 'after_options_restored'],
        // An undo of a consent banner text write lets Complianz and CookieYes
        // drop the banner caches their own save drops (#686).
        [\WPMCP\Integrations\Consent_Pack::class, 'after_rows_restored', 'wpmcp_rollback_db_rows_restored'],
        [\WPMCP\Integrations\Variation_Swatches_Pack::class, 'after_rows_restored', 'wpmcp_rollback_db_rows_restored'], // An undo of a swatch type change drops WooCommerce's attribute caches (#1096).
        [\WPMCP\Integrations\Hustle_Pack::class, 'after_rows_restored', 'wpmcp_rollback_db_rows_restored'], // An undo of a Hustle write drops its module cache and schedule flags (#1091).
        [\WPMCP\Tools\I18n\I18n_Adapter::class, 'restore_translation_group', 'wpmcp_rollback_translation_group'],
        [\WPMCP\Tools\I18n\I18n_Adapter::class, 'hold_sync', 'wpmcp_rollback_hold_translation_sync'],
        [\WPMCP\Tools\I18n\I18n_Adapter::class, 'release_sync', 'wpmcp_rollback_release_translation_sync'],
        // Modern Events Calendar reads its calendar from its own mec_events
        // and mec_dates tables, derived from the event meta; an undo that
        // restores an event post rebuilds them as the write did (#677).
        [\WPMCP\Integrations\Modern_Events_Calendar_Writes::class, 'after_post_restored', 'wpmcp_rollback_post_restored'],
        // The gallery and slider packs hook their refreshers on init: an undo
        // flushes Envira, Smart Slider 3 and NextGEN caches, and a trash keeps
        // Envira's trashed flag in step (#705).
        [\WPMCP\Integrations\Gallery_Packs::class, 'attach_refreshers', 'init'],
    ];

    /**
     * Hook each rollback refresher whose class this build ships. The list is
     * a parameter only so a test can pass a class no build ships; boot()
     * always uses ROLLBACK_REFRESHERS.
     *
     * Entries are [class, method] on wpmcp_rollback_options_restored, or
     * [class, method, hook] for another rollback action.
     *
     * @param array<int,array{0:string,1:string,2?:string}>|null $refreshers
     */
    public static function register_rollback_refreshers(?array $refreshers = null): void
    {
        foreach ($refreshers ?? self::ROLLBACK_REFRESHERS as $refresher) {
            [$class, $method] = $refresher;
            if (\WPMCP\Kernel\Kernel_Boot::ships($class)) { // a check that does not load the class: WordPress keeps [Class, method] as strings until the rollback fires
                add_action($refresher[2] ?? 'wpmcp_rollback_options_restored', [$class, $method], 10, 2);
            }
        }
        // Then the refreshers modules declare with #[On_Rollback]; a test
        // that passes its own list gets exactly that list.
        if (null === $refreshers) {
            self::instance()->kernel()->attach_rollback_hooks_on_first_rollback(); // central-list-ok: D11 seam, the compiled #[On_Rollback] rows
        }
    }

    public static function set_flavor_for_tests(?string $flavor): void
    {
        if (! defined('WPMCP_TESTING') || ! WPMCP_TESTING) {
            return;
        }
        self::$flavor_override = $flavor;
    }

    /**
     * The build flavor: 'full' unless the main plugin file of a vertical
     * build (e.g. wpmcp-for-woocommerce.php) defines WPMCP_FLAVOR.
     */
    public static function flavor(): string
    {
        if (null !== self::$flavor_override) {
            return self::$flavor_override;
        }
        return defined('WPMCP_FLAVOR') ? WPMCP_FLAVOR : 'full';
    }

    private function group_enabled(string $group): bool
    {
        $allowed = self::FLAVOR_GROUPS[ self::flavor() ] ?? null;
        return null === $allowed || in_array($group, $allowed, true);
    }

    /**
     * The text domain this build's strings carry. Each main plugin file
     * defines WPMCP_TEXT_DOMAIN to match its own Text Domain header: the
     * WooCommerce build rewrites every string in src/ to
     * 'wpmcp-for-woocommerce' at build time, so a literal 'wpmcp' here
     * would load its .mo into a domain none of its strings use.
     */
    public static function text_domain(): string
    {
        return defined('WPMCP_TEXT_DOMAIN') ? (string) WPMCP_TEXT_DOMAIN : 'wpmcp';
    }

    /**
     * Boot-time runtime hook wiring for the flavor-gated feature groups: the
     * data-driven widget/block builders, the content search index and
     * agent project memory. Stored custom CSS/JS is part of the
     * off-directory add-on, so this build has nothing to wire for it.
     * Flavor-gated with the matching ability groups: vertical builds prune
     * these classes' files from the zip, so the hooks must not reference
     * them there. Public so tests can exercise the gating directly (boot()
     * itself runs during the suite bootstrap, outside coverage collection).
     */
    public function register_builder_runtime_hooks(): void
    {
        // The widget and block builders are part of the off-directory
        // add-on, so this build has no runtime hooks to wire.
        // Theme-builder site parts (issue #70): register the wpmcp_template
        // CPT on init and boot the render adapters on wp, once the main query
        // exists and the winning template for this request can be resolved.
        if ($this->group_enabled('theme_builder')) {
            add_action('init', ['\\WPMCP\\Tools\\ThemeBuilder\\Template_Store', 'ensure_post_type']);
            add_action('wp', ['\\WPMCP\\Tools\\ThemeBuilder\\Render\\Adapters', 'boot']);
        }
        // Content search index (issue #83): keep it correct incrementally on
        // every save/delete so search-content never reads stale copy. Gated
        // with its ability group so a flavor that drops the group also drops
        // the indexing cost.
        if ($this->group_enabled('search')) {
            (new Index_Hooks())->register();
        }
        // Image optimization runs, tool-upload optimization and modern
        // format delivery (issue #432), gated on the media group.
        $this->edition()->run('runtime.image_optimization', $this->edition_context(null));
        // File tool backups written before #609 kept their .php names under
        // uploads; rename them once to names no server executes (issue #622).
        add_action('init', [\WPMCP\Tools\Filesystem\Legacy_Backup_Migration::class, 'maybe_run'], 20);
        // Agent project memory (issue #131): the wpmcp_memory CPT is the
        // store AND the approval queue, so it is registered here rather than
        // lazily from the tools. Note this runs even though the three memory
        // TOOLS are not part of this build: an administrator's published
        // guardrails are enforced in Registrar::is_permitted() regardless,
        // and a safety rule must not stop applying just because the tools
        // that author it are absent.
        if ($this->group_enabled('memory')) {
            add_action('init', [Memory_Store::class, 'ensure_post_type'], 5);
            (new Memory_Page())->register_hooks();
            // The enforced rule set is memoized per request. An administrator
            // publishing or trashing an entry in wp-admin does not go through
            // Memory_Store, so drop the memo on the post-status transition and
            // on deletion instead of trusting every write to route through us.
            add_action('transition_post_status', [Memory_Store::class, 'flush_rules_cache_on_transition'], 10, 3);
            add_action('deleted_post', [Memory_Store::class, 'flush_rules_cache_on_delete'], 10, 2);
        }
    }
    public function boot(): void
    {
        if (function_exists('register_activation_hook') && defined('WPMCP_FILE')) {
            register_activation_hook(WPMCP_FILE, [Activator::class, 'activate']);
        }
        // Clears every plugin cron event (issue #468). Here rather than in a
        // main file so all three builds get it.
        if (function_exists('register_deactivation_hook') && defined('WPMCP_FILE')) {
            register_deactivation_hook(WPMCP_FILE, [Deactivator::class, 'deactivate']);
        }
        if (function_exists('add_action')) {
            // Versioned default-disabled seeding (issue #78) runs BEFORE any
            // registration hook can fire: newly shipped default-off abilities
            // land as ordinary governance toggles, so enforcement lives in
            // the registration/permission path with no admin class loaded.
            Default_Seeder::seed();
            $hook = function_exists('wp_register_ability') ? 'wp_abilities_api_init' : 'init';
            add_action($hook, [$this, 'register_abilities']);
            if (function_exists('wp_register_ability_category')) {
                add_action('wp_abilities_api_categories_init', [$this, 'register_ability_category']);
            }
            $this->register_builder_runtime_hooks();
            // Who may manage WPMCP (issue #479): every screen, admin-post and
            // AJAX handler, admin REST route and options.php group checks
            // Management_Access::CAPABILITY. Handlers are registered through
            // Management_Access::add_handler(), which refuses before they run.
            Management_Access::register();
            add_action('admin_menu', [$this, 'register_admin_menu']);
            Management_Access::add_handler('wp_ajax_wpmcp_restore', [new Restore_Controller(), 'handle']);
            // The Audit Log's Undo session button (issue #1150).
            Management_Access::add_handler('wp_ajax_' . Restore_Controller::SESSION_ACTION, [new Restore_Controller(), 'handle_session']);
            // Redacted CSV export of the filtered request log (issue #303).
            Management_Access::add_handler('admin_post_' . Audit_Log_Page::EXPORT_ACTION, [new Audit_Log_Page(), 'export_requests']);
            // Integrations that derive state from options rebuild it after a
            // rollback puts those options back. Wired only for the classes
            // this build ships; see register_rollback_refreshers().
            self::register_rollback_refreshers();
            // The generic redirect tools route to the active redirect plugin
            // (issue #930); the providers live with the integrations, which
            // the WooCommerce build prunes.
            if (class_exists(\WPMCP\Integrations\Redirect_Providers::class)) {
                add_filter('wpmcp_redirect_providers', [\WPMCP\Integrations\Redirect_Providers::class, 'register']);
            }
            // A shipping zone deleted through WooCommerce drops the creation
            // marker a woo-write create gave it, so a zone that later reuses
            // the id is never mistaken for the created one (issue #338).
            add_action('woocommerce_delete_shipping_zone', ['\\WPMCP\\Safety\\Wc_Shipping_Zone_Snapshot', 'forget_creation']);
            // The WP-Cron executor for trigger-backup's scheduled events: runs
            // the queued job (producing a backup artifact) and flips its
            // status to completed/failed, in steps under a PHP time limit
            // (issue #783). See Run_Backup_Job's docblock.
            add_action(Run_Backup_Job::HOOK, [new Run_Backup_Job(), 'handle_scheduled']);
            // Recurring backups (issue #455): each run queues the job above.
            add_action(Backup_Schedule::HOOK, [Backup_Schedule::class, 'run']);
            $this->edition()->run('boot_cli_job', $this->edition_context(null));
            // Front-end maintenance-mode enforcement. template_redirect runs after
            // WordPress has resolved the query but before a template is loaded, and
            // does not fire for wp-admin or REST requests, so authenticated capable
            // users, wp-admin, and the REST/MCP endpoints are never affected by it.
            add_action('template_redirect', [new Maintenance_Guard(), 'enforce']);
            // Managed redirects (issue #128), at priority 1 so an explicitly
            // configured redirect wins over core's redirect_canonical guess
            // (priority 10) for a path whose post was renamed or removed. When
            // no managed redirect matches, this does nothing and canonical
            // behavior is untouched. See Redirect_Handler's docblock.
            add_action('template_redirect', [new Redirect_Handler(), 'maybe_redirect'], Redirect_Handler::PRIORITY);
            // Managed crawler files (issue #384): robots.txt rules, llms.txt and
            // the core sitemap exclusions, all read from one option at request
            // time. See Crawler_Files.
            Crawler_Files::register_runtime_hooks();
            // Staged edits (issue #417): a stage stays a noindex draft until
            // duplicate-post publishes it over its original.
            \WPMCP\Tools\Content\Post_Stage::register_runtime_hooks();
            // The WP-Cron executor for a background broken-link scan: one
            // batch per run, rescheduling itself until the scan completes.
            add_action(Run_Broken_Link_Scan::HOOK, [new Run_Broken_Link_Scan(), 'handle']);
            // Confirm/dismiss for pending redirect suggestions. Confirming
            // calls the create-redirect tool, so there is no second path that
            // can produce a redirect. See Redirect_Suggestion_Controller.
            Management_Access::add_handler(
                'wp_ajax_' . Redirect_Suggestion_Controller::ACTION,
                [new Redirect_Suggestion_Controller(), 'handle']
            );
            // Self-healing schema creation for every per-site table (issue
            // #527): sites that upgraded without re-activating the plugin,
            // sub-sites of a network, and sites created while network active.
            // Guarded by one autoloaded version option and hooked on wp-admin
            // and REST requests only, so no front-end page load risks DDL.
            Site_Tables::register();
            // OAuth 2.1 + Dynamic Client Registration REST routes (issue #43).
            // Endpoints::register() itself no-ops unless OAuth_Config::is_enabled()
            // (default false), so this hook registration is always safe to add.
            add_action('rest_api_init', [new OAuth_Endpoints(), 'register']);
            // The OAuth discovery documents under /.well-known/ (issue #473).
            // Attached here rather than on rest_api_init, which a front-end
            // GET for a well-known path never fires. The handler answers
            // nothing while OAuth is off.
            OAuth_Endpoints::register_well_known();
            // upload-media grant:true (issue #471): the single-use upload
            // link route. The token is its only credential, checked inside.
            add_action('rest_api_init', [Upload_Grant::class, 'register_routes']);
            // WordPress's personal data export and erasure (issue #478):
            // the snapshots, activity logs, memory and search index are
            // covered by Tools > Export / Erase Personal Data. Every tier,
            // because a privacy request does not depend on a license.
            Personal_Data::register();
            // The in-admin AI chat is part of the off-directory add-on, so
            // this build has no chat hooks to wire.
            // Resolves a valid OAuth Bearer token to its bound WP user via
            // determine_current_user, so Registrar's existing capability
            // checks work for OAuth callers with no change to Registrar
            // itself. Also a no-op unless OAuth_Config::is_enabled().
            (new Bearer_Auth())->register();

            // Confines the gateway credential (#142) to the MCP connection and
            // binds it to its scoped Identity (issue #130), plus the
            // `wp wpmcp gateway-revoke` kill switch. src/Gateway ships on every
            // flavor, so this is unconditional.
            \WPMCP\Gateway\Gateway_Guard::register();
            // The hardening settings set-hardening switches on (issue #847):
            // WPMCP's own hooks, each a no-op until its setting is on.
            // src/Tools/Security ships on every flavor.
            \WPMCP\Tools\Security\Hardening_Settings::register();
            // OAuth access levels (issue #454): a connection approved as
            // read-only runs read abilities only, and one bound to a scoped
            // identity acts as it. The consent screen is where the approving
            // user picks the level; both are inert while OAuth is off.
            \WPMCP\Auth\Client_Access::register();
            \WPMCP\Admin\OAuth_Consent_Page::register();
            // Identities pinned to allowed_ips (issue #416): the MCP route
            // answers a pinned identity calling from another address with a
            // generic 403 before the adapter runs. Governance and the gateway
            // token check refuse it too; see Ip_Allowlist.
            \WPMCP\Identity\Ip_Allowlist::register();
            // Site-wide audit and governance of ability calls made outside
            // the MCP endpoint (issue #412): every audit row records the
            // entry point, and the default-off site_wide setting logs (or
            // refuses, for abilities disabled by name) third-party calls.
            \WPMCP\Governance\Call_Source::register();
            \WPMCP\Governance\Site_Wide_Governance::register();
            // Admin toggles for the default-off opt-in operation groups
            // (issue #823): each hooks its group's own opt-in filter, and
            // stands down whenever site code hooks that filter too.
            \WPMCP\Governance\Opt_In_Settings::register();
            add_action('network_admin_menu', [\WPMCP\Admin\Opt_In_Settings_Page::class, 'register_network_menu']);
            add_action('wp_ability_invoked', [Registrar::class, 'note_invoked'], PHP_INT_MIN, 1);
            // A denial of our own ability reaches a standalone permission
            // check (adapter, stdio, REST run route) as the refusal naming
            // what is missing (issue #875), while the callback itself only
            // ever returns a bool. Just before Site_Wide_Governance (MAX).
            add_filter('wp_ability_permission_result', [Registrar::class, 'permission_result'], PHP_INT_MAX - 1, 2);
            // Handshake context injection (issue #80): swap the MCP
            // Adapter's initialize `instructions` for the admin-authored
            // text plus the permission-gated site summary. A no-op unless
            // the adapter (which owns this filter) is installed and fires it.
            add_filter(
                'mcp_adapter_initialize_response',
                [new Handshake_Instructions(), 'filter_initialize'],
                10,
                3
            );
            // The Settings API registration for the handshake instructions
            // option (sanitize + clamp on every save through options.php).
            add_action('admin_init', [Handshake_Settings_Page::class, 'register_setting']);
            // Settings API registration for the agent-skills module toggle
            // (issue #74). Off unregisters list-skills/get-skill entirely.
            add_action('admin_init', [Skills_Settings_Page::class, 'register_setting']);
            // Master MCP exposure switch (issue #76): narrows through the
            // existing wpmcp_ability_enabled governance filter (off = every
            // ability denies on the next request) and surfaces its state in
            // the admin bar for manage_options users.
            Exposure::register();
            // The Connection screen's handlers: the secret-free desktop bundle,
            // its create and check AJAX actions, and its assets (guarded).
            (new Connection_Page())->register_handlers();
            // Snapshot retention (issue #158): an install that arrives with
            // a deeper history than the flat cap keeps it until the owner
            // decides, and this is the notice that asks.
            Snapshot_Retention_Notice::register();
            // Backup folder served over the web (issue #846): a Dashboard
            // warning from the stored self-check, no request on page load.
            Backup_Dir_Notice::register();
            // Tools an update added and the state they arrived in (issue
            // #853), on the Dashboard until an administrator dismisses it.
            New_Tools_Notice::register();
            // Compact tool-surface mode (issue #79): in compact mode the
            // adapter's advertised tools/list collapses to the meta-tools
            // plus connection basics. Exposure-only - registration and
            // permissions are untouched - and a no-op unless the adapter
            // (which owns this filter) is installed, and while the mode
            // resolves to 'full' (the default).
            add_filter('mcp_adapter_tools_list', [new Tool_Exposure(), 'filter_tools_list'], 10, 2);
            // Transport hardening (issue #133): no-store cache headers on
            // every MCP + OAuth response, display_errors forced off so a
            // stray notice cannot corrupt JSON-RPC framing, and a 421
            // site-URL-mismatch guard for connectors left pointing at an
            // old domain. Scoped to our own routes; everything else on the
            // site is untouched.
            (new Transport_Guard())->register();
            // A direct call to a plugin-data tool held back for want of an
            // active plugin names the plugin to activate (issue #876).
            (new \WPMCP\MCP\Hidden_Tool_Refusal())->register();
            // The page request get-query-monitor-page-profile loads with a
            // one-time token (issue #703); a no-op on every other request.
            // src/Integrations is not in the WooCommerce build, so the call
            // is guarded: an unguarded reference fataled every request on
            // that build (issue #1142).
            if (class_exists(\WPMCP\Integrations\Query_Monitor_Status::class)) {
                \WPMCP\Integrations\Query_Monitor_Status::register();
            }
            // Mounts /wp-json/mcp/wpmcp-server, the JSON-RPC endpoint the
            // README and get-connection-info have always advertised. Without
            // it wp_register_ability() gives us abilities but no MCP
            // transport at all. See WPMCP\MCP\Server.
            Mcp_Server::register();
            // Public agent discovery documents (issue #302): the MCP Server
            // Card at <endpoint>/server-card, the AI Catalog and the Agent
            // Skills index under /.well-known/. Public metadata only; see
            // WPMCP\MCP\Discovery_Documents for what is never advertised.
            \WPMCP\MCP\Discovery_Endpoints::register();
            // Stdio MCP transport for WP-CLI-only and local workflows
            // (issue #77): `wp mcp-stdio serve`. Only under WP-CLI, so no
            // other request loads the transport class.
            if (defined('WP_CLI') && WP_CLI) {
                Stdio_Transport::register();
            }
            // structuredContent must serialize as a JSON object. Normalized
            // at the wire boundary only, so tool contracts are unchanged.
            (new Structured_Result())->register();
            // Every tool declares an outputSchema on tools/list (issue #387).
            (new \WPMCP\MCP\Output_Schemas())->register();
            // Scheduled OAuth garbage collection (issue #133). The cron
            // callback is always attached (so a previously scheduled event
            // still has a handler if OAuth is switched off); the event is
            // only kept on the schedule while OAuth is enabled.
            Oauth_Gc::register();
            add_action('init', static function (): void {
                if (OAuth_Config::is_enabled()) {
                    Oauth_Gc::ensure_scheduled();
                    return;
                }
                Oauth_Gc::unschedule();
            }, 20);
        }
    }

    /**
     * The Abilities API (WP 6.9+) requires every ability to belong to a
     * registered category before wp_register_ability() will accept it.
     * Categories must be registered on their own wp_abilities_api_categories_init
     * hook, separate from wp_abilities_api_init.
     */
    public function register_ability_category(): void
    {
        wp_register_ability_category('wpmcp', [
            'label'       => self::BRAND,
            'description' => sprintf(
                /* translators: %s: product name (not translated) */
                __('Abilities provided by the %s plugin.', 'safe-mcp-for-woocommerce'),
                self::BRAND
            ),
        ]);
    }

    /**
     * The sidebar icon for the top-level menu (issue #879): the WPMCP
     * logomark (undo arrow around "wp_") in one fill colour, since WordPress's
     * svg-painter recolours only fill attributes of base64 SVG data URIs, so
     * the kit's stroked arc is a filled half annulus, thick enough for 20x20.
     * Precomputed literal (AdminMenuIconTest decodes and checks it) of:
     *
     * <svg xmlns="http://www.w3.org/2000/svg" viewBox="233 231 542 542">
     * <path fill="black" d="M512.9 757.7A246 246 0 0 1 487.1 266.3L489 302.3
     * A210 210 0 0 0 511 721.7Z"/><path fill="black" d="M480 246L570 298
     * L480 350Z"/><path fill="black" d="M401.5 588H436.5 ... (wp glyph)"/>
     * <path fill="black" d="M678 602H752V636H678Z"/></svg>
     */
    private const MENU_ICON = 'data:image/svg+xml;base64,'
        . 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjIzMyAyMzEgNTQyIDU0MiI+PHBhdGggZmlsbD0iYmxhY2siIGQ9Ik01MTIuOSA3NTcuN0EyNDYgMjQ2IDAgMCAxIDQ4Ny4xIDI2Ni4zTDQ4OSAzMDIuM0EyMTAgMjEwIDAgMCAwIDUxMSA3MjEuN1oiLz48cGF0aCBmaWxsPSJibGFjayIgZD0iTTQ4MCAyNDZMNTcwIDI5OEw0ODAgMzUwWiIvPjxwYXRoIGZpbGw9ImJsYWNrIiBkPSJNNDAxLjUgNTg4SDQzNi41TDQ1MC43IDUyOS4xQzQ1My44IDUxNi4yIDQ1Ni44IDQ5OC43IDQ1OS44IDQ4Mi42QzQ2Mi44IDQ5OC42IDQ2NS44IDUxNi4xIDQ2OSA1MjkuMUw0ODMuMiA1ODhINTE4LjNMNTU2LjQgNDM1LjFINTE5LjdMNTA4LjYgNDkxLjZDNTA1LjMgNTA5IDUwMS43IDUyOC4zIDQ5OC40IDU1MC44QzQ5NC43IDUyOC4xIDQ5MC42IDUwOC44IDQ4Ny40IDQ5MS42TDQ3NS45IDQzNS4xSDQ0NC4yTDQzMi41IDQ5MS42QzQyOSA1MDkgNDI0LjkgNTI4LjQgNDIxLjMgNTUwLjdDNDE3LjkgNTI4IDQxNCA1MDguNyA0MTAuOSA0OTEuNkwzOTkuNyA0MzUuMUgzNjMuNlpNNTcwLjYgNjQ1LjFINjA1LjFWNTYzLjVINjA2LjFDNjExIDU3NS44IDYyMS41IDU5MC42IDY0Mi41IDU5MC42QzY3Mi4yIDU5MC42IDY5NC44IDU2Mi43IDY5NC44IDUxMS44QzY5NC44IDQ1OS42IDY3MS4yIDQzMy4yIDY0Mi44IDQzMy4yQzYyMC45IDQzMy4yIDYxMC44IDQ0OC43IDYwNi4xIDQ2MC45SDYwNC41VjQzNS4xSDU3MC42Wk02MzIgNTU3LjZDNjE0LjQgNTU3LjYgNjA0LjMgNTM5LjMgNjA0LjMgNTExLjdDNjA0LjMgNDg0IDYxNC4zIDQ2NiA2MzIgNDY2QzY1MCA0NjYgNjU5LjUgNDg0LjkgNjU5LjUgNTExLjdDNjU5LjUgNTM4LjYgNjQ5LjkgNTU3LjYgNjMyIDU1Ny42WiIvPjxwYXRoIGZpbGw9ImJsYWNrIiBkPSJNNjc4IDYwMkg3NTJWNjM2SDY3OFoiLz48L3N2Zz4=';

    /**
     * Top-level menu slot (issue #1026). Without one, WordPress appends the
     * menu after every other plugin's. Core puts Settings at 80 and its last
     * separator at 99; a decimal just below Settings keeps the menu next to
     * the core admin menus and clear of the integer slots plugins pick.
     */
    public const MENU_POSITION = 80.5;

    public function register_admin_menu(): void
    {
        // Pending agent memory proposals (issue #131) are surfaced as the
        // WordPress count bubble on the top-level menu, so a proposal waiting
        // for a human decision is visible from any wpmcp screen (and from
        // anywhere in wp-admin), not only from the memory list table. Vertical
        // builds that drop the memory group also drop the class, so nothing
        // here may touch it when the group is off.
        $memory     = $this->group_enabled('memory');
        $pending    = $memory ? Memory_Page::pending_count() : 0;
        $menu_title = $memory ? Memory_Page::badged(self::BRAND, $pending) : self::BRAND;

        // The history page views (and its Restore button rolls back) ALL
        // users' site-wide agent mutations, so it is gated at the management
        // capability (issue #479), like Restore_Controller's ajax handler.
        add_menu_page(
            self::BRAND,
            $menu_title,
            Management_Access::CAPABILITY,
            'wpmcp',
            [new History_Page(), 'render'],
            self::MENU_ICON,
            self::MENU_POSITION
        );

        // The history screen, added explicitly rather than left to the
        // duplicate WordPress auto-creates for the first submenu: that copy
        // clones the top-level MENU TITLE verbatim, which now carries the
        // pending-proposal bubble, and the bubble must appear once. Passing
        // the parent's own slug suppresses the auto-copy (see add_submenu_page)
        // and keeps this entry's label exactly what it has always rendered as.
        // No callback here: this entry shares the parent's slug, so both
        // resolve to the toplevel_page_wpmcp hook, and add_menu_page above
        // already attached History_Page::render to it. A second callback
        // printed the screen twice, and with it the Restore script, so one
        // click sent two restore requests.
        add_submenu_page(
            'wpmcp',
            self::BRAND,
            self::BRAND,
            Management_Access::CAPABILITY,
            'safe-mcp-for-woocommerce'
        );

        // Same management gate as the top-level page and Restore_Controller's
        // ajax handler: this screen shows and can roll back every user's
        // site-wide agent mutations, so it needs the same trust level.
        add_submenu_page(
            'wpmcp',
            self::page_title(_x('Audit Log', 'admin menu', 'safe-mcp-for-woocommerce')),
            _x('Audit Log', 'admin menu', 'safe-mcp-for-woocommerce'),
            Management_Access::CAPABILITY,
            Audit_Log_Page::SLUG,
            [new Audit_Log_Page(), 'render']
        );

        // Handshake instructions (issue #80): the text on this screen is
        // broadcast to every connecting MCP client at initialize, so editing
        // it is a site-wide trust decision - the management capability, like the rest.
        add_submenu_page(
            'wpmcp',
            self::page_title(__('Handshake Instructions', 'safe-mcp-for-woocommerce')),
            _x('Handshake', 'admin menu', 'safe-mcp-for-woocommerce'),
            Management_Access::CAPABILITY,
            'wpmcp-handshake',
            [new Handshake_Settings_Page(), 'render']
        );

        // Connection manager (issue #76): provisions Application Passwords,
        // reveals them exactly once alongside filled client configs, serves
        // the desktop bundle, and hosts the master exposure switch - all
        // site-wide trust decisions, so the management capability like the rest.
        add_submenu_page(
            'wpmcp',
            self::page_title(_x('Connection', 'admin menu', 'safe-mcp-for-woocommerce')),
            _x('Connection', 'admin menu', 'safe-mcp-for-woocommerce'),
            Management_Access::CAPABILITY,
            Connection_Page::SLUG,
            [new Connection_Page(), 'render']
        );

        // Ability toggle grid (issue #78): sees and narrows the full MCP
        // ability surface - a site-wide trust decision, so the management
        // capability like the rest.
        add_submenu_page(
            'wpmcp',
            self::page_title(_x('Abilities', 'admin menu', 'safe-mcp-for-woocommerce')),
            _x('Abilities', 'admin menu', 'safe-mcp-for-woocommerce'),
            Management_Access::CAPABILITY,
            Ability_Grid_Page::SLUG,
            [new Ability_Grid_Page(), 'render']
        );

        // Redirects (issue #128): shows the managed redirect table and the
        // pending suggestion queue, and is where a human confirms a
        // suggestion into a real redirect. Changing site-wide routing is a
        // management decision, like the rest.
        add_submenu_page(
            'wpmcp',
            self::page_title(_x('Redirects', 'admin menu', 'safe-mcp-for-woocommerce')),
            _x('Redirects', 'admin menu', 'safe-mcp-for-woocommerce'),
            Management_Access::CAPABILITY,
            Redirects_Page::SLUG,
            [new Redirects_Page(), 'render']
        );

        // Agent skills (issue #74): the toggle on this screen decides whether
        // list-skills/get-skill are registered at all, i.e. what every
        // connecting agent is told about how to work on this site.
        add_submenu_page(
            'wpmcp',
            self::page_title(__('Agent Skills', 'safe-mcp-for-woocommerce')),
            _x('Skills', 'admin menu', 'safe-mcp-for-woocommerce'),
            Management_Access::CAPABILITY,
            Skills_Settings_Page::SLUG,
            [new Skills_Settings_Page(), 'render']
        );

        // Opt-in operations (issue #823): switches on groups of operations
        // that are off by default, so a site-wide trust decision like the rest.
        // Writing a toggle also takes manage_options, checked on the screen.
        // No entry on a build that ships none of the groups.
        if ([] !== \WPMCP\Governance\Opt_In_Settings::groups()) {
            add_submenu_page(
                'wpmcp',
                self::page_title(__('Opt-in operations', 'safe-mcp-for-woocommerce')),
                _x('Opt-in operations', 'admin menu', 'safe-mcp-for-woocommerce'),
                Management_Access::CAPABILITY,
                \WPMCP\Admin\Opt_In_Settings_Page::SLUG,
                [new \WPMCP\Admin\Opt_In_Settings_Page(), 'render']
            );
        }

        $this->edition()->run('admin_menu_chat', $this->edition_context(null));

        // Agent memory (issue #131). The target is the wpmcp_memory CPT list
        // table, not a bespoke screen: approving a proposal is WordPress's own
        // pending -> publish flow, with its list table, nonces, capability
        // checks and revisions, so there is no second approval path to audit.
        // Publishing an entry can enforce a server-side denial or broadcast
        // text to every connecting agent, hence the management capability like the rest.
        if ($memory) {
            add_submenu_page(
                'wpmcp',
                self::page_title(__('Agent Memory', 'safe-mcp-for-woocommerce')),
                Memory_Page::badged(_x('Memory', 'admin menu', 'safe-mcp-for-woocommerce'), $pending),
                Management_Access::CAPABILITY,
                Memory_Page::submenu_slug()
            );
        }
    }

    /**
     * The full declared ability surface (pre-gating; see
     * Registrar::declared()) for admin display. Lazily replays
     * register_abilities() when the registration hook has not fired in this
     * request - outside a wp_abilities_api_init window Registrar only fills
     * its internal maps, so the replay never touches the Abilities API.
     *
     * @return \WPMCP\MCP\Ability[]
     */
    public function declared_abilities(): array
    {
        if ([] === $this->registrar()->declared()) {
            $this->register_abilities();
        }
        return $this->registrar()->declared();
    }

    /**
     * Hook callback (wp_abilities_api_init / init). Takes no parameters on
     * purpose: WordPress forwards hook arguments to callbacks, so an
     * optional Registrar parameter here would receive whatever the action
     * passes. Tests use register_abilities_into() with a throwaway
     * Registrar instead.
     */
    public function register_abilities(): void
    {
        $this->register_abilities_into($this->registrar());
    }

    public function register_abilities_into(Registrar $registrar): void
    {
        $get_page           = new Get_Page();
        $update_blocks      = new Update_Blocks();
        $list_operations    = new List_Operations();
        $rollback_operation = new Rollback_Operation();
        $rollback_session   = new Rollback_Session();
        $registrar->register(new Ability(
            'wpmcp/get-page',
            'free',
            'Read a page',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'id' ],
            ],
            [$get_page, 'handle'],
            'edit_posts',
            'core',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-blocks',
            'free',
            'Update a page\'s block content',
            [
                'type'       => 'object',
                'properties' => [
                    'id'         => [ 'type' => 'integer' ],
                    'blocks'     => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'blocks' ],
            ],
            [$update_blocks, 'handle'],
            'edit_posts',
            'core',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-operations',
            'free',
            'List recent safety snapshot operations',
            [
                'type'       => 'object',
                'properties' => [
                    'limit' => [ 'type' => 'integer' ],
                ],
            ],
            [$list_operations, 'handle'],
            'edit_posts',
            'core',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/rollback-operation',
            'free',
            'Undo a single operation by restoring its pre-change snapshot',
            [
                'type'       => 'object',
                'properties' => [
                    'operation_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'operation_id' ],
            ],
            [$rollback_operation, 'handle'],
            'edit_posts',
            'core',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/rollback-session',
            'free',
            'Undo all operations from a session by restoring each object\'s pre-session snapshot',
            [
                'type'       => 'object',
                'properties' => [
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'session_id' ],
            ],
            [$rollback_session, 'handle'],
            'edit_posts',
            'core',
            'update'
        ));

        $list_post_types = new List_Post_Types();
        $list_taxonomies = new List_Taxonomies();
        $create_post     = new Create_Post();
        $get_post        = new Get_Post();
        $get_preview     = new Get_Preview_Link();
        $update_post     = new Update_Post();
        $delete_post     = new Delete_Post();
        $list_posts      = new List_Posts();
        $set_post_terms  = new Set_Post_Terms();
        $duplicate_post  = new Duplicate_Post();
        $diff_revisions  = new Diff_Revisions();
        $count_content   = new Count_Content();

        $registrar->register(new Ability(
            'wpmcp/list-post-types',
            'free',
            'List registered post types (posts, pages, custom post types)',
            [
                'type'       => 'object',
                'properties' => [
                    'public_only' => [ 'type' => 'boolean' ],
                ],
            ],
            [$list_post_types, 'handle'],
            'edit_posts',
            'content',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-taxonomies',
            'free',
            'List registered taxonomies (categories, tags, custom taxonomies)',
            [
                'type'       => 'object',
                'properties' => [
                    'post_type' => [ 'type' => 'string' ],
                ],
            ],
            [$list_taxonomies, 'handle'],
            'edit_posts',
            'content',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/create-post',
            'free',
            'Create a post, page, or custom post type',
            [
                'type'       => 'object',
                'properties' => [
                    'post_type' => [ 'type' => 'string' ],
                    'title'     => [ 'type' => 'string' ],
                    'content'   => [ 'type' => 'string' ],
                    'excerpt'   => [ 'type' => 'string' ],
                    'status'    => [ 'type' => 'string' ],
                    'slug'      => [ 'type' => 'string' ],
                    'parent'    => [ 'type' => 'integer' ],
                    'terms'      => [ 'type' => 'object' ],
                    'meta'       => [ 'type' => 'object' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
            ],
            [$create_post, 'handle'],
            'edit_posts',
            'content',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-post',
            'free',
            'Read a single post, page, or custom post type',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$get_post, 'handle'],
            'edit_posts',
            'content',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-preview-link',
            'free',
            'Preview URL for a draft, pending or scheduled post you can edit',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$get_preview, 'handle'],
            'edit_posts',
            'content',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-post',
            'free',
            'Partially update a post, page, or custom post type',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'        => [ 'type' => 'integer' ],
                    'title'          => [ 'type' => 'string' ],
                    'content'        => [ 'type' => 'string' ],
                    'excerpt'        => [ 'type' => 'string' ],
                    'status'         => [ 'type' => 'string' ],
                    'slug'           => [ 'type' => 'string' ],
                    'parent'         => [ 'type' => 'integer' ],
                    'terms'          => [ 'type' => 'object' ],
                    'terms_mode'     => [ 'type' => 'string' ],
                    'meta'           => [ 'type' => 'object' ],
                    'featured_image' => [ 'type' => [ 'object', 'null' ] ],
                    'session_id'     => [ 'type' => 'string' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$update_post, 'handle'],
            'edit_posts',
            'content',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-post',
            'free',
            'Delete a post, page or CPT entry. Trash by default. force:true deletes permanently, is off until the wpmcp_enable_delete_post filter opts in, needs confirm:true. Both undoable',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'    => [ 'type' => 'integer' ],
                    'force'      => [ 'type' => 'boolean' ],
                    'confirm'    => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$delete_post, 'handle'],
            'edit_posts',
            'content',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-posts',
            'free',
            'List/search posts, pages, or custom post types',
            [
                'type'       => 'object',
                'properties' => [
                    'post_type' => [ 'type' => 'string' ],
                    'status'    => [ 'type' => 'string' ],
                    'search'    => [ 'type' => 'string' ],
                    'author'    => [ 'type' => 'integer' ],
                    'parent'    => [ 'type' => 'integer' ],
                    'per_page'  => [ 'type' => 'integer' ],
                    'page'      => [ 'type' => 'integer' ],
                    'orderby'   => [ 'type' => 'string' ],
                    'order'     => [ 'type' => 'string' ],
                ],
            ],
            [$list_posts, 'handle'],
            'edit_posts',
            'content',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/duplicate-post',
            'free',
            'Copy a post, page or CPT entry (content, meta but _edit_lock and _wp_old_slug, terms, builder data; children optional) as a draft unless status is set. stage:true: one linked draft to edit; publish_stage:<stage id> writes it over the original (same ID, URL, comments; refused if the original changed, unless force; undo: rollback-operation); discard_stage deletes it',
            [
                'type'       => 'object',
                // post_id is not required: publish_stage and discard_stage
                // name the stage instead (issue #417).
                'properties' => [
                    'post_id'          => [ 'type' => 'integer' ],
                    'title'            => [ 'type' => 'string' ],
                    'status'           => [ 'type' => 'string', 'enum' => ['draft', 'pending', 'private', 'publish'] ],
                    'include_children' => [ 'type' => 'boolean' ],
                    'stage'            => [ 'type' => 'boolean' ],
                    'publish_stage'    => [ 'type' => 'integer' ],
                    'discard_stage'    => [ 'type' => 'integer' ],
                    'force'            => [ 'type' => 'boolean' ],
                    'session_id'       => [ 'type' => 'string' ],
                ],
            ],
            [$duplicate_post, 'handle'],
            'edit_posts',
            'content',
            'create',
            null,
            // publish_stage overwrites a live post and discard_stage trashes one.
            true,
            null,
            // post_id is the copy source; staging checks edit_post itself.
            ['post_id']
        ));
        $registrar->register(new Ability(
            'wpmcp/diff-revisions',
            'free',
            'Diff two revisions of a post, or one revision against the post\'s current state: a unified diff per changed field (title, content, excerpt) rather than two full documents. Unchanged fields are omitted',
            [
                'type'       => 'object',
                'properties' => [
                    'from_revision_id' => [ 'type' => 'integer' ],
                    'to_revision_id'   => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'from_revision_id' ],
            ],
            [$diff_revisions, 'handle'],
            'edit_posts',
            'content',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/count-content',
            'free',
            'Counts so a job can be sized before it starts: posts per public type by status, media by MIME family, comments by status, terms per taxonomy, users per role. From core counting APIs, so figures match wp-admin',
            [
                'type'       => 'object',
                'properties' => [
                    'include'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'post_type' => [ 'type' => 'string' ],
                ],
            ],
            [$count_content, 'handle'],
            'edit_posts',
            'content',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/set-post-terms',
            'free',
            'Assign taxonomy terms to a post (replace, append, or remove)',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'    => [ 'type' => 'integer' ],
                    'taxonomy'   => [ 'type' => 'string' ],
                    'terms'      => [ 'type' => 'array' ],
                    'mode'       => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'post_id', 'taxonomy', 'terms' ],
            ],
            [$set_post_terms, 'handle'],
            'edit_posts',
            'content',
            'update'
        ));

        $list_revisions   = new List_Revisions();
        $get_revision     = new Get_Revision();
        $restore_revision = new Restore_Revision();

        $registrar->register(new Ability(
            'wpmcp/list-revisions',
            'free',
            'List a post\'s revisions (id, author, date, change excerpt)',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$list_revisions, 'handle'],
            'edit_posts',
            'content',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-revision',
            'free',
            'Read a single post revision\'s fields',
            [
                'type'       => 'object',
                'properties' => [
                    'revision_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'revision_id' ],
            ],
            [$get_revision, 'handle'],
            'edit_posts',
            'content',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/restore-revision',
            'free',
            'Restore a post to a given revision',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'     => [ 'type' => 'integer' ],
                    'revision_id' => [ 'type' => 'integer' ],
                    'session_id'  => [ 'type' => 'string' ],
                ],
                'required'   => [ 'post_id', 'revision_id' ],
            ],
            [$restore_revision, 'handle'],
            'edit_posts',
            'content',
            'update'
        ));

        $get_media      = new Get_Media();
        $update_media   = new Update_Media();
        $delete_media   = new Delete_Media();
        $sideload_image = new Sideload_Image();
        $upload_media   = new Upload_Media();

        $registrar->register(new Ability(
            'wpmcp/get-media',
            'free',
            'Read a Media Library attachment: title, URL, every registered size and missing_sizes, dimensions, mime type, alt text, caption and description',
            [
                'type'       => 'object',
                'properties' => [
                    'media_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'media_id' ],
            ],
            [$get_media, 'handle'],
            'edit_posts',
            'media',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-media',
            'free',
            'Update an attachment\'s title, alt text, caption or description',
            [
                'type'       => 'object',
                'properties' => [
                    'media_id'    => [ 'type' => 'integer' ],
                    'title'       => [ 'type' => 'string' ],
                    'alt'         => [ 'type' => 'string' ],
                    'caption'     => [ 'type' => 'string' ],
                    'description' => [ 'type' => 'string' ],
                    'session_id'  => [ 'type' => 'string' ],
                ],
                'required'   => [ 'media_id' ],
            ],
            [$update_media, 'handle'],
            'edit_posts',
            'media',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-media',
            'free',
            'Delete a Media Library attachment. Off until the wpmcp_enable_delete_media filter opts in; needs confirm:true. force:true deletes permanently, snapshotted (undoable)',
            [
                'type'       => 'object',
                'properties' => [
                    'media_id'   => [ 'type' => 'integer' ],
                    'confirm'    => [ 'type' => 'boolean' ],
                    'force'      => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ],
                    // Delete even when some files could not be backed up.
                    'allow_unrecoverable_files' => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'media_id', 'confirm' ],
            ],
            [$delete_media, 'handle'],
            'edit_posts',
            'media',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/sideload-image',
            'free',
            "Add an image to the Media Library from a url, or from a prompt via the site's AI provider (ratio e.g. 16:9). Undo: rollback-operation",
            [
                'type'       => 'object',
                'properties' => [
                    'url'         => [ 'type' => 'string' ],
                    'prompt'      => [ 'type' => 'string' ],
                    'ratio'       => [ 'type' => 'string' ],
                    'post_id'     => [ 'type' => 'integer' ],
                    'description' => [ 'type' => 'string' ],
                    'alt'         => [ 'type' => 'string' ],
                    'session_id'  => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
            ],
            [$sideload_image, 'handle'],
            'upload_files',
            'media',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/upload-media',
            'free',
            'Upload a file to the Media Library from base64 data, an https url, or grant:true (returns a one-use upload URL and curl, for large files). Type sniffed from the bytes, not mime_type; executables and SVG refused; capped at the upload limit. Undo: rollback-operation',
            [
                'type'       => 'object',
                'properties' => [
                    'filename'   => [ 'type' => 'string' ],
                    'data'       => [ 'type' => 'string' ],
                    'url'        => [ 'type' => 'string' ],
                    'grant'      => [ 'type' => 'boolean' ],
                    'mime_type'  => [ 'type' => 'string' ],
                    'title'      => [ 'type' => 'string' ],
                    'alt'        => [ 'type' => 'string' ],
                    'caption'    => [ 'type' => 'string' ],
                    'post_id'    => [ 'type' => 'integer' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
            ],
            [$upload_media, 'handle'],
            'upload_files',
            'media',
            'create'
        ));

        $list_media          = new List_Media();
        $find_unused_media   = new Find_Unused_Media();
        $resize_media        = new Resize_Media();
        $upload_svg          = new Upload_Svg();
        $set_stock_key       = new Set_Stock_Key();
        $search_stock_images = new Search_Stock_Images();
        $import_stock_image  = new Import_Stock_Image();
        $registrar->register(new Ability(
            'wpmcp/list-media',
            'free',
            'List Media Library attachments, filtered by type ("image" or a mime like "image/png"), after/before dates and search, paged newest first with total/pages. missing_sizes:true adds each image\'s ungenerated registered sizes',
            [
                'type'       => 'object',
                'properties' => [
                    'type'     => [ 'type' => 'string' ],
                    'search'   => [ 'type' => 'string' ],
                    'after'    => [ 'type' => 'string' ],
                    'before'   => [ 'type' => 'string' ],
                    'page'     => [ 'type' => 'integer' ],
                    'per_page' => [ 'type' => 'integer' ],
                    'missing_sizes' => [ 'type' => 'boolean' ],
                ],
            ],
            [$list_media, 'handle'],
            'edit_posts',
            'media',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/find-unused-media',
            'free',
            'Attachments nothing references (featured image, site logo/icon, content, builder data, post/term meta, options) with size on disk and checks run. Pass next_cursor as cursor until done; unattached:true lists parent-0 ones apart',
            [
                'type'       => 'object',
                'properties' => [
                    'cursor'     => [ 'type' => 'integer' ],
                    'per_page'   => [ 'type' => 'integer' ],
                    'type'       => [ 'type' => 'string' ],
                    'unattached' => [ 'type' => 'boolean' ],
                ],
            ],
            [$find_unused_media, 'handle'],
            'edit_posts',
            'media',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/resize-media',
            'free',
            'Regenerate the given registered image sizes of an attachment from its original and report each file (name, dimensions, URL). Omit sizes to fill the missing ones; media_ids: up to 20 attachments. Snapshot-first with a file backup (undoable)',
            [
                'type'       => 'object',
                'properties' => [
                    'media_id'   => [ 'type' => 'integer' ],
                    'media_ids'  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'sizes'      => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'session_id' => [ 'type' => 'string' ],
                    // Resize even when some files could not be backed up.
                    'allow_unrecoverable_files' => [ 'type' => 'boolean' ],
                ],
            ],
            [$resize_media, 'handle'],
            'edit_posts',
            'media',
            'update'
        ));
        $this->edition()->run('abilities.media.optimize', $this->edition_context($registrar));
        $registrar->register(new Ability(
            'wpmcp/upload-svg',
            'free',
            'Add an SVG to the Media Library from markup or an allowlisted URL. A fail-closed sanitizer rejects script, foreignObject, event handlers and external references; only sanitized markup is stored. Rollback deletes it',
            [
                'type'       => 'object',
                'properties' => [
                    'markup'     => [ 'type' => 'string' ],
                    'url'        => [ 'type' => 'string' ],
                    'title'      => [ 'type' => 'string' ],
                    'alt'        => [ 'type' => 'string' ],
                    'post_id'    => [ 'type' => 'integer' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
            ],
            [$upload_svg, 'handle'],
            'upload_files',
            'media',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/set-stock-key',
            'free',
            'Store (or clear with an empty api_key) your own pexels or unsplash API key. Keys are encrypted at rest with a site-salt-derived key and never echoed back',
            [
                'type'       => 'object',
                'properties' => [
                    'provider' => [ 'type' => 'string', 'enum' => [ 'pexels', 'unsplash' ] ],
                    'api_key'  => [ 'type' => 'string' ],
                ],
                'required'   => [ 'provider', 'api_key' ],
            ],
            [$set_stock_key, 'handle'],
            'manage_options',
            'media',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/search-stock-images',
            'free',
            'Search openly-licensed stock images: openverse (keyless, Creative Commons), pexels and unsplash (own key via set-stock-key). Results carry license, license_url, attribution and source_url for import-stock-image',
            [
                'type'       => 'object',
                'properties' => [
                    'query'    => [ 'type' => 'string' ],
                    'provider' => [ 'type' => 'string', 'enum' => [ 'openverse', 'pexels', 'unsplash' ], 'default' => 'openverse' ],
                    'page'     => [ 'type' => 'integer' ],
                    'per_page' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'query' ],
            ],
            [$search_stock_images, 'handle'],
            'edit_posts',
            'media',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/import-stock-image',
            'free',
            'Sideload a stock search result into the Media Library. SSRF-guarded: https only, host allowlist (wpmcp_remote_media_allowed_hosts filter) checked first, no redirects, size caps, bytes must verify as an image. Stores attribution and license. Undo: rollback-operation',
            [
                'type'       => 'object',
                'properties' => [
                    'image_url'   => [ 'type' => 'string' ],
                    'provider'    => [ 'type' => 'string' ],
                    'title'       => [ 'type' => 'string' ],
                    'alt'         => [ 'type' => 'string' ],
                    'post_id'     => [ 'type' => 'integer' ],
                    'attribution' => [ 'type' => 'string' ],
                    'license'     => [ 'type' => 'string' ],
                    'license_url' => [ 'type' => 'string' ],
                    'source_url'  => [ 'type' => 'string' ],
                    'session_id'  => [ 'type' => 'string' ],
                ],
                'required'   => [ 'image_url' ],
            ],
            [$import_stock_image, 'handle'],
            'edit_posts',
            'media',
            'create'
        ));
        $this->edition()->run('abilities.media.stock', $this->edition_context($registrar));

        $get_settings    = new Get_Settings();
        $update_settings = new Update_Settings();

        $registrar->register(new Ability(
            'wpmcp/get-settings',
            'free',
            'Read WordPress site settings (general, reading, writing, discussion, media, permalinks), each with its group, type and whether it is writable',
            [
                'type'       => 'object',
                'properties' => [
                    'group' => [ 'type' => 'string' ],
                    'keys'  => [ 'type' => 'array' ],
                ],
            ],
            [$get_settings, 'handle'],
            'manage_options',
            'settings',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-settings',
            'free',
            'Update WordPress site settings from a strict allowlist. Validates/coerces each value (enum, int range, bool), rejects unsafe permalink structures, skips read-only or non-allowlisted keys and still applies the valid subset',
            [
                'type'       => 'object',
                'properties' => [
                    'settings' => [ 'type' => 'object' ],
                    'session_id' => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
                'required'   => [ 'settings' ],
            ],
            [$update_settings, 'handle'],
            'manage_options',
            'settings',
            'update'
        ));

        $list_users  = new List_Users();
        $get_user    = new Get_User();
        $create_user = new Create_User();
        $update_user = new Update_User();

        $registrar->register(new Ability(
            'wpmcp/list-users',
            'free',
            'List WordPress users as safe summary rows (id, username, display name, email, roles, registration date). Display name and email are masked (masked: true): use get-user for full values. Never returns password hashes or other secrets',
            [
                'type'       => 'object',
                'properties' => [
                    'role'     => [ 'type' => 'string' ],
                    'search'   => [ 'type' => 'string' ],
                    'per_page' => [ 'type' => 'integer' ],
                    'page'     => [ 'type' => 'integer' ],
                    'orderby'  => [ 'type' => 'string' ],
                    'order'    => [ 'type' => 'string' ],
                ],
            ],
            [$list_users, 'handle'],
            'list_users',
            'users',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-user',
            'free',
            'Read one user\'s profile detail, including an is_admin flag derived from live capabilities. Never returns the password hash',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'id' ],
            ],
            [$get_user, 'handle'],
            'list_users',
            'users',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/create-user',
            'free',
            'Create a new non-admin user. Auto-generates a strong password (never returned) and emails the new user so they can set their own. Rejects admin and unknown roles; defaults to subscriber. Undoable while it has no content and is unchanged',
            [
                'type'       => 'object',
                'properties' => [
                    'username'     => [ 'type' => 'string' ],
                    'email'        => [ 'type' => 'string' ],
                    'role'         => [ 'type' => 'string' ],
                    'display_name' => [ 'type' => 'string' ],
                    'first_name'   => [ 'type' => 'string' ],
                    'last_name'    => [ 'type' => 'string' ],
                    'session_id'   => [ 'type' => 'string' ],
                ],
                'required'   => [ 'username', 'email' ],
            ],
            [$create_user, 'handle'],
            'create_users',
            'users',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-user',
            'free',
            'Update a non-admin user\'s profile fields (display name, email, url, nickname, first/last name, description). Refuses admin-capable users. Never changes role or password. Snapshotted so the change can be rolled back',
            [
                'type'       => 'object',
                'properties' => [
                    'id'           => [ 'type' => 'integer' ],
                    'display_name' => [ 'type' => 'string' ],
                    'email'        => [ 'type' => 'string' ],
                    'url'          => [ 'type' => 'string' ],
                    'nickname'     => [ 'type' => 'string' ],
                    'first_name'   => [ 'type' => 'string' ],
                    'last_name'    => [ 'type' => 'string' ],
                    'description'  => [ 'type' => 'string' ],
                    'session_id'   => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id' ],
            ],
            [$update_user, 'handle'],
            'edit_users',
            'users',
            'update'
        ));

        $list_comments     = new List_Comments();
        $get_comment       = new Get_Comment();
        $moderate_comment  = new Moderate_Comment();
        $edit_comment      = new Edit_Comment();
        $delete_comment    = new Delete_Comment();
        $create_comment    = new Create_Comment();
        $reply_to_comment  = new Reply_To_Comment();

        $registrar->register(new Ability(
            'wpmcp/list-comments',
            'free',
            'List comments by post and status (emails, IPs masked). view:akismet gives Akismet status and spam/pending counts',
            [
                'type'       => 'object',
                'properties' => [
                    'view'     => [ 'type' => 'string', 'enum' => [ 'akismet' ] ],
                    'post_id'  => [ 'type' => 'integer' ],
                    'status'   => [ 'type' => 'string' ],
                    'per_page' => [ 'type' => 'integer' ],
                    'page'     => [ 'type' => 'integer' ],
                ],
            ],
            [$list_comments, 'handle'],
            'moderate_comments',
            'comments',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-comment',
            'free',
            'Read one comment',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'id' ],
            ],
            [$get_comment, 'handle'],
            'moderate_comments',
            'comments',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/moderate-comment',
            'free',
            'Set a comment\'s status: approve, unapprove, spam, not_spam (Akismet learns), trash, untrash, or recheck (Akismet rechecks pending; confirm:true). ids[] for up to 100. Reversible',
            [
                'type'       => 'object',
                'properties' => [
                    'id'         => [ 'type' => 'integer' ],
                    'ids'        => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'status'     => [ 'type' => 'string' ],
                    'confirm'    => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'status' ],
            ],
            [$moderate_comment, 'handle'],
            'moderate_comments',
            'comments',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/edit-comment',
            'free',
            'Edit a comment\'s content or author name, email, url. Reversible',
            [
                'type'       => 'object',
                'properties' => [
                    'id'           => [ 'type' => 'integer' ],
                    'content'      => [ 'type' => 'string' ],
                    'author'       => [ 'type' => 'string' ],
                    'author_email' => [ 'type' => 'string' ],
                    'author_url'   => [ 'type' => 'string' ],
                    'session_id'   => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id' ],
            ],
            [$edit_comment, 'handle'],
            'moderate_comments',
            'comments',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-comment',
            'free',
            'Permanently delete a comment, or empty_spam:true for up to 100 spam. Off until the wpmcp_enable_delete_comment filter opts in; needs confirm:true. Rollback restores under a new ID',
            [
                'type'       => 'object',
                'properties' => [
                    'id'         => [ 'type' => 'integer' ],
                    'empty_spam' => [ 'type' => 'boolean' ],
                    'confirm'    => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'confirm' ],
            ],
            [$delete_comment, 'handle'],
            'moderate_comments',
            'comments',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/create-comment',
            'free',
            'Comment on a post as you. status: approved (moderate_comments) or unapproved. Reversible',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'    => [ 'type' => 'integer' ],
                    'content'    => [ 'type' => 'string' ],
                    'status'     => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'post_id', 'content' ],
            ],
            [$create_comment, 'handle'],
            'edit_posts',
            'comments',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/reply-to-comment',
            'free',
            'Reply to comment id; as create-comment',
            [
                'type'       => 'object',
                'properties' => [
                    'id'         => [ 'type' => 'integer' ],
                    'content'    => [ 'type' => 'string' ],
                    'status'     => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'content' ],
            ],
            [$reply_to_comment, 'handle'],
            'edit_posts',
            'comments',
            'create'
        ));

        $list_plugins      = new List_Plugins();
        $activate_plugin   = new Activate_Plugin();
        $deactivate_plugin = new Deactivate_Plugin();
        $install_plugin    = new Install_Plugin();
        $update_plugin     = new Update_Plugin();
        $delete_plugin     = new Delete_Plugin();
        $list_themes       = new List_Themes();
        $switch_theme      = new Switch_Theme();
        $install_theme     = new Install_Theme();
        $update_theme      = new Update_Theme();
        $delete_theme      = new Delete_Theme();
        $manage_updates    = new Manage_Updates();

        $registrar->register(new Ability(
            'wpmcp/list-plugins',
            'free',
            'List installed plugins with active status, protected-package flag and pending update info; updates:true lists core, plugin and theme updates with auto-update state',
            [
                'type'       => 'object',
                'properties' => [
                    'updates' => [ 'type' => 'boolean' ],
                ],
            ],
            [$list_plugins, 'handle'],
            'activate_plugins',
            'packages',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/activate-plugin',
            'free',
            'Activate an installed plugin. Undoable',
            [
                'type'       => 'object',
                'properties' => [
                    'plugin'       => [ 'type' => 'string' ],
                    'network_wide' => [ 'type' => 'boolean' ],
                    'session_id'   => [ 'type' => 'string' ],
                ],
                'required'   => [ 'plugin' ],
            ],
            [$activate_plugin, 'handle'],
            'activate_plugins',
            'packages',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/deactivate-plugin',
            'free',
            'Deactivate a plugin. Refuses protected packages (wpmcp, Elementor). Undoable',
            [
                'type'       => 'object',
                'properties' => [
                    'plugin'       => [ 'type' => 'string' ],
                    'network_wide' => [ 'type' => 'boolean' ],
                    'session_id'   => [ 'type' => 'string' ],
                ],
                'required'   => [ 'plugin' ],
            ],
            [$deactivate_plugin, 'handle'],
            'activate_plugins',
            'packages',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/install-plugin',
            'free',
            'Install a plugin from wordpress.org by slug. activate:true needs activate_plugins and is undoable',
            [
                'type'       => 'object',
                'properties' => [
                    'slug'         => [ 'type' => 'string' ],
                    'activate'     => [ 'type' => 'boolean' ],
                    'network_wide' => [ 'type' => 'boolean' ],
                    'session_id'   => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
                'required'   => [ 'slug' ],
            ],
            [$install_plugin, 'handle'],
            'install_plugins',
            'packages',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-plugin',
            'free',
            'Update an installed plugin to the latest wordpress.org version. Disabled by default (wpmcp_enable_update_plugin filter) and needs confirm:true. File changes are not rollback-able',
            [
                'type'       => 'object',
                'properties' => [
                    'plugin'  => [ 'type' => 'string' ],
                    'confirm' => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'plugin', 'confirm' ],
            ],
            [$update_plugin, 'handle'],
            'update_plugins',
            'packages',
            'update',
            false,
            true,
            false
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-plugin',
            'free',
            'Permanently delete an installed plugin\'s files. Disabled by default (wpmcp_enable_delete_plugin filter) and needs confirm:true. Refuses protected or active plugins. Not rollback-able',
            [
                'type'       => 'object',
                'properties' => [
                    'plugin'  => [ 'type' => 'string' ],
                    'confirm' => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'plugin', 'confirm' ],
            ],
            [$delete_plugin, 'handle'],
            'delete_plugins',
            'packages',
            'delete'
        ));

        $registrar->register(new Ability(
            'wpmcp/list-themes',
            'free',
            'List installed themes with active status, parent theme and pending update info',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$list_themes, 'handle'],
            'activate_plugins',
            'packages',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/switch-theme',
            'free',
            'Activate an installed theme. Snapshots the prior template/stylesheet options (undoable)',
            [
                'type'       => 'object',
                'properties' => [
                    'stylesheet' => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'stylesheet' ],
            ],
            [$switch_theme, 'handle'],
            'switch_themes',
            'packages',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/install-theme',
            'free',
            'Install a theme from wordpress.org by slug. activate: true also needs switch_themes and returns rollbackable operation_ids',
            [
                'type'       => 'object',
                'properties' => [
                    'slug'     => [ 'type' => 'string' ],
                    'activate' => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
                'required'   => [ 'slug' ],
            ],
            [$install_theme, 'handle'],
            'install_themes',
            'packages',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-theme',
            'free',
            'Update an installed theme to the latest wordpress.org version. Disabled by default (wpmcp_enable_update_theme filter) and needs confirm:true. File changes are not rollback-able',
            [
                'type'       => 'object',
                'properties' => [
                    'stylesheet' => [ 'type' => 'string' ],
                    'confirm'    => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'stylesheet', 'confirm' ],
            ],
            [$update_theme, 'handle'],
            'update_themes',
            'packages',
            'update',
            false,
            true,
            false
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-theme',
            'free',
            'Permanently delete an installed theme\'s files. Disabled by default (wpmcp_enable_delete_theme filter) and needs confirm:true. Refuses the active theme (or its active parent). Not rollback-able',
            [
                'type'       => 'object',
                'properties' => [
                    'stylesheet' => [ 'type' => 'string' ],
                    'confirm'    => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'stylesheet', 'confirm' ],
            ],
            [$delete_theme, 'handle'],
            'delete_themes',
            'packages',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/manage-updates',
            'free',
            'Update WordPress core or set plugin/theme auto-updates. type=core applies the offered core update (or pending DB upgrade); needs confirm:true and a full backup (trigger-backup type=full) under 1h old; refuses if expected_version differs from the offer. A core update cannot be snapshot-rolled-back; restore that backup to revert. type=plugin|theme + item + enabled sets auto-update (undoable). type=plugins|themes: action=plan lists pending updates; run (opt-in wpmcp_enable_safe_updates, confirm:true) snapshots each, updates, checks the site and auto-restores a broken one; status (run_id); restore (run_id, items)',
            [
                'type'       => 'object',
                'properties' => [
                    'type'             => [
                        'type' => 'string',
                        'enum' => ['core', 'plugin', 'theme', 'plugins', 'themes'],
                    ],
                    'item'             => [ 'type' => 'string' ],
                    'enabled'          => [ 'type' => 'boolean' ],
                    'expected_version' => [ 'type' => 'string' ],
                    'confirm'          => [ 'type' => 'boolean' ],
                    'session_id'       => [ 'type' => 'string' ],
                ] + Safe_Package_Update::input_properties(),
                'required'   => [ 'type' ],
            ],
            [$manage_updates, 'handle'],
            'update_core',
            'packages',
            'update',
            false,
            true,
            false
        ));

        $search_plugins  = new Search_Plugins();
        $get_plugin_info = new Get_Plugin_Info();

        $registrar->register(new Ability(
            'wpmcp/search-plugins',
            'free',
            'Search the wordpress.org plugin directory by keyword, with optional tag/author filters and a capped per_page',
            [
                'type'       => 'object',
                'properties' => [
                    'query'    => [ 'type' => 'string' ],
                    'per_page' => [ 'type' => 'integer' ],
                    'tag'      => [ 'type' => 'string' ],
                    'author'   => [ 'type' => 'string' ],
                ],
                'required'   => [ 'query' ],
            ],
            [$search_plugins, 'handle'],
            'install_plugins',
            'packages',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-plugin-info',
            'free',
            'Fetch full wordpress.org plugin directory info for a slug: version, rating, installs, homepage, download link and compatibility',
            [
                'type'       => 'object',
                'properties' => [
                    'slug' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'slug' ],
            ],
            [$get_plugin_info, 'handle'],
            'install_plugins',
            'packages',
            'read'
        ));

        $search_themes = new Search_Themes();

        $registrar->register(new Ability(
            'wpmcp/search-themes',
            'free',
            'Search wordpress.org themes; filters as in search-plugins',
            [
                'type'       => 'object',
                'properties' => [
                    'query'    => [ 'type' => 'string' ],
                    'per_page' => [ 'type' => 'integer' ],
                    'tag'      => [ 'type' => 'string' ],
                    'author'   => [ 'type' => 'string' ],
                ],
                'required'   => [ 'query' ],
            ],
            [$search_themes, 'handle'],
            'install_themes',
            'packages',
            'read'
        ));

        $this->edition()->run('abilities.packages.zip_install', $this->edition_context($registrar));

        $list_tables    = new List_Tables();
        $describe_table = new Describe_Table();

        $registrar->register(new Ability(
            'wpmcp/list-tables',
            'free',
            'List database tables with estimated row counts and sizes',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$list_tables, 'handle'],
            'manage_options',
            'database',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/describe-table',
            'free',
            'Return the columns, types and keys of a database table',
            [
                'type'       => 'object',
                'properties' => [
                    'table' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'table' ],
            ],
            [$describe_table, 'handle'],
            'manage_options',
            'database',
            'read'
        ));



        $analyze_performance = new Analyze_Performance();

        $registrar->register(new Ability(
            'wpmcp/analyze-performance',
            'free',
            'Scan server config, WordPress internals (database size, autoloaded options, cron backlog, object cache, OPcache, plugin count) and a page (frontpage, url or post_id) for performance bottlenecks. Returns a scored report with ranked fixes',
            [
                'type'       => 'object',
                'properties' => [
                    'url'                => [ 'type' => 'string' ],
                    'post_id'            => [ 'type' => 'integer' ],
                    'include_page_fetch' => [ 'type' => 'boolean' ],
                    'deep_assets'        => [ 'type' => 'boolean' ],
                ],
            ],
            [$analyze_performance, 'handle'],
            'manage_options',
            'performance',
            'read'
        ));

        $scan_security = new Scan_Security();

        $registrar->register(new Ability(
            'wpmcp/scan-security',
            'free',
            'Scan this site for malware (deep=true: whole tree), core file integrity, hardening gaps, outdated software and known vulnerabilities (vulnerabilities=true; sends slugs and versions to wpvulnerability.net); checks:[compromise] looks for hack traces. Returns a 0-100 score, A-F grade and ranked fixes',
            [
                'type'       => 'object',
                'properties' => [
                    'checks'          => [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => ['malware', 'integrity', 'hardening', 'software', 'compromise'],
                        ],
                    ],
                    'deep'            => [ 'type' => 'boolean' ],
                    'max_files'       => [ 'type' => 'integer' ],
                    'max_seconds'     => [ 'type' => 'integer' ],
                    'vulnerabilities' => [ 'type' => 'boolean' ],
                    // Issue #1151: scan_meta.compromise.next_cursor of a truncated compromise scan.
                    'cursor'          => [ 'type' => 'string' ],
                ],
            ],
            [$scan_security, 'handle'],
            'manage_options',
            'security',
            'read'
        ));

        // Issue #382: the containment steps after a scan-security finding.
        // Each action narrows the capability further inside the handler
        // (edit_user for another user, update_core for core files).
        $incident_response = new Incident_Response();

        $registrar->register(new Ability(
            'wpmcp/incident-response',
            'free',
            'list-app-passwords (no secrets); revoke-app-password (uuid or all); list-sessions (one user, masked, no tokens); end-sessions (user_id or all). Writes need confirm:true and cannot be undone. Your session and app password stay unless include_current. Other users take edit_users',
            [
                'type'       => 'object',
                'properties' => [
                    'action'          => [
                        'type' => 'string',
                        'enum' => Incident_Response::ACTIONS,
                    ],
                    'user_id'         => [ 'type' => 'integer' ],
                    'uuid'            => [ 'type' => 'string' ],
                    'all'             => [ 'type' => 'boolean' ],
                    'include_current' => [ 'type' => 'boolean' ],
                    'confirm'         => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'action' ],
            ],
            [$incident_response, 'handle'],
            'manage_options',
            'security',
            'delete'
        ));

        $get_cache_status = new Get_Cache_Status();

        $registrar->register(new Ability(
            'wpmcp/get-cache-status',
            'free',
            'Report active caching layers: the object cache backend (external or internal), OPcache and any page-cache plugin (WP Rocket, W3 Total Cache, WP Super Cache, LiteSpeed Cache, WP Fastest Cache) detected by its functions or constants',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$get_cache_status, 'handle'],
            'manage_options',
            'performance',
            'read'
        ));

        $clear_cache = new Clear_Cache();

        $registrar->register(new Ability(
            'wpmcp/clear-cache',
            'free',
            'Flush caches: object cache, all transients, OPcache when enabled and any detected page-cache plugin through its own API. Returns what each layer cleared or lacked. Idempotent; not snapshotted (a cache has no before-image)',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$clear_cache, 'handle'],
            'manage_options',
            'performance',
            'update'
        ));

        $groups = [
            'compose'        => fn () => $this->register_compose_abilities($registrar),
            'woocommerce'    => fn () => $this->register_woocommerce_abilities($registrar),
            'menu'           => fn () => $this->register_menu_abilities($registrar),
            'elementor'      => fn () => $this->register_elementor_abilities($registrar),
            'builder'        => fn () => $this->edition()->run('abilities.builder', $this->edition_context($registrar)),
            'acf'            => fn () => $this->register_acf_abilities($registrar),
            'seo'            => fn () => $this->register_seo_abilities($registrar),
            'i18n'           => fn () => $this->register_i18n_abilities($registrar),
            'linking'        => fn () => $this->register_linking_abilities($registrar),
            'redirects'      => fn () => $this->register_redirect_abilities($registrar),
            'meta'           => fn () => $this->register_meta_abilities($registrar),
            'diagnostics'    => fn () => $this->register_diagnostics_abilities($registrar),
            'cron'           => fn () => $this->register_cron_abilities($registrar),
            'maintenance'    => fn () => $this->register_maintenance_abilities($registrar),
            'context'        => fn () => $this->register_context_abilities($registrar),
            'rest'           => fn () => $this->register_rest_abilities($registrar),
            'block'          => fn () => $this->register_block_abilities($registrar),
            'structure'      => fn () => $this->register_structure_abilities($registrar),
            'taxonomy'       => fn () => $this->register_taxonomy_abilities($registrar),
            'export'         => fn () => $this->register_export_abilities($registrar),
            'backup'         => fn () => $this->register_backup_abilities($registrar),
            'sync'           => fn () => $this->register_sync_abilities($registrar),
            'analysis'       => fn () => $this->edition()->run('abilities.analysis', $this->edition_context($registrar)),
            'cli'            => fn () => $this->edition()->run('abilities.cli', $this->edition_context($registrar)),
            'php_exec'       => fn () => $this->edition()->run('abilities.php_exec', $this->edition_context($registrar)),
            'custom_code'    => fn () => $this->edition()->run('abilities.custom_code', $this->edition_context($registrar)),
            'connect'        => fn () => $this->register_connect_abilities($registrar),
            'governance'     => fn () => $this->register_governance_abilities($registrar),
            'multisite'      => fn () => $this->register_multisite_abilities($registrar),
            'analytics'      => fn () => $this->register_analytics_abilities($registrar),
            'dispatch'       => fn () => $this->register_dispatch_abilities($registrar),
            'bridge'         => fn () => $this->register_bridge_abilities($registrar),
            'integration'    => fn () => $this->register_integration_abilities($registrar),
            'widget_builder' => fn () => $this->edition()->run('abilities.widget_builder', $this->edition_context($registrar)),
            'block_builder'  => fn () => $this->edition()->run('abilities.block_builder', $this->edition_context($registrar)),
            'theme_builder'  => fn () => $this->register_theme_builder_abilities($registrar),
            'cloud'          => fn () => $this->edition()->run('abilities.cloud', $this->edition_context($registrar)),
            'gateway'        => fn () => $this->register_gateway_abilities($registrar),
            'search'         => fn () => $this->register_search_abilities($registrar),
            'skills'         => fn () => $this->register_skills_abilities($registrar),
            'memory'         => fn () => $this->edition()->run('abilities.memory', $this->edition_context($registrar)),
        ];
        foreach ($groups as $group => $register) {
            if ($this->group_enabled($group)) {
                $register();
            }
        }
    }

    /**
     * Agent skills over MCP (issue #74): list-skills / get-skill, serving the
     * bundled markdown playbook library plus any site-custom source.
     *
     * Both tools are FREE and read-only, and so is every skill document
     * they serve: this build has no premium skill library to withhold.
     *
     * Skills_Module::is_enabled() is checked HERE rather than in the tools,
     * so a site that turns the surface off does not merely hide it: the two
     * abilities are never registered, never reach the Abilities API, and cost
     * a connecting client zero tokens in tools/list.
     */
    private function register_skills_abilities(Registrar $registrar): void
    {
        if (! Skills_Module::is_enabled()) {
            return;
        }

        $list_skills = new List_Skills();
        $get_skill   = new Get_Skill();

        $registrar->register(new Ability(
            'wpmcp/list-skills',
            'free',
            'List installed agent skills (versioned markdown playbooks): slug, name, description, version, tags. Bodies via get-skill. Skills whose required tools are missing are hidden unless include_unavailable is set',
            [
                'type'       => 'object',
                'properties' => [
                    'search'              => [ 'type' => 'string' ],
                    'tag'                 => [ 'type' => 'string' ],
                    'include_unavailable' => [ 'type' => 'boolean' ],
                ],
            ],
            [$list_skills, 'handle'],
            'edit_posts',
            'skills',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/get-skill',
            'free',
            'Load one skill\'s full instructions by slug (from list-skills), returned exactly as written. Unknown slugs return a structured error listing the installed slugs',
            [
                'type'       => 'object',
                'properties' => [
                    'slug' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'slug' ],
            ],
            [$get_skill, 'handle'],
            'edit_posts',
            'skills',
            'read'
        ));
    }

    /**
     * Site-local gateway credential lifecycle (issue #142, phase 1 of #130).
     *
     * Its own group, NOT part of 'cloud', and free tier. That looks odd for
     * a credential whose consumer is the multi-site proxy, and it is
     * deliberate: 'cloud' is pruned from the wp.org build
     * (scripts/flavors/wporg/strip.php drops src/Tools/Cloud and this
     * method's cloud sibling entirely) and excluded from the WooCommerce
     * vertical's FLAVOR_GROUPS. A credential that can be minted on a build
     * but not revoked on it is a security hole, and the issue's requirement
     * is explicit that revocation works locally with no network. So the
     * whole lifecycle lives where every flavor can reach it.
     *
     * All manage_options, domain 'gateway'. None of these touch the
     * network, so provisioning and revocation work with the cloud
     * unreachable.
     */
    private function register_gateway_abilities(Registrar $registrar): void
    {
        $confirm_schema = [
            'type'       => 'object',
            'properties' => ['confirm' => ['type' => 'boolean'], 'session_id' => ['type' => 'string']],
            'required'   => ['confirm'],
        ];

        // The destructive and idempotent hints are overridden, not derived,
        // and both derived values would be wrong. 'create' would derive
        // destructive: false for gateway-provision, but the call
        // irreversibly kills the previous client secret, every refresh
        // token bound to it and every access token already minted from it;
        // MCP clients use destructiveHint for auto-approval, so the derived
        // value invites an agent to retry it over a live proxy credential.
        // 'delete' would derive idempotent: false for gateway-revoke, which
        // is documented and tested as safe to call repeatedly.
        //
        // Each registration is a literal `new Ability('wpmcp/...')` so the
        // wp.org free-tier assertion (scripts/flavors/wporg/assert-free-tier.php)
        // can see these free abilities in the built zip.
        $registrar->register(new Ability(
            'wpmcp/gateway-provision',
            'free',
            'Provision or rotate the site gateway credential. Returns client_id, client_secret and refresh_token once; the previous credential dies immediately. Carries the calling user\'s capabilities. Requires confirm: true',
            $confirm_schema,
            [new \WPMCP\Tools\Gateway\Gateway_Provision(), 'handle'],
            'manage_options',
            'gateway',
            'create',
            null,
            true,
            false
        ));

        $registrar->register(new Ability(
            'wpmcp/gateway-status',
            'free',
            'Whether the site gateway credential exists, its client_id and whether OAuth is enabled. Never returns secrets',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [new \WPMCP\Tools\Gateway\Gateway_Status(), 'handle'],
            'manage_options',
            'gateway',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/gateway-revoke',
            'free',
            'Revoke the site gateway credential and every token bound to it. Local, idempotent. Requires confirm: true',
            $confirm_schema,
            [new \WPMCP\Tools\Gateway\Gateway_Revoke(), 'handle'],
            'manage_options',
            'gateway',
            'delete',
            null,
            true,
            true
        ));
    }

    /**
     * Site content search index (issue #83). Two free abilities over a
     * materialized index table: `search-content` (read) and `reindex-search`
     * (rebuild).
     *
     * Free tier, because "where does this text live?" is orientation, the same
     * class of question as get-site-context and list-posts, not deep analysis.
     *
     * Capabilities are deliberately split. `search-content` is gated at
     * edit_posts, the standard content-read bar in this codebase, and then
     * re-checks `read_post` PER RESULT so the index can never surface a
     * non-published post the caller could not have read directly.
     * `reindex-search` is gated at manage_options: a full rebuild walks every
     * post on the site, which is a site-maintenance cost, not a content edit.
     *
     * Neither tool touches Safe_Mutation. The index is derived state that
     * reindex-search reconstructs from posts, postmeta, and menus, so a
     * snapshot of it would protect nothing that is not already recoverable in
     * one call.
     */
    private function register_search_abilities(Registrar $registrar): void
    {
        $search_content = new Search_Content();
        $reindex_search = new Reindex_Search();

        $registrar->register(new Ability(
            'wpmcp/search-content',
            'free',
            'Search all site text, incl. what post_content search misses: Elementor and Bricks settings, block attributes, template parts, reusable blocks and nav menus. Each hit has a location (block path, element id or menu item id) and a snippet. Read-only; hits re-checked against read_post. Empty index: run reindex-search',
            [
                'type'       => 'object',
                'properties' => [
                    'query'           => [ 'type' => 'string' ],
                    'post_types'      => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'sources'         => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'object_types'    => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'limit'           => [ 'type' => 'integer' ],
                    'offset'          => [ 'type' => 'integer' ],
                    'hits_per_result' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'query' ],
            ],
            [$search_content, 'handle'],
            'edit_posts',
            'content',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/reindex-search',
            'free',
            'Build or rebuild the search-content index. The index updates on save; use this for the first build or after bulk or out-of-band changes. Cursor-based: each call indexes up to batch_size posts and returns next_offset',
            [
                'type'       => 'object',
                'properties' => [
                    'post_types'    => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'include_menus' => [ 'type' => 'boolean' ],
                    'full'          => [ 'type' => 'boolean' ],
                    'batch_size'    => [ 'type' => 'integer' ],
                    'offset'        => [ 'type' => 'integer' ],
                ],
            ],
            [$reindex_search, 'handle'],
            'manage_options',
            'content',
            'update'
        ));
    }

    /**
     * Theme-builder site parts (issue #70): templates for header, footer, and
     * 404 assignable to contexts by include/exclude conditions, with
     * deterministic winner resolution (specificity > priority > id), rendered
     * into classic and block themes by the Render adapters.
     *
     * Named `site-part` rather than `template` on purpose: the Elementor
     * group already owns create-theme-template / resolve-theme-template /
     * apply-template for Elementor library documents, and an agent holding
     * both surfaces has to be able to tell them apart from the tool name
     * alone. These parts need no page builder.
     *
     * The whole engine is available to every install of this plugin, every
     * rule type included, with no limit on how many templates a part type
     * may have. manage_options across the group: these templates render
     * site-wide markup, and the CPT is on Content_Guard's internal list so
     * the edit_posts content tools cannot reach it either. Every write to an
     * existing template is snapshot-first through Safe_Mutation.
     */
    private function register_theme_builder_abilities(Registrar $registrar): void
    {
        $part_type         = [
            'type' => 'string',
            'enum' => \WPMCP\Tools\ThemeBuilder\Template_Store::PART_TYPES,
        ];
        $rule_schema       = [
            'type'       => 'object',
            'properties' => [
                'type'  => [
                    'type' => 'string',
                    'enum' => array_keys(\WPMCP\Tools\ThemeBuilder\Condition_Schema::RULE_TYPES),
                ],
                // Post type slug for post_type, post id for singular (omit
                // for any singular), term id for term, role slug for
                // user_role. The other rule types take no value.
                'value' => ['type' => ['string', 'integer']],
            ],
            'required'   => ['type'],
        ];
        // The include/exclude semantics are stated once, in the create
        // description, rather than repeated on both schemas (tools/list budget).
        $conditions_schema = [
            'type'       => 'object',
            'properties' => [
                'include' => ['type' => 'array', 'items' => $rule_schema],
                'exclude' => ['type' => 'array', 'items' => $rule_schema],
            ],
            'required'   => ['include'],
        ];
        $context_schema    = [
            'type'       => 'object',
            'properties' => [
                'is_front_page' => ['type' => 'boolean'],
                'is_404'        => ['type' => 'boolean'],
                'is_search'     => ['type' => 'boolean'],
                'is_archive'    => ['type' => 'boolean'],
                'is_singular'   => ['type' => 'boolean'],
                'post_type'     => ['type' => 'string'],
                'post_id'       => ['type' => 'integer'],
                'term_ids'      => ['type' => 'array', 'items' => ['type' => 'integer']],
                'user_roles'    => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
        $template_id       = ['type' => 'integer'];
        $create            = new \WPMCP\Tools\ThemeBuilder\Create_Site_Part();
        $list              = new \WPMCP\Tools\ThemeBuilder\List_Site_Parts();
        $resolve           = new \WPMCP\Tools\ThemeBuilder\Resolve_Site_Part();
        $update            = new \WPMCP\Tools\ThemeBuilder\Update_Site_Part();
        $set_status        = new \WPMCP\Tools\ThemeBuilder\Set_Site_Part_Status();
        $delete            = new \WPMCP\Tools\ThemeBuilder\Delete_Site_Part();

        $registrar->register(new Ability(
            'wpmcp/create-site-part',
            'free',
            'Create a header, footer or 404 site part, shown where an include rule matches and no exclude rule does; not the Elementor theme-template tools',
            [
                'type'       => 'object',
                'properties' => [
                    'part_type'  => $part_type,
                    'title'      => ['type' => 'string'],
                    // Block markup; filtered with wp_kses_post on save.
                    'content'    => ['type' => 'string'],
                    'conditions' => $conditions_schema,
                    // Tie-break between equally specific matches; higher wins.
                    'priority'   => ['type' => 'integer'],
                    'session_id' => ['type' => 'string'], // central-list-ok: #1255 the handler records it
                ],
                'required'   => ['part_type', 'title', 'conditions'],
            ],
            [$create, 'handle'],
            'manage_options',
            'theme',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-site-parts',
            'free',
            'List site parts with their conditions and status, optionally by part_type',
            [
                'type'       => 'object',
                'properties' => ['part_type' => $part_type],
            ],
            [$list, 'handle'],
            'manage_options',
            'theme',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/resolve-site-part',
            'free',
            'Which site part wins for a context (most specific, then priority), with every candidate and why. post_id alone fills post_type and term_ids',
            [
                'type'       => 'object',
                'properties' => ['part_type' => $part_type, 'context' => $context_schema],
                'required'   => ['part_type'],
            ],
            [$resolve, 'handle'],
            'manage_options',
            'theme',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-site-part',
            'free',
            'Edit a site part; omitted fields are kept, part_type is fixed. Snapshot-first: operation_id rolls it back',
            [
                'type'       => 'object',
                'properties' => [
                    'template_id' => $template_id,
                    'title'       => ['type' => 'string'],
                    'content'     => ['type' => 'string'],
                    'conditions'  => $conditions_schema,
                    'priority'    => ['type' => 'integer'],
                    'session_id'  => ['type' => 'string'], // central-list-ok: #1255 the handler records it
                ],
                'required'   => ['template_id'],
            ],
            [$update, 'handle'],
            'manage_options',
            'theme',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/set-site-part-status',
            'free',
            'Activate (publish) or deactivate (draft) a site part; only active parts resolve. Snapshot-first',
            [
                'type'       => 'object',
                'properties' => [
                    'template_id' => $template_id,
                    'status'      => ['type' => 'string', 'enum' => ['publish', 'draft']],
                    'session_id'  => ['type' => 'string'], // central-list-ok: #1255 the handler records it
                ],
                'required'   => ['template_id', 'status'],
            ],
            [$set_status, 'handle'],
            'manage_options',
            'theme',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-site-part',
            'free',
            'Trash a site part. Snapshot-first: operation_id rolls it back',
            [
                'type'       => 'object',
                'properties' => ['template_id' => $template_id, 'session_id' => ['type' => 'string']],
                'required'   => ['template_id'],
            ],
            [$delete, 'handle'],
            'manage_options',
            'theme',
            'delete'
        ));
    }

    /**
     * Register the integration-dispatcher pairs (issue #65): one
     * {integration}-read plus one {integration}-write ability per third-party
     * integration, dispatching to a per-operation catalog instead of N flat
     * tools. Most pairs register unconditionally: availability is a call-time
     * concern for them (a missing host plugin yields a structured
     * integration_unavailable error, never a fatal). The forms adapters
     * (issue #66) opt out of that through
     * Integration_Dispatcher::registers_only_when_available() and register
     * only while their host plugin is loaded. This runs on
     * wp_abilities_api_init, after every plugin has loaded, so that check sees
     * the real answer.
     */
    private function register_integration_abilities(Registrar $registrar): void
    {
        $this->register_integrations($registrar, [
            new \WPMCP\Integrations\ACF_Integration(),
            new \WPMCP\Integrations\Contact_Form_7_Integration(),
            new \WPMCP\Integrations\Gravity_Tables_Integration(),
            new \WPMCP\Integrations\Modern_Events_Calendar_Integration(),
            new \WPMCP\Integrations\The_Events_Calendar_Integration(),
            new \WPMCP\Integrations\Give_Integration(),
            new \WPMCP\Integrations\Paid_Memberships_Pro_Integration(),
            new \WPMCP\Integrations\Meta_Box_Integration(),
            new \WPMCP\Integrations\Plugin_Data_Integration(),
            new \WPMCP\Integrations\Forminator_Integration(),
            new \WPMCP\Integrations\SureForms_Integration(),
            new \WPMCP\Integrations\MetForm_Integration(),
            new \WPMCP\Integrations\Theme_Integration(),
        ]);
        $this->edition()->run('abilities.forms_pack', $this->edition_context($registrar));
        $this->edition()->run('abilities.block_suite', $this->edition_context($registrar));
    }

    /**
     * Register each integration's read/write pair, skipping one that asks to
     * register only while its host plugin is loaded when that plugin is not.
     *
     * @param \WPMCP\Integrations\Integration_Dispatcher[] $integrations
     */
    private function register_integrations(Registrar $registrar, array $integrations): void
    {
        foreach ($integrations as $integration) {
            if (! $integration->should_register()) {
                // Off because its plugin is not active (issue #790): the
                // meta-tools name that plugin instead of "not registered".
                // A pair a site filtered off while its plugin is loaded is
                // not held, so it stays an unknown name.
                if (! $integration->is_available()) {
                    $host = $integration->host_plugin();
                    $ops  = $integration->operation_hosts();
                    foreach ($integration->abilities() as $ability) {
                        $channel = 'read' === $ability->operation ? 'read' : 'write';
                        $registrar->hold_for_plugin(
                            $ability,
                            $host['name'],
                            $host['several'],
                            $ops,
                            static fn (string $op): ?array => $integration->unknown_operation_refusal($channel, $op)
                        );
                    }
                }
                continue;
            }
            foreach ($integration->abilities() as $ability) {
                $registrar->register($ability);
            }
        }
    }

    /**
     * Register the WP-Cron inspection and scheduling tools as free-tier
     * abilities (parity gap tracked in issue #28).
     *
     * All four are gated at manage_options and tagged domain 'cron': the cron
     * array can reveal internal hook names and scheduling, and mutating it is
     * a site-operations-level action, not a content edit. list-cron-events is
     * 'read'; schedule-event is 'create' and unschedule-event 'delete', both
     * routed through Safe_Mutation on the 'cron' option so rollback-operation
     * restores the prior cron array. run-event is 'update' but, like
     * clear-cache, is not snapshotted (firing a hook is an irreversible side
     * effect) and is additionally disabled by default behind the
     * wpmcp_enable_run_cron_event filter, so registering it does not by itself
     * allow any hook to run.
     */
    private function register_cron_abilities(Registrar $registrar): void
    {
        $list_cron_events = new List_Cron_Events();
        $schedule_event   = new Schedule_Event();
        $unschedule_event = new Unschedule_Event();
        $run_event        = new Run_Event();

        $registrar->register(new Ability(
            'wpmcp/list-cron-events',
            'free',
            'List the scheduled WP-Cron events (hook, next-run timestamp, recurrence/schedule, interval in seconds, callback args) from the cron array, plus the available schedules from wp_get_schedules(). Optional hook filter',
            [
                'type'       => 'object',
                'properties' => [
                    'hook' => [ 'type' => 'string' ],
                ],
            ],
            [$list_cron_events, 'handle'],
            'manage_options',
            'cron',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/schedule-event',
            'free',
            'Schedule a recurring (recurrence checked against wp_get_schedules()) or single event. Refuses core-critical hooks (wp_version_check, wp_update_plugins/themes, wp_scheduled_delete, delete_expired_transients, wp_privacy_delete_old_export_files). Snapshots the cron option; rollback-operation restores it',
            [
                'type'       => 'object',
                'properties' => [
                    'hook'       => [ 'type' => 'string' ],
                    'recurrence' => [ 'type' => 'string' ],
                    'timestamp'  => [ 'type' => 'integer' ],
                    'args'       => [ 'type' => 'array' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'hook' ],
            ],
            [$schedule_event, 'handle'],
            'manage_options',
            'cron',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/unschedule-event',
            'free',
            'Unschedule one occurrence (wp_unschedule_event, given timestamp and matching args) or every event for a hook (wp_clear_scheduled_hook). Core hooks included, made safe by snapshotting the cron option: rollback-operation restores it',
            [
                'type'       => 'object',
                'properties' => [
                    'hook'       => [ 'type' => 'string' ],
                    'timestamp'  => [ 'type' => 'integer' ],
                    'args'       => [ 'type' => 'array' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'hook' ],
            ],
            [$unschedule_event, 'handle'],
            'manage_options',
            'cron',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/run-event',
            'free',
            'Fire a scheduled cron hook now via do_action(), for debugging. Off until the wpmcp_enable_run_cron_event filter opts in; fires only hooks present in the cron array, always with their stored args. Not snapshotted: firing a hook is irreversible',
            [
                'type'       => 'object',
                'properties' => [
                    'hook' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'hook' ],
            ],
            [$run_event, 'handle'],
            'manage_options',
            'cron',
            'update'
        ));
    }

    /**
     * Register the maintenance-mode tools as free-tier abilities (parity
     * gap tracked in issue #42).
     *
     * All three are gated at manage_options and tagged domain 'maintenance':
     * turning maintenance mode on or off is a site-operations-level action,
     * matching the same capability already used for cron and diagnostics.
     * get-maintenance-status is 'read'. enable-maintenance and
     * disable-maintenance are both 'update', routed through Safe_Mutation on
     * the 'wpmcp_maintenance' option, so rollback-operation restores the
     * prior on/off state. Front-end enforcement (Maintenance_Guard, hooked
     * to template_redirect in boot()) reads the same option and exempts any
     * user who is logged in and holds manage_options, so registering these
     * abilities never risks locking an admin out of their own site.
     */
    private function register_maintenance_abilities(Registrar $registrar): void
    {
        $get_maintenance_status = new Get_Maintenance_Status();
        $enable_maintenance     = new Enable_Maintenance();
        $disable_maintenance    = new Disable_Maintenance();

        $registrar->register(new Ability(
            'wpmcp/get-maintenance-status',
            'free',
            'Whether the site is gated: WPMCP maintenance mode (message, Retry-After), core .maintenance, coming soon and maintenance plugins',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$get_maintenance_status, 'handle'],
            'manage_options',
            'maintenance',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/enable-maintenance',
            'free',
            'Turn maintenance mode on (wpmcp_maintenance option: enabled, message, retry_after seconds). Logged-out visitors and users without manage_options get a 503 with the message until disabled. Snapshotted; rollback-operation restores the prior state',
            [
                'type'       => 'object',
                'properties' => [
                    'message'     => [ 'type' => 'string' ],
                    'retry_after' => [ 'type' => 'integer' ],
                    'session_id'  => [ 'type' => 'string' ],
                ],
            ],
            [$enable_maintenance, 'handle'],
            'manage_options',
            'maintenance',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/disable-maintenance',
            'free',
            'Turn maintenance mode off: sets enabled=false on the wpmcp_maintenance option (message and retry_after are preserved for a later re-enable). Snapshotted; rollback-operation restores the prior state',
            [
                'type'       => 'object',
                'properties' => [
                    'session_id' => [ 'type' => 'string' ],
                ],
            ],
            [$disable_maintenance, 'handle'],
            'manage_options',
            'maintenance',
            'update'
        ));
    }

    /**
     * Register the free-tier orientation reads: get-site-context (the site
     * level, parity gap tracked in issue #19) and get-page-snapshot (the page
     * level, issue #81).
     *
     * Gated at edit_posts, a low bar reflecting that this is orientation
     * data for an agent (site identity, versions, theme, content model,
     * integrations), not a site-settings-level read. The admin email is
     * deliberately excluded from the payload so this low gate never leaks a
     * secret-shaped value. Read-only, so it never touches Safe_Mutation.
     */
    private function register_context_abilities(Registrar $registrar): void
    {
        $get_site_context = new Get_Site_Context();

        $registrar->register(new Ability(
            'wpmcp/get-site-context',
            'free',
            'Orientation payload: site name, URL, tagline, WordPress and PHP versions, theme, active plugins, public post types with counts, taxonomies, user count, locale, timezone, multisite and active integrations. No admin email',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$get_site_context, 'handle'],
            'edit_posts',
            'context',
            'read'
        ));

        // get-page-snapshot (issue #81): the page-level counterpart, and a
        // FREE ability, which is why it is registered here rather than with
        // the pro analysis suite. The wp.org build deletes src/Tools/Analysis
        // and its registration method whole, so a free tool registered
        // there would vanish from the only build free users install;
        // tests/free/Platform/WporgFreeSurfaceTest.php gates that. Its pro
        // overlay sections attach via the wpmcp_page_snapshot_sections
        // filter, so this build renders it without any pro code present.
        $get_page_snapshot = new Get_Page_Snapshot();

        $registrar->register(new Ability(
            'wpmcp/get-page-snapshot',
            'free',
            'One-call page digest from stored post_content: structure counts, outline, media and link inventory, builder detection, SEO-lite signals (content_coverage flags builder page gaps). Heavy sections (global_tokens, responsive_overrides) opt-in via sections. Size-capped',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'  => [ 'type' => 'integer' ],
                    'sections' => [
                        // Deliberately not an enum: the built-in heavy
                        // sections are global_tokens and responsive_overrides,
                        // but unknown names are passed through to the
                        // wpmcp_page_snapshot_sections filter so a pro overlay
                        // can have its own opt-in section name.
                        'type'  => 'array',
                        'items' => [ 'type' => 'string' ],
                    ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$get_page_snapshot, 'handle'],
            'edit_posts',
            'context',
            'read'
        ));

        // get-rendered-html: what a logged-out visitor actually receives for
        // a page, fetched from this site's own host only (see the SSRF model
        // on the class). Free and in the 'context' group for the same reason
        // as get-page-snapshot above.
        $get_rendered_html = new Get_Rendered_Html();

        $registrar->register(new Ability(
            'wpmcp/get-rendered-html',
            'free',
            'Visitor-view HTML of a site page (post_id or url/path), chunked',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'       => [ 'type' => 'integer' ],
                    'url'           => [ 'type' => 'string' ],
                    'chunk'         => [ 'type' => 'integer' ],
                    'offset'        => [ 'type' => 'integer' ],
                    'chunk_size'    => [ 'type' => 'integer' ],
                    'strip_scripts' => [ 'type' => 'boolean' ],
                    'text_only'     => [ 'type' => 'boolean' ],
                ],
            ],
            [$get_rendered_html, 'handle'],
            'edit_posts',
            'context',
            'read'
        ));
    }

    /**
     * Register the generic WP REST API passthrough tools as free-tier
     * abilities (parity gap tracked in issue #36).
     *
     * list-rest-routes is read-only discovery: it only reads the route table
     * off rest_get_server(), never executes a route, and is gated at
     * edit_posts like other read tools.
     *
     * call-rest is gated at edit_posts too, matching the capability every
     * other read tool in this codebase requires: the REAL authorization
     * decision for both reads and writes is made by the target endpoint's
     * own permission_callback (see Call_Rest's class docblock), which runs
     * against the current user regardless of what edit_posts alone would
     * otherwise allow. edit_posts is therefore only the floor to reach this
     * tool at all, not a grant of what it can do. The write path
     * (POST/PUT/PATCH/DELETE) is additionally disabled by default behind the
     * wpmcp_enable_rest_writes filter and requires confirm:true; sites
     * enabling that filter are encouraged to also require manage_options (or
     * an equivalent stricter capability) on whichever endpoints they expect
     * this tool to write through, since call-rest itself cannot know in
     * advance which capability a given write endpoint's permission_callback
     * enforces.
     */
    private function register_rest_abilities(Registrar $registrar): void
    {
        $list_rest_routes = new List_Rest_Routes();
        $call_rest        = new Call_Rest();

        $registrar->register(new Ability(
            'wpmcp/list-rest-routes',
            'free',
            'List routes on this site\'s REST server (core and plugin namespaces): path, methods and a short args summary. Optional namespace and search filter by substring; limit caps rows (default 50, max 200). Read-only: never executes a route',
            [
                'type'       => 'object',
                'properties' => [
                    'namespace' => [ 'type' => 'string' ],
                    'search'    => [ 'type' => 'string' ],
                    'limit'     => [ 'type' => 'integer' ],
                ],
            ],
            [$list_rest_routes, 'handle'],
            'edit_posts',
            'rest',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/call-rest',
            'free',
            'Run an internal REST request on any local route; returns status and body. The route\'s permission_callback runs as the current user, so access cannot widen. GET/HEAD always run; writes need the wpmcp_enable_rest_writes filter (default off) and confirm:true, and are not snapshotted (recoverable:false)',
            [
                'type'       => 'object',
                'properties' => [
                    'method'  => [ 'type' => 'string' ],
                    'route'   => [ 'type' => 'string' ],
                    'params'  => [ 'type' => 'object' ],
                    'confirm' => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'method', 'route' ],
            ],
            [$call_rest, 'handle'],
            'edit_posts',
            'rest',
            'update'
        ));
    }

    /**
     * Register the block-type introspection and (de)serialization tools as
     * free-tier abilities (parity gap tracked in issue #39).
     *
     * list-block-types is read-only discovery: it only reads
     * WP_Block_Type_Registry, gated at edit_posts like other read tools.
     * Tagged domain 'blocks'.
     *
     * convert-html-to-blocks (parity gap tracked in issue #48) is a pure
     * HTML-to-block-markup transform with no DB write, so it is likewise a
     * read/utility operation rather than create/update.
     */
    private function register_block_abilities(Registrar $registrar): void
    {
        $list_block_types      = new List_Block_Types();
        $get_block_type        = new Get_Block_Type();
        $parse_blocks          = new Parse_Blocks();
        $serialize_blocks      = new Serialize_Blocks();
        $convert_html_to_blocks = new Convert_Html_To_Blocks();

        $registrar->register(new Ability(
            'wpmcp/list-block-types',
            'free',
            'List registered block types: name, title, category, is_dynamic and attribute names. Optional category (exact) and search (substring on name) filters',
            [
                'type'       => 'object',
                'properties' => [
                    'category' => [ 'type' => 'string' ],
                    'search'   => [ 'type' => 'string' ],
                ],
            ],
            [$list_block_types, 'handle'],
            'edit_posts',
            'blocks',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-block-type',
            'free',
            'Return full detail for a single registered block type by name: its attributes schema, declared supports and block-context wiring (uses_context, provides_context)',
            [
                'type'       => 'object',
                'properties' => [
                    'name' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'name' ],
            ],
            [$get_block_type, 'handle'],
            'edit_posts',
            'blocks',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/parse-blocks',
            'free',
            'Parse block markup into its block tree via parse_blocks(). Takes "blocks" (raw markup) or "id" (parses that post\'s post_content). Each node reports blockName, attrs, recursively parsed innerBlocks, and an innerHTML summary',
            [
                'type'       => 'object',
                'properties' => [
                    'id'     => [ 'type' => 'integer' ],
                    'blocks' => [ 'type' => 'string' ],
                ],
            ],
            [$parse_blocks, 'handle'],
            'edit_posts',
            'blocks',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/serialize-blocks',
            'free',
            'Serialize a block tree (parse-blocks shape) back into block markup via serialize_blocks(). A pure transform that never touches a post; write the result with update-blocks',
            [
                'type'       => 'object',
                'properties' => [
                    'blocks' => [ 'type' => 'array' ],
                ],
                'required'   => [ 'blocks' ],
            ],
            [$serialize_blocks, 'handle'],
            'edit_posts',
            'blocks',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/convert-html-to-blocks',
            'free',
            'Convert raw HTML to Gutenberg block markup: h1-h6, p, img, ul/ol, blockquote, pre/code, hr and table map to core blocks; anything else is wrapped in core/html so nothing is lost. Pure transform, never touches a post; write it with update-blocks',
            [
                'type'       => 'object',
                'properties' => [
                    'html' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'html' ],
            ],
            [$convert_html_to_blocks, 'handle'],
            'edit_posts',
            'blocks',
            'read'
        ));
        $this->register_surgical_block_abilities($registrar);
        $this->register_site_template_abilities($registrar);
    }

    /**
     * Block theme templates, template parts and navigation menus (issue
     * #378). The content tools refuse wp_template, wp_template_part and
     * wp_navigation (Content_Guard), so these two are the way to change a
     * block theme's header, footer or layouts. Both take edit_theme_options,
     * as the site editor does. Every write is snapshot-first; a template
     * write is snapshotted by its key, so its rollback restores the prior
     * customization or its absence (the theme file). Entity global_styles
     * (issue #379) reads the theme.json layers and patches the user one, a
     * variation or a reset, snapshotted the same way by theme.
     */
    private function register_site_template_abilities(Registrar $registrar): void
    {
        $read   = new Site_Templates_Read();
        $write  = new Site_Templates_Write();
        $entity = [ 'type' => 'string', 'enum' => [ 'template', 'template_part', 'navigation', 'global_styles' ] ];
        $id     = [ 'type' => [ 'string', 'integer' ] ];

        $registrar->register(new Ability(
            'wpmcp/site-templates-read',
            'free',
            'List the block theme\'s templates and parts (source theme/custom, area, customized) or wp_navigation menus; entity plus id reads one as parsed blocks and content_hash. global_styles: theme/user/merged theme.json, variations, fonts (id: dot path narrows). Reports a classic theme',
            [
                'type'       => 'object',
                'properties' => [
                    'entity' => $entity,
                    'id'     => $id,
                ],
            ],
            [$read, 'handle'],
            'edit_theme_options',
            'theme',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/site-templates-write',
            'free',
            'Edit a block theme template or part (id: slug or theme//slug) or wp_navigation menu (id). save: content or blocks, customizing over the theme file; add_block (content: one block)/update_block/remove_block: by path with expected_hash; revert: delete the customization. global_styles (no id): save attrs {dot.path: value|null} to user styles, preset entries by slug; variation: title; revert: reset. Snapshot-first',
            [
                'type'       => 'object',
                'properties' => [
                    'entity'        => $entity,
                    'id'            => $id,
                    'action'        => [ 'type' => 'string', 'enum' => [ 'save', 'add_block', 'update_block', 'remove_block', 'revert', 'variation' ] ],
                    'content'       => [ 'type' => 'string' ],
                    'blocks'        => [ 'type' => 'array' ],
                    'path'          => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'attrs'         => [ 'type' => 'object' ],
                    'inner_html'    => [ 'type' => 'string' ],
                    'expected_hash' => [ 'type' => 'string' ],
                    'title'         => [ 'type' => 'string' ],
                    'area'          => [ 'type' => 'string' ],
                    'session_id'    => [ 'type' => 'string' ],
                ],
                'required'   => [ 'entity' ],
            ],
            [$write, 'handle'],
            'edit_theme_options',
            'theme',
            'update'
        ));
    }

    /**
     * Register the surgical per-block editing tools and pattern tools
     * (issue #56) as free-tier abilities, domain 'blocks'.
     *
     * Shared targeting model: a "path" is an array of zero-based indexes
     * into the tree exactly as parse-blocks reports it, each subsequent
     * index descending into innerBlocks. Every mutation requires
     * expected_hash (the content_hash returned by parse-blocks) so a stale
     * read is refused instead of clobbering concurrent edits, refuses
     * content that does not round-trip byte-identically through
     * parse/serialize (so untouched blocks are never rewritten), and is
     * snapshot-first via Safe_Mutation, restorable with rollback-operation.
     */
    private function register_surgical_block_abilities(Registrar $registrar): void
    {
        $add_block       = new Add_Block();
        $update_block    = new Update_Block();
        $remove_block    = new Remove_Block();
        $move_block      = new Move_Block();
        $duplicate_block = new Duplicate_Block();
        $list_patterns   = new List_Patterns();
        $insert_pattern  = new Insert_Pattern();

        $path_schema = [
            'type'  => 'array',
            'items' => [ 'type' => 'integer' ],
        ];

        $registrar->register(new Ability(
            'wpmcp/add-block',
            'free',
            'Insert ONE block ("<!-- wp:... -->" markup) into a post at path (zero-based indexes into the parse-blocks tree; the last may equal the sibling count to append). Needs expected_hash (content_hash from parse-blocks); stale reads refused. Snapshot-first; other blocks stay byte-identical',
            [
                'type'       => 'object',
                'properties' => [
                    'id'            => [ 'type' => 'integer' ],
                    'path'          => $path_schema,
                    'markup'        => [ 'type' => 'string' ],
                    'expected_hash' => [ 'type' => 'string' ],
                    'session_id'    => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'path', 'markup', 'expected_hash' ],
            ],
            [$add_block, 'handle'],
            'edit_posts',
            'blocks',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-block',
            'free',
            'Update ONE block by path (zero-based indexes into the parse-blocks tree, through innerBlocks): replace attrs (whole) and/or inner_html (leaf blocks only; a container\'s children by their own paths). Needs expected_hash (content_hash from parse-blocks); stale reads refused. Snapshot-first; other blocks stay byte-identical',
            [
                'type'       => 'object',
                'properties' => [
                    'id'            => [ 'type' => 'integer' ],
                    'path'          => $path_schema,
                    'attrs'         => [ 'type' => 'object' ],
                    'inner_html'    => [ 'type' => 'string' ],
                    'expected_hash' => [ 'type' => 'string' ],
                    'session_id'    => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'path', 'expected_hash' ],
            ],
            [$update_block, 'handle'],
            'edit_posts',
            'blocks',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/remove-block',
            'free',
            'Remove ONE block by path (zero-based indexes into the parse-blocks tree, descending innerBlocks); nested removals keep the container. Needs expected_hash (content_hash from parse-blocks); stale reads refused. Snapshot-first; undo: rollback-operation',
            [
                'type'       => 'object',
                'properties' => [
                    'id'            => [ 'type' => 'integer' ],
                    'path'          => $path_schema,
                    'expected_hash' => [ 'type' => 'string' ],
                    'session_id'    => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'path', 'expected_hash' ],
            ],
            [$remove_block, 'handle'],
            'edit_posts',
            'blocks',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/move-block',
            'free',
            'Move the block at from_path to position to_index among its own siblings (same parent only; compose remove-block + add-block to move across parents). Needs expected_hash (content_hash from parse-blocks); stale reads refused. Snapshot-first',
            [
                'type'       => 'object',
                'properties' => [
                    'id'            => [ 'type' => 'integer' ],
                    'from_path'     => $path_schema,
                    'to_index'      => [ 'type' => 'integer' ],
                    'expected_hash' => [ 'type' => 'string' ],
                    'session_id'    => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'from_path', 'to_index', 'expected_hash' ],
            ],
            [$move_block, 'handle'],
            'edit_posts',
            'blocks',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/duplicate-block',
            'free',
            'Duplicate the block at path (deep copy, inserted immediately after the original within the same parent) and return the copy\'s new_path. Needs expected_hash (content_hash from parse-blocks); stale reads refused. Snapshot-first',
            [
                'type'       => 'object',
                'properties' => [
                    'id'            => [ 'type' => 'integer' ],
                    'path'          => $path_schema,
                    'expected_hash' => [ 'type' => 'string' ],
                    'session_id'    => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'path', 'expected_hash' ],
            ],
            [$duplicate_block, 'handle'],
            'edit_posts',
            'blocks',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-patterns',
            'free',
            'List registered block patterns: name, title, description, categories. Optional search (substring on name or title). Markup is not returned; insert-pattern inserts it server-side',
            [
                'type'       => 'object',
                'properties' => [
                    'search' => [ 'type' => 'string' ],
                ],
            ],
            [$list_patterns, 'handle'],
            'edit_posts',
            'blocks',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/insert-pattern',
            'free',
            'Insert a registered block pattern\'s blocks into a post at path (add-block\'s path semantics; whitespace filler dropped). Needs expected_hash (content_hash from parse-blocks); stale reads refused. Snapshot-first; existing blocks stay byte-identical',
            [
                'type'       => 'object',
                'properties' => [
                    'id'            => [ 'type' => 'integer' ],
                    'name'          => [ 'type' => 'string' ],
                    'path'          => $path_schema,
                    'expected_hash' => [ 'type' => 'string' ],
                    'session_id'    => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'name', 'path', 'expected_hash' ],
            ],
            [$insert_pattern, 'handle'],
            'edit_posts',
            'blocks',
            'create'
        ));
    }

    /**
     * Taxonomy term CRUD.
     *
     * Free tier and gated at manage_categories, the capability WordPress
     * itself uses for the Categories and Tags screens, so an editor who can
     * manage terms in wp-admin can manage them here and nobody else can.
     *
     * Every write runs through Safe_Mutation with a 'term' snapshot keyed by
     * (taxonomy, slug), following the create-redirect precedent rather than
     * the create-post one: terms have a natural key that exists before the
     * write, so even a creation is reversible. A deleted term is restored at
     * its ORIGINAL term_id and term_taxonomy_id, which is what reattaches
     * the posts that were filed under it.
     */
    private function register_taxonomy_abilities(Registrar $registrar): void
    {
        $list_terms    = new List_Terms();
        $get_term      = new Get_Term();
        $create_term   = new Create_Term();
        $update_term   = new Update_Term();
        $delete_term   = new Delete_Term();
        $set_term_meta = new Set_Term_Meta();

        $registrar->register(new Ability(
            'wpmcp/list-terms',
            'free',
            'List terms in a taxonomy with search, parent filter, ordering and pagination. Empty terms are INCLUDED unless hide_empty is set (the opposite of core\'s default), so a partial list never leads to creating a duplicate',
            [
                'type'       => 'object',
                'properties' => [
                    'taxonomy'   => [ 'type' => 'string' ],
                    'search'     => [ 'type' => 'string' ],
                    'parent'     => [ 'type' => 'integer' ],
                    'hide_empty' => [ 'type' => 'boolean' ],
                    'per_page'   => [ 'type' => 'integer' ],
                    'page'       => [ 'type' => 'integer' ],
                    'orderby'    => [ 'type' => 'string', 'enum' => ['name', 'slug', 'count', 'term_id'] ],
                    'order'      => [ 'type' => 'string', 'enum' => ['ASC', 'DESC'] ],
                ],
                'required'   => [ 'taxonomy' ],
            ],
            [$list_terms, 'handle'],
            'manage_categories',
            'taxonomy',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-term',
            'free',
            'Read one term by term_id or slug, with its meta and full ancestor chain. Ancestors disambiguate a hierarchical taxonomy, where several same-named terms sit under different parents',
            [
                'type'       => 'object',
                'properties' => [
                    'taxonomy' => [ 'type' => 'string' ],
                    'term_id'  => [ 'type' => 'integer' ],
                    'slug'     => [ 'type' => 'string' ],
                ],
                'required'   => [ 'taxonomy' ],
            ],
            [$get_term, 'handle'],
            'manage_categories',
            'taxonomy',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/create-term',
            'free',
            'Create a category, tag or custom taxonomy term. Snapshot-first; rollback-operation reverses the creation. Refuses an existing slug, a missing parent, or a parent on a non-hierarchical taxonomy',
            [
                'type'       => 'object',
                'properties' => [
                    'taxonomy'    => [ 'type' => 'string' ],
                    'name'        => [ 'type' => 'string' ],
                    'slug'        => [ 'type' => 'string' ],
                    'description' => [ 'type' => 'string' ],
                    'parent'      => [ 'type' => 'integer' ],
                    'session_id'  => [ 'type' => 'string' ],
                ],
                'required'   => [ 'taxonomy', 'name' ],
            ],
            [$create_term, 'handle'],
            'manage_categories',
            'taxonomy',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-term',
            'free',
            'Update a term\'s name, slug, description or parent, snapshot-first. Refuses a slug held by another term, and refuses reparenting onto itself or a descendant: core permits that and the resulting cycle makes the taxonomy unrenderable',
            [
                'type'       => 'object',
                'properties' => [
                    'taxonomy'    => [ 'type' => 'string' ],
                    'term_id'     => [ 'type' => 'integer' ],
                    'slug'        => [ 'type' => 'string' ],
                    'name'        => [ 'type' => 'string' ],
                    'description' => [ 'type' => 'string' ],
                    'parent'      => [ 'type' => 'integer' ],
                    'session_id'  => [ 'type' => 'string' ],
                ],
                'required'   => [ 'taxonomy' ],
            ],
            [$update_term, 'handle'],
            'manage_categories',
            'taxonomy',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-term',
            'free',
            'Delete a term, snapshot-first. Rolling back restores it at its original term_id and term_taxonomy_id and refiles the posts that were in it. Reports objects affected; refuses a taxonomy\'s default term',
            [
                'type'       => 'object',
                'properties' => [
                    'taxonomy'   => [ 'type' => 'string' ],
                    'term_id'    => [ 'type' => 'integer' ],
                    'slug'       => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'taxonomy' ],
            ],
            [$delete_term, 'handle'],
            'manage_categories',
            'taxonomy',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/set-term-meta',
            'free',
            'Write or delete a term meta value, snapshot-first (whole term plus meta map captured, so rollback is exact). value:null deletes the key, staying distinct from an empty string. Protected keys are refused',
            [
                'type'       => 'object',
                'properties' => [
                    'taxonomy'   => [ 'type' => 'string' ],
                    'term_id'    => [ 'type' => 'integer' ],
                    'slug'       => [ 'type' => 'string' ],
                    'key'        => [ 'type' => 'string' ],
                    'value'      => [],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'taxonomy', 'key' ],
            ],
            [$set_term_meta, 'handle'],
            'manage_categories',
            'taxonomy',
            'update'
        ));
    }

    /**
     * Register the shortcode and widget/sidebar introspection tools as
     * free-tier abilities (parity gap tracked in issue #38).
     *
     * All are gated at edit_posts and tagged domain 'structure'. Read-only
     * except render-shortcode, which executes the shortcode's own registered
     * callback via do_shortcode() and is tagged 'read' as well since it has
     * no database side effect of its own (the callback may of course have
     * side effects, exactly as it would on a live page render).
     */
    private function register_structure_abilities(Registrar $registrar): void
    {
        $list_shortcodes      = new List_Shortcodes();
        $render_shortcode     = new Render_Shortcode();
        $list_sidebars        = new List_Sidebars();
        $list_sidebar_widgets = new List_Sidebar_Widgets();

        $registrar->register(new Ability(
            'wpmcp/list-shortcodes',
            'free',
            'List registered shortcode tags ($shortcode_tags): tag name and a short description of its callback where resolvable. Optional search (substring of the tag name) narrows the result',
            [
                'type'       => 'object',
                'properties' => [
                    'search' => [ 'type' => 'string' ],
                ],
            ],
            [$list_shortcodes, 'handle'],
            'edit_posts',
            'structure',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/render-shortcode',
            'free',
            'Render a shortcode string (e.g. "[gallery ids=\"1,2\"]") via do_shortcode() and return the HTML. Only invokes registered shortcode tags; input without an opening "[" is refused',
            [
                'type'       => 'object',
                'properties' => [
                    'shortcode' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'shortcode' ],
            ],
            [$render_shortcode, 'handle'],
            'edit_posts',
            'structure',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-sidebars',
            'free',
            'List the sidebars/widget areas registered via register_sidebar(): id, name, description',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$list_sidebars, 'handle'],
            'edit_posts',
            'structure',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-sidebar-widgets',
            'free',
            'List the widgets assigned to a single sidebar (by sidebar_id): widget id and display name, from wp_get_sidebars_widgets() resolved against the registered widgets',
            [
                'type'       => 'object',
                'properties' => [
                    'sidebar_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'sidebar_id' ],
            ],
            [$list_sidebar_widgets, 'handle'],
            'edit_posts',
            'structure',
            'read'
        ));

        // Classic widget writes (issue #285). Each snapshots widget_{id_base}
        // and sidebars_widgets as one undo point; see Sidebar_Widget_Store.
        $widget_id = [ 'type' => 'string' ];
        $position  = [ 'type' => 'integer' ];
        $session   = [ 'type' => 'string' ];
        $registrar->register(new Ability(
            'wpmcp/create-sidebar-widget',
            'free',
            'Add a classic widget (registered id_base, incl. block) to a sidebar at a 0-based position, sanitized by its update(). Undoable',
            [
                'type'       => 'object',
                'properties' => [
                    'sidebar_id' => [ 'type' => 'string' ],
                    'id_base'    => [ 'type' => 'string' ],
                    'instance'   => [ 'type' => 'object' ],
                    'position'   => $position,
                    'session_id' => $session,
                ],
                'required'   => [ 'sidebar_id', 'id_base' ],
            ],
            [new Create_Sidebar_Widget(), 'handle'],
            'edit_theme_options',
            'structure',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-sidebar-widget',
            'free',
            'Merge settings into a classic widget (id like text-2) via its update(). Undoable',
            [
                'type'       => 'object',
                'properties' => [
                    'widget_id'  => $widget_id,
                    'instance'   => [ 'type' => 'object' ],
                    'session_id' => $session,
                ],
                'required'   => [ 'widget_id', 'instance' ],
            ],
            [new Update_Sidebar_Widget(), 'handle'],
            'edit_theme_options',
            'structure',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/move-sidebar-widget',
            'free',
            'Move a classic widget to a sidebar or wp_inactive_widgets at a 0-based position. Undoable',
            [
                'type'       => 'object',
                'properties' => [
                    'widget_id'  => $widget_id,
                    'sidebar_id' => [ 'type' => 'string' ],
                    'position'   => $position,
                    'session_id' => $session,
                ],
                'required'   => [ 'widget_id', 'sidebar_id' ],
            ],
            [new Move_Sidebar_Widget(), 'handle'],
            'edit_theme_options',
            'structure',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-sidebar-widget',
            'free',
            'Delete a classic widget instance. Undoable',
            [
                'type'       => 'object',
                'properties' => [
                    'widget_id'  => $widget_id,
                    'session_id' => $session,
                ],
                'required'   => [ 'widget_id' ],
            ],
            [new Delete_Sidebar_Widget(), 'handle'],
            'edit_theme_options',
            'structure',
            'delete'
        ));
    }

    /**
     * Register the content export/import tools as free-tier abilities
     * (parity gap tracked in issue #40). Gated at manage_options: a WXR
     * export can contain the full text of every post (including private and
     * draft content), so it warrants the same capability already used for
     * other site-wide read/write tools like get-settings and update-plugin.
     */
    private function register_export_abilities(Registrar $registrar): void
    {
        $export_content = new Export_Content();
        $list_exports    = new List_Exports();
        $import_content  = new Import_Content();

        $registrar->register(new Ability(
            'wpmcp/export-content',
            'free',
            'WXR export via core export_wp(), narrowed by content, author, start_date, end_date, status, to a protected uploads dir; returns path, size, count. Once per PHP process (core limit). mirror:true instead writes builder pages as stable JSON for git (post_id, or all 50 per page)',
            [
                'type'       => 'object',
                'properties' => [
                    'content'    => [ 'type' => 'string' ],
                    'author'     => [ 'type' => 'integer' ],
                    'start_date' => [ 'type' => 'string' ],
                    'end_date'   => [ 'type' => 'string' ],
                    'status'     => [ 'type' => 'string' ],
                    'mirror'     => [ 'type' => 'boolean' ],
                    'post_id'    => [ 'type' => 'integer' ],
                    'page'       => [ 'type' => 'integer' ],
                ],
            ],
            [$export_content, 'handle'],
            'manage_options',
            'export',
            'read',
            // Not read-only (issue #420): each call writes a new export file.
            false,
            false,
            false
        ));
        $registrar->register(new Ability(
            'wpmcp/list-exports',
            'free',
            'List the WXR export files previously generated by export-content: file name, size in bytes and created timestamp for each',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$list_exports, 'handle'],
            'manage_options',
            'export',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/import-content',
            'free',
            'Import a WXR from the exports dir as new posts (title, content, status, post_type, postmeta). Off by default (wpmcp_enable_import filter), needs confirm:true, not snapshotted (recoverable:false); returns created_post_ids for delete-post. mirror:true + post_id restores that page from its export-content mirror (undoable)',
            [
                'type'       => 'object',
                'properties' => [
                    'file'    => [ 'type' => 'string' ],
                    'confirm' => [ 'type' => 'boolean' ],
                    'mirror'  => [ 'type' => 'boolean' ],
                    'post_id' => [ 'type' => 'integer' ],
                    'session_id' => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
            ],
            [$import_content, 'handle'],
            'manage_options',
            'export',
            'create'
        ));
    }

    /**
     * Register the async backup job orchestration tools as free-tier
     * abilities (parity gap tracked in issue #53). This sits on top of the
     * synchronous export-content tool: trigger-backup queues a job and
     * schedules a WP-Cron event (Run_Backup_Job) that produces the actual
     * artifact and flips the job's status, so a backup on a large site does
     * not have to complete within a single MCP request/response cycle.
     *
     * On multisite all seven take manage_network_options instead (issue
     * #525, Network_Scope): an archive there holds the whole network.
     * On single site all seven are gated at manage_options, matching Export's and Cron's
     * capability (both are comparable site-operations-level tool groups, and
     * this plugin's only precedent for a stronger gate is the
     * Elementor deep-editing tools specifically, not "heavy" operations in
     * general). trigger-backup is 'create' (it creates a job record),
     * cancel-backup-job is 'update' (it transitions an existing job's
     * status), delete-backup-archive is 'delete'; get-backup-status,
     * list-backup-jobs and get-backup-manifest are 'read'.
     *
     * The backup job itself only reads site data and writes a backup
     * artifact file plus the wpmcp_backup_jobs option: it never mutates user
     * content, so none of the job tools are routed through Safe_Mutation and
     * none touch the safety core.
     *
     * This build ships no whole-site restore (issue #1147).
     */
    private function register_backup_abilities(Registrar $registrar): void
    {
        $trigger_backup     = new Trigger_Backup();
        $get_backup_status  = new Get_Backup_Status();
        $list_backup_jobs   = new List_Backup_Jobs();
        $cancel_backup_job  = new Cancel_Backup_Job();
        $get_backup_manifest   = new Get_Backup_Manifest();
        $delete_backup_archive = new Delete_Backup_Archive();
        // A sub-site admin must not back up or restore the whole network
        // (issue #525): see Network_Scope.
        $network_cap = Network_Scope::capability();

        $registrar->register(new Ability(
            'wpmcp/trigger-backup',
            'free',
            'Queue a WP-Cron backup job; returns its job id. type=full: portable site archive (zip: full SQL dump, wp-content, origin manifest) to restore or migrate; database/files/uploads narrow it; content: WXR export. every=daily [HH:MM], weekly [mon-sun] [HH:MM] (site time) or off schedules that type instead; each run prunes its archives past keep (default 7), never manual ones or after a failed run',
            [
                'type'       => 'object',
                'properties' => [
                    'type'  => [
                        'type' => 'string',
                        'enum' => ['full', 'database', 'files', 'uploads', 'content'],
                    ],
                    'scope' => [ 'type' => 'string' ],
                    'every' => [ 'type' => 'string' ],
                    'keep'  => [ 'type' => 'integer' ],
                ],
            ],
            [$trigger_backup, 'handle'],
            $network_cap,
            'backup',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-backup-status',
            'free',
            'Return a backup job\'s current record (status: queued/running/completed/failed/canceled, result artifact reference or error, timestamps) by job id',
            [
                'type'       => 'object',
                'properties' => [
                    'job_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'job_id' ],
            ],
            [$get_backup_status, 'handle'],
            $network_cap,
            'backup',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-backup-jobs',
            'free',
            'List backup jobs, newest first, optionally by status (queued/running/completed/failed/canceled), plus schedules (next run, overdue, last run)',
            [
                'type'       => 'object',
                'properties' => [
                    'status' => [ 'type' => 'string' ],
                ],
            ],
            [$list_backup_jobs, 'handle'],
            $network_cap,
            'backup',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/cancel-backup-job',
            'free',
            'Cancel a queued or running backup job: unschedule its event, or stop the run at its next heartbeat. Errors if unknown, a running restore, or terminal',
            [
                'type'       => 'object',
                'properties' => [
                    'job_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'job_id' ],
            ],
            [$cancel_backup_job, 'handle'],
            $network_cap,
            'backup',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-backup-manifest',
            'free',
            'Read a completed site-backup archive\'s manifest by job id or path: origin site_url/home_url, table prefix, multisite, WordPress/PHP/plugin versions, scope, per-table row counts, BLOB tables and file count. Read-only; paths outside the site-backup dir are refused',
            [
                'type'       => 'object',
                'properties' => [
                    'job_id' => [ 'type' => 'integer' ],
                    'path'   => [ 'type' => 'string' ],
                ],
            ],
            [$get_backup_manifest, 'handle'],
            $network_cap,
            'backup',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-backup-archive',
            'free',
            'Delete a site-backup archive by job id or path and report bytes freed. The job record stays, flagged deleted, so history never points at a missing file. Paths outside the site-backup directory are refused',
            [
                'type'       => 'object',
                'properties' => [
                    'job_id' => [ 'type' => 'integer' ],
                    'path'   => [ 'type' => 'string' ],
                ],
            ],
            [$delete_backup_archive, 'handle'],
            $network_cap,
            'backup',
            'delete'
        ));
    }

    /**
     * Local-live sync (issue #192): change-set export derived from the
     * snapshot ledger, inspection, and apply. The unit of sync is a set of
     * explicitly selected objects, never the whole database, so live-side
     * data the local copy has never seen (orders, comments, form entries) is
     * left alone by construction.
     *
     * build-change-set and get-change-set only read site data (build writes
     * one artifact file into the protected site-backup dir), so neither is
     * routed through Safe_Mutation. apply-change-set is the mutating half:
     * every update is snapshot-first through Safe_Mutation, every creation
     * records a creation row, all under one session so rollback-session
     * undoes a whole sync. It defaults to dry_run and is advertised as
     * destructive because it can overwrite live content (never without a
     * snapshot). Gated at manage_options like the backup group it builds on.
     * Free/Pro placement is an open question on the issue; registered free
     * so the feature is exercisable, revisit before release.
     */
    private function register_sync_abilities(Registrar $registrar): void
    {
        $build_change_set = new Build_Change_Set();
        $get_change_set   = new Get_Change_Set();
        $apply_change_set = new Apply_Change_Set();

        $registrar->register(new Ability(
            'wpmcp/build-change-set',
            'free',
            'Build a local-live sync change set (objects, media bytes, terms, templates, global classes, base revisions) from a ledger marker (session_id, operation_id or since_id) and/or object refs (post:ID, option:theme_mods_X, term:TAX:SLUG) as a JSON artifact in the site-backup dir. dry_run only lists it',
            [
                'type'       => 'object',
                'properties' => [
                    'session_id'   => [ 'type' => 'string' ],
                    'operation_id' => [ 'type' => 'string' ],
                    'since_id'     => [ 'type' => 'integer' ],
                    'objects'      => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'dry_run'      => [ 'type' => 'boolean' ],
                ],
            ],
            [$build_change_set, 'handle'],
            'manage_options',
            'sync',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-change-set',
            'free',
            'Inspect a change-set artifact before it is applied: origin, objects, dependencies, exclusions, truncation. include_objects=true adds full data; raw=true returns the artifact to pass to apply-change-set on the target. Read-only; site-backup dir only',
            [
                'type'       => 'object',
                'properties' => [
                    'path'            => [ 'type' => 'string' ],
                    'include_objects' => [ 'type' => 'boolean' ],
                    'raw'             => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'path' ],
            ],
            [$get_change_set, 'handle'],
            'manage_options',
            'sync',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/apply-change-set',
            'free',
            'Apply a change set (change_set from get-change-set raw=true, or path) here; dry_run defaults to true. Only selected objects are written, snapshot-first; media, terms and templates are only added; objects changed here since the base are refused unless keyed in force; deletions never apply. rollback-session undoes it',
            [
                'type'       => 'object',
                'properties' => [
                    'change_set' => [ 'type' => 'object' ],
                    'path'       => [ 'type' => 'string' ],
                    'dry_run'    => [ 'type' => 'boolean' ],
                    'force'      => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'session_id' => [ 'type' => 'string' ],
                ],
            ],
            [$apply_change_set, 'handle'],
            'manage_options',
            'sync',
            'update',
            false,
            true,
            true
        ));
    }

    /**
     * Governance configuration, scoped identities, and the governance
     * decision audit log. All free-tier at manage_options, domain
     * 'governance', matching the existing config/audit tools in this
     * codebase (list-operations, rollback-*, get/update-settings): access
     * control configuration is site administration, not a feature add-on,
     * so none of this is gated behind Pro.
     */
    private function register_governance_abilities(Registrar $registrar): void
    {
        $get_governance_settings    = new Get_Governance_Settings();
        $update_governance_settings = new Update_Governance_Settings();
        $list_governance_audit_log  = new List_Governance_Audit_Log();
        $create_identity            = new Create_Identity();
        $list_identities            = new List_Identities();
        $delete_identity            = new Delete_Identity();

        $registrar->register(new Ability(
            'wpmcp/get-governance-settings',
            'free',
            'Return the stored governance toggle maps (ability, domain, operation) and site_wide mode: explicit enable/disable decisions layered on top of the wpmcp_{ability,domain,operation}_enabled filters',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$get_governance_settings, 'handle'],
            'manage_options',
            'governance',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/update-governance-settings',
            'free',
            'Batch-update stored governance toggles by ability, domain and operation, e.g. {ability: {"wpmcp/delete-post": false}, domain: {"database": false}}. Undoable; refuses unknown names and disabling the governance or undo tools. site_wide audits ability calls outside MCP; enforce also refuses abilities toggled off by name. auto_enable_new_tools: updates switch their new safe tools on',
            [
                'type'       => 'object',
                'properties' => [
                    'ability'   => [ 'type' => 'object' ],
                    'domain'    => [ 'type' => 'object' ],
                    'operation' => [ 'type' => 'object' ],
                    'site_wide' => [ 'type' => 'string', 'enum' => [ 'off', 'audit', 'enforce' ] ],
                    'auto_enable_new_tools' => [ 'type' => 'boolean' ],
                    'session_id'            => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
            ],
            [$update_governance_settings, 'handle'],
            'manage_options',
            'governance',
            'update'
        ));

        $registrar->register(new Ability(
            'wpmcp/list-governance-audit-log',
            'free',
            'List governance-decision audit log entries (ability, active identity or "none", allowed/denied, timestamp, source: mcp/rest/server/cli/php), newest first. Optional limit (default 20) and source filter',
            [
                'type'       => 'object',
                'properties' => [
                    'limit'  => [ 'type' => 'integer' ],
                    'source' => [ 'type' => 'string' ],
                ],
            ],
            [$list_governance_audit_log, 'handle'],
            'manage_options',
            'governance',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/create-identity',
            'free',
            'Create or overwrite (by name) a scoped identity that, once active (wpmcp_current_identity filter), narrows abilities beyond capability and Governance. Optional domains/operations/abilities allowlists, mode (allow default, or deny), exposure (full/compact, overrides site-wide mode), allowed_ips (IPs/CIDRs; other clients refused)',
            [
                'type'       => 'object',
                'properties' => [
                    'name'        => [ 'type' => 'string' ],
                    'domains'     => [ 'type' => 'array' ],
                    'operations'  => [ 'type' => 'array' ],
                    'abilities'   => [ 'type' => 'array' ],
                    'mode'        => [ 'type' => 'string' ],
                    'exposure'    => [ 'type' => 'string' ],
                    'allowed_ips' => [ 'type' => 'array' ],
                ],
                'required'   => [ 'name' ],
            ],
            [$create_identity, 'handle'],
            'manage_options',
            'governance',
            'create'
        ));

        $registrar->register(new Ability(
            'wpmcp/list-identities',
            'free',
            'List every registered scoped identity',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$list_identities, 'handle'],
            'manage_options',
            'governance',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/delete-identity',
            'free',
            'Delete a scoped identity by name. Returns an error if no identity with that name exists',
            [
                'type'       => 'object',
                'properties' => [
                    'name' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'name' ],
            ],
            [$delete_identity, 'handle'],
            'manage_options',
            'governance',
            'delete'
        ));
    }

    /**
     * Register the multisite/network-introspection tools as free-tier
     * abilities (parity gap tracked in issue #35).
     *
     * This tool group is READ-ONLY network introspection plus honest
     * flagging when a tool is called outside a network. Fleet-management
     * writes (create/delete/archive/activate a site) are explicitly OUT OF
     * SCOPE: those operations are not covered by the snapshot/rollback
     * safety model (Safe_Mutation understands single-site content, options,
     * and postmeta, not whole-site lifecycle events), and exposing them here
     * would violate the product's "nothing unrecoverable" promise. If fleet
     * writes are ever added, they need their own safety story first, not a
     * bolt-on to this read-only group.
     *
     * is-multisite is registered unconditionally: it is the tool a caller
     * uses to discover whether a network exists at all, so it must be
     * reachable even on a single-site install (compare get-seo-status, which
     * is also always registered so it can report "no plugin active"). The
     * other three tools (get-network-info, list-network-sites,
     * get-site-details) are gated behind is_multisite(), following the same
     * conditional-registration pattern as the ACF/SEO/i18n tool groups:
     * WordPress's own multisite flag is the signal, and skipping
     * registration entirely keeps them out of the catalog on single-site
     * installs rather than registering tools that would only ever return a
     * "not a network" error.
     *
     * Gated at manage_network (WordPress's network-admin capability),
     * falling back to manage_options: manage_network does not exist as a
     * meaningful capability on a single-site install (current_user_can()
     * against it is effectively always false there), so manage_options keeps
     * these usable in that context while still requiring the equivalent
     * network-admin capability on an actual multisite install.
     */
    private function register_multisite_abilities(Registrar $registrar): void
    {
        $is_multisite = new Is_Multisite();

        $registrar->register(new Ability(
            'wpmcp/is-multisite',
            'free',
            'Whether this install is a multisite network. Always registered, even on single-site, so network status can be checked before the other multisite tools',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$is_multisite, 'handle'],
            'edit_posts',
            'multisite',
            'read'
        ));

        if (! is_multisite()) {
            return;
        }

        $get_network_info = new Get_Network_Info();

        $registrar->register(new Ability(
            'wpmcp/get-network-info',
            'free',
            'Report this network\'s id, name, domain, total site count, and main site id, via get_network()/get_main_site_id()',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$get_network_info, 'handle'],
            'manage_network',
            'multisite',
            'read'
        ));

        $list_network_sites = new List_Network_Sites();

        $registrar->register(new Ability(
            'wpmcp/list-network-sites',
            'free',
            'List sites on the network (blog_id, url, name, last_updated) via get_sites(), with optional limit (default 50) and offset for pagination. limit is capped at 500',
            [
                'type'       => 'object',
                'properties' => [
                    'limit'  => [ 'type' => 'integer' ],
                    'offset' => [ 'type' => 'integer' ],
                ],
            ],
            [$list_network_sites, 'handle'],
            'manage_network',
            'multisite',
            'read'
        ));

        $get_site_details = new Get_Site_Details();

        $registrar->register(new Ability(
            'wpmcp/get-site-details',
            'free',
            'Report a single network site\'s details (blog_id, url, name, last_updated) by blog_id, via get_site()/get_blog_details(). Returns an error for an unrecognized blog_id',
            [
                'type'       => 'object',
                'properties' => [
                    'blog_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'blog_id' ],
            ],
            [$get_site_details, 'handle'],
            'manage_network',
            'multisite',
            'read'
        ));
    }

    /**
     * The `source` argument of get-analytics-summary and get-top-pages
     * (issue #699): Site Kit or one of the stats plugins.
     *
     * @return array<string,mixed>
     */
    private static function analytics_source_schema(): array
    {
        return [
            'type' => 'string',
            'enum' => [ 'site-kit', 'independent-analytics', 'burst', 'wp-statistics', 'monsterinsights', 'jetpack' ],
        ];
    }

    /**
     * Register the analytics/Search Console reporting tools as free-tier
     * abilities (issue #49).
     *
     * FREE tier, matching every other "read data from a connected
     * third-party plugin/service" tool group in this codebase: Multisite
     * introspection, I18n (Polylang/WPML), SEO status/meta, and ALL of
     * WooCommerce including get-sales-report (a reporting tool directly
     * analogous to analytics summaries) are all free-tier. 'pro' tier here is
     * reserved for a different kind of feature: deep content scoring/analysis
     * (analyze-seo, analyze-accessibility, check-contrast, extract-content,
     * see the abilities.analysis edition slot) and deep Elementor element-tree
     * editing. Analytics summary/top-pages/GSC-summary/GSC-queries are the
     * same shape as WooCommerce's sales report or Multisite's site listing:
     * basic read/reporting access to a connected system, not deep analysis.
     *
     * All five tools are registered unconditionally, including the four data
     * tools (not just get-analytics-connection-status): unlike is_multisite(),
     * which is fixed for the lifetime of a request, whether an analytics
     * provider is connected can change at runtime without a page reload (a
     * site admin can activate/connect Site Kit at any time), so gating
     * registration on current connection state would require re-registering
     * abilities mid-request. Each data tool instead returns a
     * wpmcp_analytics_not_connected WP_Error gracefully when nothing is
     * connected, the same "always in the catalog, fails gracefully at call
     * time" shape as get-network-info et al. do for is_multisite() (the
     * difference being is_multisite() cannot change per-request, so that
     * group gates registration itself; this group's connection state can, so
     * it does not).
     *
     * get-analytics-connection-status specifically is the tool a caller uses
     * to discover whether any analytics provider is connected at all, before
     * deciding whether to use the rest of the group (mirrors
     * wpmcp/is-multisite and get-seo-status).
     *
     * Gated at manage_options for all five tools, matching the issue's spec:
     * analytics/Search Console data, even a "not connected" status check, is
     * site-administration information, not a content-editing capability.
     *
     * This is deliberately READ-ONLY: analytics/Search Console summaries and
     * reports only. There is no write path to Google or to this site here,
     * so none of these tools touch Safe_Mutation.
     */
    private function register_analytics_abilities(Registrar $registrar): void
    {
        $get_connection_status = new Get_Analytics_Connection_Status();
        $get_analytics_summary = new Get_Analytics_Summary();
        $get_top_pages         = new Get_Top_Pages();
        $get_search_console_summary = new Get_Search_Console_Summary();
        $get_search_console_queries = new Get_Search_Console_Queries();

        $registrar->register(new Ability(
            'wpmcp/get-analytics-connection-status',
            'free',
            'Whether an analytics provider (Google Site Kit or configured credentials) is active and appears connected. Always registered, so it can be checked before the other analytics tools',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$get_connection_status, 'handle'],
            'manage_options',
            'analytics',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/get-analytics-summary',
            'free',
            'Read-only sessions/users/pageviews summary over a date range (Y-m-d; default the 28 days ending yesterday) from the connected analytics provider, or the stats plugin named in source (default: first active). Errors when no provider is connected',
            [
                'type'       => 'object',
                'properties' => [
                    'start_date' => [ 'type' => 'string' ],
                    'end_date'   => [ 'type' => 'string' ],
                    'source'     => self::analytics_source_schema(),
                ],
            ],
            [$get_analytics_summary, 'handle'],
            'manage_options',
            'analytics',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/get-top-pages',
            'free',
            'Read-only list of top pages by pageviews over a date range (Y-m-d; default the 28 days ending yesterday) from the connected analytics provider or the stats plugin named in source; limit default 10, max 100. Errors when no provider is connected',
            [
                'type'       => 'object',
                'properties' => [
                    'start_date' => [ 'type' => 'string' ],
                    'end_date'   => [ 'type' => 'string' ],
                    'limit'      => [ 'type' => 'integer' ],
                    'source'     => self::analytics_source_schema(),
                ],
            ],
            [$get_top_pages, 'handle'],
            'manage_options',
            'analytics',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/get-search-console-summary',
            'free',
            'Read-only clicks/impressions/ctr/position summary over a date range (Y-m-d; default the 28 days ending yesterday) from the connected Search Console provider. Errors when no provider is connected',
            [
                'type'       => 'object',
                'properties' => [
                    'start_date' => [ 'type' => 'string' ],
                    'end_date'   => [ 'type' => 'string' ],
                ],
            ],
            [$get_search_console_summary, 'handle'],
            'manage_options',
            'analytics',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/get-search-console-queries',
            'free',
            'Read-only list of top search queries by clicks over a date range (Y-m-d; default the 28 days ending yesterday) from the connected Search Console provider; limit default 10, max 100. Errors when no provider is connected',
            [
                'type'       => 'object',
                'properties' => [
                    'start_date' => [ 'type' => 'string' ],
                    'end_date'   => [ 'type' => 'string' ],
                    'limit'      => [ 'type' => 'integer' ],
                ],
            ],
            [$get_search_console_queries, 'handle'],
            'manage_options',
            'analytics',
            'read'
        ));
    }

    /**
     * Register the system diagnostics tools as free-tier abilities (parity
     * gap tracked in issue #32).
     *
     * All four are gated at manage_options: debug config/log and the
     * transient list can reveal server paths and cache internals, so this
     * matches the same capability already used for get-cache-status and
     * clear-cache. get-debug-config, get-debug-log, and list-transients are
     * 'read' operations; delete-transient is 'update' but, like clear-cache,
     * is not routed through Safe_Mutation: a transient is cache-like data
     * with no meaningful before-image to restore. Its cleanup list (issue
     * #414, see Db_Cleanup) is a dry run by default; the confirmed run
     * snapshots every row it deletes, expired transients aside.
     *
     * get-site-health (issue #381) runs the Site Health tests and is gated
     * at view_site_health_checks, the capability core checks for the Site
     * Health screen itself. Its mail_test (issue #415) sending path adds its
     * own manage_options, confirm and rate-limit gates (see Mail_Check).
     */
    private function register_diagnostics_abilities(Registrar $registrar): void
    {
        $get_debug_config = new Get_Debug_Config();
        $get_debug_log    = new Get_Debug_Log();
        $list_transients  = new List_Transients();
        $delete_transient = new Delete_Transient();
        $get_site_health  = new Get_Site_Health();

        $registrar->register(new Ability(
            'wpmcp/get-debug-config',
            'free',
            'Debug constants (WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, SAVEQUERIES) and, when logging is on, the debug.log path. No secrets',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$get_debug_config, 'handle'],
            'manage_options',
            'diagnostics',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-debug-log',
            'free',
            'Bounded tail (max 200 lines / 64KB) of the debug log, by default WP_CONTENT_DIR/debug.log or the WP_DEBUG_LOG path. A path argument is confined to WP_CONTENT_DIR. source:access searches the access log, newest first (opt-in, IPs masked): filter {ip,path,status,method,agent,since,until,around_file,window minutes}, lines max 200, cursor; summary:true: top IPs, paths, wp-content PHP POSTs',
            [
                'type'       => 'object',
                'properties' => [
                    'path'    => [ 'type' => 'string' ],
                    'lines'   => [ 'type' => 'integer' ],
                    'source'  => [ 'type' => 'string', 'enum' => [ 'debug', 'access' ] ],
                    'filter'  => [ 'type' => 'object' ],
                    'summary' => [ 'type' => 'boolean' ],
                    'cursor'  => [ 'type' => 'string' ],
                ],
            ],
            [$get_debug_log, 'handle'],
            // One debug.log for the whole network (issue #559).
            Filesystem_Guard::read_capability(),
            'diagnostics',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-transients',
            'free',
            'List transients (name, expiry) with an optional search substring; limit default 50, max 500',
            [
                'type'       => 'object',
                'properties' => [
                    'search' => [ 'type' => 'string' ],
                    'limit'  => [ 'type' => 'integer' ],
                ],
            ],
            [$list_transients, 'handle'],
            'manage_options',
            'diagnostics',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-transient',
            'free',
            'Delete one named transient (cache data, not snapshotted) or run cleanup: expired_transients, revisions (beyond keep newest per post, default 5), auto_drafts (7+ days old), trash (older than days, default 0), spam_comments, orphaned_meta. Dry run by default (counts, bytes, sample ids); dry_run:false+confirm applies up to 200 items/10s, returning a cursor to resume. rollback-operation undoes all but transients; attachments and parents are skipped',
            [
                'type'       => 'object',
                'properties' => [
                    'name'    => [ 'type' => 'string' ],
                    'cleanup' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'keep'    => [ 'type' => 'integer' ],
                    'days'    => [ 'type' => 'integer' ],
                    'dry_run' => [ 'type' => 'boolean' ],
                    'confirm' => [ 'type' => 'boolean' ],
                    'cursor'  => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
            ],
            [$delete_transient, 'handle'],
            'manage_options',
            'diagnostics',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-site-health',
            'free',
            'Run Site Health tests, plugin-added ones included, as plain text. Async tests past timeout seconds (default 10, max 30) report not completed. cached: last full run. mail_test: how mail is sent (no secrets); your or admin email + confirm sends a test (manage_options, rate limited)',
            [
                'type'       => 'object',
                'properties' => [
                    'tests'     => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'timeout'   => [ 'type' => 'integer' ],
                    'cached'    => [ 'type' => 'boolean' ],
                    'mail_test' => [ 'type' => 'string' ],
                    'confirm'   => [ 'type' => 'boolean' ],
                ],
            ],
            [$get_site_health, 'handle'],
            'view_site_health_checks',
            'diagnostics',
            'read',
            // Not read-only (issue #420): mail_test + confirm sends a test email.
            // Still operation 'read', so scoped read identities keep Site Health.
            false,
            false,
            false
        ));
    }

    /**
     * Register the generic post-meta and wp_options tools as free-tier
     * abilities (parity gap tracked in issue #31).
     *
     * get-post-meta/set-post-meta are gated at edit_posts, matching the rest
     * of the content tools; get-option/update-option are gated at
     * manage_options, since arbitrary option access is a site-settings-level
     * capability, not a content-editing one. set-post-meta and update-option
     * are 'update' operations (route through Safe_Mutation and are
     * undoable); update-option is additionally disabled by default behind
     * the wpmcp_enable_option_write filter (see Update_Option), so
     * registering the ability does not by itself allow any write.
     */
    private function register_meta_abilities(Registrar $registrar): void
    {
        $get_post_meta = new Get_Post_Meta();
        $set_post_meta = new Set_Post_Meta();
        $get_option    = new Get_Option();
        $update_option = new Update_Option();

        $registrar->register(new Ability(
            'wpmcp/get-post-meta',
            'free',
            'Read a post\'s meta, either the full map or a single key. Protected meta (a leading underscore, or is_protected_meta) is always skipped',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id' => [ 'type' => 'integer' ],
                    'key'     => [ 'type' => 'string' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$get_post_meta, 'handle'],
            'edit_posts',
            'meta',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/set-post-meta',
            'free',
            'Set a single meta key/value on a post. Refuses protected meta keys (a leading underscore, or is_protected_meta). Snapshotted via object_type post; rollback-operation restores the prior value',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'    => [ 'type' => 'integer' ],
                    'key'        => [ 'type' => 'string' ],
                    'value'      => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'post_id', 'key' ],
            ],
            [$set_post_meta, 'handle'],
            'edit_posts',
            'meta',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-option',
            'free',
            'Read one wp_options value by name. Refuses sensitive/core option names (auth keys, salts, siteurl, home, active_plugins and secret/password/token-shaped names)',
            [
                'type'       => 'object',
                'properties' => [
                    'name' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'name' ],
            ],
            [$get_option, 'handle'],
            'manage_options',
            'settings',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-option',
            'free',
            'Update one wp_options value. Refuses get-option\'s denylist; off unless filter wpmcp_enable_option_write is on. Keeps an array/object option\'s type unless allow_type_change. Undoable',
            [
                'type'       => 'object',
                'properties' => [
                    'name'              => [ 'type' => 'string' ],
                    'value'             => [ 'type' => [ 'string', 'array', 'object' ] ],
                    'allow_type_change' => [ 'type' => 'boolean' ],
                    'session_id'        => [ 'type' => 'string' ],
                ],
                'required'   => [ 'name', 'value' ],
            ],
            [$update_option, 'handle'],
            'manage_options',
            'settings',
            'update'
        ));
    }

    /**
     * Register the Elementor widget catalog tools as free-tier abilities.
     *
     * Registered unconditionally, matching every other tool group: a caller
     * only reaches a handler by invoking the ability, and each handler
     * degrades gracefully with a WP_Error when Elementor is not loaded. Both
     * tools are read-only (list-widgets and get-widget-schema inspect
     * Elementor's own widgets manager plus the curated widget catalog and
     * never touch post content), so neither is routed through the safety
     * core. The catalog itself is pure data (Widget_Catalog), so widget
     * coverage grows without growing this advertised tool surface - pinned
     * by tests/free/Platform/ToolsListBudgetTest.php.
     */
    private function register_elementor_abilities(Registrar $registrar): void
    {
        $list_widgets      = new List_Widgets();
        $get_widget_schema = new Get_Widget_Schema();

        $registrar->register(new Ability(
            'wpmcp/list-widgets',
            'free',
            'List Elementor registered widget types (name, title, categories, icon, tier, availability), annotated from the curated catalog (purpose, cataloged flag). Filter by tier (free/pro), category or case-insensitive search (name, title, catalog keywords)',
            [
                'type'       => 'object',
                'properties' => [
                    'tier'     => [ 'type' => 'string' ],
                    'category' => [ 'type' => 'string' ],
                    'search'   => [ 'type' => 'string' ],
                ],
            ],
            [$list_widgets, 'handle'],
            'edit_posts',
            'elementor',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/get-widget-schema',
            'free',
            'The settings schema for one Elementor widget type: curated typed params (defaults, enums, responsive hints, required plugin) for cataloged widgets, or with full:true (and for non-cataloged widgets) the full introspected control stack',
            [
                'type'       => 'object',
                'properties' => [
                    'widget_name' => [ 'type' => 'string' ],
                    'full'        => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'widget_name' ],
            ],
            [$get_widget_schema, 'handle'],
            'edit_posts',
            'elementor',
            'read'
        ));

        $get_global_settings = new Get_Global_Settings();

        $registrar->register(new Ability(
            'wpmcp/get-global-settings',
            'free',
            'Read the Elementor kit\'s global colors, typography, spacing and layout. Returns the settings_hash the global color and typography writes require',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$get_global_settings, 'handle'],
            'edit_posts',
            'elementor',
            'read'
        ));

        // A cache operation, not a content write: like clear-cache it is not
        // snapshotted, since generated CSS has no before-image worth restoring.
        $regenerate_elementor_css = new Regenerate_Elementor_Css();

        $registrar->register(new Ability(
            'wpmcp/regenerate-elementor-css',
            'free',
            'Rebuild Elementor CSS; all needs confirm',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id' => [ 'type' => 'integer' ],
                    'confirm' => [ 'type' => 'boolean' ],
                ],
            ],
            [$regenerate_elementor_css, 'handle'],
            'edit_posts',
            'elementor',
            'update'
        ));

        $this->edition()->run('abilities.elementor_pro', $this->edition_context($registrar));
    }

    /**
     * Register the WooCommerce store tools: the simple store tools plus the
     * deep operations catalog (woo-ops, woo-read, woo-write, issue #68).
     *
     * These are registered unconditionally (matching every other tool group):
     * a caller only reaches a handler by invoking the ability, and each handler
     * uses WooCommerce functions that are present whenever WooCommerce is
     * active. Product mutations reuse the existing 'post' snapshot object type
     * (a product is a 'product' post), and update-order-status uses the
     * additive 'wc_order' snapshot type, so both are undoable through the same
     * engine. Writes require manage_woocommerce; order writes require
     * edit_shop_orders. The destructive delete-product tool is disabled by
     * default behind the wpmcp_enable_delete_product filter and needs confirm.
     *
     * The catalog is the deep surface over the store's own wc/v3 REST API:
     * woo-ops lists the named ops, woo-read and woo-write dispatch one
     * in-process. All three carry manage_woocommerce at the ability layer;
     * the dispatchers then enforce per-op governance and the SAME per-op
     * capability split as the simple tools above (edit_shop_orders for
     * orders, notes and refunds, list_users to read customers, edit_users /
     * create_users to write them) before dispatching, so the catalog is never
     * looser than the tool covering the same data. They refuse to dispatch at
     * all when WooCommerce is inactive, returning a structured
     * integration_unavailable error rather than a bare rest_no_route 404.
     * woo-write routes every change to existing state through Safe_Mutation.
     *
     * Issue #195 depth: variation create/delete and bulk updates, coupons
     * (shop_coupon posts, 'post' snapshots) and tax rates (custom tables, the
     * dedicated 'wc_tax_rate' snapshot type). Each delete tool has its own
     * opt-in filter (wpmcp_enable_delete_variation, _coupon, _tax_rate) and
     * needs confirm:true. All free: the WooCommerce listing depends on it.
     */
    private function register_woocommerce_abilities(Registrar $registrar): void
    {
        $list_products           = new List_Products();
        $get_product             = new Get_Product();
        $create_product          = new Create_Product();
        $update_product          = new Update_Product();
        $delete_product          = new Delete_Product();
        $list_product_categories = new List_Product_Categories();
        $list_orders             = new List_Orders();
        $get_order               = new Get_Order();
        $update_order_status     = new Update_Order_Status();
        $add_order_note          = new Add_Order_Note();
        $get_sales_report        = new Get_Sales_Report();
        $list_variations         = new List_Variations();
        $update_variation        = new Update_Variation();
        $list_low_stock          = new List_Low_Stock_Products();

        $registrar->register(new Ability(
            'wpmcp/list-products',
            'free',
            'List WooCommerce products as safe summary rows (id, name, sku, price, stock status), filterable by search, status, type, or category, with paging',
            [
                'type'       => 'object',
                'properties' => [
                    'search'   => [ 'type' => 'string' ],
                    'status'   => [ 'type' => 'string' ],
                    'type'     => [ 'type' => 'string' ],
                    'category' => [ 'type' => 'string' ],
                    'per_page' => [ 'type' => 'integer' ],
                    'page'     => [ 'type' => 'integer' ],
                ],
            ],
            [$list_products, 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-product',
            'free',
            'Read full detail for one WooCommerce product (prices, stock, description, categories, tags)',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'id' ],
            ],
            [$get_product, 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/create-product',
            'free',
            'Create a simple WooCommerce product via the CRUD layer. rollback-operation on the returned operation_id trashes it',
            [
                'type'       => 'object',
                'properties' => [
                    'name'              => [ 'type' => 'string' ],
                    'regular_price'     => [ 'type' => 'string' ],
                    'sale_price'        => [ 'type' => 'string' ],
                    'sku'               => [ 'type' => 'string' ],
                    'description'       => [ 'type' => 'string' ],
                    'short_description' => [ 'type' => 'string' ],
                    'status'            => [ 'type' => 'string' ],
                    'manage_stock'      => [ 'type' => 'boolean' ],
                    'stock_quantity'    => [ 'type' => 'integer' ],
                    'session_id'        => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
                'required'   => [ 'name' ],
            ],
            [$create_product, 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-product',
            'free',
            'Update a WooCommerce product\'s fields (price, stock, description, etc.). stock_status is accepted only while stock is unmanaged and never on a variable product. Snapshotted as a post; rollback-operation restores the prior price and stock exactly',
            [
                'type'       => 'object',
                'properties' => [
                    'id'                => [ 'type' => 'integer' ],
                    'name'              => [ 'type' => 'string' ],
                    'regular_price'     => [ 'type' => 'string' ],
                    'sale_price'        => [ 'type' => 'string' ],
                    'sku'               => [ 'type' => 'string' ],
                    'description'       => [ 'type' => 'string' ],
                    'short_description' => [ 'type' => 'string' ],
                    'status'            => [ 'type' => 'string' ],
                    'manage_stock'      => [ 'type' => 'boolean' ],
                    'stock_quantity'    => [ 'type' => 'integer' ],
                    'stock_status'      => [ 'type' => 'string' ],
                    'session_id'        => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id' ],
            ],
            [$update_product, 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-product',
            'free',
            'Delete a WooCommerce product (trash by default, force for permanent). Off until the wpmcp_enable_delete_product filter opts in; needs confirm:true. Snapshotted: rollback resurrects a force-deleted product at its id with price, stock and terms',
            [
                'type'       => 'object',
                'properties' => [
                    'id'         => [ 'type' => 'integer' ],
                    'confirm'    => [ 'type' => 'boolean' ],
                    'force'      => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'confirm' ],
            ],
            [$delete_product, 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-product-categories',
            'free',
            'List WooCommerce product categories (the product_cat taxonomy) as summary rows (id, name, slug, parent, count)',
            [
                'type'       => 'object',
                'properties' => [
                    'hide_empty' => [ 'type' => 'boolean' ],
                ],
            ],
            [$list_product_categories, 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-orders',
            'free',
            'List WooCommerce orders as safe summary rows (id, number, status, total, currency, date), filterable by status and customer, with paging. HPOS- and CPT-safe',
            [
                'type'       => 'object',
                'properties' => [
                    'status'      => [ 'type' => 'string' ],
                    'customer_id' => [ 'type' => 'integer' ],
                    'per_page'    => [ 'type' => 'integer' ],
                    'page'        => [ 'type' => 'integer' ],
                ],
            ],
            [$list_orders, 'handle'],
            'edit_shop_orders',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-order',
            'free',
            'Read full detail for one WooCommerce order (status, billing email, payment method, line items, customer note). HPOS- and CPT-safe',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'id' ],
            ],
            [$get_order, 'handle'],
            'edit_shop_orders',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-order-status',
            'free',
            'Change a WooCommerce order\'s status, validated against the store\'s registered statuses. Snapshotted via the wc_order object type so rollback-operation restores the prior status exactly. HPOS- and CPT-safe',
            [
                'type'       => 'object',
                'properties' => [
                    'id'         => [ 'type' => 'integer' ],
                    'status'     => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'status' ],
            ],
            [$update_order_status, 'handle'],
            'edit_shop_orders',
            'woocommerce',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/add-order-note',
            'free',
            'Add an internal or customer-facing note to a WooCommerce order. Additive only; nothing to roll back',
            [
                'type'       => 'object',
                'properties' => [
                    'id'            => [ 'type' => 'integer' ],
                    'note'          => [ 'type' => 'string' ],
                    'customer_note' => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'id', 'note' ],
            ],
            [$add_order_note, 'handle'],
            'edit_shop_orders',
            'woocommerce',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-sales-report',
            'free',
            'Read-only sales summary over a date range: order count, gross sales, items sold and top products by quantity. Aggregated over wc_get_orders() (HPOS- and CPT-safe)',
            [
                'type'       => 'object',
                'properties' => [
                    'date_from' => [ 'type' => 'string' ],
                    'date_to'   => [ 'type' => 'string' ],
                ],
            ],
            [$get_sales_report, 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-variations',
            'free',
            'List the variations of one variable WooCommerce product as safe summary rows (id, sku, attributes, prices, stock), with paging',
            [
                'type'       => 'object',
                'properties' => [
                    'product_id' => [ 'type' => 'integer' ],
                    'per_page'   => [ 'type' => 'integer' ],
                    'page'       => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'product_id' ],
            ],
            [$list_variations, 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-variation',
            'free',
            'Update a WooCommerce variation: regular_price, sale_price, sku, status (publish/private), manage_stock, stock_quantity and stock_status (only while stock is unmanaged). Snapshotted; rollback-operation restores price and stock, keeps it attached to its parent and re-syncs the parent\'s price range',
            [
                'type'       => 'object',
                'properties' => [
                    'id'             => [ 'type' => 'integer' ],
                    'regular_price'  => [ 'type' => 'string' ],
                    'sale_price'     => [ 'type' => 'string' ],
                    'sku'            => [ 'type' => 'string' ],
                    'status'         => [ 'type' => 'string' ],
                    'manage_stock'   => [ 'type' => 'boolean' ],
                    'stock_quantity' => [ 'type' => 'integer' ],
                    'stock_status'   => [ 'type' => 'string' ],
                    'session_id'     => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id' ],
            ],
            [$update_variation, 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-low-stock-products',
            'free',
            'List products and variations at or below a managed-stock threshold (default: the store\'s low-stock setting) or out of stock, as rows whose ids feed update-product/update-variation. Page while has_more is true',
            [
                'type'       => 'object',
                'properties' => [
                    'threshold' => [ 'type' => 'integer' ],
                    'per_page'  => [ 'type' => 'integer' ],
                    'page'      => [ 'type' => 'integer' ],
                ],
            ],
            [$list_low_stock, 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/create-variation',
            'free',
            'Add a variation to a variable product. attributes maps each variation attribute to one of its options ("" or omitted = any); unknown attributes or options are refused. Prices, sku, status and stock follow update-variation\'s rules. rollback-operation trashes it',
            [
                'type'       => 'object',
                'properties' => [
                    'product_id' => [ 'type' => 'integer' ],
                    'attributes' => [ 'type' => 'object' ],
                    'description' => [ 'type' => 'string' ],
                    'regular_price' => [ 'type' => 'string' ],
                    'sale_price' => [ 'type' => 'string' ],
                    'sku' => [ 'type' => 'string' ],
                    'status' => [ 'type' => 'string' ],
                    'manage_stock' => [ 'type' => 'boolean' ],
                    'stock_quantity' => [ 'type' => 'integer' ],
                    'stock_status' => [ 'type' => 'string' ],
                    'session_id'   => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
                'required'   => [ 'product_id' ],
            ],
            [new Create_Variation(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-variation',
            'free',
            'Permanently delete a product variation (there is no variation trash). Disabled until the site opts in via the wpmcp_enable_delete_variation filter; needs confirm:true. Snapshotted: rollback-operation resurrects it at the same id, attached to its parent',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                    'confirm' => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'confirm' ],
            ],
            [new Delete_Variation(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/bulk-update-products',
            'free',
            'Update up to 50 products and variations in one call; each item {id, ...fields} as update-product/update-variation take them, same rules and permission checks. Per item: ok, operation_id or error; one failure does not stop the rest. rollback-session on the returned session_id undoes the batch',
            [
                'type'       => 'object',
                'properties' => [
                    'items' => [
                        'type'     => 'array',
                        'maxItems' => 50,
                        'items'    => [
                            'type'       => 'object',
                            'properties' => [ 'id' => [ 'type' => 'integer' ] ],
                            'required'   => [ 'id' ],
                        ],
                    ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'items' ],
            ],
            [new Bulk_Update_Products(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-coupons',
            'free',
            'List coupons as summary rows (id, code, status, type, amount, usage, expiry), with code search, status filter and paging',
            [
                'type'       => 'object',
                'properties' => [
                    'search' => [ 'type' => 'string' ],
                    'status' => [ 'type' => 'string' ],
                    'per_page' => [ 'type' => 'integer' ],
                    'page' => [ 'type' => 'integer' ],
                ],
            ],
            [new List_Coupons(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-coupon',
            'free',
            'Read one coupon\'s full settings by id or code (limits, restrictions, usage)',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                    'code' => [ 'type' => 'string' ],
                ],
            ],
            [new Get_Coupon(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/create-coupon',
            'free',
            'Create a coupon. Refuses a code already in use, an unknown discount_type, a percent over 100 or an invalid email restriction. Status defaults to draft. rollback-operation trashes it',
            [
                'type'       => 'object',
                'properties' => [
                    'code' => [ 'type' => 'string' ],
                    'discount_type' => [ 'type' => 'string' ],
                    'amount' => [ 'type' => [ 'string', 'number' ] ],
                    'description' => [ 'type' => 'string' ],
                    'date_expires' => [ 'type' => [ 'string', 'null' ] ],
                    'individual_use' => [ 'type' => 'boolean' ],
                    'free_shipping' => [ 'type' => 'boolean' ],
                    'exclude_sale_items' => [ 'type' => 'boolean' ],
                    'minimum_amount' => [ 'type' => [ 'string', 'number' ] ],
                    'maximum_amount' => [ 'type' => [ 'string', 'number' ] ],
                    'usage_limit' => [ 'type' => [ 'integer', 'null' ] ],
                    'usage_limit_per_user' => [ 'type' => [ 'integer', 'null' ] ],
                    'limit_usage_to_x_items' => [ 'type' => [ 'integer', 'null' ] ],
                    'product_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'excluded_product_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'product_categories' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'excluded_product_categories' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'email_restrictions' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'status' => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
                'required'   => [ 'code' ],
            ],
            [new Create_Coupon(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-coupon',
            'free',
            'Update a coupon\'s fields with the same checks as create-coupon. Snapshotted: rollback-operation restores every setting and usage history',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                    'code' => [ 'type' => 'string' ],
                    'discount_type' => [ 'type' => 'string' ],
                    'amount' => [ 'type' => [ 'string', 'number' ] ],
                    'description' => [ 'type' => 'string' ],
                    'date_expires' => [ 'type' => [ 'string', 'null' ] ],
                    'individual_use' => [ 'type' => 'boolean' ],
                    'free_shipping' => [ 'type' => 'boolean' ],
                    'exclude_sale_items' => [ 'type' => 'boolean' ],
                    'minimum_amount' => [ 'type' => [ 'string', 'number' ] ],
                    'maximum_amount' => [ 'type' => [ 'string', 'number' ] ],
                    'usage_limit' => [ 'type' => [ 'integer', 'null' ] ],
                    'usage_limit_per_user' => [ 'type' => [ 'integer', 'null' ] ],
                    'limit_usage_to_x_items' => [ 'type' => [ 'integer', 'null' ] ],
                    'product_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'excluded_product_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'product_categories' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'excluded_product_categories' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                    'email_restrictions' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'status' => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id' ],
            ],
            [new Update_Coupon(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-coupon',
            'free',
            'Delete a coupon (trash by default, force for permanent). Disabled until the site opts in via the wpmcp_enable_delete_coupon filter; needs confirm:true. Snapshotted: rollback-operation restores it at the same id',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                    'confirm' => [ 'type' => 'boolean' ],
                    'force' => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'confirm' ],
            ],
            [new Delete_Coupon(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/validate-coupon',
            'free',
            'Check whether a coupon code would be accepted: published, expiry, usage limits, and (when email or subtotal is given) per-customer limit, email allow-list and spend rules. Each rule reports pass, fail or skipped; cart-dependent rules are flagged',
            [
                'type'       => 'object',
                'properties' => [
                    'code' => [ 'type' => 'string' ],
                    'email' => [ 'type' => 'string' ],
                    'subtotal' => [ 'type' => [ 'string', 'number' ] ],
                ],
                'required'   => [ 'code' ],
            ],
            [new Validate_Coupon(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-tax-rates',
            'free',
            'List the store\'s tax classes and tax rates, filterable by class or country, with paging. Read-only',
            [
                'type'       => 'object',
                'properties' => [
                    'class' => [ 'type' => 'string' ],
                    'country' => [ 'type' => 'string' ],
                    'per_page' => [ 'type' => 'integer' ],
                    'page' => [ 'type' => 'integer' ],
                ],
            ],
            [new List_Tax_Rates(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/create-tax-rate',
            'free',
            'Create a tax rate. rate is a percentage from 0 to 100; country must be a known ISO code or "" for all; class must be standard or an existing class. rollback-operation deletes it while unchanged',
            [
                'type'       => 'object',
                'properties' => [
                    'country' => [ 'type' => 'string' ],
                    'state' => [ 'type' => 'string' ],
                    'postcodes' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'cities' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'rate' => [ 'type' => [ 'string', 'number' ] ],
                    'name' => [ 'type' => 'string' ],
                    'priority' => [ 'type' => 'integer' ],
                    'compound' => [ 'type' => 'boolean' ],
                    'shipping' => [ 'type' => 'boolean' ],
                    'order' => [ 'type' => 'integer' ],
                    'class' => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
                'required'   => [ 'rate' ],
            ],
            [new Create_Tax_Rate(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-tax-rate',
            'free',
            'Update a tax rate with the same checks as create-tax-rate; postcodes and cities replace the whole list. Snapshotted: rollback-operation restores the row and its locations',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                    'country' => [ 'type' => 'string' ],
                    'state' => [ 'type' => 'string' ],
                    'postcodes' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'cities' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'rate' => [ 'type' => [ 'string', 'number' ] ],
                    'name' => [ 'type' => 'string' ],
                    'priority' => [ 'type' => 'integer' ],
                    'compound' => [ 'type' => 'boolean' ],
                    'shipping' => [ 'type' => 'boolean' ],
                    'order' => [ 'type' => 'integer' ],
                    'class' => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id' ],
            ],
            [new Update_Tax_Rate(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-tax-rate',
            'free',
            'Delete a tax rate. Disabled until the site opts in via the wpmcp_enable_delete_tax_rate filter; needs confirm:true. Snapshotted: rollback-operation restores it at the same id',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                    'confirm' => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'id', 'confirm' ],
            ],
            [new Delete_Tax_Rate(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/plan-product-import',
            'free',
            'Plan a product import; writes nothing. Rows: sku, name, type (simple/variable), regular_price, sale_price, stock, categories, attributes, variations, images (media ids or https URLs), status. Per row: create, update (diff), skip or error; plus a plan_hash',
            [
                'type'       => 'object',
                'properties' => [
                    'rows' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
                    'mode' => [ 'type' => 'string', 'enum' => [ 'upsert', 'create', 'update' ] ],
                ],
                'required'   => [ 'rows' ],
            ],
            [new Plan_Product_Import(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/apply-product-import',
            'free',
            'Apply a plan-product-import plan: same rows and mode plus its plan_hash. Refused if the store or rows changed; confirm:true above the threshold. rollback-session on its session_id undoes it',
            [
                'type'       => 'object',
                'properties' => [
                    'rows' => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
                    'mode' => [ 'type' => 'string', 'enum' => [ 'upsert', 'create', 'update' ] ],
                    'plan_hash' => [ 'type' => 'string' ],
                    'confirm' => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ], // central-list-ok: #1255 the handler records it
                ],
                'required'   => [ 'rows', 'plan_hash' ],
            ],
            [new Apply_Product_Import(), 'handle'],
            'manage_woocommerce',
            'woocommerce',
            'create'
        ));

        // Deep WooCommerce operations catalog (issue #68). The tools above
        // stay the simple surface; the catalog dispatchers template internal
        // wc/v3 REST routes through their own in-process dispatch
        // (Wc_Rest_Dispatch for reads, Wc_Rest_Write_Dispatch for writes,
        // both free of any dependency on src/Tools/Rest or src/Integrations,
        // which the vertical wpmcp-for-woocommerce build prunes), so
        // authorization is the target endpoint's own permission_callback
        // running as the current user, on top of per-op governance and the
        // per-op capability (Op_Guard). woo-write snapshots every change to
        // existing state through Safe_Mutation, keeps destructive ops off
        // until a site opts in and behind confirm:true, and batches by
        // running each item through the same gates.
        // The handlers are constructed inline so the directory build's strip,
        // which deletes these registrations whole, leaves the catalog classes
        // unreferenced and sweeps them out of that zip.
        $this->edition()->run('abilities.woocommerce.catalog_ops', $this->edition_context($registrar));
        $this->edition()->run('abilities.woocommerce.catalog_read', $this->edition_context($registrar));
        $this->edition()->run('abilities.woocommerce.catalog_write', $this->edition_context($registrar));
    }

    /**
     * Register the navigation menu management tools as free-tier abilities.
     *
     * All require the edit_theme_options capability, WordPress's own gate for
     * managing menus. Reads (list/get menus, list locations) have no side
     * effects. Menu-item edits act on nav_menu_item posts and are undoable via
     * the existing 'post' snapshot type; assign-menu-to-location changes the
     * nav_menu_locations theme_mod and is undoable via the existing 'option'
     * type. delete-menu removes a nav_menu term: it is disabled by default
     * behind the wpmcp_enable_delete_menu filter, needs confirm, and is honest
     * that it cannot be rolled back automatically.
     */
    /**
     * One-call declarative page composition (issue #57). This build
     * composes block editor (Gutenberg) pages; the Elementor builder
     * dialect ships with the off-directory add-on.
     */
    private function register_compose_abilities(Registrar $registrar): void
    {
        $build_page = new \WPMCP\Tools\Compose\Build_Page();

        $node_schema = [
            'type'        => 'object',
            'description' => 'One node of the recursive sections tree. Gutenberg dialect types: group, columns, column, buttons (containers, may have children); heading{text,level}, paragraph{text}, list{items,ordered}, quote{text,citation}, image{attachment_id|url,alt}, button{text,url}, separator, spacer{height}, code{text}, html{html}, pattern{slug, top-level only} (leaves).',
            'properties'  => [
                'type'     => [ 'type' => 'string' ],
                'settings' => [ 'type' => 'object' ],
                'children' => [ 'type' => 'array', 'items' => [ '$ref' => '#/properties/spec/properties/content/items' ] ],
            ],
            'required'    => [ 'type' ],
        ];

        $registrar->register(new Ability(
            'wpmcp/build-page',
            'free',
            'Compose a page from ONE declarative spec: title, a recursive sections/blocks tree, media (existing attachment ids), optional menu placement. One atomic, recoverable operation: the spec is strictly validated (node-path errors, bounded size/nodes/depth) before any write, a mid-build failure removes everything it created, and success returns one operation_id whose rollback-operation removes the page and its menu placement. Markup is composed deterministically; nothing in the spec is evaluated or executed. The spec composes block editor (Gutenberg) markup. dry_run=true validates and composes WITHOUT writing, reporting element counts per node type, nesting depth, markup size and every referential problem at once',
            [
                'type'       => 'object',
                'properties' => [
                    'spec' => [
                        'type'       => 'object',
                        'properties' => [
                            'title'   => [ 'type' => 'string' ],
                            'status'  => [ 'type' => 'string', 'enum' => [ 'draft', 'publish' ] ],
                            'slug'    => [ 'type' => 'string' ],
                            'dialect' => [ 'type' => 'string', 'enum' => [ 'gutenberg' ] ],
                            'content' => [ 'type' => 'array', 'items' => $node_schema ],
                            'media'   => [
                                'type'       => 'object',
                                'properties' => [ 'featured' => [ 'type' => 'integer' ] ],
                            ],
                            'menu'    => [
                                'type'       => 'object',
                                'properties' => [
                                    'menu_id'  => [ 'type' => 'integer' ],
                                    'title'    => [ 'type' => 'string' ],
                                    'position' => [ 'type' => 'integer' ],
                                    'parent'   => [ 'type' => 'integer' ],
                                ],
                                'required'   => [ 'menu_id' ],
                            ],
                        ],
                        'required'   => [ 'title', 'content' ],
                    ],
                    'dry_run'    => [ 'type' => 'boolean' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'spec' ],
            ],
            [$build_page, 'handle'],
            'edit_posts',
            'content',
            'create'
        ));
    }

    private function register_menu_abilities(Registrar $registrar): void
    {
        $list_menus              = new List_Menus();
        $get_menu                = new Get_Menu();
        $list_menu_locations     = new List_Menu_Locations();
        $create_menu             = new Create_Menu();
        $add_menu_item           = new Add_Menu_Item();
        $update_menu_item        = new Update_Menu_Item();
        $remove_menu_item        = new Remove_Menu_Item();
        $assign_menu_to_location = new Assign_Menu_To_Location();
        $delete_menu             = new Delete_Menu();

        $registrar->register(new Ability(
            'wpmcp/list-menus',
            'free',
            'List the site\'s navigation menus as safe summary rows (id, name, slug, item count)',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$list_menus, 'handle'],
            'edit_theme_options',
            'menus',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-menu',
            'free',
            'Read one navigation menu with its ordered items (id, title, url, type, parent, order)',
            [
                'type'       => 'object',
                'properties' => [
                    'id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'id' ],
            ],
            [$get_menu, 'handle'],
            'edit_theme_options',
            'menus',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/list-menu-locations',
            'free',
            'List the theme\'s registered menu locations and the menu (if any) assigned to each',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$list_menu_locations, 'handle'],
            'edit_theme_options',
            'menus',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/create-menu',
            'free',
            'Create a navigation menu (a nav_menu term). Undoable while unchanged, empty and in no location',
            [
                'type'       => 'object',
                'properties' => [
                    'name'       => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'name' ],
            ],
            [$create_menu, 'handle'],
            'edit_theme_options',
            'menus',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/add-menu-item',
            'free',
            'Add an item to a navigation menu (custom link by title and url, or an object link via type, object, object_id). Undoable',
            [
                'type'       => 'object',
                'properties' => [
                    'menu_id'    => [ 'type' => 'integer' ],
                    'title'      => [ 'type' => 'string' ],
                    'url'        => [ 'type' => 'string' ],
                    'parent'     => [ 'type' => 'integer' ],
                    'position'   => [ 'type' => 'integer' ],
                    'type'       => [ 'type' => 'string' ],
                    'object'     => [ 'type' => 'string' ],
                    'object_id'  => [ 'type' => 'integer' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'menu_id' ],
            ],
            [$add_menu_item, 'handle'],
            'edit_theme_options',
            'menus',
            'create'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-menu-item',
            'free',
            'Update a navigation menu item\'s title, url, parent, or position. Snapshotted as a post; rollback-operation restores the prior values exactly',
            [
                'type'       => 'object',
                'properties' => [
                    'item_id'    => [ 'type' => 'integer' ],
                    'title'      => [ 'type' => 'string' ],
                    'url'        => [ 'type' => 'string' ],
                    'parent'     => [ 'type' => 'integer' ],
                    'position'   => [ 'type' => 'integer' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'item_id' ],
            ],
            [$update_menu_item, 'handle'],
            'edit_theme_options',
            'menus',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/remove-menu-item',
            'free',
            'Remove an item from a navigation menu. Snapshotted as a post; rollback-operation resurrects it at its original id, re-attached to its menu',
            [
                'type'       => 'object',
                'properties' => [
                    'item_id'    => [ 'type' => 'integer' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'item_id' ],
            ],
            [$remove_menu_item, 'handle'],
            'edit_theme_options',
            'menus',
            'delete'
        ));
        $registrar->register(new Ability(
            'wpmcp/assign-menu-to-location',
            'free',
            'Assign a navigation menu to a registered theme location (the nav_menu_locations theme_mod). Snapshotted; rollback-operation restores the prior assignment',
            [
                'type'       => 'object',
                'properties' => [
                    'menu_id'    => [ 'type' => 'integer' ],
                    'location'   => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'menu_id', 'location' ],
            ],
            [$assign_menu_to_location, 'handle'],
            'edit_theme_options',
            'menus',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/delete-menu',
            'free',
            'Delete a navigation menu (a nav_menu term). Off until the wpmcp_enable_delete_menu filter opts in; needs confirm:true. Not automatically reversible: the menu name and items are returned for a manual rebuild',
            [
                'type'       => 'object',
                'properties' => [
                    'id'      => [ 'type' => 'integer' ],
                    'confirm' => [ 'type' => 'boolean' ],
                ],
                'required'   => [ 'id', 'confirm' ],
            ],
            [$delete_menu, 'handle'],
            'edit_theme_options',
            'menus',
            'delete'
        ));
    }

    /**
     * Register the ACF (Advanced Custom Fields) tools as free-tier abilities.
     *
     * Registered conditionally, gated on function_exists('acf_get_field_groups'),
     * unlike WooCommerce and Elementor's tool groups (which register
     * unconditionally and degrade at call time): ACF has no free/pro split of
     * its own to key off, so absence of the plugin is the only signal, and
     * skipping registration entirely keeps these abilities out of the
     * catalog on sites that don't run ACF at all.
     *
     * update-fields is disabled by default via the wpmcp_enable_acf_write
     * filter (checked inside Update_Fields::handle()); the ability itself is
     * still registered so a caller can discover it and see why it refuses.
     */
    private function register_acf_abilities(Registrar $registrar): void
    {
        if (! function_exists('acf_get_field_groups')) {
            return;
        }

        $list_field_groups = new List_Field_Groups();
        $get_fields        = new Get_Fields();
        $update_fields     = new Update_Fields();

        $registrar->register(new Ability(
            'wpmcp/list-field-groups',
            'free',
            'List registered ACF (Advanced Custom Fields) field groups: key, title, flattened location rules and whether each is active',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$list_field_groups, 'handle'],
            'edit_posts',
            'acf',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-fields',
            'free',
            'Read a post\'s ACF field values, keyed by field name, via get_fields()',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$get_fields, 'handle'],
            'edit_posts',
            'acf',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-fields',
            'free',
            'Set one or more ACF field values on a post via update_field(). Snapshotted as a post; rollback-operation restores the prior values exactly. Disabled by default (site must opt in via the wpmcp_enable_acf_write filter)',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'    => [ 'type' => 'integer' ],
                    'fields'     => [ 'type' => 'object' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'post_id', 'fields' ],
            ],
            [$update_fields, 'handle'],
            'edit_posts',
            'acf',
            'update'
        ));
    }

    /**
     * Register the SEO tools as free-tier abilities.
     *
     * get-seo-status is registered unconditionally: it must be reachable to
     * report "no SEO plugin active" at all, and it does not touch any
     * plugin-specific postmeta so it has nothing to degrade. get-seo-meta and
     * update-seo-meta are registered conditionally on SEO_Adapter detecting a
     * supported plugin, following the same conditional-registration pattern
     * as the ACF tool group: no supported plugin has a free/pro split of its
     * own to key off, so plugin absence is the only signal, and skipping
     * keeps these out of the catalog on sites running none of them.
     */
    private function register_seo_abilities(Registrar $registrar): void
    {
        $get_seo_status = new Get_SEO_Status();

        $registrar->register(new Ability(
            'wpmcp/get-seo-status',
            'free',
            'Which SEO plugin is active, by name and version',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$get_seo_status, 'handle'],
            'edit_posts',
            'seo',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/get-crawler-files',
            'free',
            'robots.txt (served output, physical file, managed rules), llms.txt, and the served sitemap (core or SEO plugin) with its post types',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [new Get_Crawler_Files(), 'handle'],
            'edit_posts',
            'seo',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/update-crawler-files',
            'free',
            'Add robots.txt rules per User-agent, build llms.txt, or exclude post types, taxonomies or posts from the core sitemap (lists replace). Refused if a file or SEO plugin owns it. Snapshotted',
            [
                'type'       => 'object',
                'properties' => [
                    'robots_rules' => [ 'type' => 'string' ],
                    'user_agent'   => [ 'type' => 'string' ],
                    'llms'         => [
                        'type'       => 'object',
                        'properties' => [
                            'enabled' => [ 'type' => 'boolean' ],
                            'title'   => [ 'type' => 'string' ],
                            'summary' => [ 'type' => 'string' ],
                            'pages'   => [ 'type' => 'array', 'items' => [ 'type' => 'object' ] ],
                        ],
                    ],
                    'sitemap'      => [
                        'type'       => 'object',
                        'properties' => [
                            'exclude_post_types' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                            'exclude_taxonomies' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                            'exclude_post_ids'   => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
                        ],
                    ],
                    'session_id'   => [ 'type' => 'string' ],
                ],
            ],
            [new Update_Crawler_Files(), 'handle'],
            'manage_options',
            'seo',
            'update'
        ));

        $this->edition()->run('abilities.seo_pro', $this->edition_context($registrar));

        if ('' === SEO_Adapter::active_plugin()) {
            return;
        }

        $get_seo_meta    = new Get_SEO_Meta();
        $update_seo_meta = new Update_SEO_Meta();

        $registrar->register(new Ability(
            'wpmcp/get-seo-meta',
            'free',
            'Read a post\'s SEO title, meta description, focus keyword, canonical URL, and robots flags (noindex/nofollow) via the active SEO plugin\'s postmeta keys',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$get_seo_meta, 'handle'],
            'edit_posts',
            'seo',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/update-seo-meta',
            'free',
            'Set a post\'s SEO title, meta description, focus keyword, canonical URL and/or robots flags (noindex/nofollow) via the active SEO plugin\'s postmeta keys. Snapshotted as a post; rollback-operation restores the prior values exactly',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'       => [ 'type' => 'integer' ],
                    'title'         => [ 'type' => 'string' ],
                    'description'   => [ 'type' => 'string' ],
                    'focus_keyword' => [ 'type' => 'string' ],
                    'canonical'     => [ 'type' => 'string' ],
                    'noindex'       => [ 'type' => 'boolean' ],
                    'nofollow'      => [ 'type' => 'boolean' ],
                    'session_id'    => [ 'type' => 'string' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$update_seo_meta, 'handle'],
            'edit_posts',
            'seo',
            'update'
        ));
    }

    /**
     * Register the multilingual (i18n) tools as free-tier abilities.
     *
     * All four are registered only when I18n_Adapter detects an active
     * multilingual plugin (Polylang or WPML), following the same
     * conditional-registration pattern as the ACF and SEO tool groups:
     * neither plugin has a free/pro split of its own to key off, so plugin
     * absence is the only signal, and skipping keeps these tools out of the
     * catalog on sites running no multilingual plugin. They share the
     * 'translation' domain.
     */
    private function register_i18n_abilities(Registrar $registrar): void
    {
        if ('' === I18n_Adapter::active_plugin()) {
            return;
        }

        $list_languages         = new List_Languages();
        $get_post_translations  = new Get_Post_Translations();
        $set_post_language      = new Set_Post_Language();
        $link_post_translations = new Link_Post_Translations();

        $registrar->register(new Ability(
            'wpmcp/list-languages',
            'free',
            'List the site\'s languages (code, name, which is default) via the active multilingual plugin (Polylang or WPML)',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$list_languages, 'handle'],
            'edit_posts',
            'translation',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-post-translations',
            'free',
            'Read a post\'s translations (the translated post id and title, keyed by language code) via the active multilingual plugin (Polylang or WPML)',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id' => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$get_post_translations, 'handle'],
            'edit_posts',
            'translation',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/set-post-language',
            'free',
            'Assign a post to a language (by code) via the active multilingual plugin (Polylang or WPML). Snapshotted as a post (a Polylang language is a term); rollback-operation restores the prior language assignment exactly',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'    => [ 'type' => 'integer' ],
                    'language'   => [ 'type' => 'string' ],
                    'session_id' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'post_id', 'language' ],
            ],
            [$set_post_language, 'handle'],
            'edit_posts',
            'translation',
            'update'
        ));
        $registrar->register(new Ability(
            'wpmcp/link-post-translations',
            'free',
            'Link posts as translations of one another from {language, post_id} pairs via Polylang or WPML. Only the primary (first) post is snapshotted, so rollback restores that post, not the others',
            [
                'type'       => 'object',
                'properties' => [
                    'translations' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'language' => [ 'type' => 'string' ],
                                'post_id'  => [ 'type' => 'integer' ],
                            ],
                            'required'   => [ 'language', 'post_id' ],
                        ],
                    ],
                    'session_id'   => [ 'type' => 'string' ],
                ],
                'required'   => [ 'translations' ],
            ],
            [$link_post_translations, 'handle'],
            'edit_posts',
            'translation',
            'update'
        ));
    }

    /**
     * Register the internal-linking analysis tools as free-tier abilities.
     *
     * All three are read-only: they build the internal-link graph from a
     * bounded set of published posts and report on it, never writing anything,
     * so none touch the safety core. They share domain 'seo' and operation
     * 'read', so their read_only_hint annotation derives to true automatically.
     */
    private function register_linking_abilities(Registrar $registrar): void
    {
        $find_orphan_posts      = new Find_Orphan_Posts();
        $suggest_internal_links = new Suggest_Internal_Links();
        $get_link_map           = new Get_Link_Map();

        $registrar->register(new Ability(
            'wpmcp/find-orphan-posts',
            'free',
            'List published posts or pages that have zero incoming internal links (orphans), by scanning the most-recent posts for links that resolve to this site\'s own content',
            [
                'type'       => 'object',
                'properties' => [
                    'post_type' => [ 'type' => 'string' ],
                    'limit'     => [ 'type' => 'integer' ],
                    'cap'       => [ 'type' => 'integer' ],
                ],
            ],
            [$find_orphan_posts, 'handle'],
            'edit_posts',
            'seo',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/suggest-internal-links',
            'free',
            'Suggest related published posts a given post should link to, ranked by shared categories/tags and title keyword overlap, excluding posts it already links to',
            [
                'type'       => 'object',
                'properties' => [
                    'post_id'   => [ 'type' => 'integer' ],
                    'post_type' => [ 'type' => 'string' ],
                    'limit'     => [ 'type' => 'integer' ],
                    'cap'       => [ 'type' => 'integer' ],
                ],
                'required'   => [ 'post_id' ],
            ],
            [$suggest_internal_links, 'handle'],
            'edit_posts',
            'seo',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-link-map',
            'free',
            'Summarize the internal-link graph: per-post outgoing and incoming link counts, the orphan list and the most-linked posts',
            [
                'type'       => 'object',
                'properties' => [
                    'post_type' => [ 'type' => 'string' ],
                    'limit'     => [ 'type' => 'integer' ],
                    'cap'       => [ 'type' => 'integer' ],
                ],
            ],
            [$get_link_map, 'handle'],
            'edit_posts',
            'seo',
            'read'
        ));
    }

    /**
     * The redirect manager (issue #128): CRUD over the managed redirect table
     * plus the broken-link scanner. Domain 'seo', so the existing governance
     * domain toggle that covers the linking tools covers these too.
     *
     * Reads are edit_posts (an editor should be able to see why a URL bounces
     * and which links are dead); the three writes are manage_options, because
     * a redirect changes site-wide routing for every visitor. Every write
     * runs through Safe_Mutation with object_type 'redirect' and is undoable
     * with rollback-operation.
     *
     * There is no create-redirect-from-suggestion tool: deleting a post or
     * moving a published URL only ever emits a suggested_redirect in its own
     * response, and turning that into a live redirect always takes an
     * explicit create-redirect call.
     */
    private function register_redirect_abilities(Registrar $registrar): void
    {
        $registrar->register(new Ability(
            'wpmcp/list-redirects',
            'free',
            'List this site\'s redirects (the active redirect plugin\'s, else WPMCP\'s own: source path, resolved target, status code, enabled, hit count), plus pending suggestions raised when a published post was deleted or moved. Filter by enabled state or a search string. Read-only',
            [
                'type'       => 'object',
                'properties' => [
                    'enabled'  => [ 'type' => 'boolean' ],
                    'search'   => [ 'type' => 'string' ],
                    'limit'    => [ 'type' => 'integer' ],
                    'offset'   => [ 'type' => 'integer' ],
                    'provider' => [ 'type' => 'string', 'description' => Redirect_Router::PROVIDER_HINT ],
                ],
            ],
            [new List_Redirects(), 'handle'],
            'edit_posts',
            'seo',
            'read'
        ));

        $registrar->register(new Ability(
            'wpmcp/create-redirect',
            'free',
            'Create a redirect (in the active redirect plugin, else WPMCP\'s own table) from a source path to a target URL or target_post_id (which survives slug changes). Chains are flattened to one hop and loops refused; the stored target is reported. Snapshotted; rollback-operation removes it',
            [
                'type'       => 'object',
                'properties' => [
                    'source'         => [ 'type' => 'string' ],
                    'target'         => [ 'type' => 'string' ],
                    'target_post_id' => [ 'type' => 'integer' ],
                    'status_code'    => [ 'type' => 'integer', 'enum' => Redirect_Store::ALLOWED_STATUS_CODES ],
                    'enabled'        => [ 'type' => 'boolean' ],
                    'notes'          => [ 'type' => 'string' ],
                    'provider'       => [ 'type' => 'string', 'description' => Redirect_Router::PROVIDER_HINT ],
                    'session_id'     => [ 'type' => 'string' ],
                ],
                'required'   => [ 'source' ],
            ],
            [new Create_Redirect(), 'handle'],
            'manage_options',
            'seo',
            'create'
        ));

        $registrar->register(new Ability(
            'wpmcp/update-redirect',
            'free',
            'Change one redirect: its source, target/target_post_id, status code, enabled state or notes. Only the fields you pass change. Retargeting re-runs chain flattening and loop detection. Snapshotted and reversible',
            [
                'type'       => 'object',
                'properties' => [
                    'redirect_id'    => [ 'type' => 'integer' ],
                    'source'         => [ 'type' => 'string' ],
                    'target'         => [ 'type' => 'string' ],
                    'target_post_id' => [ 'type' => 'integer' ],
                    'status_code'    => [ 'type' => 'integer', 'enum' => Redirect_Store::ALLOWED_STATUS_CODES ],
                    'enabled'        => [ 'type' => 'boolean' ],
                    'notes'          => [ 'type' => 'string' ],
                    'provider'       => [ 'type' => 'string', 'description' => Redirect_Router::PROVIDER_HINT ],
                    'session_id'     => [ 'type' => 'string' ],
                ],
                'required'   => [ 'redirect_id' ],
            ],
            [new Update_Redirect(), 'handle'],
            'manage_options',
            'seo',
            'update'
        ));

        $registrar->register(new Ability(
            'wpmcp/delete-redirect',
            'free',
            'Delete one redirect. The whole row is snapshotted first, so rollback-operation brings the same redirect back; use update-redirect with enabled:false instead to stop it firing while keeping its hit history',
            [
                'type'       => 'object',
                'properties' => [
                    'redirect_id' => [ 'type' => 'integer' ],
                    'provider'    => [ 'type' => 'string', 'description' => Redirect_Router::PROVIDER_HINT ],
                    'confirm'     => [ 'type' => 'boolean' ],
                    'session_id'  => [ 'type' => 'string' ],
                ],
                'required'   => [ 'redirect_id' ],
            ],
            [new Delete_Redirect(), 'handle'],
            'manage_options',
            'seo',
            'delete'
        ));

        $registrar->register(new Ability(
            'wpmcp/find-broken-links',
            'free',
            'Scan published content for internal links that are dead, point at a non-public post or go through a redirect. background:true queues a batched scan; poll it with scan_id. Proposes fixes, changes no content',
            [
                'type'       => 'object',
                'properties' => [
                    'post_types' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
                    'limit'      => [ 'type' => 'integer' ],
                    'background' => [ 'type' => 'boolean' ],
                    'batch_size' => [ 'type' => 'integer' ],
                    'scan_id'    => [ 'type' => 'integer' ],
                ],
            ],
            [new Find_Broken_Links(), 'handle'],
            'edit_posts',
            'seo',
            'read',
            // Not read-only (issue #420): background:true stores a scan and schedules cron.
            false,
            false,
            false
        ));
    }

    /**
     * Connection-info tooling for the admin connection area (issue #18).
     * get-connection-info is read-only and returns only a placeholder
     * Authorization value, never a real credential, so it needs no
     * Safe_Mutation snapshot/rollback and does not touch the safety core.
     * Gated at manage_options since it exposes this site's MCP endpoint URL
     * and connection instructions, matching the admin-only trust level of
     * the other introspection tools (list-rest-routes, get-cache-status).
     */
    private function register_connect_abilities(Registrar $registrar): void
    {
        $get_connection_info = new Get_Connection_Info();

        $registrar->register(new Ability(
            'wpmcp/get-connection-info',
            'free',
            'How to connect an MCP client here: the MCP endpoint URL, ready-to-paste snippets for Claude Code, Cursor, Claude Desktop and Antigravity with an Application Password placeholder, and the ChatGPT and Claude connector steps. Never returns a real credential. Read-only',
            [
                'type'       => 'object',
                'properties' => [],
            ],
            [$get_connection_info, 'handle'],
            'manage_options',
            'connect',
            'read'
        ));

        $list_tool_catalog = new List_Tool_Catalog();

        $registrar->register(new Ability(
            'wpmcp/list-tool-catalog',
            'free',
            'List abilities by domain: tier, operation, capability, read-only/destructive hints, counts. Filters: domain, tier, search (name, description or op name; op hits in matched_operations). Read-only',
            [
                'type'       => 'object',
                'properties' => [
                    'domain' => [ 'type' => 'string' ],
                    'tier'   => [ 'type' => 'string' ],
                    'search' => [ 'type' => 'string' ],
                ],
            ],
            [$list_tool_catalog, 'handle'],
            'manage_options',
            'connect',
            'read'
        ));
    }

    /**
     * The compact-surface meta-tools (issue #79), registered UNCONDITIONALLY
     * in both exposure modes: compact mode is exposure-only, so the
     * registered ability surface - and with it the ability-manifest drift
     * guard - never varies with the mode. In full mode these three simply
     * ride along as ordinary tools; in compact mode they ARE the surface.
     *
     * call-tool is deliberately classified domain=dispatch, operation=update
     * with explicit destructive annotations: it proxies writes and deletes,
     * so it must not advertise itself as read-only, and Governance/identity
     * narrowing applies to the shell like any other ability (a scoped
     * identity that should dispatch must include domain 'dispatch' and
     * operation 'update'; AND-of-narrowing, no special bypass). The REAL
     * authorization decision for a dispatched call is made by the target
     * ability's own permission callback - see Call_Tool's docblock, and the
     * call-rest precedent for a gateway tool whose floor capability is
     * edit_posts while every target enforces its own gate.
     */
    private function register_dispatch_abilities(Registrar $registrar): void
    {
        $list_tools      = new List_Tools();
        $get_tool_schema = new Get_Tool_Schema();
        $call_tool       = new Call_Tool();

        $registrar->register(new Ability(
            'wpmcp/list-tools',
            'free',
            'List tools: name, summary, domain, operation, tier. Filters: domain, search. full:true adds descriptions, annotations; include_unavailable:true, what cannot run and why. Schemas: get-tool-schema. Read-only',
            [
                'type'       => 'object',
                'properties' => [
                    'domain' => [ 'type' => 'string' ],
                    'full'   => [ 'type' => 'boolean' ],
                    'search' => [ 'type' => 'string' ],
                    'include_unavailable' => [ 'type' => 'boolean' ],
                ],
            ],
            [$list_tools, 'handle'],
            'edit_posts',
            'dispatch',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-tool-schema',
            'free',
            'One tool\'s contract by name: input schema, description, annotations, domain/operation/tier, availability. Read-only; list-tools finds names',
            [
                'type'       => 'object',
                'properties' => [
                    'name' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'name' ],
            ],
            [$get_tool_schema, 'handle'],
            'edit_posts',
            'dispatch',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/call-tool',
            'free',
            'Invoke any wpmcp tool by name with an arguments object (reaches tools compact mode hides). The target\'s own checks (capability, governance, identity scope, license, rate limit, input validation, snapshot/rollback) apply as if called directly, never widening access. Refuses non-wpmcp tools and the meta-tools',
            [
                'type'       => 'object',
                'properties' => [
                    'name'      => [ 'type' => 'string' ],
                    'arguments' => [ 'type' => 'object' ],
                ],
                'required'   => [ 'name' ],
            ],
            [$call_tool, 'handle'],
            'edit_posts',
            'dispatch',
            'update',
            false,
            true,
            false
        ));
    }

    /**
     * The third-party ability bridge (issue #194), registered like the
     * dispatch meta-tools: three shells that discover and invoke abilities
     * OTHER plugins registered through the Abilities API, without a second
     * MCP plugin and without ever widening access. The whole surface sits
     * behind Bridge_Guard's default-off opt-in
     * (WPMCP_ENABLE_ABILITY_BRIDGE / wpmcp_enable_ability_bridge), and a
     * bridged invocation always runs the target ability's own
     * permission_callback; there is no bypass path, filter or setting.
     *
     * Bridged abilities are never added to tools/list; discovery goes
     * through list-site-abilities and execution through
     * execute-site-ability, the same compact-mode pattern as call-tool.
     * execute-site-ability carries explicit destructive annotations for the
     * same reason call-tool does: it proxies writes we did not author, and
     * nothing bridged carries the snapshot/rollback guarantee.
     */
    private function register_bridge_abilities(Registrar $registrar): void
    {
        $list_site_abilities  = new List_Site_Abilities();
        $get_site_ability     = new Get_Site_Ability();
        $execute_site_ability = new Execute_Site_Ability();

        $registrar->register(new Ability(
            'wpmcp/list-site-abilities',
            'free',
            'Abilities OTHER plugins register via the Abilities API: name, summary, owning plugin, whether an input schema exists and reversible:false (bridged results are outside wpmcp rollback). Optional plugin filter. Needs the ability bridge opt-in (default off)',
            [
                'type'       => 'object',
                'properties' => [
                    'plugin' => [ 'type' => 'string' ],
                ],
            ],
            [$list_site_abilities, 'handle'],
            'edit_posts',
            'bridge',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/get-site-ability',
            'free',
            'Read one third-party ability\'s full contract by name: description, input and output schemas, meta where provided, and owning plugin. Refuses wpmcp\'s own abilities (use get-tool-schema). Read-only; list-site-abilities finds names',
            [
                'type'       => 'object',
                'properties' => [
                    'name' => [ 'type' => 'string' ],
                ],
                'required'   => [ 'name' ],
            ],
            [$get_site_ability, 'handle'],
            'edit_posts',
            'bridge',
            'read'
        ));
        $registrar->register(new Ability(
            'wpmcp/execute-site-ability',
            'free',
            'Invoke one third-party ability by name with an arguments object. Its own permission callback always runs, plus wpmcp governance, identity scope and rate limiting. Results are reversible:false (bridged writes are outside wpmcp rollback). Refuses wpmcp\'s own abilities; needs the site bridge opt-in (default off)',
            [
                'type'       => 'object',
                'properties' => [
                    'name'      => [ 'type' => 'string' ],
                    'arguments' => [ 'type' => 'object' ],
                ],
                'required'   => [ 'name' ],
            ],
            [$execute_site_ability, 'handle'],
            'edit_posts',
            'bridge',
            'update',
            false,
            true,
            false
        ));
    }

    private ?\WPMCP\Kernel\Kernel_Boot $kernel = null;

    /** The kernel boot (D11): the compiled module registry and what modules contribute. */
    public function kernel(): \WPMCP\Kernel\Kernel_Boot // central-list-ok: D11 kernel boot, one accessor
    {
        return $this->kernel ??= new \WPMCP\Kernel\Kernel_Boot(); // central-list-ok: D11 kernel boot
    }
}
