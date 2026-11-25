<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One MCP session the MCP adapter recorded for a user: when it was opened,
 * when it was last active, and the client's self-reported name and version
 * (untrusted text, escaped and capped where it is read).
 */
final class Session_Evidence
{
    public function __construct(
        public readonly string $client_name,
        public readonly string $client_version,
        public readonly int $created_at,
        public readonly int $last_activity,
    ) {
    }
}
