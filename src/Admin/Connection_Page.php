<?php

namespace WPMCP\Admin;

use WPMCP\Admin\Connection\Activity_Hook;
use WPMCP\Admin\Connection\Activity_Store;
use WPMCP\Admin\Connection\App_Icons;
use WPMCP\Admin\Connection\App_Labels;
use WPMCP\Admin\Connection\Check_Result;
use WPMCP\Admin\Connection\Check_View;
use WPMCP\Admin\Connection\Client_App;
use WPMCP\Admin\Connection\Connect_Flow;
use WPMCP\Admin\Connection\Connect_Screen;
use WPMCP\Admin\Connection\Connect_View;
use WPMCP\Admin\Connection\Connection_Ajax;
use WPMCP\Admin\Connection\Connection_Assets;
use WPMCP\Admin\Connection\Connection_Check;
use WPMCP\Admin\Connection\Flow_Input;
use WPMCP\Admin\Connection\Instructions_View;
use WPMCP\Admin\Connection\Provisioned;
use WPMCP\Admin\Connection\Sign_In;
use WPMCP\Admin\Connection\Snippet_View;
use WPMCP\Admin\Connection\Wp_Activity_Store;
use WPMCP\Admin\Connection\Wp_Evidence_Source;
use WPMCP\Auth\Client_Access;
use WPMCP\Auth\Client_Metadata_Document;
use WPMCP\Auth\Client_Store;
use WPMCP\Auth\Mcp_Resource;
use WPMCP\Auth\Network_Sites;
use WPMCP\Auth\OAuth_Config;
use WPMCP\Connect\Bundle_Builder;
use WPMCP\Connect\Client_Config_Generator;
use WPMCP\Connect\Connection_Tester;
use WPMCP\Connect\Exposure;
use WPMCP\Data_Registry;
use WPMCP\Identity\Identity_Store;
use WPMCP\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The Connection screen (issue #76): the first-ten-minutes experience, in
 * three tabs. "Connect an app" walks four steps (pick the app, create the
 * connection, add it to the app, check it; src/Admin/Connection). It
 * provisions a core Application Password for a chosen user (with
 * least-privilege guidance), shows only the chosen app's filled config and
 * checks the connection against what WordPress recorded. "Connections" lists
 * the passwords it issued (revoke) and the OAuth clients; "Settings" holds the
 * master exposure switch, OAuth sign-in, management access, the uninstall
 * choice and Advanced (endpoint, self-test, OAuth discovery, proxies). It also
 * serves the secret-free desktop bundle.
 *
 * Credential handling contract:
 *  - The plaintext Application Password exists only in the provision
 *    request's response array, rendered once. It is never stored, logged,
 *    audited, or echoed on any later render.
 *  - The persisted ledger (self::OPTION) holds only user_id, uuid, name,
 *    and a timestamp - enough to list and revoke, never enough to connect.
 *  - Revocation calls the core WP_Application_Passwords API, so the hashed
 *    credential is deleted and the connected client is cut off immediately.
 *
 * Every state-changing action requires the management capability AND a valid nonce;
 * provisioning additionally requires edit_user on the target user and
 * defers to core's wp_is_application_passwords_available_for_user() check
 * rather than bypassing it.
 */
class Connection_Page
{
    public const SLUG         = 'wpmcp-connection';
    public const NONCE_ACTION = 'wpmcp_connection';
    public const OPTION       = 'wpmcp_connection_passwords';

    /** The last self-test result, shown under Settings, Advanced (issue #1127): { ok, message, at }. */
    public const SELF_TEST_OPTION = 'wpmcp_connection_last_self_test';

    /** The Connect tab's AJAX actions (Connection_Ajax). Named here so registering them loads no class. */
    public const AJAX_CREATE = 'wpmcp_connection_create';
    public const AJAX_CHECK  = 'wpmcp_connection_check';

    /** Prefix of the hour-long transient that remembers a create form's request id. */
    private const REQUEST_PREFIX = 'wpmcp_conn_req_';

    /**
     * Dispatch a posted screen action. Returns null when no action was
     * posted, otherwise a result array for render(); failures come back as
     * ['error' => message] so the screen can show them inline.
     *
     * @param array $post The (unslashed) POST payload.
     * @return array<string, mixed>|null
     */
    public function handle_request(array $post): ?array
    {
        $action = self::str($post['wpmcp_connection_action'] ?? '');
        if ('' === $action) {
            return null;
        }

        if (! current_user_can(Management_Access::CAPABILITY)) {
            return ['error' => __('You are not allowed to manage MCP connections on this site.', 'safe-mcp-for-woocommerce')];
        }

        if (! wp_verify_nonce(self::str($post['_wpnonce'] ?? ''), self::NONCE_ACTION)) {
            return ['error' => __('Security check failed. Reload the page and try again.', 'safe-mcp-for-woocommerce')];
        }

        switch ($action) {
            case 'provision':
                return $this->provision($post);
            case 'revoke':
                return $this->revoke($post);
            case 'toggle':
                Exposure::set_enabled('1' === self::str($post['enabled'] ?? ''));
                return ['action' => 'toggle', 'enabled' => Exposure::is_enabled()];
            case 'self_test':
                $self_test = (new Connection_Tester())->test();
                update_option(self::SELF_TEST_OPTION, [
                    'ok'      => ! empty($self_test['ok']),
                    'message' => (string) ($self_test['message'] ?? ''),
                    'at'      => time(),
                ], false);
                return ['action' => 'self_test', 'self_test' => $self_test];
            case 'oauth_enabled':
                return $this->set_oauth_enabled($post);
            case 'oauth_client_approval':
                Client_Metadata_Document::set_requires_approval('1' === self::str($post['require'] ?? ''));
                return ['action' => 'oauth_client_approval'];
            case 'oauth_client_approve':
            case 'oauth_client_deny':
            case 'oauth_client_forget':
                return $this->decide_oauth_client($action, self::str($post['client_id'] ?? ''));
            case 'oauth_access':
                return $this->set_oauth_access($post);
            case 'uninstall_data':
                return $this->set_uninstall_data($post);
            case 'management_access':
                return $this->save_management_access($post);
            case 'check':
                return $this->check_action($post);
        }

        return ['error' => __('Unknown action.', 'safe-mcp-for-woocommerce')];
    }

    private function provision(array $post): array
    {
        $user_id = (int) ($post['user_id'] ?? 0);
        $user    = get_userdata($user_id);
        if (! $user) {
            return ['error' => __('That user does not exist.', 'safe-mcp-for-woocommerce')];
        }

        if (! current_user_can('edit_user', $user_id)) {
            return ['error' => __('You are not allowed to create credentials for that user.', 'safe-mcp-for-woocommerce')];
        }

        if (! wp_is_application_passwords_available_for_user($user)) {
            $state = \WPMCP\Auth\App_Passwords_Status::state(true);
            return ['error' => $state['message'] ?? __('Application Passwords are not available for that user on this site. They require HTTPS (or a local environment) and must not be disabled by another plugin.', 'safe-mcp-for-woocommerce')];
        }

        $client  = Client_App::from_request(self::str($post['client'] ?? ''));
        $request = $this->request_key(self::str($post['request_id'] ?? ''), $user_id);
        if ('' !== $request && false !== get_transient($request)) {
            return ['error' => __('Already created. Lost the password? Revoke that connection on the Connections tab and create a new one.', 'safe-mcp-for-woocommerce')];
        }

        $name = sanitize_text_field(self::str($post['name'] ?? ''));
        if ('' === $name) {
            $name = null === $client ? sprintf('wpmcp (%s)', gmdate('Y-m-d H:i')) : self::default_name($client);
        }

        $created = \WP_Application_Passwords::create_new_application_password($user_id, ['name' => $name]);
        if (is_wp_error($created)) {
            return ['error' => $created->get_error_message()];
        }

        [$password, $item] = $created;
        if ('' !== $request) {
            set_transient($request, 1, HOUR_IN_SECONDS);
        }

        $created   = time();
        $records   = $this->records();
        $records[] = [
            'user_id' => $user_id,
            'uuid'    => (string) $item['uuid'],
            'name'    => (string) $item['name'],
            'created' => $created,
            'client'  => null === $client ? '' : $client->value,
        ];
        update_option(self::OPTION, $records, false);

        return [
            'action'      => 'provision',
            'client'      => null === $client ? '' : $client->value,
            'created'     => $created,
            'user_login'  => $user->user_login,
            'name'        => (string) $item['name'],
            'uuid'        => (string) $item['uuid'],
            'password'    => $password,
            'auth_header' => Client_Config_Generator::auth_header($user->user_login, $password),
            'configs'     => (new Client_Config_Generator())->configs($user->user_login, $password),
        ];
    }

    /**
     * Approve, deny or forget an OAuth client that connected with a Client
     * ID Metadata Document (issue #388). Denying also revokes its tokens.
     */
    private function decide_oauth_client(string $action, string $client_id): array
    {
        // Matched exactly against the registry keys, which are the
        // validated client_ids themselves, so no sanitizing rewrite here.
        $done = match ($action) {
            'oauth_client_approve' => Client_Metadata_Document::approve($client_id),
            'oauth_client_deny'    => Client_Metadata_Document::deny($client_id),
            default                => Client_Metadata_Document::forget($client_id),
        };

        if (! $done) {
            return ['error' => __('That OAuth client is not known to this site.', 'safe-mcp-for-woocommerce')];
        }

        return ['action' => $action, 'client_id' => $client_id];
    }

    /**
     * Change the access level of one OAuth connection (issue #454). Applies
     * on the connection's next request; a level above what the client was
     * granted has no effect, because the token's own scope still applies.
     */
    private function set_oauth_access(array $post): array
    {
        $client_id = self::str($post['client_id'] ?? '');
        $user_id   = (int) self::str($post['user_id'] ?? '');
        $parsed    = Client_Access::parse(self::str($post['access'] ?? ''));

        if (null === $parsed || ! Client_Access::set($client_id, $user_id, $parsed[0], $parsed[1])) {
            return ['error' => __('That access level could not be applied.', 'safe-mcp-for-woocommerce')];
        }

        return ['action' => 'oauth_access', 'client_id' => $client_id];
    }

    /**
     * The "Allow OAuth sign-in" switch (issue #842). Refused while the
     * WPMCP_OAUTH_ENABLED constant or the wpmcp_oauth_enabled filter
     * decides, so the screen never saves a value that has no effect.
     */
    private function set_oauth_enabled(array $post): array
    {
        if (! OAuth_Config::set_enabled('1' === self::str($post['enabled'] ?? ''))) {
            return ['error' => 'constant' === OAuth_Config::locked_by()
                ? __('OAuth sign-in is fixed by WPMCP_OAUTH_ENABLED in wp-config.php, so this switch cannot change it.', 'safe-mcp-for-woocommerce')
                : __('OAuth sign-in is fixed by the wpmcp_oauth_enabled filter in a plugin or mu-plugin, so this switch cannot change it.', 'safe-mcp-for-woocommerce')];
        }

        return ['action' => 'oauth_enabled', 'enabled' => OAuth_Config::is_enabled()];
    }

    /**
     * The "delete all data when the plugin is deleted" opt-in (issue #480).
     * One network-wide value, so on multisite only a network admin may
     * change it. Refused while WPMCP_UNINSTALL_DELETE_DATA decides.
     */
    private function set_uninstall_data(array $post): array
    {
        if (Data_Registry::is_forced()) {
            return ['error' => __('This setting is fixed by WPMCP_UNINSTALL_DELETE_DATA in wp-config.php.', 'safe-mcp-for-woocommerce')];
        }
        if (is_multisite() && ! current_user_can('manage_network_options')) {
            return ['error' => __('Only a network administrator can change this setting.', 'safe-mcp-for-woocommerce')];
        }

        Data_Registry::set_delete_on_uninstall('1' === self::str($post['delete'] ?? ''));

        return ['action' => 'uninstall_data', 'delete' => Data_Registry::delete_on_uninstall()];
    }

    /**
     * Save who may manage WPMCP (issue #479). Management_Access::save()
     * refuses a self-lockout, a non-administrator, a stale form and any
     * save while the wp-config.php constant is defined.
     */
    private function save_management_access(array $post): array
    {
        $ids = $post['management_ids'] ?? [];
        $ids = is_array($ids) ? array_map(static fn ($id): string => self::str($id), $ids) : [];

        $saved = Management_Access::save(
            self::str($post['management_mode'] ?? ''),
            $ids,
            get_current_user_id(),
            self::str($post['management_version'] ?? '')
        );
        if (is_wp_error($saved)) {
            return ['error' => $saved->get_error_message()];
        }

        return ['action' => 'management_access'];
    }

    private function revoke(array $post): array
    {
        $user_id = (int) ($post['user_id'] ?? 0);
        $uuid    = sanitize_text_field(self::str($post['uuid'] ?? ''));

        $deleted = \WP_Application_Passwords::delete_application_password($user_id, $uuid);
        // Deleted elsewhere (the user's profile screen, another plugin, the
        // user deleted): nothing is left to revoke, so the record just
        // leaves the list and the revoke still succeeds.
        $already_gone = is_wp_error($deleted) && 'application_password_not_found' === $deleted->get_error_code();
        if (is_wp_error($deleted) && ! $already_gone) {
            return ['error' => $deleted->get_error_message()];
        }

        $records = array_values(array_filter(
            $this->records(),
            static fn (array $record): bool => $record['uuid'] !== $uuid || (int) $record['user_id'] !== $user_id
        ));
        update_option(self::OPTION, $records, false);
        (new Wp_Activity_Store())->forget($user_id, $uuid);

        return ['action' => 'revoke', 'uuid' => $uuid, 'already_gone' => $already_gone];
    }

    /**
     * Request values may arrive as arrays (?_wpnonce[]=x); treat anything
     * that is not a plain string as absent instead of letting a (string)
     * cast raise an array-to-string warning.
     *
     * @param mixed $value
     */
    private static function str($value): string
    {
        return is_string($value) ? $value : '';
    }

    /** @return array<int, array{user_id: int, uuid: string, name: string, created: int, client?: string}> */
    private function records(): array
    {
        $records = get_option(self::OPTION, []);
        return is_array($records) ? $records : [];
    }

    /**
     * A create form carries a one-time request id, so a reload that sends
     * the form again is refused instead of making a second password. The key
     * of the transient that remembers it for an hour; nothing secret is kept.
     * A form without an id (an older page) is not checked.
     */
    private function request_key(string $request_id, int $user_id): string
    {
        $request_id = sanitize_key($request_id);

        return '' === $request_id ? '' : self::REQUEST_PREFIX . md5($user_id . '|' . $request_id);
    }

    /** A connection's default name: the app and today's date, such as "Claude Code (November 23, 2026)". */
    public static function default_name(Client_App $app): string
    {
        return sprintf('%s (%s)', (new App_Labels())->label($app), (string) wp_date(self::str(get_option('date_format'))));
    }

    /**
     * The screen's handlers: behind the management guard, the desktop
     * bundle download and the two AJAX actions of the Connect tab; the
     * stylesheet and script, which load on this screen only; and the record
     * of what apps do with the passwords, which "Check connection" and the
     * Connections tab read (Activity_Hook on the MCP route, issue #1258).
     * Registering loads no class: the views, the AJAX handler and the
     * recorder are built when they run, so other screens and requests keep
     * their footprint (FootprintTest).
     */
    public function register_handlers(): void
    {
        Management_Access::add_handler('admin_post_wpmcp_download_bundle', [$this, 'download_bundle']);
        Management_Access::add_handler('wp_ajax_' . self::AJAX_CREATE, [$this, 'ajax_create']);
        Management_Access::add_handler('wp_ajax_' . self::AJAX_CHECK, [$this, 'ajax_check']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('rest_post_dispatch', [Activity_Hook::class, 'on_rest_post_dispatch'], 99, 3);
    }

    /** admin-ajax: create a connection (Connection_Ajax::create()). */
    public function ajax_create(): void
    {
        $this->ajax()->create();
    }

    /** admin-ajax: check a connection (Connection_Ajax::check()). */
    public function ajax_check(): void
    {
        $this->ajax()->check();
    }

    /** admin_enqueue_scripts: the screen's stylesheet and script, on this screen only. */
    public function enqueue_assets(string $hook_suffix): void
    {
        if (! str_ends_with($hook_suffix, '_page_' . self::SLUG)) {
            return;
        }
        (new Connection_Assets())->enqueue($hook_suffix);
    }

    private function ajax(): Connection_Ajax
    {
        $labels = new App_Labels();
        $checks = new Check_View($labels);

        return new Connection_Ajax($this, self::connect_view($labels, $checks), $checks);
    }

    private static function connect_view(App_Labels $labels, Check_View $checks): Connect_View
    {
        return new Connect_View($labels, new App_Icons(), new Instructions_View(new Snippet_View()), $checks);
    }

    /**
     * The Connect tab's state: the app, route and variant the request names
     * (client, route, variant) and the result of the action it posted. A
     * password provisioned without an app (an older form) is shown with the
     * instructions for any MCP client.
     *
     * @param array<string, string>     $request
     * @param array<string, mixed>|null $result
     */
    public function connect_screen(array $request, ?array $result): Connect_Screen
    {
        $provisioned = $this->provisioned($result);
        $app         = Client_App::from_request($request['client'] ?? '');
        if (null === $app && null !== $provisioned) {
            $app = Client_App::Other;
        }
        $connector = Client_App::ClaudeDesktop === $app && 'connector' === ($request['route'] ?? '');
        $endpoint  = Client_Config_Generator::endpoint();
        $warning   = Client_Config_Generator::public_address_warning($endpoint);
        $passwords = \WPMCP\Auth\App_Passwords_Status::state(true);
        $check     = is_array($result) && ($result['check'] ?? null) instanceof Check_Result ? $result['check'] : null;
        $uuid      = null !== $provisioned ? $provisioned->uuid : ($request['uuid'] ?? '');
        $record    = '' === $uuid ? null : $this->record($uuid);
        $locked    = OAuth_Config::locked_by();

        $model = (new Connect_Flow())->model(new Flow_Input(
            app: $app,
            connector: $connector,
            created: null !== $provisioned || null !== $record,
            verified: null !== $check && $check->is_done(),
            passwords_unavailable: $passwords['available'] ? null : ($passwords['reason'] ?? 'disabled'),
            oauth_on: OAuth_Config::is_enabled(),
            oauth_locked: null !== $locked,
            public_address: null === $warning,
        ));

        return new Connect_Screen(
            model: $model,
            app: $app,
            connector: $connector,
            generic: 'generic' === ($request['variant'] ?? ''),
            public_address: null === $warning,
            endpoint: $endpoint,
            nonce: wp_create_nonce(self::NONCE_ACTION),
            default_name: null === $app ? '' : self::default_name($app),
            request_id: wp_generate_uuid4(),
            provisioned: $provisioned,
            created_name: null === $record ? '' : $record['name'],
            passwords_message: $passwords['message'] ?? '',
            oauth_locked_message: Client_Config_Generator::oauth_switch_on(true),
            public_warning: (string) $warning,
            bundle_url: wp_nonce_url(admin_url('admin-post.php?action=wpmcp_download_bundle'), self::NONCE_ACTION),
            check_uuid: null === $record ? '' : $record['uuid'],
            check: $check,
            self_test: is_array($result) ? $this->self_test_checks($result['checks'] ?? null) : [],
        );
    }

    /**
     * "Check connection": for an OAuth app, its clients and tokens; for a
     * password app, the password this screen issued (by uuid). Null when the
     * uuid is not one of this screen's records. With $self_test, a check
     * that finds nothing also runs the server self-test and keeps it as the
     * last result.
     *
     * @return array{result: Check_Result, self_test: list<array{id: string, label: string, ok: bool, detail: string}>}|null
     */
    public function check(Client_App $app, bool $connector, string $uuid, bool $self_test): ?array
    {
        $source = new Wp_Evidence_Source(new Wp_Activity_Store());
        $mcp_on = Exposure::is_enabled();
        if (Sign_In::Oauth === $app->sign_in($connector)) {
            $result = (new Connection_Check())->for_oauth($source->for_oauth($app), $mcp_on);
        } else {
            $record = $this->record($uuid);
            if (null === $record) {
                return null;
            }
            $result = (new Connection_Check())->for_password($source->for_password($record['user_id'], $record['uuid'], $record['created']), $mcp_on);
        }

        return [
            'result'    => $result,
            'self_test' => $self_test && ! $result->is_done() ? $this->run_self_test() : [],
        ];
    }

    /**
     * The no-JavaScript "Check connection" form.
     *
     * @param array<mixed> $post
     * @return array<string, mixed>
     */
    private function check_action(array $post): array
    {
        $app = Client_App::from_request(self::str($post['client'] ?? ''));
        if (null === $app) {
            return ['error' => __('Unknown app.', 'safe-mcp-for-woocommerce')];
        }

        $connector = 'connector' === self::str($post['route'] ?? '');
        $uuid      = self::str($post['uuid'] ?? '');
        $checked   = $this->check($app, $connector, $uuid, '1' === self::str($post['selftest'] ?? ''));
        if (null === $checked) {
            return ['error' => __('That connection was not made on this screen.', 'safe-mcp-for-woocommerce')];
        }

        return ['action' => 'check', 'check' => $checked['result'], 'checks' => $checked['self_test']];
    }

    /**
     * The server self-test, kept as the last result for the Settings tab,
     * as its checks with the reachability line first.
     *
     * @return list<array{id: string, label: string, ok: bool, detail: string}>
     */
    private function run_self_test(): array
    {
        $test = (new Connection_Tester())->test();
        update_option(self::SELF_TEST_OPTION, [
            'ok'      => $test['ok'],
            'message' => $test['message'],
            'at'      => time(),
        ], false);

        $checks = [ [ 'id' => 'reachable', 'label' => __('The endpoint answers', 'safe-mcp-for-woocommerce'), 'ok' => $test['ok'], 'detail' => $test['message'] ] ];
        foreach ($test['checks'] as $check) {
            $checks[] = $check;
        }

        return $checks;
    }

    /**
     * Self-test checks from a request result, keeping only well-formed rows.
     *
     * @return list<array{id: string, label: string, ok: bool, detail: string}>
     */
    private function self_test_checks(mixed $checks): array
    {
        $out = [];
        foreach (is_array($checks) ? $checks : [] as $check) {
            if (! is_array($check) || ! is_string($check['id'] ?? null) || ! is_string($check['label'] ?? null) || ! is_string($check['detail'] ?? null)) {
                continue;
            }
            $out[] = [ 'id' => $check['id'], 'label' => $check['label'], 'ok' => true === ($check['ok'] ?? null), 'detail' => $check['detail'] ];
        }

        return $out;
    }

    /**
     * The password a provision result carries, with the configs filled from
     * it again (Client_Config_Generator is deterministic).
     *
     * @param array<string, mixed>|null $result
     */
    private function provisioned(?array $result): ?Provisioned
    {
        if (null === $result || 'provision' !== ($result['action'] ?? null)) {
            return null;
        }
        $login    = $result['user_login'] ?? null;
        $password = $result['password'] ?? null;
        $uuid     = $result['uuid'] ?? null;
        $name     = $result['name'] ?? null;
        if (! is_string($login) || ! is_string($password) || ! is_string($uuid) || ! is_string($name)) {
            return null;
        }

        return new Provisioned(
            name: $name,
            uuid: $uuid,
            user_login: $login,
            password: $password,
            auth_header: Client_Config_Generator::auth_header($login, $password),
            configs: (new Client_Config_Generator())->configs($login, $password),
            created: is_int($result['created'] ?? null) ? $result['created'] : time(),
        );
    }

    /**
     * The ledger record of a password this screen issued.
     *
     * @return array{user_id: int, uuid: string, name: string, created: int}|null
     */
    private function record(string $uuid): ?array
    {
        if ('' === $uuid) {
            return null;
        }
        foreach ($this->records() as $record) {
            if ($uuid === $record['uuid']) {
                return [ 'user_id' => $record['user_id'], 'uuid' => $record['uuid'], 'name' => $record['name'], 'created' => $record['created'] ];
            }
        }

        return null;
    }

    /**
     * admin_post handler for the desktop bundle download. GET + nonce (the
     * download link is nonce'd) + the management capability. The bundle is secret-free
     * (see Bundle_Builder), so serving it discloses only the endpoint URL -
     * the same fact this screen already shows.
     *
     * @param callable|null $sender Test seam: receives the built bundle path
     *                              instead of streaming it and exiting.
     * @return \WP_Error|null WP_Error on refusal when a $sender is injected;
     *                        otherwise streams and exits.
     */
    public function download_bundle(?callable $sender = null)
    {
        $nonce      = is_string($_GET['_wpnonce'] ?? null) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        $authorized = current_user_can(Management_Access::CAPABILITY)
            && '' !== $nonce
            && wp_verify_nonce($nonce, self::NONCE_ACTION);

        if (! $authorized) {
            if (null !== $sender) {
                return new \WP_Error('wpmcp_forbidden', __('You are not allowed to download the connection bundle.', 'safe-mcp-for-woocommerce'));
            }
            wp_die(esc_html__('You are not allowed to download the connection bundle.', 'safe-mcp-for-woocommerce'), 403);
        }

        $path = (new Bundle_Builder())->build(Client_Config_Generator::endpoint());

        if (null !== $sender) {
            $sender($path);
            return null;
        }

        // readfile() is an error under WordPress.WP.AlternativeFunctions;
        // file_get_contents() is one of the three names the Plugin Check
        // review ruleset excludes from it. The bundle is a few kilobytes of
        // generated JSON, so reading it into memory costs nothing.
        $bundle = (string) file_get_contents($path);
        wp_delete_file($path);

        nocache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="wpmcp.mcpb"');
        header('Content-Length: ' . (string) strlen($bundle));
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary .mcpb body sent as application/octet-stream; any escaper would corrupt it.
        echo $bundle;
        exit;
    }

    public function render(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce + capability are verified inside handle_request().
        $post     = wp_unslash($_POST);
        $result   = $this->handle_request($post);
        $endpoint = Client_Config_Generator::endpoint();
        $nonce    = wp_create_nonce(self::NONCE_ACTION);
        $exposed  = Exposure::is_enabled();
        $labels   = new App_Labels();
        $icons    = new App_Icons();
        $count    = count(array_filter($this->records(), static fn (array $record): bool => null !== \WP_Application_Passwords::get_user_application_password($record['user_id'], $record['uuid']))) + count(Client_Access::connections());
        $badge    = $count > 0 ? sprintf(' <span class="wpmcp-count">%d</span>', $count) : '';
        ?>
        <div class="wrap wpmcp-connection">
            <div class="wpmcp-head">
                <div class="wpmcp-title">
                    <span class="wpmcp-mark" aria-hidden="true"><?php echo wp_kses($icons->mark(), $icons->allowed_mark_html()); ?></span>
                    <h1><?php echo esc_html(Plugin::page_title(_x('Connection', 'admin menu', 'safe-mcp-for-woocommerce'))); ?></h1>
                </div>
                <a class="wpmcp-chip<?php echo esc_attr($exposed ? '' : ' is-off'); ?>" href="#wpmcp-tab-settings"><i aria-hidden="true"></i><?php echo esc_html($exposed ? __('MCP is on', 'safe-mcp-for-woocommerce') : __('MCP is off', 'safe-mcp-for-woocommerce')); ?><span class="screen-reader-text"><?php echo esc_html__('. Change it on the Settings tab.', 'safe-mcp-for-woocommerce'); ?></span></a>
            </div>
            <p class="wpmcp-lede">
                <?php
                echo wp_kses(sprintf(
                    /* translators: %s: site name */
                    __('Connect an AI app to %s in four short steps.', 'safe-mcp-for-woocommerce'),
                    '<strong>' . esc_html(get_bloginfo('name')) . '</strong>'
                ), [ 'strong' => [] ]);
                ?>
            </p>
            <hr class="wp-header-end">
            <?php $this->render_notices($result, $nonce, $exposed); ?>
            <nav class="wpmcp-tabs" aria-label="<?php echo esc_attr__('Connection screen sections', 'safe-mcp-for-woocommerce'); ?>">
                <a class="wpmcp-tab" href="#wpmcp-tab-connect" data-tab="connect"><?php echo esc_html__('Connect an app', 'safe-mcp-for-woocommerce'); ?></a>
                <a class="wpmcp-tab" href="#wpmcp-tab-connections" data-tab="connections"><?php echo esc_html__('Connections', 'safe-mcp-for-woocommerce') . wp_kses($badge, [ 'span' => [ 'class' => true ] ]); ?></a>
                <a class="wpmcp-tab" href="#wpmcp-tab-settings" data-tab="settings"><?php echo esc_html__('Settings', 'safe-mcp-for-woocommerce'); ?></a>
            </nav>

            <div class="wpmcp-connection-panel" id="wpmcp-tab-connect" data-tab="connect">
                <?php self::connect_view($labels, new Check_View($labels))->render($this->connect_screen($this->request_values($post), $result)); ?>
                <p class="wpmcp-aside"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><span><?php echo esc_html__('The endpoint address, OAuth discovery and the full server self-test are on the Settings tab, under Advanced.', 'safe-mcp-for-woocommerce'); ?></span></p>
            </div><!-- /wpmcp-tab-connect -->

            <div class="wpmcp-connection-panel" id="wpmcp-tab-connections" data-tab="connections">
                <?php $this->render_connections($nonce, $icons, $labels); ?>
            </div><!-- /wpmcp-tab-connections -->

            <div class="wpmcp-connection-panel" id="wpmcp-tab-settings" data-tab="settings">
                <?php $this->render_settings($nonce, $endpoint, $exposed, $result); ?>
            </div><!-- /wpmcp-tab-settings -->
        </div>
        <?php
    }

    /**
     * The values the Connect tab reads (client, route, variant, uuid): from
     * the query string, or from the form just posted.
     *
     * @param array<mixed> $post
     * @return array<string, string>
     */
    private function request_values(array $post): array
    {
        $values = [];
        foreach ([ 'client', 'route', 'variant', 'uuid' ] as $key) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which app to show; no action is taken on it.
            $query          = isset($_GET[ $key ]) && is_string($_GET[ $key ]) ? sanitize_text_field(wp_unslash($_GET[ $key ])) : '';
            $posted         = sanitize_text_field(self::str($post[ $key ] ?? ''));
            $values[ $key ] = '' !== $posted ? $posted : $query;
        }

        return $values;
    }

    /**
     * Outcome notices, then the page-wide states: MCP switched off, a site
     * without HTTPS, and the multisite note.
     *
     * @param array<string, mixed>|null $result
     */
    private function render_notices(?array $result, string $nonce, bool $exposed): void
    {
        $action = is_array($result) ? ($result['action'] ?? '') : '';
        if (is_array($result) && is_string($result['error'] ?? null)) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($result['error']));
        } elseif ('revoke' === $action) {
            printf('<div class="notice notice-success"><p>%s</p></div>', esc_html(true === ($result['already_gone'] ?? null)
                ? __('Removed from the list. Its application password had already been deleted in WordPress, so nothing could sign in with it.', 'safe-mcp-for-woocommerce')
                : __('Application password revoked. The client it was issued to is disconnected.', 'safe-mcp-for-woocommerce')));
        } elseif ('oauth_enabled' === $action) {
            printf('<div class="notice notice-success"><p>%s</p></div>', esc_html(true === ($result['enabled'] ?? null)
                ? __('OAuth sign-in is on. ChatGPT and the Claude custom connector can now sign in.', 'safe-mcp-for-woocommerce')
                : __('OAuth sign-in is off. New sign-ins are refused, and so are OAuth connections made earlier.', 'safe-mcp-for-woocommerce')));
        } elseif ('management_access' === $action) {
            printf('<div class="notice notice-success"><p>%s</p></div>', esc_html__('Management access saved.', 'safe-mcp-for-woocommerce'));
        }

        if (! $exposed) {
            ?>
            <div class="notice notice-warning">
                <p><strong><?php echo esc_html__('MCP is off on this site.', 'safe-mcp-for-woocommerce'); ?></strong> <?php echo esc_html__('All WPMCP tools refuse, for all apps and passwords, until you turn it back on.', 'safe-mcp-for-woocommerce'); ?></p>
                <form method="post" action="#wpmcp-tab-connect">
                    <input type="hidden" name="wpmcp_connection_action" value="toggle">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                    <input type="hidden" name="enabled" value="1">
                    <p><?php submit_button(__('Turn MCP on', 'safe-mcp-for-woocommerce'), 'secondary', 'submit', false); ?></p>
                </form>
            </div>
            <?php
        }

        $passwords = \WPMCP\Auth\App_Passwords_Status::state(true);
        if (! $passwords['available'] && 'https_required' === ($passwords['reason'] ?? '')) {
            printf(
                '<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
                esc_html__('This site doesn\'t use HTTPS, so AI apps can\'t connect yet.', 'safe-mcp-for-woocommerce'),
                esc_html__('WordPress keeps application passwords off on sites without HTTPS. Serve the site over HTTPS, then reload this page.', 'safe-mcp-for-woocommerce')
            );
        }

        if (is_multisite()) {
            printf(
                '<div class="notice notice-info"><p><strong>%1$s</strong> %2$s</p></div>',
                esc_html(sprintf(
                    /* translators: %s: site name */
                    __('Network site: %s.', 'safe-mcp-for-woocommerce'),
                    get_bloginfo('name')
                )),
                esc_html(sprintf(
                    /* translators: %s: site address */
                    __('This screen connects apps to this site (%s). Each site in the network has its own address and its own connections. An application password belongs to the user, not the site, so it also signs in to other network sites where that user has a role.', 'safe-mcp-for-woocommerce'),
                    home_url('/')
                ))
            );
        }
    }

    /** The Connections tab: the passwords this screen issued, the OAuth clients and the scoped identities. */
    private function render_connections(string $nonce, App_Icons $icons, App_Labels $labels): void
    {
        $records  = $this->records();
        $oauth    = OAuth_Config::is_enabled() && [] !== $this->oauth_client_rows();
        $activity = new Wp_Activity_Store();

        if ([] === $records && ! $oauth) {
            ?>
            <div class="wpmcp-card wpmcp-empty">
                <h2><?php echo esc_html__('No apps connected yet', 'safe-mcp-for-woocommerce'); ?></h2>
                <p><?php echo esc_html__('Connect your first AI app in four short steps.', 'safe-mcp-for-woocommerce'); ?></p>
                <a class="button button-primary" href="#wpmcp-tab-connect"><?php echo esc_html__('Connect an app', 'safe-mcp-for-woocommerce'); ?></a>
            </div>
            <?php
        }

        if ([] !== $records) {
            ?>
            <div class="wpmcp-card">
                <h2><?php echo esc_html__('Apps with an application password', 'safe-mcp-for-woocommerce'); ?></h2>
                <p class="wpmcp-intro"><?php echo esc_html__('Revoke one and that app is disconnected at once. Last used is the newest MCP request the app made with its password, with the name the app reports.', 'safe-mcp-for-woocommerce'); ?></p>
                <table class="widefat striped wpmcp-table">
                    <thead><tr>
                        <th scope="col"><?php echo esc_html__('App', 'safe-mcp-for-woocommerce'); ?></th>
                        <th scope="col"><?php echo esc_html__('Signs in as', 'safe-mcp-for-woocommerce'); ?></th>
                        <th scope="col"><?php echo esc_html__('Created', 'safe-mcp-for-woocommerce'); ?></th>
                        <th scope="col"><?php echo esc_html__('Last used', 'safe-mcp-for-woocommerce'); ?></th>
                        <th scope="col"><span class="screen-reader-text"><?php echo esc_html__('Actions', 'safe-mcp-for-woocommerce'); ?></span></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($records as $record) : ?>
                        <?php $this->render_password_row($nonce, $record, $icons, $labels, $activity); ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }

        echo '<div class="wpmcp-card">';
        $this->render_oauth_clients($nonce);
        echo '</div>';

        $this->render_identities();
    }

    /**
     * One password this screen issued. Last used shows the newest MCP request
     * the route answered for it and the client's name (issue #1258), on
     * either protocol revision; a password WordPress accepted with no MCP
     * request says so.
     *
     * @param array{user_id: int, uuid: string, name: string, created: int, client?: string} $record
     */
    private function render_password_row(string $nonce, array $record, App_Icons $icons, App_Labels $labels, Activity_Store $activities): void
    {
        $user_id   = $record['user_id'];
        $user      = get_userdata($user_id);
        $item      = \WP_Application_Passwords::get_user_application_password($user_id, $record['uuid']);
        $gone      = null === $item;
        $last_used = null === $item ? 0 : (int) $item['last_used'];
        $activity  = $gone ? null : $activities->find($user_id, $record['uuid']);
        $app       = Client_App::from_request($record['client'] ?? '');
        $date      = self::str(get_option('date_format'));
        $time      = self::str(get_option('time_format'));
        $app_name  = null === $app ? '' : '<span>' . esc_html($labels->label($app)) . '</span>';
        ?>
        <tr data-uuid="<?php echo esc_attr($record['uuid']); ?>"<?php echo $gone ? ' class="is-gone"' : ''; ?>>
            <td>
                <div class="wpmcp-who">
                    <?php echo wp_kses($icons->tile($app ?? Client_App::Other), $icons->allowed_html()); ?>
                    <div><b><?php echo esc_html($record['name']); ?></b><?php echo wp_kses($app_name, [ 'span' => [] ]); ?></div>
                </div>
            </td>
            <td><?php echo esc_html($user instanceof \WP_User ? $user->user_login : '#' . $user_id); ?></td>
            <td><?php echo esc_html((string) wp_date($date, $record['created'])); ?></td>
            <td>
                <?php if ($gone) : ?>
                    <span class="wpmcp-status is-gone"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php echo esc_html__('Removed outside WPMCP', 'safe-mcp-for-woocommerce'); ?></span>
                    <span class="wpmcp-help"><?php echo esc_html__('Its application password was deleted in WordPress, so nothing can sign in with it.', 'safe-mcp-for-woocommerce'); ?></span>
                <?php elseif (null !== $activity) : ?>
                    <?php echo esc_html((string) wp_date(trim($date . ' ' . $time), $activity->last_at)); ?>
                    <span class="wpmcp-help"><?php echo esc_html($activity->client->is_named() ? $activity->client->label() : __('Unnamed MCP client', 'safe-mcp-for-woocommerce')); ?></span>
                <?php elseif ($last_used > 0) : ?>
                    <?php echo esc_html((string) wp_date($date, $last_used)); ?>
                    <span class="wpmcp-help"><?php echo esc_html__('Password accepted, no MCP request yet', 'safe-mcp-for-woocommerce'); ?></span>
                <?php else : ?>
                    <?php echo esc_html__('Not used yet', 'safe-mcp-for-woocommerce'); ?>
                <?php endif; ?>
            </td>
            <td class="wpmcp-actions-cell">
                <form method="post" action="#wpmcp-tab-connections">
                    <input type="hidden" name="wpmcp_connection_action" value="revoke">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $user_id); ?>">
                    <input type="hidden" name="uuid" value="<?php echo esc_attr($record['uuid']); ?>">
                    <?php
                    if ($gone) {
                        submit_button(__('Remove from list', 'safe-mcp-for-woocommerce'), 'secondary', 'submit', false, [ 'aria-label' => sprintf(
                            /* translators: %s: connection name */
                            __('Remove %s from the list', 'safe-mcp-for-woocommerce'),
                            $record['name']
                        ) ]);
                    } else {
                        submit_button(__('Revoke', 'safe-mcp-for-woocommerce'), 'delete', 'submit', false, [ 'aria-label' => sprintf(
                            /* translators: %s: connection name */
                            __('Revoke %s', 'safe-mcp-for-woocommerce'),
                            $record['name']
                        ) ]);
                    }
                    ?>
                </form>
            </td>
        </tr>
        <?php
    }

    /**
     * The Settings tab: the MCP switch, OAuth sign-in, who may manage WPMCP,
     * what happens when the plugin is deleted, and Advanced.
     *
     * @param array<string, mixed>|null $result
     */
    private function render_settings(string $nonce, string $endpoint, bool $exposed, ?array $result): void
    {
        ?>
        <div class="wpmcp-card">
            <h2><?php echo esc_html__('MCP access', 'safe-mcp-for-woocommerce'); ?></h2>
            <p class="wpmcp-intro"><?php echo esc_html($exposed
                ? __('On: apps you connect can use WPMCP tools on this site. Turning it off is the quickest way to stop all agents at once.', 'safe-mcp-for-woocommerce')
                : __('Off: all WPMCP tools refuse, for all apps and passwords.', 'safe-mcp-for-woocommerce')); ?></p>
            <form method="post" action="#wpmcp-tab-settings">
                <input type="hidden" name="wpmcp_connection_action" value="toggle">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="enabled" value="<?php echo esc_attr($exposed ? '0' : '1'); ?>">
                <p><?php submit_button($exposed ? __('Turn MCP off', 'safe-mcp-for-woocommerce') : __('Turn MCP on', 'safe-mcp-for-woocommerce'), $exposed ? 'secondary' : 'primary', 'submit', false); ?></p>
                <p class="wpmcp-help"><?php echo esc_html__('Off means every tool refuses at once, for every client and credential. Its state is shown in the admin bar.', 'safe-mcp-for-woocommerce'); ?></p>
            </form>
        </div>
        <?php $this->render_oauth_card($nonce); ?>
        <div class="wpmcp-card"><?php $this->render_management_access($nonce); ?></div>
        <div class="wpmcp-card"><?php $this->render_uninstall_data($nonce); ?></div>
        <div class="wpmcp-card"><?php $this->render_advanced($nonce, $endpoint, $result); ?></div>
        <?php
    }

    /**
     * OAuth sign-in (issues #581, #842): the clients that need it, whether it
     * is on, the switch, and the ways developers fix it in code, or what
     * fixes it when the constant or the filter does.
     */
    private function render_oauth_card(string $nonce): void
    {
        $enabled = OAuth_Config::is_enabled();
        $locked  = OAuth_Config::locked_by();
        ?>
        <div class="wpmcp-card">
            <h2><?php echo esc_html__('OAuth sign-in', 'safe-mcp-for-woocommerce'); ?></h2>
            <?php if ($enabled) : ?>
                <p><strong><?php echo esc_html__('OAuth is on.', 'safe-mcp-for-woocommerce'); ?></strong> <?php echo esc_html__('ChatGPT and the Claude custom connector can sign in, after a user approves them in the browser.', 'safe-mcp-for-woocommerce'); ?></p>
            <?php else : ?>
                <p><strong><?php echo esc_html__('OAuth is off.', 'safe-mcp-for-woocommerce'); ?></strong> <?php echo esc_html(__('ChatGPT and the Claude custom connector sign in only with OAuth, so they can\'t connect until it is on.', 'safe-mcp-for-woocommerce') . ' ' . Client_Config_Generator::oauth_switch_on(true)); ?></p>
            <?php endif; ?>
            <?php if (null === $locked) : ?>
                <form method="post" action="#wpmcp-tab-settings">
                    <input type="hidden" name="wpmcp_connection_action" value="oauth_enabled">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                    <input type="hidden" name="enabled" value="<?php echo esc_attr($enabled ? '0' : '1'); ?>">
                    <p>
                        <?php
                        submit_button(
                            /* translators: %s: the switch label, Allow OAuth sign-in */
                            $enabled ? __('Turn OAuth sign-in off', 'safe-mcp-for-woocommerce') : sprintf(__('%s: turn on', 'safe-mcp-for-woocommerce'), Client_Config_Generator::OAUTH_SWITCH_LABEL),
                            $enabled ? 'secondary' : 'primary',
                            'submit',
                            false
                        );
                        ?>
                    </p>
                    <p class="wpmcp-help">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: the switch label, Allow OAuth sign-in */
                            __('%s lets clients that sign in with OAuth connect, after a user approves them in the browser. Turning it off stops new sign-ins and refuses OAuth connections made earlier on their next request.', 'safe-mcp-for-woocommerce'),
                            Client_Config_Generator::OAUTH_SWITCH_LABEL
                        ));
                        ?>
                    </p>
                </form>
                <p class="wpmcp-help">
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: the PHP line define('WPMCP_OAUTH_ENABLED', true);, 2: the filter name */
                        __('For developers: %1$s in wp-config.php, or the %2$s filter in a plugin or mu-plugin, decides instead of this switch.', 'safe-mcp-for-woocommerce'),
                        Client_Config_Generator::OAUTH_CONSTANT_LINE,
                        'wpmcp_oauth_enabled'
                    ));
                    ?>
                </p>
            <?php else : ?>
                <p class="wpmcp-help">
                    <?php echo 'constant' === $locked
                        ? esc_html__('This setting is fixed by WPMCP_OAUTH_ENABLED in wp-config.php; the switch is hidden while that line is there.', 'safe-mcp-for-woocommerce')
                        : esc_html__('This setting is fixed by the wpmcp_oauth_enabled filter in a plugin or mu-plugin; the switch is hidden while that filter is there.', 'safe-mcp-for-woocommerce'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Advanced (issue #1127), collapsed by default: the endpoint, the server
     * self-test, the OAuth discovery addresses and the proxy note, which only
     * matter when debugging a connection. It opens after a self-test ran.
     *
     * @param array<string, mixed>|null $result
     */
    private function render_advanced(string $nonce, string $endpoint, ?array $result): void
    {
        $fresh = is_array($result) && 'self_test' === ($result['action'] ?? null) && is_array($result['self_test'] ?? null) ? $result['self_test'] : null;
        $last  = get_option(self::SELF_TEST_OPTION);
        $last  = is_array($last) ? $last : null;
        $when  = null === $last ? '' : (string) wp_date(self::str(get_option('date_format')) . ' ' . self::str(get_option('time_format')), is_int($last['at'] ?? null) ? $last['at'] : 0);
        ?>
        <details class="wpmcp-advanced"<?php echo null === $fresh ? '' : ' open'; ?>>
            <summary><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><?php echo esc_html__('Advanced: endpoint, server self-test, OAuth discovery and proxies', 'safe-mcp-for-woocommerce'); ?></summary>
            <h3><?php echo esc_html__('MCP endpoint', 'safe-mcp-for-woocommerce'); ?></h3>
            <?php (new Snippet_View())->copy_row('wpmcp-endpoint', $endpoint, __('Copy the endpoint address', 'safe-mcp-for-woocommerce'), __('Copied the endpoint address.', 'safe-mcp-for-woocommerce')); ?>

            <h3><?php echo esc_html__('Server self-test', 'safe-mcp-for-woocommerce'); ?></h3>
            <p class="wpmcp-help"><?php echo esc_html__('Posts an MCP initialize request from this site to its own endpoint, without a password, and checks the answer.', 'safe-mcp-for-woocommerce'); ?></p>
            <p>
                <strong><?php echo esc_html__('Last self-test:', 'safe-mcp-for-woocommerce'); ?></strong>
                <?php if (null !== $last) : ?>
                    <?php echo esc_html((true === ($last['ok'] ?? null) ? "\u{2713} " : "\u{2717} ") . self::str($last['message'] ?? '')); ?>
                    <?php
                    /* translators: %s: date and time in the site timezone. */
                    echo esc_html(sprintf(__('(%s)', 'safe-mcp-for-woocommerce'), $when));
                    ?>
                <?php else : ?>
                    <?php echo esc_html__('Not run yet.', 'safe-mcp-for-woocommerce'); ?>
                <?php endif; ?>
            </p>
            <?php if (null !== $fresh && is_array($fresh['checks'] ?? null)) : ?>
                <ul class="wpmcp-checks">
                    <?php foreach ($fresh['checks'] as $check) : ?>
                        <?php if (is_array($check)) : ?>
                            <li>
                                <strong><?php echo esc_html((true === ($check['ok'] ?? null) ? "\u{2713} " : "\u{2717} ") . self::str($check['label'] ?? '')); ?></strong>
                                <p class="description"><?php echo esc_html(self::str($check['detail'] ?? '')); ?></p>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post" action="#wpmcp-tab-settings">
                <input type="hidden" name="wpmcp_connection_action" value="self_test">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                <?php submit_button(__('Run self-test', 'safe-mcp-for-woocommerce'), 'secondary', 'submit', false); ?>
            </form>

            <?php if (OAuth_Config::is_enabled()) : ?>
                <?php $this->render_oauth_discovery(); ?>
            <?php else : ?>
                <h2><?php echo esc_html__('OAuth discovery', 'safe-mcp-for-woocommerce'); ?></h2>
                <p><?php echo esc_html__('OAuth is off, so this site publishes no OAuth discovery documents.', 'safe-mcp-for-woocommerce'); ?></p>
            <?php endif; ?>
            <h2><?php echo esc_html__('Behind a proxy or CDN', 'safe-mcp-for-woocommerce'); ?></h2>
            <p><?php echo esc_html__('Allowed addresses on scoped identities are checked against REMOTE_ADDR. Behind a reverse proxy, list the proxy with the wpmcp_trusted_proxies filter so the X-Forwarded-For address is used instead.', 'safe-mcp-for-woocommerce'); ?></p>
        </details>
        <?php
    }

    /** The "delete all data when the plugin is deleted" opt-in (issue #480). */
    private function render_uninstall_data(string $nonce): void
    {
        $delete   = Data_Registry::delete_on_uninstall();
        $forced   = Data_Registry::is_forced();
        $editable = ! $forced && (! is_multisite() || current_user_can('manage_network_options'));
        ?>
        <h2><?php echo esc_html__('Delete all WPMCP data when the plugin is deleted', 'safe-mcp-for-woocommerce'); ?></h2>
        <p>
            <?php if ($delete) : ?>
                <strong><?php echo esc_html__('On.', 'safe-mcp-for-woocommerce'); ?></strong>
                <?php echo esc_html__('Deleting WPMCP from the Plugins screen will also delete its tables (snapshots, redirects, search index), settings, OAuth clients and tokens, scoped identities, the application passwords issued here, and its directories, including site backup archives and file backups, on every site of the network. This cannot be undone.', 'safe-mcp-for-woocommerce'); ?>
            <?php else : ?>
                <strong><?php echo esc_html__('Off.', 'safe-mcp-for-woocommerce'); ?></strong>
                <?php echo esc_html__('WPMCP\'s data (snapshots, settings, credentials and backup archives) is kept when the plugin is deleted, so reinstalling picks up where you left off.', 'safe-mcp-for-woocommerce'); ?>
            <?php endif; ?>
        </p>
        <?php if ($forced) : ?>
            <p class="description"><?php echo esc_html__('Set by WPMCP_UNINSTALL_DELETE_DATA in wp-config.php; remove the constant to change it here.', 'safe-mcp-for-woocommerce'); ?></p>
        <?php elseif (! $editable) : ?>
            <p class="description"><?php echo esc_html__('Only a network administrator can change this setting.', 'safe-mcp-for-woocommerce'); ?></p>
        <?php else : ?>
            <form method="post">
                <input type="hidden" name="wpmcp_connection_action" value="uninstall_data">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="delete" value="<?php echo $delete ? '0' : '1'; ?>">
                <?php submit_button(
                    $delete ? __('Keep data when the plugin is deleted', 'safe-mcp-for-woocommerce') : __('Delete all data when the plugin is deleted', 'safe-mcp-for-woocommerce'),
                    $delete ? 'primary' : 'delete',
                    'submit',
                    false
                ); ?>
            </form>
        <?php endif; ?>
        <?php
    }

    /**
     * Who may manage WPMCP (issue #479). Read-only while the
     * WPMCP_MANAGEMENT_ADMINS constant is defined, with listed ids that are
     * not administrators flagged; otherwise every administrator or a chosen
     * few. Agent execution is not affected either way.
     */
    private function render_management_access(string $nonce): void
    {
        $policy = Management_Access::policy();
        ?>
        <h2><?php echo esc_html__('Management access', 'safe-mcp-for-woocommerce'); ?></h2>
        <p class="description">
            <?php echo esc_html__('Who can open these screens and change WPMCP settings. Administrators left out keep the rest of wp-admin, and what agents may do is still decided by identities and governance.', 'safe-mcp-for-woocommerce'); ?>
        </p>
        <?php if ('constant' === $policy['source']) : ?>
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: %s: constant name. */
                    __('Set in wp-config.php by %s, so it is read-only here.', 'safe-mcp-for-woocommerce'),
                    Management_Access::CONSTANT
                ));
                ?>
            </p>
            <?php if ('closed' === $policy['mode']) : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html__('The constant is not valid, so nobody can manage WPMCP until it is fixed. Use \'all\', a comma-separated list of user ids, or an array of user ids.', 'safe-mcp-for-woocommerce'); ?></p></div>
            <?php elseif ('all' === $policy['mode']) : ?>
                <p><?php echo esc_html__('Every administrator can manage WPMCP.', 'safe-mcp-for-woocommerce'); ?></p>
            <?php else : ?>
                <?php $not_admin = Management_Access::non_admin_ids(); ?>
                <ul style="margin-left: 1.5em; list-style: disc;">
                    <?php foreach ($policy['ids'] as $id) : ?>
                        <?php $listed = get_userdata($id); ?>
                        <li>
                            <?php echo esc_html($listed ? $listed->user_login . ' (#' . $id . ')' : '#' . $id); ?>
                            <?php if (in_array($id, $not_admin, true)) : ?>
                                <strong data-wpmcp-not-admin="<?php echo esc_attr((string) $id); ?>"><?php echo esc_html__('not an administrator of this site, so this entry grants nothing', 'safe-mcp-for-woocommerce'); ?></strong>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php
            return;
        endif;
        ?>
        <form method="post">
            <input type="hidden" name="wpmcp_connection_action" value="management_access">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="management_version" value="<?php echo esc_attr(Management_Access::version()); ?>">
            <p>
                <label>
                    <input type="radio" name="management_mode" value="all" <?php checked('list' !== $policy['mode']); ?>>
                    <?php echo esc_html__('Every administrator', 'safe-mcp-for-woocommerce'); ?>
                </label>
                <br>
                <label>
                    <input type="radio" name="management_mode" value="list" <?php checked('list', $policy['mode']); ?>>
                    <?php echo esc_html__('Only these administrators', 'safe-mcp-for-woocommerce'); ?>
                </label>
            </p>
            <fieldset style="margin-left: 1.5em;">
                <?php foreach (Management_Access::administrators() as $admin) : ?>
                    <label style="display: block;">
                        <input type="checkbox" name="management_ids[]" value="<?php echo esc_attr((string) $admin->ID); ?>" <?php checked(in_array((int) $admin->ID, $policy['ids'], true)); ?>>
                        <?php echo esc_html($admin->display_name . ' (' . $admin->user_login . ')'); ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <p class="description">
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: constant name, 2: WP-CLI command. */
                    __('You cannot remove yourself. To pin this in wp-config.php, define %1$s. If nobody on the list can log in, run %2$s.', 'safe-mcp-for-woocommerce'),
                    Management_Access::CONSTANT,
                    'wp wpmcp management-access reset'
                ));
                ?>
            </p>
            <?php submit_button(__('Save management access', 'safe-mcp-for-woocommerce'), 'secondary', 'submit', false); ?>
        </form>
        <?php
    }

    /**
     * Scoped identities and the client addresses each is pinned to (issue
     * #416). Read-only: identities are edited with create-identity. Hidden
     * while no identity exists.
     */
    private function render_identities(): void
    {
        $identities = Identity_Store::list();
        if (! $identities) {
            return;
        }
        ?>
        <h2><?php echo esc_html__('Scoped identities', 'safe-mcp-for-woocommerce'); ?></h2>
        <p><?php echo esc_html__('An identity with allowed addresses refuses MCP requests from anywhere else. The admin screens are never restricted. Sites behind a proxy or CDN: see Advanced.', 'safe-mcp-for-woocommerce'); ?></p>
        <table class="widefat striped">
            <thead><tr>
                <th><?php echo esc_html__('Identity', 'safe-mcp-for-woocommerce'); ?></th>
                <th><?php echo esc_html__('Allowed addresses', 'safe-mcp-for-woocommerce'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($identities as $identity) : ?>
                <?php $ips = array_map('strval', (array) ($identity['allowed_ips'] ?? [])); ?>
                <tr>
                    <td><?php echo esc_html((string) ($identity['name'] ?? '')); ?></td>
                    <td><?php echo $ips ? '<code>' . esc_html(implode(', ', $ips)) . '</code>' : esc_html__('Any address', 'safe-mcp-for-woocommerce'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Where MCP clients find this site's OAuth metadata (issue #473). On a
     * site whose home has a path, the RFC 8414 address sits at the domain
     * root: a multisite main site answers it for its subsites, while on a
     * single site in a subdirectory it is outside WordPress and discovery
     * relies on the OpenID path-appended address, which spec-following
     * clients try last.
     */
    private function render_oauth_discovery(): void
    {
        $home  = untrailingslashit(home_url());
        $path  = trim((string) wp_parse_url($home, PHP_URL_PATH), '/');
        $parts = (array) wp_parse_url($home);
        $root  = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $metadata = '' === $path ? $home . '/.well-known/oauth-authorization-server' : $home . '/.well-known/openid-configuration';
        $rfc8414  = $root . '/.well-known/oauth-authorization-server' . ('' === $path ? '' : '/' . $path);
        $network  = is_multisite() && ! is_main_site() && ! is_subdomain_install() && Network_Sites::main_site_answers();
        ?>
        <h2><?php echo esc_html__('OAuth discovery', 'safe-mcp-for-woocommerce'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Issuer', 'safe-mcp-for-woocommerce'); ?></th>
                <td><code><?php echo esc_html($home); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Protected resource metadata', 'safe-mcp-for-woocommerce'); ?></th>
                <td><code><?php echo esc_html(Mcp_Resource::metadata_url()); ?></code></td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Authorization server metadata', 'safe-mcp-for-woocommerce'); ?></th>
                <td>
                    <code><?php echo esc_html($metadata); ?></code>
                    <?php if ('' !== $path && $network) : ?>
                        <p class="description"><?php
                            /* translators: %s: the authorization server metadata URL at the domain root. */
                            echo esc_html(sprintf(__('The main site of this network also answers %s for this site.', 'safe-mcp-for-woocommerce'), $rfc8414));
                        ?></p>
                    <?php elseif ('' !== $path) : ?>
                        <p class="description"><?php
                            /* translators: 1: the authorization server metadata URL at the domain root, 2: the same document at this site's own address. */
                            echo esc_html(sprintf(__('WordPress is installed in a subdirectory, so the standard address at the domain root (%1$s) is outside WordPress. MCP clients that follow the MCP authorization spec fall back to the address above, so no server setup is needed. For a client that only checks the domain root, rewrite that address to %2$s on your web server.', 'safe-mcp-for-woocommerce'), $rfc8414, $home . '/.well-known/oauth-authorization-server'));
                        ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * OAuth clients that connected with a Client ID Metadata Document
     * (issue #388), and the switch that holds new ones for approval. Only
     * shown while OAuth is on. Each client's redirect hosts are listed, and
     * a client that only redirects to this machine's loopback address is
     * flagged: its document cannot prove who is really running it.
     */
    private function render_oauth_clients(string $nonce): void
    {
        if (! OAuth_Config::is_enabled()) {
            ?>
            <h2><?php echo esc_html__('OAuth clients', 'safe-mcp-for-woocommerce'); ?></h2>
            <p><?php echo esc_html__('OAuth is off, so no client signs in with OAuth. The switch is on the Settings tab.', 'safe-mcp-for-woocommerce'); ?></p>
            <?php
            return;
        }

        $required = Client_Metadata_Document::requires_approval();
        $labels   = [
            'approved' => __('Approved', 'safe-mcp-for-woocommerce'),
            'denied'   => __('Blocked', 'safe-mcp-for-woocommerce'),
            'pending'  => __('Waiting for approval', 'safe-mcp-for-woocommerce'),
            'seen'     => __('Allowed (approval off)', 'safe-mcp-for-woocommerce'),
        ];
        $rows = $this->oauth_client_rows();
        ?>
        <h2><?php echo esc_html__('OAuth clients', 'safe-mcp-for-woocommerce'); ?></h2>
        <p><?php echo esc_html__('Each application that signs in through OAuth, once: who approved it, what it may do and its status. Read only refuses anything that would change the site; a scoped identity limits it exactly as that identity is limited. Access changes apply on its next request.', 'safe-mcp-for-woocommerce'); ?></p>
        <form method="post">
            <input type="hidden" name="wpmcp_connection_action" value="oauth_client_approval">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="require" value="<?php echo $required ? '0' : '1'; ?>">
            <?php submit_button(
                $required ? __('Stop requiring approval', 'safe-mcp-for-woocommerce') : __('Require approval for new clients', 'safe-mcp-for-woocommerce'),
                'secondary',
                'submit',
                false
            ); ?>
            <p class="description">
                <?php echo $required
                    ? esc_html__('New clients wait here until you approve them.', 'safe-mcp-for-woocommerce')
                    : esc_html__('New clients can connect as soon as a signed-in user authorizes them. Turning approval on keeps the clients already listed.', 'safe-mcp-for-woocommerce'); ?>
            </p>
        </form>
        <?php if (! $rows) : ?>
            <p><?php echo esc_html__('No application has signed in through OAuth yet.', 'safe-mcp-for-woocommerce'); ?></p>
            <?php return; ?>
        <?php endif; ?>
        <table class="widefat striped" style="margin-top: 1em;">
            <thead><tr>
                <th><?php echo esc_html__('Client', 'safe-mcp-for-woocommerce'); ?></th>
                <th><?php echo esc_html__('Status', 'safe-mcp-for-woocommerce'); ?></th>
                <th><?php echo esc_html__('Access', 'safe-mcp-for-woocommerce'); ?></th>
                <th><?php echo esc_html__('Actions', 'safe-mcp-for-woocommerce'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $client_id => $row) : ?>
                <?php
                $hosts    = $row['redirect_hosts'];
                $loopback = $hosts && ! array_diff($hosts, ['127.0.0.1', '::1', 'localhost']);
                $status   = $row['status'];
                ?>
                <tr class="wpmcp-oauth-client">
                    <td>
                        <strong><?php echo esc_html('' !== $row['name'] ? $row['name'] : __('Unnamed application', 'safe-mcp-for-woocommerce')); ?></strong><br>
                        <code><?php echo esc_html($client_id); ?></code>
                        <?php if ($hosts) : ?>
                            <p class="description">
                                <?php
                                /* translators: %s: comma-separated host names. */
                                echo esc_html(sprintf(__('Redirects to %s', 'safe-mcp-for-woocommerce'), implode(', ', $hosts)));
                                ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($loopback) : ?>
                            <p class="description"><?php echo esc_html__('Loopback only: any program on the user\'s computer could present this client ID. Approve it only if you expect this client.', 'safe-mcp-for-woocommerce'); ?></p>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html('' === $status ? __('Registered', 'safe-mcp-for-woocommerce') : ($labels[ $status ] ?? $status)); ?></td>
                    <td>
                        <?php if (! $row['connections']) : ?>
                            <?php echo esc_html__('Not connected', 'safe-mcp-for-woocommerce'); ?>
                        <?php endif; ?>
                        <?php foreach ($row['connections'] as $connection) : ?>
                            <?php $this->render_access_form($nonce, $connection); ?>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <?php if ($row['cimd']) : ?>
                            <?php foreach (['oauth_client_approve' => __('Approve', 'safe-mcp-for-woocommerce'), 'oauth_client_deny' => __('Block', 'safe-mcp-for-woocommerce'), 'oauth_client_forget' => __('Forget', 'safe-mcp-for-woocommerce')] as $action => $label) : ?>
                                <?php if (('oauth_client_approve' === $action && 'approved' === $status) || ('oauth_client_deny' === $action && 'denied' === $status)) {
                                    continue;
                                } ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="wpmcp_connection_action" value="<?php echo esc_attr($action); ?>">
                                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                                    <input type="hidden" name="client_id" value="<?php echo esc_attr($client_id); ?>">
                                    <?php submit_button($label, 'oauth_client_approve' === $action ? 'secondary' : 'delete', 'submit', false); ?>
                                </form>
                            <?php endforeach; ?>
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * One row per OAuth client (issue #1127): the clients known by a client
     * metadata document and those with a live connection (issue #454),
     * merged by client_id, each with its connections.
     *
     * @return array<string, array{name: string, redirect_hosts: string[], status: string, cimd: bool, connections: array<int, array>}>
     */
    private function oauth_client_rows(): array
    {
        $rows = [];
        foreach (Client_Metadata_Document::clients() as $client_id => $client) {
            $rows[ (string) $client_id ] = [
                'name'           => (string) ($client['client_name'] ?? ''),
                'redirect_hosts' => array_map('strval', (array) ($client['redirect_hosts'] ?? [])),
                'status'         => (string) ($client['status'] ?? ''),
                'cimd'           => true,
                'connections'    => [],
            ];
        }
        foreach (Client_Access::connections() as $connection) {
            $client_id = (string) $connection['client_id'];
            if (! isset($rows[ $client_id ])) {
                $client             = Client_Store::get($client_id);
                $rows[ $client_id ] = [
                    'name'           => (string) ($client['client_name'] ?? ''),
                    'redirect_hosts' => [],
                    'status'         => '',
                    'cimd'           => false,
                    'connections'    => [],
                ];
            }
            $rows[ $client_id ]['connections'][] = $connection;
        }
        return $rows;
    }

    /** The access level control of one OAuth connection (issue #454). */
    private function render_access_form(string $nonce, array $connection): void
    {
        $identities = array_values(array_filter(array_map(static fn (array $i): string => (string) ($i['name'] ?? ''), Identity_Store::list()), 'strlen'));
        $user       = get_userdata($connection['user_id']);
        $current    = Client_Access::IDENTITY === $connection['level'] ? 'identity:' . $connection['identity'] : $connection['level'];
        $capped     = Client_Access::SCOPE_READ === $connection['scope'];
        $choices    = [
            Client_Access::FULL => $capped ? __('Full access (granted read only)', 'safe-mcp-for-woocommerce') : __('Full access', 'safe-mcp-for-woocommerce'),
            Client_Access::READ => __('Read only', 'safe-mcp-for-woocommerce'),
        ];
        foreach ($identities as $identity) {
            /* translators: %s: scoped identity name. */
            $choices[ 'identity:' . $identity ] = sprintf(__('As the "%s" identity', 'safe-mcp-for-woocommerce'), $identity);
        }
        ?>
        <form method="post" style="margin-bottom: 0.5em;">
            <input type="hidden" name="wpmcp_connection_action" value="oauth_access">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="client_id" value="<?php echo esc_attr($connection['client_id']); ?>">
            <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $connection['user_id']); ?>">
            <label>
                <?php
                /* translators: %s: login of the user who approved the client. */
                echo esc_html(sprintf(__('Approved by %s:', 'safe-mcp-for-woocommerce'), $user ? $user->user_login : __('Deleted user', 'safe-mcp-for-woocommerce')));
                ?>
                <select name="access">
                    <?php foreach ($choices as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php submit_button(__('Save', 'safe-mcp-for-woocommerce'), 'secondary', 'submit', false); ?>
            <?php if (! $connection['stored']) : ?>
                <p class="description"><?php echo esc_html__('Connected before access levels existed, so it has full access.', 'safe-mcp-for-woocommerce'); ?></p>
            <?php endif; ?>
        </form>
        <?php
    }
}
