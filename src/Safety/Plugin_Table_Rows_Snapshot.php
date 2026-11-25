<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps -- WP-style snake_case class name is intentional (matches the rest of WPMCP\Safety).
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps -- WP-style snake_case method names are intentional (matches the rest of WPMCP\Safety).

namespace WPMCP\Safety;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Snapshots of rows in a host plugin's own table, keyed by the integer `id`
 * column (issue #299): a Pods table-storage row (the `pods_<pod>` table,
 * whose id is the post id) or TranslatePress dictionary rows (a
 * `trp_dictionary_<default>_<language>` table). The key is
 * "<kind>:<suffix>:<id>[,<id>...]", and the table is always rebuilt from the
 * site prefix, the kind's own prefix and a suffix of [a-z0-9_-] only, so a
 * snapshot can never name any other table.
 *
 * Every captured row is kept verbatim (NULLs included), so the restore puts
 * back exactly that set: it deletes every row under the captured ids and
 * re-inserts what was there. A row the write inserted is removed again, and
 * a row the write changed returns in place. Not db_rows: that type restores
 * only rows a WHERE matched, so it cannot undo an insert.
 *
 * MemberPress rule access conditions (issue #668, the
 * `mepr_rule_access_conditions` table) are keyed by the rule they belong to
 * (`rule_id`) rather than by their own id, so a snapshot taken before a
 * write covers the rows the write inserts as well: the restore removes every
 * row of the captured rules and puts back the ones that were there. That
 * kind allows the one suffix, "conditions", so it can never name another
 * MemberPress table.
 *
 * Rank Math redirections (issue #681) are the rows of its
 * `rank_math_redirections` table (the one suffix, "redirections"), keyed by
 * id. The lookup cache table beside it is derived data: after a restore the
 * cache rows of the captured ids are purged through Rank Math's own cache
 * class, so the next request rebuilds them. Restoring takes manage_options,
 * the capability the pack's writes need.
 *
 * Wordfence (issue #577) keeps its state in network-wide tables named the
 * way Wordfence names them (the network prefix, lowercased when Wordfence's
 * own wordfence_case option says so). Two kinds cover them, each with the one
 * suffix "network": scan issues (`wfIssues`) keyed by id, and IP blocks
 * (`wfBlocks7`) keyed by the 16-byte `IP` column, so a snapshot taken before
 * a block covers the row Wordfence inserts and one taken before an unblock
 * covers every row it deletes. A binary column cannot ride in the JSON
 * snapshot as raw bytes, so its key and its values are kept as lowercase
 * hex and turned back into bytes in SQL (UNHEX) on restore. After a block
 * restore, Wordfence's firewall is told to resync its copy of the blocks,
 * and after an issue restore its scan notice is recounted, the way its own
 * screens do after the same change.
 *
 * Pretty Links links (issue #682, the `prli_links` table, the one suffix
 * "links") are keyed by their own id. A create reserves its id before the
 * snapshot, so the restore removes the row the write inserted.
 *
 * WP Go Maps markers (issue #907, the `wpgmza` table, kind wpgmza with the
 * one suffix "markers") are keyed by their own id. Their latlng POINT column
 * rides as lowercase hex of its stored bytes and goes back with UNHEX(), so
 * the restore puts the geometry back byte for byte. A create reserves its id
 * before the snapshot, so the restore removes the row the write inserted.
 *
 * FileBird media folders (issue #906) are two kinds, each with one fixed
 * table: folders (`fbv`, the table {prefix}fbv itself, suffix "fbv") keyed
 * by id, and folder assignments (`fbv_attachment`, the
 * {prefix}fbv_attachment_folder table, suffix "attachment_folder") keyed by
 * `attachment_id`, so a snapshot taken before a write covers every folder
 * row of those attachments, the ones the write inserts included. The
 * assignment table has no id column, so its rows are read in attachment
 * order.
 *
 * Smash Balloon Instagram Feed (issue #902) keeps its feeds in
 * `sbi_feeds` and their fetched and rendered data in `sbi_feed_caches`,
 * both keyed by id (kind sbi, suffixes "feeds" and "feed_caches" only, so
 * its `sbi_sources` table, which holds access tokens, can never be
 * captured). After a restore the plugin's object cache entry for each
 * restored feed is dropped, so the next page view reads the rows back.
 *
 * Discount Rules for WooCommerce rules (issue #901, the `wdr_rules` table,
 * the one suffix "rules") are keyed by their own id the same way; restoring
 * one takes manage_woocommerce, the plugin's own capability.
 *
 * Thrive Ultimatum (issue #828) keeps a campaign's timeline
 * (`tve_ult_events`, kind tve_ult_events, suffix "events") and its display
 * settings (`tve_ult_settings_campaign`, kind tve_ult_display, suffix
 * "settings_campaign") in tables of its own, keyed here by the campaign they
 * belong to (`campaign_id`), so a snapshot taken before a write covers the
 * rows it inserts and the ones it deletes. Restoring takes manage_options.
 *
 * Thrive Leads (issue #825) keeps a lead group's display rules as one row of
 * `tve_leads_group_options` (kind tve_leads_options, the one suffix
 * "group_options"), keyed by the `group` column because Thrive Leads saves
 * them by deleting the group's row and inserting a new one, and a form
 * design as a row of `tve_leads_form_variations` (kind tve_leads_variations,
 * the one suffix "form_variations"), keyed and ordered by its `key` column.
 * Restoring either takes manage_options or Thrive Leads' own tve-use-tl.
 *
 * Paid Memberships Pro (issue #667) keeps levels, memberships and discount
 * codes in tables of its own, each kind with its one suffix: levels
 * (`pmpro_membership_levels`) and discount codes (`pmpro_discount_codes`)
 * by id, and a member's membership rows (`pmpro_memberships_users`) by
 * user_id, so a snapshot taken before a level change covers the row PMPro
 * inserts as well as the ones it ends. A level's group assignment
 * (`pmpro_membership_levels_groups`, by level) and a code's level pricing
 * (`pmpro_discount_codes_levels`, by code_id) are companion rows: captured
 * and put back with their parent rows, when their table exists. After a
 * membership restore PMPro's cached levels of each captured user are
 * dropped, so its next read sees the restored rows.
 *
 * wpDataTables simple tables (issue #706) keep their cells as one JSON row
 * per table row in `wpdatatables_rows` and their size in the table's own
 * row in `wpdatatables`. Kind wpdatatables_rows (the one suffix "rows") is
 * keyed by `table_id`, so one snapshot covers every row of a table, rows a
 * write adds included; kind wpdatatables (the table name split as "wpdata"
 * and the one suffix "tables") is the table's own row, keyed by id.
 * Ninja Tables rows (kind ninja_table, the one suffix "items") are keyed by
 * their own id; an insert names the ids it made once it has them. After a
 * Ninja Tables restore its render cache of the tables involved is cleared
 * through its own function, as its own row edits do.
 *
 * Booking plugin rows (issue #679) are one booking each: Bookly's
 * `bookly_customer_appointments`, Simply Schedule Appointments'
 * `ssa_appointments`, BookingPress's `bookingpress_appointment_bookings`
 * (keyed by bookingpress_appointment_booking_id) and LatePoint's
 * `latepoint_bookings`, each kind allowing only that one suffix.
 *
 * Forum rows (issue #671) come from two forum plugins' own tables. wpForo
 * keeps its main board in {prefix}wpforo_forums, _topics, _posts and
 * _postmeta, keyed by forumid and topicid (posts and postmeta captured by
 * the topic they belong to, so a moved topic's rows all go back). Asgaros
 * Forum keeps {prefix}forum_topics and forum_posts keyed by id, and a
 * topic's posts by their parent_id. Each kind allows only its one table.
 * After a wpForo restore its own cache is cleaned (wpforo_clean_cache), as
 * its screens do after a change.
 *
 * Restrict Content membership levels (issue #669, the
 * `restrict_content_pro` table the plugin names its levels table, so kind
 * rcp_levels with the prefix "restrict_content_" and the one suffix
 * "pro") are keyed by their own id. After a restore the plugin's cached
 * copies of the rows are dropped (its BerlinDB cache group
 * rcp-membership_levels), as its own query does after a write.
 *
 * Jetpack CRM contacts (issue #684, the `zbs_contacts` table, the one
 * suffix "contacts") are keyed by their `ID` column. An update's image is
 * put back as it was. A create's image holds no row: its undo removes the
 * contact through Jetpack CRM's own deleteContact(), which also drops the
 * tags, aliases, meta and logs the plugin added with it, and is refused,
 * leaving the contact in place, once quotes, invoices, transactions or tasks
 * have been linked to it since or while the plugin is not loaded.
 *
 * LearnPress (issue #672) keeps a course's curriculum in two tables of its
 * own: `learnpress_sections` (kind learnpress_sections, the one suffix
 * "sections"), keyed by the course (`section_course_id`), and
 * `learnpress_section_items` (kind learnpress_section_items, the one suffix
 * "section_items"), keyed by the section (`section_id`). A snapshot taken
 * before a write therefore covers the rows the write inserts, the way the
 * MemberPress kind does. After a restore, LearnPress's cached copy of each
 * course it touched is rebuilt through LearnPress's own course model, as its
 * curriculum editor does after a change.
 *
 * MasterStudy LMS (issue #1033) keeps a course's curriculum the same way:
 * `stm_lms_curriculum_sections` (kind masterstudy_sections, the one suffix
 * "curriculum_sections"), keyed by the course (`course_id`), and
 * `stm_lms_curriculum_materials` (kind masterstudy_materials, the one suffix
 * "curriculum_materials"), keyed by the section (`section_id`). MasterStudy
 * caches neither, so a restore puts the rows back and nothing more.
 *
 * WPML String Translation strings (issue #1053, the `icl_strings` table,
 * the one suffix "strings") are keyed by their own id, with every
 * `icl_string_translations` row of each string as a companion, so a restore
 * puts back a string's status and all its translations and removes the
 * ones a write added.
 *
 * Easy Digital Downloads discounts (issue #664, the `edd_adjustments` table,
 * the one suffix "adjustments") are keyed by their own id; a create
 * reserves its id the same way. EDD reads the table through BerlinDB, which
 * caches rows and query results in the object cache, so after a restore the
 * cached lookups of the table are retired (purge_edd_cache()), the way
 * BerlinDB's own writes do.
 *
 * Depicter (issue #1091) keeps a document's published revisions as rows of
 * depicter_documents whose parent is the document (kind depicter_revisions,
 * keyed by parent) and its display rules as depicter_meta rows of the
 * document (kind depicter_meta, keyed by relation_id), so a snapshot taken
 * before a publish covers the revision it inserts. Restoring takes
 * manage_options.
 *
 * Restoring takes the capability the write itself needs: edit_post on each
 * captured post for a Pods row, a MemberPress rule's rows or a LearnPress
 * course's sections, edit_post on the course a LearnPress section belongs to
 * for that section's items, manage_options
 * for a dictionary row, a Pretty Links link or a WP Go Maps marker, a Restrict Content level, an EDD discount, a forum row, a booking, an Instagram Feed row or a wpDataTables or Ninja Tables row, upload_files for a FileBird row, and Wordfence's own admin capability (manage_options,
 * manage_network on a multisite network) for a Wordfence row, and Jetpack
 * CRM's admin_zerobs_customers (or manage_options) for a contact.
 */
final class Plugin_Table_Rows_Snapshot
{
    public const TYPE = 'plugin_table_rows';

    /** kind => table prefix after the site prefix. */
    public const KINDS = [
        'pods'             => 'pods_',
        'trp_dictionary'   => 'trp_dictionary_',
        'mepr_rule_access' => 'mepr_rule_access_',
        'rank_math'        => 'rank_math_',
        'prli'             => 'prli_',
        'wpgmza'           => 'wpgmza',
        'fbv'              => '',
        'fbv_attachment'   => 'fbv_',
        'sbi'              => 'sbi_',
        'wdr'              => 'wdr_',
        'tve_ult_events'   => 'tve_ult_',
        'tve_ult_display'  => 'tve_ult_',
        'tve_leads_options'    => 'tve_leads_',
        'tve_leads_variations' => 'tve_leads_',
        'bookly'           => 'bookly_',
        'ssa'              => 'ssa_',
        'bookingpress'     => 'bookingpress_',
        'latepoint'        => 'latepoint_',
        'wpforo_forum'          => 'wpforo_',
        'wpforo_topic'          => 'wpforo_',
        'wpforo_topic_posts'    => 'wpforo_',
        'wpforo_topic_postmeta' => 'wpforo_',
        'asgaros_topic'         => 'forum_',
        'asgaros_post'          => 'forum_',
        'asgaros_topic_posts'   => 'forum_',
        'pmpro_levels'     => 'pmpro_',
        'pmpro_members'    => 'pmpro_',
        'pmpro_codes'      => 'pmpro_',
        'wpdatatables'      => 'wpdata',
        'wpdatatables_rows' => 'wpdatatables_',
        'ninja_table'       => 'ninja_table_',
        'rcp_levels'       => 'restrict_content_',
        'jpcrm'            => 'zbs_',
        'learnpress_sections'      => 'learnpress_',
        'learnpress_section_items' => 'learnpress_',
        'masterstudy_sections'     => 'stm_lms_',
        'masterstudy_materials'    => 'stm_lms_',
        'wpml_strings'             => 'icl_',
        'edd'              => 'edd_',
        'depicter_revisions' => 'depicter_',
        'depicter_meta'      => 'depicter_',
    ];

    /**
     * kind => the Wordfence table it covers, unprefixed and as Wordfence
     * spells it before its own case conversion. Suffix "network" only.
     */
    public const WORDFENCE_KINDS = [
        'wordfence_issues' => 'wfIssues',
        'wordfence_blocks' => 'wfBlocks7',
    ];

    /** kind => the column its ids name, for a kind not keyed by `id`. */
    private const KEY_COLUMNS = [
        'mepr_rule_access' => 'rule_id',
        'wordfence_blocks' => 'IP',
        'tve_ult_events'   => 'campaign_id',
        'tve_ult_display'  => 'campaign_id',
        'bookingpress'     => 'bookingpress_appointment_booking_id',
        'pmpro_members'    => 'user_id',
        'wpdatatables_rows' => 'table_id',
        'jpcrm'            => 'ID',
        'depicter_revisions' => 'parent',
        'depicter_meta'      => 'relation_id',
        'learnpress_sections'      => 'section_course_id',
        'learnpress_section_items' => 'section_id',
        'masterstudy_sections'     => 'course_id',
        'masterstudy_materials'    => 'section_id',
        'wpforo_forum'          => 'forumid',
        'wpforo_topic'          => 'topicid',
        'wpforo_topic_posts'    => 'topicid',
        'wpforo_topic_postmeta' => 'topicid',
        'asgaros_topic_posts'   => 'parent_id',
        'tve_leads_options'    => 'group',
        'tve_leads_variations' => 'key',
        'fbv_attachment'   => 'attachment_id',
    ];

    /** kind => the column its rows are read in order of, for a table with no `id` column. */
    private const ORDER_COLUMNS = [
        'bookingpress'             => 'bookingpress_appointment_booking_id',
        'learnpress_sections'      => 'section_id',
        'learnpress_section_items' => 'section_item_id',
        'wpforo_forum'          => 'forumid',
        'wpforo_topic'          => 'topicid',
        'wpforo_topic_posts'    => 'postid',
        'wpforo_topic_postmeta' => 'metaid',
        'tve_leads_variations' => 'key',
        'fbv_attachment'   => 'attachment_id',
    ];

    /** kind => the only suffixes it allows, for a kind with a fixed table. */
    private const SUFFIXES = [
        'mepr_rule_access' => [ 'conditions' ],
        'rank_math'        => [ 'redirections' ],
        'prli'             => [ 'links' ],
        'wpgmza'           => [ 'markers' ],
        'fbv'              => [ 'fbv' ],
        'fbv_attachment'   => [ 'attachment_folder' ],
        'sbi'              => [ 'feeds', 'feed_caches' ],
        'wdr'              => [ 'rules' ],
        'tve_ult_events'   => [ 'events' ],
        'tve_ult_display'  => [ 'settings_campaign' ],
        'tve_leads_options'    => [ 'group_options' ],
        'tve_leads_variations' => [ 'form_variations' ],
        'bookly'           => [ 'customer_appointments' ],
        'ssa'              => [ 'appointments' ],
        'bookingpress'     => [ 'appointment_bookings' ],
        'latepoint'        => [ 'bookings' ],
        'wpforo_forum'          => [ 'forums' ],
        'wpforo_topic'          => [ 'topics' ],
        'wpforo_topic_posts'    => [ 'posts' ],
        'wpforo_topic_postmeta' => [ 'postmeta' ],
        'asgaros_topic'         => [ 'topics' ],
        'asgaros_post'          => [ 'posts' ],
        'asgaros_topic_posts'   => [ 'posts' ],
        'pmpro_levels'     => [ 'membership_levels' ],
        'pmpro_members'    => [ 'memberships_users' ],
        'pmpro_codes'      => [ 'discount_codes' ],
        'wpdatatables'      => [ 'tables' ],
        'wpdatatables_rows' => [ 'rows' ],
        'ninja_table'       => [ 'items' ],
        'rcp_levels'       => [ 'pro' ],
        'jpcrm'            => [ 'contacts' ],
        'learnpress_sections'      => [ 'sections' ],
        'learnpress_section_items' => [ 'section_items' ],
        'masterstudy_sections'     => [ 'curriculum_sections' ],
        'masterstudy_materials'    => [ 'curriculum_materials' ],
        'wpml_strings'             => [ 'strings' ],
        'edd'              => [ 'adjustments' ],
        'depicter_revisions' => [ 'documents' ],
        'depicter_meta'      => [ 'meta' ],
    ];

    /** kind => suffix => the unprefixed table, for a kind whose one table is not prefix plus suffix. */
    private const FIXED_TABLES = [
        'wpgmza' => [ 'markers' => 'wpgmza' ],
    ];

    /**
     * kind => [companion table suffix (after the kind's own prefix) => the
     * column holding the parent id]. Companion rows are captured and
     * restored with their parent rows, when the table exists.
     */
    private const COMPANIONS = [
        'pmpro_levels' => [ 'membership_levels_groups' => 'level' ],
        'pmpro_codes'  => [ 'discount_codes_levels' => 'code_id' ],
        'wpml_strings' => [ 'string_translations' => 'string_id' ],
    ];

    /** Booking plugin kinds (issue #679), restored under manage_options. */
    private const BOOKING_KINDS = [ 'bookly', 'ssa', 'bookingpress', 'latepoint' ];

    /** kind => its binary columns, carried as lowercase hex in the snapshot and in the key. */
    private const BINARY_COLUMNS = [
        'wordfence_blocks' => [ 'IP' ],
        'wpgmza'           => [ 'latlng' ],
    ];

    /** The column a kind's ids name. */
    public static function key_column(string $kind): string
    {
        return self::KEY_COLUMNS[ $kind ] ?? 'id';
    }

    /** Whether a kind's ids are binary values (lowercase hex in the key) rather than integers. */
    private static function binary_key(string $kind): bool
    {
        return in_array(self::key_column($kind), self::BINARY_COLUMNS[ $kind ] ?? [], true);
    }

    /**
     * A kind's ids, cleaned: positive integers, or for a binary-keyed kind
     * lowercase hex of up to 64 bytes. Anything else is dropped.
     *
     * @return array<int, int|string>
     */
    private static function clean_ids(string $kind, array $ids): array
    {
        if (self::binary_key($kind)) {
            $ids = array_map(static fn ($id): string => strtolower((string) $id), $ids);
            return array_values(array_filter($ids, static fn (string $id): bool => 1 === preg_match('/^(?:[0-9a-f]{2}){1,64}$/', $id)));
        }
        return array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    }

    /** The snapshot key for a set of rows. */
    public static function key(string $kind, string $suffix, array $ids): string
    {
        $ids = self::binary_key($kind) ? array_values(array_unique(self::clean_ids($kind, $ids))) : array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        return $kind . ':' . $suffix . ':' . implode(',', $ids);
    }

    /**
     * A Wordfence table as Wordfence names it: the network prefix and the
     * name, lowercased when Wordfence's wordfence_case option (a network
     * option on multisite) says its tables are lowercase, as on every
     * install since it switched.
     */
    public static function wordfence_table(string $name): string
    {
        global $wpdb;

        $lower = is_multisite() ? get_network_option(null, 'wordfence_case') : get_option('wordfence_case');
        return $wpdb->base_prefix . ($lower ? strtolower($name) : $name);
    }

    /** The prefixed table for a kind and suffix, or null when either is not allowed. */
    public static function table(string $kind, string $suffix): ?string
    {
        global $wpdb;

        if (isset(self::WORDFENCE_KINDS[ $kind ])) {
            return 'network' === $suffix ? self::wordfence_table(self::WORDFENCE_KINDS[ $kind ]) : null;
        }
        if (! isset(self::KINDS[ $kind ]) || 1 !== preg_match('/^[a-z0-9_-]{1,64}$/', $suffix)) {
            return null;
        }
        if (isset(self::SUFFIXES[ $kind ]) && ! in_array($suffix, self::SUFFIXES[ $kind ], true)) {
            return null;
        }
        if (isset(self::FIXED_TABLES[ $kind ])) {
            return isset(self::FIXED_TABLES[ $kind ][ $suffix ]) ? $wpdb->prefix . self::FIXED_TABLES[ $kind ][ $suffix ] : null;
        }
        return $wpdb->prefix . self::KINDS[ $kind ] . $suffix;
    }

    /**
     * The suffix of a full table name under a kind, or null when the table is
     * not one of that kind's tables on this site.
     */
    public static function suffix_of(string $kind, string $table): ?string
    {
        global $wpdb;

        if (! isset(self::KINDS[ $kind ])) {
            return null;
        }
        foreach (self::FIXED_TABLES[ $kind ] ?? [] as $suffix => $name) {
            if ($wpdb->prefix . $name === $table) {
                return (string) $suffix;
            }
        }
        if (isset(self::FIXED_TABLES[ $kind ])) {
            return null;
        }
        $prefix = $wpdb->prefix . self::KINDS[ $kind ];
        if (! str_starts_with($table, $prefix)) {
            return null;
        }
        $suffix = substr($table, strlen($prefix));
        return self::table($kind, $suffix) === $table ? $suffix : null;
    }

    /** Whether a table exists (SHOW COLUMNS, which also sees temporary tables). */
    public static function table_exists(string $table): bool
    {
        return [] !== self::columns($table);
    }

    /**
     * The live column names of a table, or [] when it does not exist.
     *
     * @return string[]
     */
    public static function columns(string $table): array
    {
        global $wpdb;

        $suppress = $wpdb->suppress_errors(true);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- probing a third-party table's live shape; nothing to cache.
        $columns = $wpdb->get_col($wpdb->prepare('SHOW COLUMNS FROM %i', $table));
        $wpdb->suppress_errors($suppress);

        return array_map('strval', (array) $columns);
    }

    /**
     * Every row under the given ids (in $column, `id` unless the kind keys
     * its rows by another column), oldest id first, read live.
     *
     * @param int[] $ids
     * @return array<int, array<string, string|null>>
     */
    public static function rows(string $table, array $ids, string $column = 'id', string $order = 'id'): array
    {
        global $wpdb;

        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ([] === $ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '%d'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- a snapshot must read the live rows of a third-party table; $in holds only %d placeholders.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE %i IN ($in) ORDER BY %i ASC", ...array_merge([ $table, $column ], $ids, [ $order ])), ARRAY_A);

        return array_values((array) $rows);
    }

    /** @return array{kind: string, suffix: string, ids: array<int, int|string>} */
    public static function parse(string $key): array
    {
        [$kind, $suffix, $ids] = array_pad(explode(':', $key, 3), 3, '');
        $ids = self::clean_ids((string) $kind, explode(',', (string) $ids));

        return [ 'kind' => (string) $kind, 'suffix' => (string) $suffix, 'ids' => $ids ];
    }

    public static function capture(string $key): array
    {
        $parsed = self::parse($key);
        $table  = self::table($parsed['kind'], $parsed['suffix']);
        $exists = null !== $table && self::table_exists($table);

        return [
            'object_type' => self::TYPE,
            'object_id'   => $key,
            'data'        => [
                'kind'         => $parsed['kind'],
                'suffix'       => $parsed['suffix'],
                'ids'          => $parsed['ids'],
                'table_exists' => $exists,
                'rows'         => $exists ? self::kind_rows($parsed['kind'], (string) $table, $parsed['ids']) : [],
            ] + (isset(self::COMPANIONS[ $parsed['kind'] ]) ? [ 'companions' => self::capture_companions($parsed['kind'], $parsed['ids']) ] : []),
        ];
    }

    /**
     * The companion rows of a kind under its ids: suffix => [exists, rows].
     *
     * @param array<int, int|string> $ids
     * @return array<string, array{exists: bool, rows: array}>
     */
    private static function capture_companions(string $kind, array $ids): array
    {
        $out = [];
        foreach (self::COMPANIONS[ $kind ] ?? [] as $suffix => $column) {
            $table          = self::companion_table($kind, (string) $suffix);
            $exists         = self::table_exists($table);
            $out[ $suffix ] = [ 'exists' => $exists, 'rows' => $exists ? self::companion_rows($table, (string) $column, $ids) : [] ];
        }
        return $out;
    }

    /** A kind's companion table: the site prefix, the kind's prefix and the fixed companion suffix. */
    private static function companion_table(string $kind, string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . self::KINDS[ $kind ] . $suffix;
    }

    /**
     * Rows of a companion table under the parent ids, ordered by the parent
     * column (a companion table need not have an id column).
     *
     * @param array<int, int|string> $ids
     * @return array<int, array<string, string|null>>
     */
    private static function companion_rows(string $table, string $column, array $ids): array
    {
        global $wpdb;

        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ([] === $ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- a snapshot must read the live rows of a third-party table; $in holds only %d placeholders.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE %i IN ($in) ORDER BY %i ASC", $table, $column, ...array_merge($ids, [ $column ])), ARRAY_A);

        return array_values((array) $rows);
    }

    /**
     * Put a kind's companion rows back: delete every row under the captured
     * ids, then insert each captured row. A companion table that did not
     * exist at capture, or is gone now, is left alone.
     *
     * @param array<int, int|string> $ids
     */
    private static function restore_companions(string $kind, array $ids, array $captured): void
    {
        global $wpdb;

        $in = implode(',', array_fill(0, count($ids), '%d'));
        foreach (self::COMPANIONS[ $kind ] ?? [] as $suffix => $column) {
            $data  = (array) ($captured[ $suffix ] ?? []);
            $table = self::companion_table($kind, (string) $suffix);
            if (empty($data['exists']) || ! self::table_exists($table)) {
                continue;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- third-party table with no WP API; $in holds only %d placeholders.
            if (false === $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE %i IN ($in)", $table, (string) $column, ...$ids))) {
                throw new Mutation_Failed('Could not clear the rows being restored in ' . esc_html($table) . '.');
            }
            foreach ((array) ($data['rows'] ?? []) as $row) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- third-party table with no WP API.
                if (false === $wpdb->insert($table, (array) $row)) {
                    throw new Mutation_Failed('Could not restore a row of ' . esc_html($table) . ': ' . esc_html($wpdb->last_error));
                }
            }
        }
    }

    /**
     * Drop Paid Memberships Pro's cached levels of each user whose
     * membership rows were restored (its own cache keys, group pmpro).
     *
     * @param array<int, int|string> $ids
     */
    private static function clear_pmpro_user_cache(array $ids): void
    {
        global $all_membership_levels;
        foreach (array_map('intval', $ids) as $user_id) {
            if (is_array($all_membership_levels)) {
                unset($all_membership_levels[ $user_id ]);
            }
            foreach ([ '', '_all', '_active' ] as $suffix) {
                wp_cache_delete('user_' . $user_id . '_levels' . $suffix, 'pmpro');
            }
        }
    }

    /**
     * Put the captured rows back: delete every row under the captured ids,
     * then insert each captured row as it was. Any failure is a
     * Mutation_Failed.
     */
    public static function restore(array $snapshot): void
    {
        $data  = (array) ($snapshot['data'] ?? []);
        $kind  = (string) ($data['kind'] ?? '');
        $ids   = self::clean_ids($kind, (array) ($data['ids'] ?? []));
        $table = self::table($kind, (string) ($data['suffix'] ?? ''));

        if (null === $table || [] === $ids || empty($data['table_exists']) || ! self::table_exists($table)) {
            return;
        }

        self::authorize($kind, $ids);

        if (isset(self::BINARY_COLUMNS[ $kind ])) {
            self::restore_binary($kind, $table, $ids, (array) ($data['rows'] ?? []));
            self::after_wordfence_restore($kind);
            return;
        }

        if ('jpcrm' === $kind && [] === (array) ($data['rows'] ?? [])) {
            foreach ($ids as $id) {
                self::remove_jpcrm_contact((int) $id);
            }
            return;
        }

        global $wpdb;
        $in = implode(',', array_fill(0, count($ids), '%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- third-party table with no WP API; $in holds only %d placeholders.
        if (false === $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE %i IN ($in)", $table, self::key_column($kind), ...$ids))) {
            throw new Mutation_Failed('Could not clear the rows being restored in ' . esc_html($table) . '.');
        }
        foreach ((array) ($data['rows'] ?? []) as $row) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- third-party table with no WP API.
            if (false === $wpdb->insert($table, (array) $row)) {
                throw new Mutation_Failed('Could not restore a row of ' . esc_html($table) . ': ' . esc_html($wpdb->last_error));
            }
        }

        if ('pods' === $kind) {
            foreach ($ids as $id) {
                clean_post_cache($id);
            }
        }
        if ('trp_dictionary' === $kind) {
            self::flush_translatepress_cache();
        }
        if ('rank_math' === $kind) {
            self::purge_rank_math_cache($ids);
        }
        if ('fbv_attachment' === $kind) {
            foreach ($ids as $id) {
                clean_post_cache((int) $id);
            }
        }
        if ('sbi' === $kind) {
            self::drop_instagram_feed_cache((string) ($data['suffix'] ?? ''), (array) ($data['rows'] ?? []));
        }
        if (isset(self::COMPANIONS[ $kind ])) {
            self::restore_companions($kind, $ids, (array) ($data['companions'] ?? []));
        }
        if ('pmpro_members' === $kind) {
            self::clear_pmpro_user_cache($ids);
        }
        if ('ninja_table' === $kind) {
            self::clear_ninja_tables_cache(array_merge(array_column((array) ($data['rows'] ?? []), 'table_id'), (array) ($data['table_ids'] ?? [])));
        }
        if (str_starts_with($kind, 'wpforo_') && function_exists('wpforo_clean_cache')) {
            wpforo_clean_cache();
        }
        if ('rcp_levels' === $kind) {
            self::flush_rcp_level_cache($ids);
        }
        if ('learnpress_sections' === $kind) {
            self::refresh_learnpress_courses($ids);
        }
        if ('learnpress_section_items' === $kind) {
            self::refresh_learnpress_courses(self::learnpress_courses_of($ids));
        }
        if ('edd' === $kind) {
            self::purge_edd_cache((string) $data['suffix'], $ids);
        }
        self::after_wordfence_restore($kind);
    }

    /**
     * Drop Instagram Feed's object cache entry (sbi_feed_<feed>_1, the key
     * its cache class reads first) for every feed whose rows were restored.
     */
    private static function drop_instagram_feed_cache(string $suffix, array $rows): void
    {
        foreach ($rows as $row) {
            $feed = 'feeds' === $suffix ? (string) ($row['id'] ?? '') : (string) ($row['feed_id'] ?? '');
            if ('' !== $feed) {
                wp_cache_delete('sbi_feed_' . $feed . '_1');
            }
        }
    }

    /**
     * Drop Ninja Tables' cached rows and HTML of the given tables through
     * its own function. A no-op while Ninja Tables is not loaded.
     *
     * @param array<int, int|string> $table_ids
     */
    public static function clear_ninja_tables_cache(array $table_ids): void
    {
        if (! function_exists('ninjaTablesClearTableDataCache')) {
            return;
        }
        foreach (array_unique(array_filter(array_map('intval', $table_ids))) as $table_id) {
            \ninjaTablesClearTableDataCache($table_id);
        }
    }

    /**
     * Drop Restrict Content's cached copies of the given membership level
     * rows and move its level query cache on, as its own BerlinDB query does
     * after a write (cache group rcp-membership_levels).
     *
     * @param array<int, int|string> $ids
     */
    public static function flush_rcp_level_cache(array $ids): void
    {
        foreach ($ids as $id) {
            wp_cache_delete((int) $id, 'rcp-membership_levels');
        }
        wp_cache_set('last_changed', microtime(), 'rcp-membership_levels');
    }

    /**
     * Undo a Jetpack CRM contact create: remove the contact through the
     * plugin's own deleteContact(), so the tags, aliases, meta and logs it
     * added go with it. Refused (Mutation_Failed, the contact left in place)
     * once quotes, invoices, transactions or tasks are linked to it, or
     * while the plugin's API is not loaded. A contact already gone is fine.
     */
    private static function remove_jpcrm_contact(int $id): void
    {
        global $wpdb;

        $contacts = (string) self::table('jpcrm', 'contacts');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Jetpack CRM's own table, read live.
        if (0 === (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE ID = %d', $contacts, $id))) {
            return;
        }
        $links = $wpdb->prefix . 'zbs_object_links';
        if (self::table_exists($links)) {
            // Quote 3, invoice 4, transaction 5 and task 6, linked to contact (type 1).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Jetpack CRM's own table, read live.
            $linked = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE zbsol_objtype_to = 1 AND zbsol_objid_to = %d AND zbsol_objtype_from IN (3, 4, 5, 6)', $links, $id));
            if ($linked > 0) {
                throw new Mutation_Failed(sprintf('Rollback refused: Jetpack CRM contact %d has %d quote(s), invoice(s), transaction(s) or task(s) linked since it was created, so it was left in place.', (int) $id, (int) $linked));
            }
        }
        $zbs = $GLOBALS['zbs'] ?? null;
        $api = is_object($zbs) && isset($zbs->DAL) && is_object($zbs->DAL) && isset($zbs->DAL->contacts) ? $zbs->DAL->contacts : null;
        if (! is_object($api) || ! method_exists($api, 'deleteContact')) {
            throw new Mutation_Failed(sprintf('Rollback refused: Jetpack CRM is not active, so contact %d was left in place. Activate it and undo again.', (int) $id));
        }
        $api->deleteContact([ 'id' => $id, 'saveOrphans' => false ]);
    }

    /**
     * Retire Easy Digital Downloads' cached lookups for rows of one of its
     * tables, as BerlinDB does after its own writes: the rows cached by id
     * (group edd-<suffix>) are dropped, and the last_changed stamp of the
     * table's query cache and of each by-column lookup group
     * (edd-<suffix>-by-<column>) is rotated, so every cached result is
     * rebuilt on the next read.
     *
     * @param array<int, int|string> $ids
     */
    public static function purge_edd_cache(string $suffix, array $ids): void
    {
        $table = self::table('edd', $suffix);
        if (null === $table) {
            return;
        }
        $group = 'edd-' . $suffix;
        foreach ($ids as $id) {
            wp_cache_delete((int) $id, $group);
        }
        wp_cache_set('last_changed', microtime(), $group);
        foreach (self::columns($table) as $column) {
            wp_cache_set('last_changed', microtime(), $group . '-by-' . $column);
        }
    }

    /**
     * Drop Rank Math's derived lookup rows for redirections whose row was
     * written or restored. A no-op while Rank Math's cache class is absent.
     *
     * @param array<int, int|string> $ids
     */
    public static function purge_rank_math_cache(array $ids): void
    {
        $class = '\\RankMath\\Redirections\\Cache';
        if (class_exists($class) && method_exists($class, 'purge')) {
            $class::purge(array_map('intval', $ids));
        }
    }

    /**
     * The courses MasterStudy LMS sections belong to (0 for a section that
     * is gone), section id => course id (issue #1033).
     *
     * @param array<int, int|string> $sections
     * @return array<int, int>
     */
    public static function masterstudy_courses_of(array $sections): array
    {
        global $wpdb;

        $sections = array_values(array_filter(array_map('intval', $sections), static fn (int $id): bool => $id > 0));
        $table    = (string) self::table('masterstudy_sections', 'curriculum_sections');
        if ([] === $sections || ! self::table_exists($table)) {
            return array_fill_keys($sections, 0);
        }
        $in = implode(',', array_fill(0, count($sections), '%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- MasterStudy LMS's own table; $in holds only %d placeholders.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, course_id FROM %i WHERE id IN ($in)", $table, ...$sections), ARRAY_A);

        $out = array_fill_keys($sections, 0);
        foreach ((array) $rows as $row) {
            $out[ (int) $row['id'] ] = (int) $row['course_id'];
        }
        return $out;
    }

    /**
     * The courses LearnPress sections belong to (0 for a section that is
     * gone), section id => course id.
     *
     * @param array<int, int|string> $sections
     * @return array<int, int>
     */
    public static function learnpress_courses_of(array $sections): array
    {
        global $wpdb;

        $sections = array_values(array_filter(array_map('intval', $sections), static fn (int $id): bool => $id > 0));
        $table    = (string) self::table('learnpress_sections', 'sections');
        if ([] === $sections || ! self::table_exists($table)) {
            return array_fill_keys($sections, 0);
        }
        $in = implode(',', array_fill(0, count($sections), '%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- LearnPress's own table; $in holds only %d placeholders.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT section_id, section_course_id FROM %i WHERE section_id IN ($in)", $table, ...$sections), ARRAY_A);

        $out = array_fill_keys($sections, 0);
        foreach ((array) $rows as $row) {
            $out[ (int) $row['section_id'] ] = (int) $row['section_course_id'];
        }
        return $out;
    }

    /**
     * Rebuild LearnPress's cached copy of each course (its learnpress_courses
     * row and object cache) after its curriculum rows changed, the way
     * LearnPress's own section models do after a save. A no-op while
     * LearnPress is not loaded.
     *
     * @param array<int, int|string> $courses
     */
    public static function refresh_learnpress_courses(array $courses): void
    {
        $model = '\\LearnPress\\Models\\CourseModel';
        $cache = '\\LP_Cache';
        foreach (array_unique(array_filter(array_map('intval', $courses))) as $course) {
            if (class_exists($model) && method_exists($model, 'find')) {
                try {
                    $found = $model::find($course, true);
                    if (is_object($found) && method_exists($found, 'save')) {
                        $found->sections_items = null;
                        $found->total_items    = null;
                        $found->save();
                    }
                } catch (\Throwable $e) {
                    // A cache rebuild that fails leaves LearnPress to rebuild it on its next save.
                    unset($e);
                }
            }
            if (class_exists($cache)) {
                $lp_cache = new $cache();
                if (method_exists($lp_cache, 'clear')) {
                    $lp_cache->clear("courseSection/find/course/{$course}");
                }
            }
        }
    }

    /**
     * Rows of a kind under its ids. A binary-keyed kind is matched with
     * UNHEX() and its binary columns come back as lowercase hex.
     *
     * @param array<int, int|string> $ids
     * @return array<int, array<string, string|null>>
     */
    private static function kind_rows(string $kind, string $table, array $ids): array
    {
        if (! isset(self::BINARY_COLUMNS[ $kind ])) {
            return self::rows($table, $ids, self::key_column($kind), self::ORDER_COLUMNS[ $kind ] ?? 'id');
        }
        if ([] === $ids) {
            return [];
        }

        global $wpdb;
        $in = implode(',', array_fill(0, count($ids), self::binary_key($kind) ? 'UNHEX(%s)' : '%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- a snapshot must read the live rows of a third-party table; $in holds only UNHEX(%s) or %d placeholders.
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE %i IN ($in) ORDER BY id ASC", $table, self::key_column($kind), ...$ids), ARRAY_A);

        $out = [];
        foreach ($rows as $row) {
            foreach (self::BINARY_COLUMNS[ $kind ] as $column) {
                if (isset($row[ $column ])) {
                    $row[ $column ] = bin2hex((string) $row[ $column ]);
                }
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * The binary-keyed restore: delete every row under the captured keys,
     * then insert each captured row as it was, its binary columns turned
     * back into bytes with UNHEX(). Column names are taken only from the
     * table's live columns.
     *
     * @param array<int, int|string> $ids
     */
    private static function restore_binary(string $kind, string $table, array $ids, array $rows): void
    {
        global $wpdb;

        $in = implode(',', array_fill(0, count($ids), self::binary_key($kind) ? 'UNHEX(%s)' : '%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- third-party table with no WP API; $in holds only UNHEX(%s) or %d placeholders.
        if (false === $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE %i IN ($in)", $table, self::key_column($kind), ...$ids))) {
            throw new Mutation_Failed('Could not clear the rows being restored in ' . esc_html($table) . '.');
        }

        $live   = self::columns($table);
        $binary = self::BINARY_COLUMNS[ $kind ];
        foreach ($rows as $row) {
            $values = [];
            $names  = [];
            $args   = [];
            foreach ((array) $row as $column => $value) {
                $column = (string) $column;
                if (! in_array($column, $live, true)) {
                    throw new Mutation_Failed('Could not restore a row of ' . esc_html($table) . ': its column ' . esc_html($column) . ' is gone.');
                }
                $names[] = $column;
                // Each value is one of three literals: NULL, %s, or UNHEX(%s)
                // for a binary column.
                $values[] = (null === $value ? 'NULL' : (in_array($column, $binary, true) ? 'UNHEX(%s)' : '%s'));
                if (null !== $value) {
                    $args[] = (string) $value;
                }
            }
            if ([] === $names) {
                continue;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- third-party table with no WP API; one %i per column, and $values holds only %s, UNHEX(%s) and NULL.
            if (false === $wpdb->query($wpdb->prepare('INSERT INTO %i (' . implode(', ', array_fill(0, count($names), '%i')) . ') VALUES (' . implode(', ', $values) . ')', $table, ...$names, ...$args))) {
                throw new Mutation_Failed('Could not restore a row of ' . esc_html($table) . ': ' . esc_html($wpdb->last_error));
            }
        }
    }

    /**
     * Bring Wordfence's derived state back in line after one of its tables
     * was restored, the way its own screens do after the same change: its
     * firewall keeps a copy of the IP blocks, resynced at the end of the
     * request, and its scan notice counts the open issues.
     */
    private static function after_wordfence_restore(string $kind): void
    {
        if ('wordfence_blocks' === $kind && class_exists('wfWAFIPBlocksController', false) && ! (defined('WFWAF_SUBDIRECTORY_INSTALL') && WFWAF_SUBDIRECTORY_INSTALL)) {
            \wfWAFIPBlocksController::setNeedsSynchronizeConfigSettings();
        }
        if ('wordfence_issues' === $kind && class_exists('wfScanEngine', false) && class_exists('wfIssues', false)) {
            \wfScanEngine::refreshScanNotification(\wfIssues::shared());
        }
    }

    /** Wordfence's own admin capability: manage_options, manage_network on a network. */
    public static function wordfence_capability(): string
    {
        return is_multisite() ? 'manage_network' : 'manage_options';
    }

    /** Drop TranslatePress's cached dictionary lookups (its 'trp' cache group). */
    public static function flush_translatepress_cache(): void
    {
        if (function_exists('wp_cache_supports') && wp_cache_supports('flush_group')) {
            wp_cache_flush_group('trp');
        }
    }

    /** @param array<int, int|string> $ids */
    private static function authorize(string $kind, array $ids): void
    {
        if (isset(self::WORDFENCE_KINDS[ $kind ])) {
            if (! current_user_can(self::wordfence_capability())) {
                throw new Mutation_Failed('Rollback refused: restoring Wordfence ' . ('wordfence_blocks' === $kind ? 'IP blocks' : 'scan issues') . ' requires the ' . esc_html(self::wordfence_capability()) . ' capability.');
            }
            return;
        }
        if (str_starts_with($kind, 'depicter_')) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring a Depicter document\'s revisions or display rules requires the manage_options capability.');
            }
            return;
        }
        if ('rank_math' === $kind) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring a Rank Math redirection requires the manage_options capability.');
            }
            return;
        }
        if ('wpml_strings' === $kind) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring WPML string translations requires the manage_options capability.');
            }
            return;
        }
        if ('trp_dictionary' === $kind) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring TranslatePress translations requires the manage_options capability.');
            }
            return;
        }
        if ('fbv' === $kind || 'fbv_attachment' === $kind) {
            if (! current_user_can('upload_files')) {
                throw new Mutation_Failed('Rollback refused: restoring FileBird media folders requires the upload_files capability.');
            }
            return;
        }
        if ('wdr' === $kind) {
            if (! current_user_can('manage_woocommerce')) {
                throw new Mutation_Failed('Rollback refused: restoring a Discount Rules rule requires the manage_woocommerce capability.');
            }
            return;
        }
        if ('tve_ult_events' === $kind || 'tve_ult_display' === $kind) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring a Thrive Ultimatum campaign\'s ' . ('tve_ult_events' === $kind ? 'timeline' : 'display rules') . ' requires the manage_options capability.');
            }
            return;
        }
        if ('tve_leads_options' === $kind || 'tve_leads_variations' === $kind) {
            if (! current_user_can('manage_options') && ! current_user_can('tve-use-tl')) {
                throw new Mutation_Failed('Rollback refused: restoring Thrive Leads settings requires the manage_options or tve-use-tl capability.');
            }
            return;
        }
        if (str_starts_with($kind, 'pmpro_')) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring Paid Memberships Pro levels, memberships or discount codes requires the manage_options capability.');
            }
            return;
        }
        if ('jpcrm' === $kind) {
            if (! current_user_can('admin_zerobs_customers') && ! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring a Jetpack CRM contact requires the admin_zerobs_customers capability.');
            }
            return;
        }
        if (in_array($kind, self::BOOKING_KINDS, true)) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring a booking requires the manage_options capability.');
            }
            return;
        }
        if ('prli' === $kind) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring Pretty Links links requires the manage_options capability.');
            }
            return;
        }
        if ('wpgmza' === $kind) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring WP Go Maps markers requires the manage_options capability.');
            }
            return;
        }
        if ('sbi' === $kind) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring Instagram Feed feeds or caches requires the manage_options capability.');
            }
            return;
        }
        if (in_array($kind, [ 'wpdatatables', 'wpdatatables_rows', 'ninja_table' ], true)) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring ' . ('ninja_table' === $kind ? 'Ninja Tables' : 'wpDataTables') . ' rows requires the manage_options capability.');
            }
            return;
        }
        if (str_starts_with($kind, 'wpforo_') || str_starts_with($kind, 'asgaros_')) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring forum rows requires the manage_options capability.');
            }
            return;
        }
        if ('rcp_levels' === $kind) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring a Restrict Content membership level requires the manage_options capability.');
            }
            return;
        }
        if ('learnpress_section_items' === $kind) {
            // A section is a row, not a post: its items take edit_post on its
            // course, and a section that is gone takes manage_options.
            $ids = array_values(self::learnpress_courses_of($ids));
            if (in_array(0, $ids, true) && ! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring the items of a LearnPress section that is gone requires the manage_options capability.');
            }
            $ids = array_values(array_filter($ids));
        }
        if ('masterstudy_materials' === $kind) {
            // Issue #1033: as for LearnPress, a section is a row; its items
            // take edit_post on its course, a gone section manage_options.
            $ids = array_values(self::masterstudy_courses_of($ids));
            if (in_array(0, $ids, true) && ! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring the items of a MasterStudy LMS section that is gone requires the manage_options capability.');
            }
            $ids = array_values(array_filter($ids));
        }
        if ('edd' === $kind) {
            if (! current_user_can('manage_options')) {
                throw new Mutation_Failed('Rollback refused: restoring Easy Digital Downloads discounts requires the manage_options capability.');
            }
            return;
        }
        foreach ($ids as $id) {
            if (current_user_can('edit_post', $id)) {
                continue;
            }
            if ('mepr_rule_access' === $kind) {
                throw new Mutation_Failed('Rollback refused: restoring the MemberPress access conditions of rule ' . (int) $id . ' requires permission to edit it.');
            }
            if (str_starts_with($kind, 'learnpress_')) {
                throw new Mutation_Failed('Rollback refused: restoring the curriculum of LearnPress course ' . (int) $id . ' requires permission to edit it.');
            }
            if (str_starts_with($kind, 'masterstudy_')) {
                throw new Mutation_Failed('Rollback refused: restoring the curriculum of MasterStudy LMS course ' . (int) $id . ' requires permission to edit it.');
            }
            throw new Mutation_Failed('Rollback refused: restoring the Pods fields of post ' . (int) $id . ' requires permission to edit it.');
        }
    }
}
