<?php

declare(strict_types=1);

namespace WPMCP\MCP;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WPMCP's own answer to tools/call (and the other MCP methods): the stdio
 * transport's dispatcher, which the HTTP route uses too (issue #628), so
 * the transports cannot drift.
 */
interface Tool_Call_Handler
{
    /**
     * @param array<string, mixed> $request a decoded JSON-RPC request
     * @return array<string, mixed>|null its JSON-RPC response; null for a notification
     */
    public function handle_request(array $request): ?array;

    /**
     * Whether a tool by this advertised name is this server's to answer:
     * registered, or declared and answering with its refusal.
     */
    public function knows_tool(string $tool_name): bool;
}
