<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

use WPMCP\Auth\Client_Access;
use WPMCP\Auth\Client_Metadata_Document;
use WPMCP\Auth\Client_Store;
use WPMCP\Auth\Refresh_Token_Store;
use WPMCP\Auth\Token_Store;
use WPMCP\MCP\Request_Log;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Reads, without changing anything, what WordPress recorded about a
 * connection:
 *
 *  - core's last_used and last_ip of an application password (written on the
 *    first authenticated request, then at most once a day);
 *  - the MCP requests the route answered for that password, with the name
 *    the client gave (Activity_Store, issue #1258). They are kept per
 *    password and for either protocol revision, so a password app is judged
 *    by them alone, never by a session of the same user;
 *  - for an OAuth app, the MCP adapter's sessions of the users who approved
 *    it (user meta mcp_adapter_sessions, mcp_adapter_sessions_{blog_id} on
 *    multisite), with the client's self-reported clientInfo. That storage is
 *    private to the adapter, so every row is shape-checked and a missing or
 *    changed shape reads as no session;
 *  - the first request-log row of the user after the password was made;
 *  - the OAuth clients of an app, the newest token each holds and the
 *    consent behind it.
 */
final class Wp_Evidence_Source implements Evidence_Source
{
    private const SESSION_META = 'mcp_adapter_sessions';

    /** Longest OAuth client name kept from what a client registered. */
    private const TEXT_MAX = 64;

    public function __construct(
        private readonly Activity_Store $activity,
    ) {
    }

    public function for_password(int $user_id, string $uuid, int $since): Connection_Evidence
    {
        $item      = \WP_Application_Passwords::get_user_application_password($user_id, $uuid);
        $last_used = null === $item ? null : $item['last_used'];
        $last_ip   = null === $item || '' === (string) $item['last_ip'] ? null : $item['last_ip'];

        return new Connection_Evidence(
            $last_used,
            null === $last_used ? null : $last_ip,
            [],
            $this->first_tool_call($user_id, $since),
            [],
            $this->activity->find($user_id, $uuid)
        );
    }

    public function for_oauth(Client_App $app): Connection_Evidence
    {
        $grants = [];
        $users  = [];
        foreach ($this->oauth_clients() as $client_id => $client) {
            if (! $app->matches_oauth_client($client['name'], $client['hosts'])) {
                continue;
            }

            $token    = $this->newest_token($client_id);
            $user_id  = $token['user_id'];
            $consent  = $user_id > 0 ? Client_Access::get($client_id, $user_id) : null;
            $approver = $user_id > 0 ? get_userdata($user_id) : false;
            $grants[] = new Oauth_Grant(
                $client['name'],
                $client['status'],
                $client['registered_at'],
                null === $consent ? 0 : $consent['set_at'],
                $token['issued_at'],
                $approver instanceof \WP_User ? $approver->user_login : '',
                null === $consent ? $token['access'] : $consent['level']
            );
            if ($user_id > 0) {
                $users[ $user_id ] = true;
            }
        }

        $sessions = [];
        foreach (array_keys($users) as $user_id) {
            $sessions = array_merge($sessions, $this->sessions($user_id));
        }

        return new Connection_Evidence(null, null, $sessions, null, $grants);
    }

    /** @return list<Session_Evidence> */
    private function sessions(int $user_id): array
    {
        $stored = get_user_meta($user_id, $this->session_key(), true);
        if (! is_array($stored)) {
            return [];
        }

        $sessions = [];
        foreach ($stored as $row) {
            if (! is_array($row) || ! is_numeric($row['created_at'] ?? null)) {
                continue;
            }

            $client     = Mcp_Client::from_initialize_params($row['client_params'] ?? null);
            $sessions[] = new Session_Evidence(
                $client->name,
                $client->version,
                (int) $row['created_at'],
                is_numeric($row['last_activity'] ?? null) ? (int) $row['last_activity'] : (int) $row['created_at']
            );
        }

        return $sessions;
    }

    /** The adapter's meta key on this site (see its SessionManager). */
    private function session_key(): string
    {
        $blog_id = get_current_blog_id();

        return is_multisite() && $blog_id > 0 ? self::SESSION_META . '_' . $blog_id : self::SESSION_META;
    }

    private function first_tool_call(int $user_id, int $since): ?Tool_Call
    {
        $first = null;
        foreach (Request_Log::query([ 'user_id' => $user_id ], Request_Log::cap()) as $row) {
            $at = is_numeric($row['timestamp'] ?? null) ? (int) $row['timestamp'] : 0;
            if ($at < $since || (null !== $first && $at >= $first->at)) {
                continue;
            }
            $first = new Tool_Call(is_string($row['tool'] ?? null) ? $row['tool'] : '', $at, true === ($row['ok'] ?? null));
        }

        return $first;
    }

    /**
     * Every OAuth client this site knows, keyed by client_id: the ones that
     * registered dynamically (ChatGPT) and the ones known by a client
     * metadata document (the Claude connector). Protected clients, such as
     * the cloud gateway, are left out.
     *
     * @return array<string, array{name: string, hosts: list<string>, status: string, registered_at: int}>
     */
    private function oauth_clients(): array
    {
        $clients = [];

        $registered = get_option(Client_Store::OPTION, []);
        foreach (is_array($registered) ? $registered : [] as $client_id => $client) {
            if (! is_array($client) || true === ($client['protected'] ?? null)) {
                continue;
            }
            $hosts = [];
            foreach (is_array($client['redirect_uris'] ?? null) ? $client['redirect_uris'] : [] as $uri) {
                $host = is_string($uri) ? wp_parse_url($uri, PHP_URL_HOST) : null;
                if (is_string($host) && '' !== $host) {
                    $hosts[] = $host;
                }
            }
            $clients[ (string) $client_id ] = [
                'name'          => $this->plain($client['client_name'] ?? null),
                'hosts'         => $hosts,
                'status'        => '',
                'registered_at' => is_numeric($client['created_at'] ?? null) ? (int) $client['created_at'] : 0,
            ];
        }

        foreach (Client_Metadata_Document::clients() as $client_id => $client) {
            $hosts = array_values(array_filter(
                is_array($client['redirect_hosts'] ?? null) ? $client['redirect_hosts'] : [],
                'is_string'
            ));
            $clients[ $client_id ] = [
                'name'          => $this->plain($client['client_name'] ?? null),
                'hosts'         => $hosts,
                'status'        => is_string($client['status'] ?? null) ? $client['status'] : '',
                'registered_at' => is_numeric($client['first_seen'] ?? null) ? (int) $client['first_seen'] : 0,
            ];
        }

        return $clients;
    }

    /**
     * The newest token a client holds outside the gateway, the user it was
     * issued to and the access its scope allows.
     *
     * @return array{issued_at: int, user_id: int, access: string}
     */
    private function newest_token(string $client_id): array
    {
        $newest = [ 'issued_at' => 0, 'user_id' => 0, 'access' => '' ];
        foreach ([ Refresh_Token_Store::OPTION, Token_Store::OPTION ] as $option) {
            $records = get_option($option, []);
            foreach (is_array($records) ? $records : [] as $record) {
                if (! is_array($record) || $client_id !== ($record['client_id'] ?? null) || true === ($record['gateway'] ?? null)) {
                    continue;
                }
                $issued_at = is_numeric($record['issued_at'] ?? null) ? (int) $record['issued_at'] : 0;
                if ($issued_at <= $newest['issued_at']) {
                    continue;
                }
                $scope  = is_string($record['scope'] ?? null) ? $record['scope'] : '';
                $newest = [
                    'issued_at' => $issued_at,
                    'user_id'   => is_numeric($record['user_id'] ?? null) ? (int) $record['user_id'] : 0,
                    'access'    => Client_Access::is_read_only_scope($scope) ? Client_Access::READ : Client_Access::FULL,
                ];
            }
        }

        return $newest;
    }

    /**
     * An OAuth client's registered name as short plain text.
     *
     * @param mixed $value Whatever a client sent or a store kept: only scalars become text.
     */
    private function plain(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return '';
        }

        return mb_substr(sanitize_text_field((string) $value), 0, self::TEXT_MAX);
    }
}
