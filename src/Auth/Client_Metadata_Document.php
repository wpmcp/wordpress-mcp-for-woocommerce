<?php

namespace WPMCP\Auth;

use WPMCP\Tools\Media\Remote_Image_Guard;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * OAuth Client ID Metadata Documents (issue #388), the registration method
 * the MCP authorization spec prefers over dynamic client registration.
 *
 * The client_id is an https URL. When /authorize sees one that is not a
 * dynamically registered client, this class fetches the JSON document at
 * that URL, requires its client_id to equal the URL exactly, and takes the
 * client's name and redirect_uris from it. The client has no secret. A
 * public client (token_endpoint_auth_method "none") is accepted at the
 * token endpoint on PKCE alone; one declaring "private_key_jwt" (ChatGPT,
 * issue #1098) must also sign an RFC 7523 client assertion with a key from
 * its published JWKS (Client_Assertion). Documents declaring a shared-secret
 * method, or carrying a client_secret, are refused.
 *
 * Fetching a caller-chosen URL is an SSRF surface, so the request is held to
 * the same rules the remote readers use: https only, the default port, no
 * credentials, no fragment or dot segments, a host that is not and does not
 * resolve to a loopback, private or reserved address
 * (Remote_Image_Guard::assert_public_host), then wp_safe_remote_get with
 * redirects off, a short timeout and a small byte cap. Only a logged-in
 * user reaching /authorize can trigger a fetch, and results are cached
 * (successes per Cache-Control, failures briefly) so repeat authorizations
 * cost nothing.
 *
 * Every client that passes validation is recorded in a small registry an
 * administrator sees on the Connection screen. Approval of newly seen
 * clients is off by default; when a site owner turns it on, clients already
 * seen are approved and any new client is held as pending until approved
 * there. A denied client is
 * refused whatever the setting, and denying one revokes its tokens.
 */
class Client_Metadata_Document
{
    public const REGISTRY_OPTION = 'wpmcp_oauth_cimd_clients';
    public const APPROVAL_OPTION = 'wpmcp_oauth_cimd_require_approval';

    /** The draft recommends servers cap documents at 5 KB. */
    public const DEFAULT_MAX_BYTES = 5120;
    public const TIMEOUT           = 5;
    public const DEFAULT_TTL       = 3600;
    public const MAX_TTL           = 86400;
    public const FAILURE_TTL       = 60;

    /** Registry size cap; the oldest non-approved entries are dropped first. */
    public const MAX_CLIENTS = 100;

    private const CACHE_PREFIX = 'wpmcp_oauth_cimd_';

    public static function is_enabled(): bool
    {
        return (bool) apply_filters('wpmcp_oauth_cimd_enabled', true);
    }

    /** Whether $client_id is shaped like a metadata document URL at all (https scheme). */
    public static function looks_like_url(string $client_id): bool
    {
        return 0 === stripos($client_id, 'https://') || 0 === stripos($client_id, 'http://');
    }

    /**
     * Fetch (or read from cache) and validate the document behind $client_id.
     *
     * @return array{client_id: string, client_name: string, redirect_uris: string[], auth_method: string}|\WP_Error
     */
    public static function resolve(string $client_id): array|\WP_Error
    {
        if (! self::is_enabled()) {
            return new \WP_Error('invalid_client', 'Unknown client_id.');
        }

        try {
            self::assert_safe_url($client_id);
        } catch (\InvalidArgumentException $e) {
            // A refusal costs no request, so it is not cached.
            return new \WP_Error('invalid_client', 'The client_id is not an acceptable client metadata document URL: ' . $e->getMessage());
        }

        $key    = self::cache_key($client_id);
        $cached = get_transient($key);
        if (is_array($cached) && isset($cached['ok'])) {
            return $cached['ok'] && is_array($cached['client'] ?? null)
                ? $cached['client']
                : new \WP_Error('invalid_client', (string) ($cached['error'] ?? 'The client metadata document could not be used.'));
        }

        $fetched = self::fetch($client_id);
        if (is_wp_error($fetched)) {
            set_transient($key, ['ok' => false, 'error' => $fetched->get_error_message()], self::FAILURE_TTL);
            return $fetched;
        }

        [$client, $ttl] = $fetched;
        if ($ttl > 0) {
            set_transient($key, ['ok' => true, 'client' => $client], $ttl);
        }

        return $client;
    }

    public static function flush_cache(string $client_id): void
    {
        delete_transient(self::cache_key($client_id));
    }

    /**
     * Cache lifetime for a successful fetch, from its Cache-Control header:
     * no-store, no-cache and max-age=0 mean do not cache; max-age is
     * honoured up to MAX_TTL; no header means DEFAULT_TTL.
     */
    public static function cache_ttl(string $cache_control): int
    {
        $header = strtolower($cache_control);
        if (1 === preg_match('/(?:^|[\s,])(no-store|no-cache)(?:$|[\s,=])/', $header)) {
            return 0;
        }
        if (1 === preg_match('/(?:^|[\s,])max-age\s*=\s*"?(\d+)/', $header, $m)) {
            return (int) min(self::MAX_TTL, (int) $m[1]);
        }

        return self::DEFAULT_TTL;
    }

    /**
     * URL rules from the metadata document draft plus the SSRF guard.
     *
     * @throws \InvalidArgumentException
     */
    private static function assert_safe_url(string $url): void
    {
        $parts = wp_parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            throw new \InvalidArgumentException('it could not be parsed.');
        }
        if ('https' !== strtolower((string) ($parts['scheme'] ?? ''))) {
            throw new \InvalidArgumentException('it must use https.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('it must not embed credentials.');
        }
        if (isset($parts['port']) && 443 !== (int) $parts['port']) {
            throw new \InvalidArgumentException('it must use the default https port.');
        }
        if (isset($parts['fragment']) || false !== strpos($url, '#')) {
            throw new \InvalidArgumentException('it must not contain a fragment.');
        }

        $path = (string) ($parts['path'] ?? '');
        if ('' === $path || '/' === $path) {
            throw new \InvalidArgumentException('it must contain a path.');
        }
        foreach (explode('/', $path) as $segment) {
            if ('.' === $segment || '..' === $segment) {
                throw new \InvalidArgumentException('it must not contain dot path segments.');
            }
        }

        Remote_Image_Guard::assert_public_host((string) $parts['host']);
    }

    private static function max_bytes(): int
    {
        return max(512, (int) apply_filters('wpmcp_oauth_cimd_max_bytes', self::DEFAULT_MAX_BYTES));
    }

    /** @return array{0: array<string, mixed>, 1: int}|\WP_Error */
    private static function fetch(string $client_id): array|\WP_Error
    {
        $fetched = self::fetch_json($client_id, self::max_bytes(), 'client metadata document', 'WPMCP-OAuth-Client-Metadata/1.0');
        if (is_wp_error($fetched)) {
            return self::invalid($fetched->get_error_message());
        }

        $client = self::validate_document($fetched['document'], $client_id);
        if (is_wp_error($client)) {
            return $client;
        }

        return [$client, self::cache_ttl($fetched['cache_control'])];
    }

    /**
     * The guarded GET behind both the document fetch and the client's JWKS
     * fetch (issue #1098): the URL must pass assert_safe_url(), redirects
     * are not followed, the body is capped at $max_bytes and must be a JSON
     * object. Failure messages are lowercase reasons naming $what.
     *
     * @return array{document: array<mixed>, cache_control: string}|\WP_Error
     */
    public static function fetch_json(string $url, int $max_bytes, string $what, string $user_agent): array|\WP_Error
    {
        try {
            self::assert_safe_url($url);
        } catch (\InvalidArgumentException $e) {
            return new \WP_Error('invalid_client', sprintf('the %s URL is not acceptable: %s', $what, $e->getMessage()));
        }

        $response = wp_safe_remote_get($url, [
            'timeout'             => self::TIMEOUT,
            'redirection'         => 0,
            'limit_response_size' => $max_bytes + 1,
            'user-agent'          => $user_agent,
            'headers'             => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('invalid_client', sprintf('the %s could not be fetched.', $what));
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 300 && $code < 400) {
            return new \WP_Error('invalid_client', sprintf('the %s URL redirected; redirects are not followed.', $what));
        }
        if (200 !== $code) {
            return new \WP_Error('invalid_client', sprintf('the %s URL answered HTTP %d.', $what, $code));
        }

        $declared = wp_remote_retrieve_header($response, 'content-length');
        $body     = (string) wp_remote_retrieve_body($response);
        $declared = is_array($declared) ? implode('', array_slice(array_filter($declared, 'is_string'), -1)) : $declared;
        if (('' !== $declared && (int) $declared > $max_bytes) || strlen($body) > $max_bytes) {
            return new \WP_Error('invalid_client', sprintf('the %s is larger than %d bytes.', $what, $max_bytes));
        }

        $document = json_decode($body, true, 16);
        if (! is_array($document) || array_is_list($document)) {
            return new \WP_Error('invalid_client', sprintf('the %s is not a JSON object.', $what));
        }

        $cache_control = wp_remote_retrieve_header($response, 'cache-control');
        $cache_control = is_array($cache_control) ? implode(', ', $cache_control) : (string) $cache_control;

        return ['document' => $document, 'cache_control' => $cache_control];
    }

    /** @return array{client_id: string, client_name: string, redirect_uris: string[], auth_method: string}|\WP_Error */
    private static function validate_document(array $document, string $client_id): array|\WP_Error
    {
        if (! isset($document['client_id']) || $client_id !== $document['client_id']) {
            return self::invalid('the document\'s client_id does not match its URL.');
        }

        $name = $document['client_name'] ?? null;
        if (! is_string($name) || '' === trim($name)) {
            return self::invalid('the document has no client_name.');
        }

        $uris = $document['redirect_uris'] ?? null;
        if (! is_array($uris) || [] === $uris) {
            return self::invalid('the document has no redirect_uris.');
        }
        foreach ($uris as $uri) {
            if (! is_string($uri) || ! Redirect_Uri_Validator::is_valid($uri)) {
                return self::invalid('the document lists a redirect_uri that is not a valid absolute HTTPS (or loopback) URI.');
            }
        }

        if (array_key_exists('client_secret', $document) || array_key_exists('client_secret_expires_at', $document)) {
            return self::invalid('a client metadata document must not carry a client secret.');
        }

        $auth = self::validate_auth_method($document, $client_id);
        if (is_wp_error($auth)) {
            return $auth;
        }

        return [
            'client_id'     => $client_id,
            'client_name'   => sanitize_text_field(substr($name, 0, 200)),
            'redirect_uris' => array_values(array_unique($uris)),
        ] + $auth;
    }

    /**
     * The token endpoint authentication the document declares (issue
     * #1098). The singular token_endpoint_auth_method is what the client
     * will use, so it is honoured as declared and never downgraded:
     *
     *  - "none" (or absent): a public client, PKCE alone, as before.
     *  - "private_key_jwt": RFC 7523 client assertions, as ChatGPT
     *    declares. The CIMD draft says the server MUST then require that
     *    authentication. The keys come from a jwks_uri on the document's own
     *    origin, or an inline jwks; a pinned token_endpoint_auth_signing_alg
     *    must be one Client_Assertion verifies.
     *  - anything else, the shared-secret methods included, is refused: a
     *    secret cannot live in a document anyone can read.
     *
     * @param array<mixed> $document
     * @return array{auth_method: string, jwks_uri?: string, jwks?: array<mixed>, signing_alg?: string}|\WP_Error
     */
    private static function validate_auth_method(array $document, string $client_id): array|\WP_Error
    {
        $method = $document['token_endpoint_auth_method'] ?? 'none';

        if ('none' === $method) {
            return ['auth_method' => 'none'];
        }

        if (Client_Assertion::METHOD !== $method) {
            $shown = is_string($method) && 1 === preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $method) ? '"' . $method . '"' : 'an unrecognised value';

            return self::invalid(sprintf(
                'the document declares token_endpoint_auth_method %s. Client metadata documents may use "none" or "private_key_jwt"; a client that authenticates with a client secret must use dynamic client registration instead.',
                $shown
            ));
        }

        $auth = ['auth_method' => Client_Assertion::METHOD];

        $alg = $document['token_endpoint_auth_signing_alg'] ?? null;
        if (null !== $alg) {
            if (! is_string($alg) || ! in_array($alg, Client_Assertion::ALGORITHMS, true)) {
                return self::invalid('the document\'s token_endpoint_auth_signing_alg is not one of ' . implode(', ', Client_Assertion::ALGORITHMS) . '.');
            }
            $auth['signing_alg'] = $alg;
        }

        $jwks_uri = $document['jwks_uri'] ?? null;
        $jwks     = $document['jwks'] ?? null;
        if (null !== $jwks_uri && null !== $jwks) {
            return self::invalid('the document carries both jwks and jwks_uri; RFC 7591 allows only one.');
        }

        if (null !== $jwks) {
            $keys = is_array($jwks) ? Client_Assertion::parse_jwks($jwks) : [];
            if ([] === $keys) {
                return self::invalid('the document\'s jwks holds no usable RSA signing key.');
            }
            $auth['jwks'] = $jwks;

            return $auth;
        }

        if (! is_string($jwks_uri) || '' === $jwks_uri) {
            return self::invalid('a private_key_jwt client must publish its keys at a jwks_uri (or an inline jwks).');
        }

        try {
            self::assert_safe_url($jwks_uri);
        } catch (\InvalidArgumentException $e) {
            return self::invalid('the document\'s jwks_uri is not acceptable: ' . $e->getMessage());
        }

        // Same origin as the document: the document is the client's
        // identity, and a key set it points at elsewhere would let whoever
        // controls that other host sign for the client.
        if (strtolower((string) wp_parse_url($jwks_uri, PHP_URL_HOST)) !== strtolower((string) wp_parse_url($client_id, PHP_URL_HOST))) {
            return self::invalid('the document\'s jwks_uri must be on the same host as its client_id.');
        }

        $auth['jwks_uri'] = $jwks_uri;

        return $auth;
    }

    private static function invalid(string $reason): \WP_Error
    {
        return new \WP_Error('invalid_client', 'The client metadata document was refused: ' . $reason);
    }

    private static function cache_key(string $client_id): string
    {
        return self::CACHE_PREFIX . md5($client_id);
    }

    // Approval registry.

    public static function requires_approval(): bool
    {
        return (bool) apply_filters('wpmcp_oauth_cimd_require_approval', (bool) get_option(self::APPROVAL_OPTION, false));
    }

    /**
     * Turning approval on grandfathers every client already seen, so the
     * switch holds back new clients without cutting off connections that
     * were working a moment ago.
     */
    public static function set_requires_approval(bool $required): void
    {
        update_option(self::APPROVAL_OPTION, $required, false);

        if (! $required) {
            return;
        }

        $clients = self::clients();
        foreach ($clients as $client_id => $client) {
            if ('seen' === ($client['status'] ?? '')) {
                $clients[ $client_id ]['status'] = 'approved';
            }
        }
        self::save($clients);
    }

    /**
     * Every recorded client, keyed by client_id: { client_id, client_name,
     * redirect_hosts, status, first_seen, last_seen }.
     *
     * @return array<string, array>
     */
    public static function clients(): array
    {
        $stored = get_option(self::REGISTRY_OPTION, []);
        return is_array($stored) ? $stored : [];
    }

    /**
     * 'approved' or 'denied' (an administrator decided), 'pending' (seen
     * while approval was required, waiting), 'seen' (used while approval
     * was off), or '' for a client never seen.
     */
    public static function status(string $client_id): string
    {
        return (string) (self::clients()[ $client_id ]['status'] ?? '');
    }

    /**
     * Record a validated client and decide whether it may authorize now.
     * Called by /authorize after the document validated.
     *
     * @param array{client_id: string, client_name: string, redirect_uris: string[]} $client
     * @return true|\WP_Error true when the client may authorize now (bool in the
     *                        signature: a true type needs PHP 8.2, issue #960)
     */
    public static function admit(array $client): bool|\WP_Error
    {
        $client_id = $client['client_id'];
        $clients   = self::clients();
        $status    = (string) ($clients[ $client_id ]['status'] ?? '');
        $required  = self::requires_approval();

        if ('' === $status) {
            $status = $required ? 'pending' : 'seen';
        }

        $clients[ $client_id ] = [
            'client_id'      => $client_id,
            'client_name'    => $client['client_name'],
            'redirect_hosts' => self::redirect_hosts($client['redirect_uris']),
            'status'         => $status,
            'first_seen'     => (int) ($clients[ $client_id ]['first_seen'] ?? time()),
            'last_seen'      => time(),
        ] + self::auth_fields($client);
        self::save(self::trim($clients));

        return self::admitted_status($status, $required)
            ? true
            : self::refusal($status);
    }

    /**
     * How the token endpoint must authenticate a recorded client (issue
     * #1098), as its document declared at the last authorization: an
     * auth_method of "none" or "private_key_jwt", plus jwks_uri or jwks and
     * an optional signing_alg for the latter. An entry recorded before this
     * field existed came from a document that could only declare "none".
     *
     * @return array{auth_method: string, jwks_uri?: string, jwks?: array<mixed>, signing_alg?: string}
     */
    public static function auth_of(string $client_id): array
    {
        $entry  = self::clients()[ $client_id ] ?? [];
        $method = $entry['auth_method'] ?? null;

        return ['auth_method' => is_string($method) ? $method : 'none'] + self::auth_fields($entry);
    }

    /**
     * @param array<mixed> $source
     * @return array{auth_method?: string, jwks_uri?: string, jwks?: array<mixed>, signing_alg?: string}
     */
    private static function auth_fields(array $source): array
    {
        $fields = [];
        foreach (['auth_method', 'jwks_uri', 'signing_alg'] as $key) {
            if (isset($source[ $key ]) && is_string($source[ $key ])) {
                $fields[ $key ] = $source[ $key ];
            }
        }
        if (isset($source['jwks']) && is_array($source['jwks'])) {
            $fields['jwks'] = $source['jwks'];
        }

        return $fields;
    }

    /** Whether a CIMD client_id may redeem codes and refresh tokens right now. */
    public static function is_admitted(string $client_id): bool
    {
        if (! self::is_enabled() || ! self::looks_like_url($client_id)) {
            return false;
        }

        return self::admitted_status(self::status($client_id), self::requires_approval());
    }

    public static function approve(string $client_id): bool
    {
        return self::set_status($client_id, 'approved');
    }

    /** Deny a client and revoke every token it holds. */
    public static function deny(string $client_id): bool
    {
        $changed = self::set_status($client_id, 'denied');
        Token_Store::revoke_for_client($client_id);
        Refresh_Token_Store::revoke_for_client($client_id);

        return $changed;
    }

    /** Drop a client from the registry, so it is treated as newly seen next time. */
    public static function forget(string $client_id): bool
    {
        $clients = self::clients();
        if (! isset($clients[ $client_id ])) {
            return false;
        }
        unset($clients[ $client_id ]);
        self::save($clients);
        self::flush_cache($client_id);

        return true;
    }

    private static function admitted_status(string $status, bool $required): bool
    {
        if ('denied' === $status) {
            return false;
        }

        return 'approved' === $status || ! $required;
    }

    private static function refusal(string $status): \WP_Error
    {
        return 'denied' === $status
            ? new \WP_Error('access_denied', 'A site administrator has blocked this client.')
            : new \WP_Error('access_denied', 'This client is waiting for a site administrator to approve it on the WPMCP Connection screen.');
    }

    private static function set_status(string $client_id, string $status): bool
    {
        $clients = self::clients();
        if (! isset($clients[ $client_id ])) {
            if ('denied' !== $status || ! self::looks_like_url($client_id)) {
                return false;
            }
            $clients[ $client_id ] = [
                'client_id'      => $client_id,
                'client_name'    => '',
                'redirect_hosts' => [],
                'first_seen'     => time(),
                'last_seen'      => time(),
            ];
        }

        $clients[ $client_id ]['status'] = $status;
        self::save(self::trim($clients));

        return true;
    }

    /** @return string[] */
    private static function redirect_hosts(array $uris): array
    {
        $hosts = [];
        foreach ($uris as $uri) {
            $host = (string) wp_parse_url((string) $uri, PHP_URL_HOST);
            $hosts[] = '' === $host ? (string) wp_parse_url((string) $uri, PHP_URL_SCHEME) . ':' : strtolower(trim($host, '[]'));
        }

        return array_values(array_unique($hosts));
    }

    /** Keep the registry bounded by dropping the least recently seen undecided entries, never an administrator's decision. */
    private static function trim(array $clients): array
    {
        if (count($clients) <= self::MAX_CLIENTS) {
            return $clients;
        }

        $pending = array_filter($clients, static fn (array $c): bool => in_array($c['status'] ?? '', ['pending', 'seen'], true));
        uasort($pending, static fn (array $a, array $b): int => (int) $a['last_seen'] <=> (int) $b['last_seen']);
        foreach (array_keys($pending) as $client_id) {
            if (count($clients) <= self::MAX_CLIENTS) {
                break;
            }
            unset($clients[ $client_id ]);
        }

        return $clients;
    }

    private static function save(array $clients): void
    {
        update_option(self::REGISTRY_OPTION, $clients, false);
    }
}
