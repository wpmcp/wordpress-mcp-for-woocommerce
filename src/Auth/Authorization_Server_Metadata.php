<?php

namespace WPMCP\Auth;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Builds the RFC 8414 OAuth 2.0 Authorization Server Metadata document,
 * served at /.well-known/oauth-authorization-server by
 * Metadata_Endpoint::authorization_server(). $issuer is the site's own
 * origin (scheme + host, no trailing slash), matching RFC 8414's
 * requirement that 'issuer' be the authorization server's own identifier.
 *
 * Deliberately advertises only what this plugin actually implements:
 * authorization_code plus refresh_token as the grant types (the latter
 * added with rotation and reuse detection in issue #133), S256 as the sole
 * PKCE method (never 'plain'), and client_secret_post as the sole token
 * endpoint auth method for registered clients -- every client
 * Client_Store::create() registers is issued a secret and
 * Token_Grant::exchange() requires it. While Client ID Metadata Document
 * support (issue #388) is on, 'none' is advertised for public CIMD clients
 * and 'private_key_jwt' for those that sign RFC 7523 client assertions
 * (issue #1098, ChatGPT), with the signing algorithms Client_Assertion
 * verifies and client_id_metadata_document_supported.
 */
class Authorization_Server_Metadata
{
    public static function build(string $issuer): array
    {
        $base = self::base($issuer);
        $cimd = Client_Metadata_Document::is_enabled();

        $doc = [
            'issuer'                                => $issuer,
            'authorization_endpoint'                 => $base . '/authorize',
            'token_endpoint'                          => self::token_endpoint($issuer),
            'registration_endpoint'                   => $base . '/register',
            'response_types_supported'                => ['code'],
            'grant_types_supported'                   => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported'        => ['S256'],
            'token_endpoint_auth_methods_supported'   => $cimd ? ['client_secret_post', 'none', Client_Assertion::METHOD] : ['client_secret_post'],
            'scopes_supported'                        => Mcp_Resource::SCOPES_SUPPORTED,
        ];

        if ($cimd) {
            $doc['token_endpoint_auth_signing_alg_values_supported'] = Client_Assertion::ALGORITHMS;
            $doc['client_id_metadata_document_supported']            = true;
        }

        return $doc;
    }

    /** The token endpoint URL advertised for $issuer; also a valid client assertion aud. */
    public static function token_endpoint(string $issuer): string
    {
        return self::base($issuer) . '/token';
    }

    private static function base(string $issuer): string
    {
        return rtrim($issuer, '/') . '/wp-json/wpmcp/v1/oauth';
    }

    /**
     * The same metadata in the shape OpenID Connect Discovery parsers expect,
     * served at /.well-known/openid-configuration (issue #473). That is the
     * one discovery address every install shape can answer: for an issuer
     * with a path (a subdirectory install or subsite) the OpenID form appends
     * the well-known segment to the issuer, so it reaches WordPress, while
     * the RFC 8414 form inserts it at the domain root.
     *
     * Adds subject_types_supported, which OpenID parsers require. No ID
     * token is issued, so nothing about ID tokens is advertised.
     */
    public static function build_openid(string $issuer): array
    {
        return self::build($issuer) + ['subject_types_supported' => ['public']];
    }
}
