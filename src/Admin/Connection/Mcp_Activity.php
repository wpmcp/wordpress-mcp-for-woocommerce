<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * What WPMCP recorded for one application password on this site: the
 * client that last named itself with it, and when the MCP route first and
 * last answered a request made with it (the last to the minute:
 * Activity_Recorder writes it at most once a minute).
 */
final class Mcp_Activity
{
    public function __construct(
        public readonly Mcp_Client $client,
        public readonly int $first_at,
        public readonly int $last_at,
    ) {
    }

    /**
     * This activity after one more answered request at $now. A client that
     * names itself replaces the one kept; a request that names nobody (a
     * 2025 request after initialize) keeps it. The last request never moves
     * back in time.
     */
    public function after(Mcp_Client $client, int $now): self
    {
        return new self($client->is_named() ? $client : $this->client, $this->first_at, max($this->last_at, $now));
    }
}
