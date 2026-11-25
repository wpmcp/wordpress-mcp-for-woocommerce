<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

use WPMCP\MCP\Server;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Hands each request the MCP route answered for an application password to
 * Activity_Recorder (issue #1258), on rest_post_dispatch: after the route's
 * permission check and whoever answered it (the MCP adapter, the 2026-07-28
 * bridge or WPMCP's own tools/call), with the final status. That is the one
 * point every HTTP request of either revision passes, so no transport records
 * anything itself. The stdio transport has nothing to record here: it runs
 * as the WP-CLI --user on the server, with no password to credit.
 *
 * A request counts when the route answered it (a 2xx status and no JSON-RPC
 * error) for a user signed in with an application password. A cookie session
 * or an OAuth token is not this record's to keep. A batch (2025-03-26) is
 * left out: the one request that names the client, initialize, is never part
 * of one.
 *
 * Registered by name (Connection_Page::register_handlers()), so a request
 * loads this class only when a REST response goes out, and the recorder only
 * when there is something to record.
 */
final class Activity_Hook
{
    private const ROUTE = '/' . Server::NAMESPACE . '/' . Server::SERVER_ID;

    /**
     * rest_post_dispatch, the composition root of the recording.
     *
     * @param mixed $response The response about to be sent; a filter value, so of any type.
     * @param mixed $server   The REST server (unused).
     * @param mixed $request  The request it answers.
     * @return mixed The response, unchanged.
     */
    public static function on_rest_post_dispatch(mixed $response, mixed $server = null, mixed $request = null): mixed
    {
        $answered = self::answered($response, $request);
        if (null !== $answered) {
            (new Activity_Recorder(new Wp_Activity_Store()))->record(
                $answered['user_id'],
                $answered['uuid'],
                Mcp_Client::from_message($answered['message']),
                time()
            );
        }

        return $response;
    }

    /**
     * The user, the application password and the decoded request of an MCP
     * request the route answered, or null when there is nothing to record.
     *
     * @param mixed $response The response about to be sent.
     * @param mixed $request  The request it answers.
     * @return array{user_id: int, uuid: string, message: array<mixed>}|null
     */
    private static function answered(mixed $response, mixed $request): ?array
    {
        if (! $response instanceof \WP_HTTP_Response || ! $request instanceof \WP_REST_Request || self::ROUTE !== $request->get_route()) {
            return null;
        }

        $status = $response->get_status();
        $uuid   = rest_get_authenticated_app_password();
        $user   = get_current_user_id();
        if ($status < 200 || $status > 299 || ! is_string($uuid) || '' === $uuid || $user <= 0) {
            return null;
        }

        $message = json_decode($request->get_body(), true);
        if (! is_array($message) || array_is_list($message) || ! is_string($message['method'] ?? null)) {
            return null;
        }

        $answer = $response->get_data();
        if (is_array($answer) && array_key_exists('error', $answer)) {
            return null;
        }

        return [ 'user_id' => $user, 'uuid' => $uuid, 'message' => $message ];
    }
}
