<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Reads what WordPress recorded about a connection. Wp_Evidence_Source is
 * the WordPress adapter; tests use an in-memory one.
 */
interface Evidence_Source
{
    /** Evidence for an application password WPMCP issued, made at $since. */
    public function for_password(int $user_id, string $uuid, int $since): Connection_Evidence;

    /** Evidence for an app that signs in with OAuth. */
    public function for_oauth(Client_App $app): Connection_Evidence;
}
