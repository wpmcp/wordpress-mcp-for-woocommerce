<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Keeps, for each application password, when the MCP route first and last
 * answered a request made with it and the name the client gave (issue
 * #1258). Every answered request counts, so an app on MCP 2026-07-28, which
 * never sends initialize, is recorded by its first tools/list as surely as a
 * 2025 app by its initialize.
 *
 * Writes stay cheap on a busy connection: a new password, or a client that
 * names itself differently, is written at once; the time of a known one at
 * most once every INTERVAL seconds.
 */
final class Activity_Recorder
{
    /** Least seconds between two writes of the same password's last request. */
    public const INTERVAL = 60;

    public function __construct(
        private readonly Activity_Store $store,
    ) {
    }

    /** The route answered a request of $user_id made with the password $uuid, naming $client, at $now. */
    public function record(int $user_id, string $uuid, Mcp_Client $client, int $now): void
    {
        if ($user_id <= 0 || '' === $uuid) {
            return;
        }

        $known = $this->store->find($user_id, $uuid);
        if (null === $known) {
            $this->store->save($user_id, $uuid, new Mcp_Activity($client, $now, $now));
            return;
        }

        $next = $known->after($client, $now);
        if ($next->client->equals($known->client) && $next->last_at - $known->last_at < self::INTERVAL) {
            return;
        }

        $this->store->save($user_id, $uuid, $next);
    }
}
