<?php

declare(strict_types=1);

namespace WPMCP\MCP;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The 2025-11-25 session check of the HTTP route (issue #628). The session
 * lifecycle belongs to the MCP adapter that mounts the route; a request WPMCP
 * answers itself must pass the same check first.
 */
interface Mcp_Session
{
    /**
     * Whether the request rides a live session of the current user. False
     * when the session is missing, unknown or expired, and when the check
     * cannot be made at all, so the caller leaves the request to the adapter.
     */
    public function accepts(\WP_REST_Request $request): bool;
}
