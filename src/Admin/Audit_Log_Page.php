<?php

namespace WPMCP\Admin;

use WPMCP\MCP\Request_Log;
use WPMCP\Plugin;
use WPMCP\Safety\Snapshot_Store;
use WPMCP\Tools\Backup\Backup_Job_Store;
use WPMCP\Tools\List_Operations;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read-only admin screen with two tabs, so an admin has one observability
 * page instead of two:
 *  - Mutations (default): agent mutations (who/when/what) with a one-click
 *    Restore wired to the existing Restore_Controller/Rollback_Service ajax
 *    endpoint.
 *  - Requests (issue #134): the MCP request outcome log, every ability call
 *    including reads, with duration and error code, and a link straight from
 *    a row to its undo point on the History screen when the call took a
 *    snapshot. Filterable by date, user, tool and outcome, with a redacted
 *    CSV export of the filtered rows (issue #303).
 *
 * Gated at the management capability, matching History_Page and Restore_Controller.
 * get_operations()/get_requests() are the testable seams: they return data
 * with no HTML, mirroring how List_Operations itself is unit-testable
 * independent of render().
 */
class Audit_Log_Page
{
    public const SLUG = 'wpmcp-audit-log';

    public const TAB_MUTATIONS = 'mutations';
    public const TAB_REQUESTS  = 'requests';

    /** Undo points per page on the Mutations tab (issue #759). */
    public const MUTATIONS_PER_PAGE = 20;

    /** Request rows per page on the Requests tab (issue #770). */
    public const REQUESTS_PER_PAGE = 20;

    /** admin-post action and nonce action of the request log CSV export. */
    public const EXPORT_ACTION = 'wpmcp_export_request_log';

    /** Query-string filters the Requests tab and its export accept. */
    private const REQUEST_FILTERS = [ 'date_from', 'date_to', 'user_id', 'tool', 'outcome' ];

    /**
     * Test seam: receives the CSV the export would stream, so a test can run
     * the export through its real admin-post hook (issue #587) without the
     * exit. Guarded by WPMCP_TESTING.
     *
     * @var callable|null
     */
    private static $sender_for_tests = null;

    /** Set or clear the export's test sender. Ignored outside WPMCP_TESTING. */
    public static function send_for_tests(?callable $sender): void
    {
        self::$sender_for_tests = defined('WPMCP_TESTING') && WPMCP_TESTING ? $sender : null;
    }

    /**
     * @param array<string, mixed> $filters Same shape as wpmcp/list-operations'
     *                                       input_schema (user_id, tool_name,
     *                                       domain, object_type, object_id,
     *                                       date_from, date_to, limit,
     *                                       offset).
     */
    public function get_operations(array $filters): array
    {
        return (new List_Operations())->handle($filters);
    }

    /**
     * Newest-first MCP request outcome rows matching $filters (see
     * Request_Log::query()), $limit of them from $offset.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function get_requests(int $limit = self::REQUESTS_PER_PAGE, array $filters = [], int $offset = 0): array
    {
        return Request_Log::query($filters, $limit, $offset);
    }

    /**
     * admin-post handler: the filtered request log as a redacted CSV
     * download. Needs the management capability and the export nonce. $sender is the
     * test seam; it receives the CSV and file name instead of the response.
     *
     * @return \WP_Error|null
     */
    public function export_requests(?callable $sender = null)
    {
        $nonce      = is_string($_GET['_wpnonce'] ?? null) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        $authorized = current_user_can(Management_Access::CAPABILITY)
            && '' !== $nonce
            && wp_verify_nonce($nonce, self::EXPORT_ACTION);

        if (! $authorized) {
            if (null !== $sender) {
                return new \WP_Error('wpmcp_forbidden', __('You are not allowed to export the request log.', 'safe-mcp-for-woocommerce'));
            }
            wp_die(esc_html__('You are not allowed to export the request log.', 'safe-mcp-for-woocommerce'), 403);
        }

        $csv      = Request_Log::to_csv(Request_Log::query($this->request_filters(), Request_Log::cap()));
        $filename = 'wpmcp-request-log-' . gmdate('Ymd-His') . '.csv';

        // Only the download is diverted for a hook-level test; a refusal
        // above still dies with the 403 the real request gets.
        if (null === $sender && defined('WPMCP_TESTING') && WPMCP_TESTING) {
            $sender = self::$sender_for_tests;
        }
        if (null !== $sender) {
            $sender($csv, $filename);
            return null;
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a text/csv attachment built by Request_Log::to_csv(), redacted and formula-safe; HTML escaping would corrupt it.
        echo $csv;
        exit;
    }

    public function render(): void
    {
        $tab = $this->current_tab();

        echo '<div class="wrap"><h1>' . esc_html(Plugin::page_title(_x('Audit Log', 'admin menu', 'safe-mcp-for-woocommerce'))) . '</h1>';
        $this->render_tabs($tab);

        if (self::TAB_REQUESTS === $tab) {
            $this->render_requests();
            echo '</div>';
            return;
        }

        $this->render_mutations();
        echo '</div>';
        $this->render_restore_script();
    }

    private function current_tab(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection on an admin list screen; the value only picks which list renders.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        return self::TAB_REQUESTS === $tab ? self::TAB_REQUESTS : self::TAB_MUTATIONS;
    }

    private function render_tabs(string $current): void
    {
        $tabs = [
            self::TAB_MUTATIONS => __('Mutations', 'safe-mcp-for-woocommerce'),
            self::TAB_REQUESTS  => __('Requests', 'safe-mcp-for-woocommerce'),
        ];

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $slug => $label) {
            printf(
                '<a href="%s" class="nav-tab%s">%s</a>',
                esc_url(add_query_arg(['page' => self::SLUG, 'tab' => $slug], admin_url('admin.php'))),
                $slug === $current ? ' nav-tab-active' : '',
                esc_html($label)
            );
        }
        echo '</h2>';
    }

    private function render_mutations(): void
    {
        $filters  = $this->filters_from_request();
        $per_page = self::MUTATIONS_PER_PAGE;
        $paged    = $this->current_page();

        // The rows and the total come from List_Operations, which scopes
        // both to the caller's own undo points below manage_options (#461);
        // the screen itself is gated at the management capability.
        $result = $this->get_operations($filters + ['limit' => $per_page, 'offset' => ($paged - 1) * $per_page]);
        $total  = (int) $result['total_count'];
        $pages  = max(1, (int) ceil($total / $per_page));
        if ($paged > $pages) {
            // A page past the end (rows undone or filtered away since the
            // link was made) shows the last page instead of an empty table.
            $paged  = $pages;
            $result = $this->get_operations($filters + ['limit' => $per_page, 'offset' => ($paged - 1) * $per_page]);
        }
        $ops           = $result['operations'];
        $nonce         = wp_create_nonce('wpmcp_restore');
        $session_nonce = wp_create_nonce(Restore_Controller::SESSION_ACTION);
        $sessions_seen = [];

        $this->render_filter_form($filters);
        $this->render_pagination(self::TAB_MUTATIONS, $filters, $total, $paged, $pages, 'top');
        echo '<table class="widefat striped"><thead><tr>'
            . '<th>' . esc_html__('Who', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('When', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('What', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Domain', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Session', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Undo', 'safe-mcp-for-woocommerce') . '</th>'
            . '</tr></thead><tbody>';

        foreach (self::group_calls($ops) as $group) {
            $op    = $group[0];
            $user  = get_userdata((int) $op['user_id']);
            /* translators: %d: numeric user ID. */
            $user_label = __('User #%d', 'safe-mcp-for-woocommerce');
            $who   = $user ? $user->display_name : sprintf($user_label, (int) $op['user_id']);
            $what  = self::what_label($op);

            echo '<tr>';
            printf('<td>%s</td>', esc_html($who));
            printf('<td>%s</td>', esc_html(self::site_time((string) $op['created_at'])));
            echo '<td>' . esc_html($what);
            if (count($group) > 1) {
                printf(
                    '<br><span class="wpmcp-call-count">%s</span>',
                    /* translators: %d: number of changes one call made. */
                    esc_html(sprintf(_n('%d change', '%d changes', count($group), 'safe-mcp-for-woocommerce'), count($group)))
                );
            }
            echo '</td>';
            printf('<td class="column-domain">%s</td>', esc_html(self::domain_label($op)));
            echo '<td>';
            $this->render_session_cell((string) ($op['session_id'] ?? ''), $session_nonce, $sessions_seen);
            echo '</td><td>';
            $live = array_values(array_filter($group, static fn (array $o): bool => ! empty($o['rollback_available'])));
            if ([] !== $live) {
                printf(
                    '<button class="button wpmcp-restore" data-op="%s" data-nonce="%s" data-label="%s">%s</button>',
                    esc_attr(implode(',', array_column($live, 'operation_id'))),
                    esc_attr($nonce),
                    esc_attr($what),
                    esc_html__('Restore', 'safe-mcp-for-woocommerce')
                );
            } elseif (! empty($op['rolled_back_at'])) {
                // Already undone (issue #593): no second Restore.
                /* translators: %s: date and time the change was undone, in the site timezone. */
                echo esc_html(sprintf(__('Undone %s', 'safe-mcp-for-woocommerce'), self::site_time((string) $op['rolled_back_at'])));
            }
            echo '</td></tr>';
        }

        echo '</tbody></table>';
        $this->render_pagination(self::TAB_MUTATIONS, $filters, $total, $paged, $pages, 'bottom');
        $this->render_jobs();
    }

    /**
     * Consecutive rows of one call (issue #1126): same session, tool, user
     * and arguments hash, written within two seconds of each other. A
     * switch-theme call, for one, records two operations.
     *
     * @param array<int, array<string, mixed>> $ops newest first
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function group_calls(array $ops): array
    {
        $hashes = Snapshot_Store::args_hashes(array_column($ops, 'operation_id'));
        $groups = [];
        $key    = null;
        $at     = 0;
        foreach ($ops as $op) {
            $this_key = implode('|', [ (string) $op['session_id'], (string) $op['tool_name'], (int) $op['user_id'], $hashes[ (string) $op['operation_id'] ] ?? (string) $op['operation_id'] ]);
            $this_at  = (int) strtotime((string) $op['created_at'] . ' UTC');
            if (null !== $key && $this_key === $key && abs($at - $this_at) <= 2) {
                $groups[ count($groups) - 1 ][] = $op;
            } else {
                $groups[] = [ $op ];
            }
            $key = $this_key;
            $at  = $this_at;
        }
        return $groups;
    }

    /** "tool: Title (#id)" where the object has a title, else "tool (#id)" (issue #1126). */
    public static function what_label(array $op): string
    {
        $tool   = (string) $op['tool_name'];
        $object = List_Operations::object_label($op);
        if ('' === $object) {
            return $tool;
        }
        $title = self::object_title((string) ($op['object_type'] ?? ''), (int) ($op['object_id'] ?? 0));
        return '' !== $title ? sprintf('%s: %s (%s)', $tool, $title, $object) : sprintf('%s (%s)', $tool, $object);
    }

    private static function object_title(string $type, int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        switch ($type) {
            case 'post':
                $post = get_post($id);
                return $post instanceof \WP_Post ? wp_strip_all_tags(get_the_title($post)) : '';
            case 'term':
                $term = get_term($id);
                return $term instanceof \WP_Term ? $term->name : '';
            case 'user':
                $user = get_userdata($id);
                return $user ? $user->display_name : '';
            case 'comment':
                $comment = get_comment($id);
                /* translators: %s: comment author. */
                return $comment ? sprintf(__('comment by %s', 'safe-mcp-for-woocommerce'), $comment->comment_author) : '';
        }
        return '';
    }

    /** A plain domain name (issue #1126): theme writes read Themes, not the packages domain. */
    public static function domain_label(array $op): string
    {
        $domain = (string) ($op['domain'] ?? '');
        if (false !== strpos((string) $op['tool_name'], 'theme') && in_array($domain, [ 'packages', 'theme', '' ], true)) {
            return __('Themes', 'safe-mcp-for-woocommerce');
        }
        $names = [
            'packages' => __('Plugins and themes', 'safe-mcp-for-woocommerce'),
            'theme'    => __('Themes', 'safe-mcp-for-woocommerce'),
            'seo'      => 'SEO',
            'acf'      => 'Advanced Custom Fields',
            'woocommerce' => 'WooCommerce',
            'rest'     => 'REST API',
            'cli'      => 'WP-CLI',
        ];
        return $names[ $domain ] ?? ucwords(str_replace([ '-', '_' ], ' ', $domain));
    }

    /** A stored UTC time in the site timezone, named (issue #1126). */
    public static function site_time(string $utc): string
    {
        $ts = strtotime($utc . ' UTC');
        return false === $ts ? $utc : self::site_time_ts($ts);
    }

    public static function site_time_ts(int $ts): string
    {
        return wp_date('Y-m-d H:i:s', $ts) . ' ' . wp_timezone_string();
    }

    /** The newest backup and restore jobs with status and time (issue #1126). */
    private function render_jobs(): void
    {
        $jobs = array_slice(Backup_Job_Store::list(), 0, 10);
        echo '<h2>' . esc_html__('Backup and restore jobs', 'safe-mcp-for-woocommerce') . '</h2>';
        if ([] === $jobs) {
            echo '<p>' . esc_html__('No backup or restore has run yet.', 'safe-mcp-for-woocommerce') . '</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr>'
            . '<th>' . esc_html__('Job', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Kind', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Status', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('When', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Details', 'safe-mcp-for-woocommerce') . '</th>'
            . '</tr></thead><tbody>';
        foreach ($jobs as $job) {
            $restore = 'restore' === ($job['type'] ?? '');
            printf(
                '<tr class="wpmcp-job" data-job="%d"><td>#%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                (int) $job['id'],
                (int) $job['id'],
                esc_html($restore ? __('Restore', 'safe-mcp-for-woocommerce') : __('Backup', 'safe-mcp-for-woocommerce') . ' (' . (string) ($job['scope'] ?? $job['type'] ?? '') . ')'),
                esc_html((string) ($job['status'] ?? '')),
                esc_html(self::site_time_ts((int) ($job['updated_at'] ?? $job['created_at'] ?? 0))),
                esc_html((string) ($job['error'] ?? ($job['purpose'] ?? '')))
            );
        }
        echo '</tbody></table>';
        printf('<p class="description">%s</p>', esc_html__('get-backup-status shows a job in full.', 'safe-mcp-for-woocommerce'));
    }

    /**
     * A row's session (issue #1150): a link that filters the table to it,
     * and on the session's first row on the page an Undo session button
     * that runs rollback-session. The catch-all session ('' or 'default')
     * and a session with nothing left to undo get no button.
     *
     * @param array<string, bool> $seen sessions that already have a button on this page
     */
    private function render_session_cell(string $session_id, string $nonce, array &$seen): void
    {
        if (Snapshot_Store::is_loose_session($session_id)) {
            echo esc_html__('None', 'safe-mcp-for-woocommerce');
            return;
        }

        printf(
            '<a href="%s" title="%s"><code>%s</code></a>',
            esc_url(add_query_arg(['page' => self::SLUG, 'tab' => self::TAB_MUTATIONS, 'session_id' => rawurlencode($session_id)], admin_url('admin.php'))),
            esc_attr__('Show only this session', 'safe-mcp-for-woocommerce'),
            esc_html(strlen($session_id) > 24 ? substr($session_id, 0, 24) . '...' : $session_id)
        );

        if (isset($seen[ $session_id ])) {
            return;
        }
        $seen[ $session_id ] = true;

        $live = Snapshot_Store::live_count_for_session($session_id);
        if ($live < 1) {
            return;
        }
        printf(
            ' <button type="button" class="button wpmcp-undo-session" data-session="%s" data-count="%d" data-nonce="%s">%s</button>',
            esc_attr($session_id),
            (int) $live,
            esc_attr($nonce),
            esc_html__('Undo session', 'safe-mcp-for-woocommerce')
        );
    }

    /** The requested page of either tab, 1 or more. */
    private function current_page(): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page number on an admin list screen; it only picks which rows render.
        $paged = isset($_GET['paged']) && is_scalar($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
        return max(1, $paged);
    }

    /**
     * The list-table style "N items" count and page links (issues #759 and
     * #770). Each link keeps the tab and the filters, so paging never drops
     * them.
     *
     * @param array<string, mixed> $filters
     */
    private function render_pagination(string $tab, array $filters, int $total, int $paged, int $pages, string $which): void
    {
        printf('<div class="tablenav %s"><div class="tablenav-pages%s">', esc_attr($which), $pages > 1 ? '' : ' one-page');
        printf(
            '<span class="displaying-num">%s</span>',
            /* translators: %s: number of rows (undo points or requests). */
            esc_html(sprintf(_n('%s item', '%s items', $total, 'safe-mcp-for-woocommerce'), number_format_i18n($total)))
        );

        if ($pages > 1) {
            $base = add_query_arg(
                array_map('rawurlencode', array_merge(['page' => self::SLUG, 'tab' => $tab], array_map('strval', $filters))),
                admin_url('admin.php')
            );
            echo ' <span class="pagination-links">';
            // Core list-table paging (issue #1125): first, previous, "N of M",
            // next and last, a disabled span where a button would go nowhere.
            $this->render_page_button('first-page', __('First page', 'safe-mcp-for-woocommerce'), '&laquo;', $paged > 1 ? 1 : null, $base);
            $this->render_page_button('prev-page', __('Previous page', 'safe-mcp-for-woocommerce'), '&lsaquo;', $paged > 1 ? $paged - 1 : null, $base);
            printf(
                '<span class="screen-reader-text">%s</span><span id="table-paging-%s" class="paging-input"><span class="tablenav-paging-text">%s</span></span>',
                esc_html__('Current Page', 'safe-mcp-for-woocommerce'),
                esc_attr($which),
                sprintf(
                    /* translators: 1: current page, 2: total pages. */
                    esc_html_x('%1$s of %2$s', 'paging', 'safe-mcp-for-woocommerce'),
                    esc_html(number_format_i18n($paged)),
                    '<span class="total-pages">' . esc_html(number_format_i18n($pages)) . '</span>'
                )
            );
            $this->render_page_button('next-page', __('Next page', 'safe-mcp-for-woocommerce'), '&rsaquo;', $paged < $pages ? $paged + 1 : null, $base);
            $this->render_page_button('last-page', __('Last page', 'safe-mcp-for-woocommerce'), '&raquo;', $paged < $pages ? $pages : null, $base);
            echo '</span>';
        }

        echo '</div><br class="clear" /></div>';
    }

    /** One core paging button, or its disabled span when $target is null. */
    private function render_page_button(string $class, string $label, string $glyph, ?int $target, string $base): void
    {
        if (null === $target) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $glyph is one of the literal HTML entities passed above.
            printf('<span class="tablenav-pages-navspan button disabled" aria-hidden="true">%s</span> ', $glyph);
            return;
        }
        printf(
            '<a class="%s button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a> ',
            esc_attr($class),
            esc_url(add_query_arg('paged', $target, $base)),
            esc_html($label),
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $glyph is one of the literal HTML entities passed above.
            $glyph
        );
    }

    private function render_requests(): void
    {
        $filters  = $this->request_filters();
        $per_page = self::REQUESTS_PER_PAGE;
        $paged    = $this->current_page();

        // Every stored request is reachable through the pages (issue #770);
        // the CSV export keeps taking the whole filtered log in one go.
        $total = Request_Log::count($filters);
        $pages = max(1, (int) ceil($total / $per_page));
        if ($paged > $pages) {
            // A page past the end (rows pruned or filtered away since the
            // link was made) shows the last page instead of an empty table.
            $paged = $pages;
        }
        $rows = $this->get_requests($per_page, $filters, ($paged - 1) * $per_page);

        printf(
            '<p class="description">%s</p>',
            esc_html(
                Request_Log::is_capturing_arguments()
                    ? __('Argument capture is ON: tool arguments are stored with secret-looking values redacted. Turn it off to stop recording payloads.', 'safe-mcp-for-woocommerce')
                    : __('Tool arguments are not recorded. Enable the wpmcp_request_log_capture_args option or filter to capture redacted payloads while debugging.', 'safe-mcp-for-woocommerce')
            )
        );

        $this->render_request_filter_form($filters);
        $this->render_pagination(self::TAB_REQUESTS, $filters, $total, $paged, $pages, 'top');

        echo '<table class="widefat striped"><thead><tr>'
            . '<th>' . esc_html__('When', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Tool', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Client', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Outcome', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Duration', 'safe-mcp-for-woocommerce') . '</th>'
            . '<th>' . esc_html__('Undo point', 'safe-mcp-for-woocommerce') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            printf(
                '<td>%s</td>',
                esc_html(self::site_time_ts((int) ($row['timestamp'] ?? 0)))
            );
            printf('<td>%s</td>', esc_html((string) ($row['tool'] ?? '')));
            printf('<td>%s</td>', esc_html((string) ($row['client'] ?? '')));
            /* translators: %s: machine-readable error code. */
            $error_label = __('Error: %s', 'safe-mcp-for-woocommerce');
            printf(
                '<td>%s</td>',
                empty($row['ok'])
                    ? esc_html(sprintf($error_label, (string) ($row['error_code'] ?? '')))
                    : esc_html__('OK', 'safe-mcp-for-woocommerce')
            );
            /* translators: %d: duration in milliseconds. */
            $duration_label = __('%d ms', 'safe-mcp-for-woocommerce');
            printf(
                '<td>%s</td>',
                esc_html(sprintf($duration_label, (int) ($row['duration_ms'] ?? 0)))
            );
            echo '<td>';
            $this->render_undo_link((string) ($row['operation_id'] ?? ''));
            echo '</td></tr>';
        }

        echo '</tbody></table>';
        $this->render_pagination(self::TAB_REQUESTS, $filters, $total, $paged, $pages, 'bottom');
    }

    /**
     * A row that took a snapshot links to that exact operation on the History
     * screen, so an admin can go from a suspect or failed write straight to
     * the row whose Restore button undoes it.
     */
    private function render_undo_link(string $operation_id): void
    {
        if ('' === $operation_id) {
            echo '-';
            return;
        }

        printf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=wpmcp') . '#' . History_Page::row_anchor($operation_id)),
            esc_html__('View in History', 'safe-mcp-for-woocommerce')
        );
    }

    /** @return array<string, string> the Requests tab filters present in the query string */
    private function request_filters(): array
    {
        $filters = [];
        foreach (self::REQUEST_FILTERS as $key) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter; the export verifies its own nonce before using these.
            $value = isset($_GET[ $key ]) ? sanitize_text_field(wp_unslash($_GET[ $key ])) : '';
            if ('' !== $value) {
                $filters[ $key ] = $value;
            }
        }
        return $filters;
    }

    /** @param array<string, string> $filters */
    private function render_request_filter_form(array $filters): void
    {
        echo '<form method="get">';
        printf('<input type="hidden" name="page" value="%s" />', esc_attr(self::SLUG));
        printf('<input type="hidden" name="tab" value="%s" />', esc_attr(self::TAB_REQUESTS));
        printf(
            '<input type="date" name="date_from" value="%s" aria-label="%s" />',
            esc_attr($filters['date_from'] ?? ''),
            esc_attr__('From date (UTC)', 'safe-mcp-for-woocommerce')
        );
        printf(
            '<input type="date" name="date_to" value="%s" aria-label="%s" />',
            esc_attr($filters['date_to'] ?? ''),
            esc_attr__('To date (UTC)', 'safe-mcp-for-woocommerce')
        );
        // Dropdowns of the users and tools in the log (issue #1126).
        $rows  = Request_Log::list();
        $tools = array_values(array_unique(array_filter(array_map(static fn (array $r): string => (string) ($r['tool'] ?? ''), $rows), 'strlen')));
        sort($tools);
        $users = array_values(array_unique(array_map(static fn (array $r): int => (int) ($r['user_id'] ?? 0), $rows)));
        sort($users);
        $this->render_select('user_id', __('All users', 'safe-mcp-for-woocommerce'), self::user_choices($users), (string) ($filters['user_id'] ?? ''));
        $this->render_select('tool', __('All tools', 'safe-mcp-for-woocommerce'), array_combine($tools, $tools) ?: [], (string) ($filters['tool'] ?? ''));
        $outcome = $filters['outcome'] ?? '';
        echo '<select name="outcome">';
        foreach ([ '' => __('Any outcome', 'safe-mcp-for-woocommerce'), 'ok' => __('OK', 'safe-mcp-for-woocommerce'), 'error' => __('Error', 'safe-mcp-for-woocommerce') ] as $value => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($outcome, $value, false), esc_html($label));
        }
        echo '</select> ';
        printf('<button type="submit" class="button">%s</button> ', esc_html__('Filter', 'safe-mcp-for-woocommerce'));
        printf(
            '<a class="button" href="%s">%s</a>',
            esc_url(wp_nonce_url(add_query_arg(array_merge([ 'action' => self::EXPORT_ACTION ], $filters), admin_url('admin-post.php')), self::EXPORT_ACTION)),
            esc_html__('Export CSV', 'safe-mcp-for-woocommerce')
        );
        echo '</form>';
    }

    /** @return array<string, mixed> */
    private function filters_from_request(): array
    {
        $filters = [];
        foreach (['user_id', 'tool_name', 'domain', 'object_type', 'object_id', 'session_id', 'date_from', 'date_to'] as $key) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter on an admin list screen; the value only narrows what is displayed.
            $value = isset($_GET[ $key ]) ? sanitize_text_field(wp_unslash($_GET[ $key ])) : '';
            if ('' !== $value) {
                $filters[ $key ] = $value;
            }
        }
        return $filters;
    }

    /** @param array<string, mixed> $filters */
    private function render_filter_form(array $filters): void
    {
        echo '<form method="get">';
        // The submenu callback is registered only under self::SLUG (see Plugin::register_admin_menu),
        // so $_GET['page'] can never hold anything else here; echo the constant instead of
        // round-tripping the superglobal.
        printf('<input type="hidden" name="page" value="%s" />', esc_attr(self::SLUG));
        printf('<input type="hidden" name="tab" value="%s" />', esc_attr(self::TAB_MUTATIONS));
        $values = Snapshot_Store::filter_values();
        $this->render_select('tool_name', __('All tools', 'safe-mcp-for-woocommerce'), array_combine($values['tools'], $values['tools']) ?: [], (string) ($filters['tool_name'] ?? ''));
        $this->render_select('user_id', __('All users', 'safe-mcp-for-woocommerce'), self::user_choices($values['users']), (string) ($filters['user_id'] ?? ''));
        printf(
            '<input type="text" name="session_id" placeholder="%s" value="%s" />',
            esc_attr__('Session', 'safe-mcp-for-woocommerce'),
            esc_attr((string) ($filters['session_id'] ?? ''))
        );
        printf(
            '<input type="date" name="date_from" value="%s" />',
            esc_attr((string) ($filters['date_from'] ?? ''))
        );
        printf(
            '<input type="date" name="date_to" value="%s" />',
            esc_attr((string) ($filters['date_to'] ?? ''))
        );
        printf('<button type="submit" class="button">%s</button>', esc_html__('Filter', 'safe-mcp-for-woocommerce'));
        echo '</form>';
    }

    /** A filter dropdown (issue #1126). @param array<string, string> $choices value => label */
    private function render_select(string $name, string $any, array $choices, string $current): void
    {
        printf('<select name="%s">', esc_attr($name));
        printf('<option value="">%s</option>', esc_html($any));
        foreach ($choices as $value => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr((string) $value), selected($current, (string) $value, false), esc_html($label));
        }
        echo '</select> ';
    }

    /**
     * @param int[] $ids
     * @return array<string, string> user id => display name
     */
    private static function user_choices(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $user = get_userdata($id);
            /* translators: %d: numeric user ID. */
            $out[ (string) $id ] = $user ? $user->display_name : (0 === $id ? __('No user', 'safe-mcp-for-woocommerce') : sprintf(__('User #%d', 'safe-mcp-for-woocommerce'), $id));
        }
        return $out;
    }

    private function render_restore_script(): void
    {
        $undo_text = [
            /* translators: 1: number of writes, 2: session id. */
            'ask'    => __('Undo %1$s writes from session %2$s, newest first? Each object goes back to how it was before the session. If any write cannot be undone, nothing is changed.', 'safe-mcp-for-woocommerce'),
            /* translators: %s: number of writes undone. */
            'done'   => __('Undid %s writes from the session.', 'safe-mcp-for-woocommerce'),
            'failed' => __('The session could not be undone.', 'safe-mcp-for-woocommerce'),
            /* translators: %s: what the change was, e.g. "update-post: About (#12)". */
            'restore'       => __('Restore %s to how it was before this change? Anything changed on it since is overwritten.', 'safe-mcp-for-woocommerce'),
            'restoreFailed' => __('The change could not be restored.', 'safe-mcp-for-woocommerce'),
        ];
        ?>
        <script>
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.wpmcp-restore');
            if (! btn) {
                return;
            }
            e.preventDefault();
            // Issue #1126: confirm naming the object, then show the refusal
            // instead of reloading as if it had worked.
            if (! window.confirm(wpmcpUndoText.restore.replace('%s', btn.dataset.label || btn.dataset.op))) {
                return;
            }
            btn.disabled = true;
            var body = new URLSearchParams();
            body.set('action', 'wpmcp_restore');
            body.set('operation_id', btn.dataset.op);
            body.set('nonce', btn.dataset.nonce);
            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success) {
                        window.location.reload();
                        return;
                    }
                    btn.disabled = false;
                    wpmcpUndoNotice(false, (res && res.data && res.data.message) ? res.data.message : wpmcpUndoText.restoreFailed);
                })
                .catch(function () {
                    btn.disabled = false;
                    wpmcpUndoNotice(false, wpmcpUndoText.restoreFailed);
                });
        });

        // Undo session (issue #1150): confirm with the number of writes,
        // run rollback-session, then show what it did or the refusal.
        var wpmcpUndoKey = 'wpmcpUndoSessionResult';
        var wpmcpUndoText = <?php echo wp_json_encode($undo_text); ?>;
        var wpmcpUndoNotice = function (ok, text) {
            var wrap = document.querySelector('.wrap h1');
            if (! wrap) {
                return;
            }
            var div = document.createElement('div');
            div.className = 'notice is-dismissible ' + (ok ? 'notice-success' : 'notice-error');
            var p = document.createElement('p');
            p.textContent = text;
            div.appendChild(p);
            wrap.insertAdjacentElement('afterend', div);
        };
        try {
            var wpmcpSaved = window.sessionStorage.getItem(wpmcpUndoKey);
            if (wpmcpSaved) {
                window.sessionStorage.removeItem(wpmcpUndoKey);
                var wpmcpParsed = JSON.parse(wpmcpSaved);
                wpmcpUndoNotice(!! wpmcpParsed.ok, String(wpmcpParsed.text));
            }
        } catch (err) {}
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.wpmcp-undo-session');
            if (! btn) {
                return;
            }
            e.preventDefault();
            var count = parseInt(btn.dataset.count, 10) || 0;
            if (! window.confirm(wpmcpUndoText.ask.replace('%1$s', count).replace('%2$s', btn.dataset.session))) {
                return;
            }
            btn.disabled = true;
            var body = new URLSearchParams();
            body.set('action', '<?php echo esc_js(Restore_Controller::SESSION_ACTION); ?>');
            body.set('session_id', btn.dataset.session);
            body.set('nonce', btn.dataset.nonce);
            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success) {
                        var done = wpmcpUndoText.done.replace('%s', res.data.restored_count);
                        var warnings = (res.data.warnings || []).join(' ');
                        try {
                            window.sessionStorage.setItem(wpmcpUndoKey, JSON.stringify({ ok: true, text: warnings ? done + ' ' + warnings : done }));
                        } catch (err) {}
                        window.location.reload();
                        return;
                    }
                    btn.disabled = false;
                    wpmcpUndoNotice(false, (res && res.data && res.data.message) ? res.data.message : wpmcpUndoText.failed);
                })
                .catch(function () {
                    btn.disabled = false;
                    wpmcpUndoNotice(false, wpmcpUndoText.failed);
                });
        });
        </script>
        <?php
    }
}
