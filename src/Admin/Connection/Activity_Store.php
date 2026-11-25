<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Where the MCP activity of each application password is kept, per user and
 * site. Wp_Activity_Store is the WordPress adapter; tests use an in-memory
 * one.
 */
interface Activity_Store
{
    /** What was recorded for the application password $uuid of $user_id on this site, or null. */
    public function find(int $user_id, string $uuid): ?Mcp_Activity;

    public function save(int $user_id, string $uuid, Mcp_Activity $activity): void;

    /** Drops what was recorded for a password, once it is revoked. */
    public function forget(int $user_id, string $uuid): void;
}
