<?php

declare(strict_types=1);

namespace WPMCP\MCP;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A 2025-11-25 tools/call on the HTTP route, answered by WPMCP instead of
 * the bundled adapter (issue #628).
 *
 * The adapter's tools handler sends a WP_Error back as its message alone, so
 * the code (insufficient_scope, wpmcp_rate_limited, plugin_inactive,
 * unfiltered_html_required) and the data (retry_after) never reached the
 * agent, and a dispatcher pair's refusal went out as a success. The stdio
 * transport and the 2026-07-28 bridge already answer tools/call through WPMCP's
 * own dispatcher (Tool_Call_Handler); this makes the 2025 session
 * the same, so every transport sends the same result (Refusal_Result).
 *
 * The adapter keeps the session lifecycle and every request this does not
 * take. It takes only what the adapter would route to its tools handler:
 *
 *  - one JSON-RPC request (a batch stays the adapter's), method tools/call,
 *    with an id;
 *  - the 2025 lifecycle at a revision the route serves; the bridge answers
 *    2026-07-28, and the adapter its own unsupported-version error;
 *  - a tool this server knows: a registered wpmcp tool, or one declared but
 *    not registered for this connection, which answers with its refusal. A
 *    name nothing declares keeps the adapter's "Tool not found";
 *  - a session the adapter's own check accepts (Mcp_Session). When that
 *    check cannot be made, the adapter answers.
 *
 * It runs from Modern_Http_Bridge on rest_dispatch_request, which core
 * applies after the route's permission_callback, so the transport's
 * permission check (and everything WPMCP layers on it) still decides who
 * gets in.
 */
final class Http_Tool_Call
{
    public function __construct(
        private readonly Mcp_Session $session,
        private readonly Tool_Call_Handler $handler,
    ) {
    }

    /**
     * The response to $message when it is a call this route answers itself,
     * or null to leave the request to the adapter.
     *
     * @param array<mixed> $message the decoded JSON-RPC message
     */
    public function answer(\WP_REST_Request $request, array $message): ?\WP_REST_Response
    {
        $call = $this->call($request, $message);
        if (null === $call || ! $this->session->accepts($request)) {
            return null;
        }

        try {
            $response = $this->handler->handle_request($call);
        } catch (\Throwable $e) {
            // What the adapter answers when its handler throws.
            $response = [ 'jsonrpc' => '2.0', 'id' => $call['id'], 'error' => [ 'code' => -32603, 'message' => 'Handler error occurred' ] ];
        }

        $error = $response['error'] ?? null;
        $code  = is_array($error) ? ($error['code'] ?? null) : null;

        return new \WP_REST_Response($response, -32603 === $code ? 500 : 200);
    }

    /**
     * $message as the request to dispatch, or null when it is not a call
     * this route answers.
     *
     * @param array<mixed> $message
     * @return array{jsonrpc: string, id: int|string, method: string, params: array<mixed>}|null
     */
    private function call(\WP_REST_Request $request, array $message): ?array
    {
        $id     = $message['id'] ?? null;
        $params = $message['params'] ?? [];
        if ('2.0' !== ($message['jsonrpc'] ?? null) || 'tools/call' !== ($message['method'] ?? null) || ! (is_int($id) || is_string($id)) || ! is_array($params)) {
            return null;
        }

        $name = $params['name'] ?? null;
        if (! is_string($name) || ! $this->legacy_revision($request, $params) || ! $this->handler->knows_tool($name)) {
            return null;
        }

        return [ 'jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => $params ];
    }

    /**
     * Whether the request is on the 2025 lifecycle at a revision the route
     * serves: the MCP-Protocol-Version header and the revision the body
     * names, where either is given.
     *
     * @param array<mixed> $params
     */
    private function legacy_revision(\WP_REST_Request $request, array $params): bool
    {
        $header = $request->get_header('mcp_protocol_version');
        $meta   = $params['_meta'] ?? null;
        $meta   = is_object($meta) ? get_object_vars($meta) : $meta;
        $body   = is_array($meta) ? ($meta[ Protocol_Revision::META_PROTOCOL_VERSION ] ?? null) : null;

        foreach ([ null === $header ? null : trim($header), $body ] as $named) {
            if (null === $named) {
                continue;
            }
            if (! is_string($named) || Protocol_Revision::MODERN === $named || ! Protocol_Revision::is_servable($named)) {
                return false;
            }
        }

        return true;
    }
}
