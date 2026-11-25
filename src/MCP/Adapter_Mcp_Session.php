<?php

declare(strict_types=1);

namespace WPMCP\MCP;

use WP\MCP\Transport\Infrastructure\HttpRequestContext;
use WP\MCP\Transport\Infrastructure\HttpSessionValidator;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Mcp_Session through the loaded adapter's own session validator, the one
 * its HTTP transport runs before every request but initialize: the
 * Mcp-Session-Id header, a logged-in user, and a session that user holds
 * and has not let expire.
 *
 * An older adapter copy can win the autoload (WooCommerce 11.2 bundles
 * 0.1.0, issue #500). Without the validator, or when it does not take this
 * request, the answer is false and the adapter keeps the call.
 */
final class Adapter_Mcp_Session implements Mcp_Session
{
    public function accepts(\WP_REST_Request $request): bool
    {
        if (! class_exists(HttpRequestContext::class) || ! class_exists(HttpSessionValidator::class)) {
            return false;
        }

        try {
            return true === HttpSessionValidator::validate_session(new HttpRequestContext($request));
        } catch (\Throwable $e) {
            return false;
        }
    }
}
