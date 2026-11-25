<?php

declare(strict_types=1);

namespace WPMCP\Auth;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * RFC 7523 private_key_jwt client authentication for Client ID Metadata
 * Document clients (issue #1098).
 *
 * ChatGPT's metadata document (https://chatgpt.com/oauth/client.json)
 * declares token_endpoint_auth_method "private_key_jwt", signing alg RS256
 * and a jwks_uri on its own origin. The CIMD draft says a server MUST then
 * require that client authentication, so the token endpoint verifies the
 * client_assertion the client sends with every token request:
 *
 *  - client_assertion_type is urn:ietf:params:oauth:client-assertion-type:jwt-bearer;
 *  - the JWS uses RS256, RS384 or RS512 (and the document's pinned alg when
 *    it pins one); "none", HMAC and every header that would let the
 *    assertion name its own key (jku, jwk, x5u, x5c) or demand unknown
 *    processing (crit) are refused;
 *  - the signature verifies with an RSA key of at least 2048 bits from the
 *    client's JWKS: its inline jwks, or its jwks_uri fetched through the same
 *    SSRF guard as the document (Client_Metadata_Document::fetch_json()) and
 *    cached for at most five minutes. An unknown kid refetches once, at most
 *    once a minute per URI, so a key rotation does not lock the client out;
 *  - iss and sub equal the client_id; aud names this authorization server
 *    (its token endpoint or its issuer identifier, the two forms RFC 7523
 *    and its update allow);
 *  - exp is present, not past (with a minute of clock leeway) and at most
 *    ten minutes ahead; nbf and iat, when present, are not in the future;
 *  - jti is present and single-use: it is recorded until the assertion
 *    expires, in one options row updated by compare-and-swap
 *    (Atomic_Option), so two concurrent requests cannot both spend it.
 *
 * The jti is recorded only after everything else passed, so a forged
 * assertion cannot burn the real client's jti.
 */
final class Client_Assertion
{
    public const METHOD         = 'private_key_jwt';
    public const ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
    public const ALGORITHMS     = ['RS256', 'RS384', 'RS512'];

    public const JTI_OPTION = 'wpmcp_oauth_assertion_jtis';

    public const LEEWAY       = 60;
    public const MAX_LIFETIME = 600;
    public const MIN_RSA_BITS = 2048;

    public const JWKS_MAX_BYTES   = 16384;
    public const JWKS_MAX_TTL     = 300;
    public const JWKS_FAILURE_TTL = 30;
    public const REFETCH_COOLDOWN = 60;

    private const MAX_ASSERTION_BYTES = 8192;
    private const MAX_KEYS            = 10;
    private const MAX_JTIS            = 5000;
    private const FORBIDDEN_HEADERS   = ['jku', 'jwk', 'x5u', 'x5c', 'crit'];
    private const DIGESTS             = ['RS256' => OPENSSL_ALGO_SHA256, 'RS384' => OPENSSL_ALGO_SHA384, 'RS512' => OPENSSL_ALGO_SHA512];

    private const CACHE_PREFIX   = 'wpmcp_oauth_jwks_';
    private const REFETCH_PREFIX = 'wpmcp_oauth_jwks_refetch_';

    /**
     * Verify $jwt as $client_id's client assertion.
     *
     * @param array<mixed> $auth
     *        The client's binding, from Client_Metadata_Document::auth_of().
     * @return true|\WP_Error Error messages are short lowercase reasons.
     */
    public static function verify(string $jwt, string $client_id, array $auth): bool|\WP_Error
    {
        $parsed = self::parse($jwt);
        if (is_wp_error($parsed)) {
            return $parsed;
        }
        [$header, $claims, $signing_input, $signature] = $parsed;

        $alg = $header['alg'] ?? null;
        if (! is_string($alg) || ! isset(self::DIGESTS[ $alg ])) {
            return self::fail('the client assertion is not signed with ' . implode(', ', self::ALGORITHMS) . '.');
        }
        if (isset($auth['signing_alg']) && $auth['signing_alg'] !== $alg) {
            return self::fail('the client assertion is not signed with the algorithm the client metadata document declares.');
        }
        foreach (self::FORBIDDEN_HEADERS as $name) {
            if (array_key_exists($name, $header)) {
                return self::fail(sprintf('the client assertion carries a "%s" header, which is not accepted.', $name));
            }
        }

        $checked = self::check_claims($claims, $client_id);
        if (is_wp_error($checked)) {
            return $checked;
        }

        $kid      = isset($header['kid']) && is_string($header['kid']) ? $header['kid'] : null;
        $verified = self::verify_signature($signing_input, $signature, $alg, $kid, $auth);
        if (is_wp_error($verified)) {
            return $verified;
        }

        $jti = $claims['jti'] ?? null;
        $exp = $claims['exp'] ?? null;
        if (! is_string($jti) || ! is_int($exp) && ! is_float($exp)) {
            return self::fail('the client assertion has no jti or exp.');
        }

        return self::spend_jti($client_id, $jti, (int) $exp);
    }

    /**
     * The iss claim of an assertion, unverified, so a token request that
     * omits client_id (RFC 7523 allows that) can still be routed to the
     * client the assertion claims to be. verify() then checks it.
     */
    public static function unverified_issuer(string $jwt): ?string
    {
        $parsed = self::parse($jwt);
        if (is_wp_error($parsed)) {
            return null;
        }
        $iss = $parsed[1]['iss'] ?? null;

        return is_string($iss) && '' !== $iss ? $iss : null;
    }

    /**
     * The usable keys of a JWK Set: RSA public signing keys of at least
     * MIN_RSA_BITS, each as a PEM public key. A key that carries private
     * parameters, declares another use or an unsupported alg is skipped.
     *
     * @param array<mixed> $jwks
     * @return array<int, array{kid: ?string, alg: ?string, pem: string}>
     */
    public static function parse_jwks(array $jwks): array
    {
        $keys = $jwks['keys'] ?? null;
        if (! is_array($keys) || ! array_is_list($keys)) {
            return [];
        }

        $usable = [];
        foreach (array_slice($keys, 0, self::MAX_KEYS) as $jwk) {
            $key = is_array($jwk) ? self::rsa_key($jwk) : null;
            if (null !== $key) {
                $usable[] = $key;
            }
        }

        return $usable;
    }

    /**
     * The usable keys at $jwks_uri, cached. $fresh skips the cache.
     *
     * @return array<int, array{kid: ?string, alg: ?string, pem: string}>|\WP_Error
     */
    public static function keys_for(string $jwks_uri, bool $fresh = false): array|\WP_Error
    {
        $key = self::CACHE_PREFIX . md5($jwks_uri);

        if (! $fresh) {
            $cached = get_transient($key);
            if (is_array($cached) && isset($cached['ok'])) {
                $jwks  = $cached['jwks'] ?? null;
                $error = $cached['error'] ?? null;
                return true === $cached['ok'] && is_array($jwks)
                    ? self::parse_jwks($jwks)
                    : self::fail(is_string($error) ? $error : 'the client\'s JWKS could not be used.');
            }
        }

        $fetched = Client_Metadata_Document::fetch_json($jwks_uri, self::JWKS_MAX_BYTES, 'client JWKS', 'WPMCP-OAuth-Client-Metadata/1.0');
        if (is_wp_error($fetched)) {
            set_transient($key, ['ok' => false, 'error' => $fetched->get_error_message()], self::JWKS_FAILURE_TTL);
            return self::fail($fetched->get_error_message());
        }

        $keys = self::parse_jwks($fetched['document']);
        if ([] === $keys) {
            $error = 'the client\'s JWKS holds no usable RSA signing key.';
            set_transient($key, ['ok' => false, 'error' => $error], self::JWKS_FAILURE_TTL);
            return self::fail($error);
        }

        $ttl = min(self::JWKS_MAX_TTL, Client_Metadata_Document::cache_ttl($fetched['cache_control']));
        if ($ttl > 0) {
            set_transient($key, ['ok' => true, 'jwks' => $fetched['document']], $ttl);
        } else {
            delete_transient($key);
        }

        return $keys;
    }

    public static function flush_jwks_cache(string $jwks_uri): void
    {
        delete_transient(self::CACHE_PREFIX . md5($jwks_uri));
        delete_transient(self::REFETCH_PREFIX . md5($jwks_uri));
    }

    /** @return array{0: array<mixed>, 1: array<mixed>, 2: string, 3: string}|\WP_Error */
    private static function parse(string $jwt): array|\WP_Error
    {
        if ('' === $jwt || strlen($jwt) > self::MAX_ASSERTION_BYTES) {
            return self::fail('the client assertion is missing or too large.');
        }

        $parts = explode('.', $jwt);
        if (3 !== count($parts)) {
            return self::fail('the client assertion is not a signed JWT.');
        }

        $header    = self::decode_json($parts[0]);
        $claims    = self::decode_json($parts[1]);
        $signature = self::b64_decode($parts[2]);
        if (null === $header || null === $claims || null === $signature || '' === $signature) {
            return self::fail('the client assertion is not a well-formed signed JWT.');
        }

        return [$header, $claims, $parts[0] . '.' . $parts[1], $signature];
    }

    /**
     * @param array<mixed> $claims
     * @return true|\WP_Error
     */
    private static function check_claims(array $claims, string $client_id): bool|\WP_Error
    {
        if (($claims['iss'] ?? null) !== $client_id || ($claims['sub'] ?? null) !== $client_id) {
            return self::fail('the client assertion\'s iss and sub must both be the client_id.');
        }

        $aud = $claims['aud'] ?? null;
        $aud = is_string($aud) ? [$aud] : $aud;
        if (! is_array($aud) || [] === array_intersect(array_filter($aud, 'is_string'), self::audiences())) {
            return self::fail('the client assertion\'s aud does not name this authorization server.');
        }

        $now = time();
        $exp = $claims['exp'] ?? null;
        if (! self::is_numeric_date($exp)) {
            return self::fail('the client assertion has no exp.');
        }
        if ($exp < $now - self::LEEWAY) {
            return self::fail('the client assertion has expired.');
        }
        if ($exp > $now + self::MAX_LIFETIME + self::LEEWAY) {
            return self::fail(sprintf('the client assertion\'s exp is more than %d seconds ahead.', self::MAX_LIFETIME));
        }
        foreach (['nbf', 'iat'] as $name) {
            if (! array_key_exists($name, $claims)) {
                continue;
            }
            if (! self::is_numeric_date($claims[ $name ]) || $claims[ $name ] > $now + self::LEEWAY) {
                return self::fail(sprintf('the client assertion\'s %s is in the future.', $name));
            }
        }

        $jti = $claims['jti'] ?? null;
        if (! is_string($jti) || '' === $jti || strlen($jti) > 255) {
            return self::fail('the client assertion has no jti.');
        }

        return true;
    }

    /** RFC 7519 NumericDate: a finite number. */
    private static function is_numeric_date(mixed $value): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value);
    }

    /**
     * The values an assertion's aud may take for this server: its token
     * endpoint and its issuer identifier, as advertised in its metadata.
     *
     * @return string[]
     */
    private static function audiences(): array
    {
        $issuer = untrailingslashit(home_url());

        return [Authorization_Server_Metadata::token_endpoint($issuer), $issuer];
    }

    /**
     * @param array<mixed> $auth
     * @return true|\WP_Error
     */
    private static function verify_signature(string $input, string $signature, string $alg, ?string $kid, array $auth): bool|\WP_Error
    {
        $jwks   = $auth['jwks'] ?? null;
        $inline = is_array($jwks);
        $uri    = is_string($auth['jwks_uri'] ?? null) ? $auth['jwks_uri'] : '';
        if (! $inline && '' === $uri) {
            return self::fail('the client has no published keys.');
        }

        $keys = is_array($jwks) ? self::parse_jwks($jwks) : self::keys_for($uri);
        if (is_wp_error($keys)) {
            return $keys;
        }

        $candidates = self::candidates($keys, $alg, $kid);

        // A kid the cached set does not know may be a rotated key: refetch
        // once, at most once per cooldown, so an unknown kid cannot be used
        // to make this server hammer the client's host.
        if ([] === $candidates && ! $inline && null !== $kid) {
            $cooldown = self::REFETCH_PREFIX . md5($uri);
            if (false === get_transient($cooldown)) {
                set_transient($cooldown, 1, self::REFETCH_COOLDOWN);
                $keys = self::keys_for($uri, true);
                if (is_wp_error($keys)) {
                    return $keys;
                }
                $candidates = self::candidates($keys, $alg, $kid);
            }
        }

        if ([] === $candidates) {
            return self::fail('no key in the client\'s JWKS matches the client assertion.');
        }

        foreach ($candidates as $key) {
            $public = openssl_pkey_get_public($key['pem']);
            if (false !== $public && 1 === openssl_verify($input, $signature, $public, self::DIGESTS[ $alg ])) {
                return true;
            }
        }

        return self::fail('the client assertion\'s signature does not verify.');
    }

    /**
     * @param array<int, array{kid: ?string, alg: ?string, pem: string}> $keys
     * @return array<int, array{kid: ?string, alg: ?string, pem: string}>
     */
    private static function candidates(array $keys, string $alg, ?string $kid): array
    {
        return array_values(array_filter($keys, static function (array $key) use ($alg, $kid): bool {
            if (null !== $key['alg'] && $alg !== $key['alg']) {
                return false;
            }

            return null === $kid || $kid === $key['kid'];
        }));
    }

    /**
     * @param array<mixed> $jwk
     * @return array{kid: ?string, alg: ?string, pem: string}|null
     */
    private static function rsa_key(array $jwk): ?array
    {
        if ('RSA' !== ($jwk['kty'] ?? null)) {
            return null;
        }
        if (isset($jwk['use']) && 'sig' !== $jwk['use']) {
            return null;
        }
        foreach (['d', 'p', 'q', 'dp', 'dq', 'qi', 'oth'] as $private) {
            if (array_key_exists($private, $jwk)) {
                return null;
            }
        }
        $alg = $jwk['alg'] ?? null;
        if (null !== $alg && (! is_string($alg) || ! isset(self::DIGESTS[ $alg ]))) {
            return null;
        }

        $n = is_string($jwk['n'] ?? null) ? self::b64_decode($jwk['n']) : null;
        $e = is_string($jwk['e'] ?? null) ? self::b64_decode($jwk['e']) : null;
        if (null === $n || null === $e || '' === $e) {
            return null;
        }
        $n = ltrim($n, "\0");
        if (strlen($n) * 8 < self::MIN_RSA_BITS || strlen($e) > 8) {
            return null;
        }

        $kid = isset($jwk['kid']) && is_string($jwk['kid']) ? $jwk['kid'] : null;

        return ['kid' => $kid, 'alg' => $alg, 'pem' => self::rsa_pem($n, $e)];
    }

    /** A SubjectPublicKeyInfo PEM for the RSA public key (n, e). */
    private static function rsa_pem(string $n, string $e): string
    {
        $rsa_public = self::der(0x30, self::der_integer($n) . self::der_integer($e));
        // AlgorithmIdentifier: rsaEncryption (1.2.840.113549.1.1.1), NULL parameters.
        $algorithm = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $spki      = self::der(0x30, $algorithm . self::der(0x03, "\x00" . $rsa_public));

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PEM armour for the client's RSA public key; not obfuscation.
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function der_integer(string $unsigned): string
    {
        $unsigned = ltrim($unsigned, "\0");
        if ('' === $unsigned || ord($unsigned[0]) > 0x7f) {
            $unsigned = "\0" . $unsigned;
        }

        return self::der(0x02, $unsigned);
    }

    private static function der(int $tag, string $content): string
    {
        $length = strlen($content);
        if ($length < 0x80) {
            return chr($tag) . chr($length) . $content;
        }
        $bytes = ltrim(pack('N', $length), "\0");

        return chr($tag) . chr(0x80 | strlen($bytes)) . $bytes . $content;
    }

    /** @return array<mixed>|null */
    private static function decode_json(string $segment): ?array
    {
        $raw = self::b64_decode($segment);
        if (null === $raw) {
            return null;
        }
        $decoded = json_decode($raw, true, 8);

        return is_array($decoded) && ! array_is_list($decoded) ? $decoded : null;
    }

    /** Strict base64url (RFC 7515 section 2: no padding, URL-safe alphabet). */
    private static function b64_decode(string $segment): ?string
    {
        if ('' === $segment || 1 !== preg_match('/^[A-Za-z0-9_-]+$/', $segment)) {
            return null;
        }
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decodes a base64url JWT segment (RFC 7515); not obfuscation.
        $raw = base64_decode(strtr($segment, '-_', '+/'), true);

        return false === $raw ? null : $raw;
    }

    /**
     * Record a verified assertion's jti until it expires; refuse one already
     * recorded. Expired entries are pruned on every write. A full store
     * fails closed.
     *
     * @return true|\WP_Error
     */
    private static function spend_jti(string $client_id, string $jti, int $exp): bool|\WP_Error
    {
        $id    = hash('sha256', $client_id . "\n" . $jti);
        $now   = time();
        $until = $exp + self::LEEWAY;

        $outcome = Atomic_Option::mutate(
            self::JTI_OPTION,
            static function (array $seen) use ($id, $now, $until): array {
                $live = array_filter($seen, static fn (mixed $expires): bool => is_int($expires) && $expires >= $now);
                if (isset($live[ $id ])) {
                    return [$live === $seen ? null : $live, 'replayed'];
                }
                if (count($live) >= self::MAX_JTIS) {
                    return [$live === $seen ? null : $live, 'full'];
                }
                $live[ $id ] = $until;

                return [$live, 'ok'];
            },
            'busy'
        );

        if ('ok' === $outcome) {
            return true;
        }

        return self::fail('replayed' === $outcome
            ? 'the client assertion\'s jti was already used.'
            : 'the client assertion could not be recorded; try again.');
    }

    private static function fail(string $reason): \WP_Error
    {
        return new \WP_Error('invalid_client', $reason);
    }
}
