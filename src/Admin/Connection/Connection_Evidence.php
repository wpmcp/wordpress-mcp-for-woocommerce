<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Everything WordPress recorded that can show an app connected: the
 * password's last use and address (core), the MCP requests the route
 * answered for that password (Activity_Recorder), the first tool call after
 * the password was made (the request log) and, for an OAuth app, its grants
 * and the MCP sessions of the users who approved it (the MCP adapter).
 */
final class Connection_Evidence
{
    /**
     * @param list<Session_Evidence> $sessions
     * @param list<Oauth_Grant>      $grants
     */
    public function __construct(
        public readonly ?int $password_last_used,
        public readonly ?string $password_last_ip,
        public readonly array $sessions,
        public readonly ?Tool_Call $tool_call,
        public readonly array $grants,
        public readonly ?Mcp_Activity $activity = null,
    ) {
    }
}
