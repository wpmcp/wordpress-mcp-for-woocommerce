<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * "Check connection": a verdict from what WordPress recorded, never more.
 *
 * For an application password: Connected once the MCP route answered a
 * request made with that very password (Activity_Recorder, issue #1258):
 * any request, so an initialize on MCP 2025-11-25 and earlier and a
 * tools/list or tools/call on 2026-07-28, which has no initialize, both
 * count. PasswordOnly when WordPress accepted the password (core records the
 * first use at once, later uses at most daily) but the route answered no MCP
 * request made with it; NotSeen when it was never used. Requests and
 * sessions of the same user made with another password are never credited
 * to this one.
 *
 * For an OAuth app: OauthSignedIn when one of its clients holds a token,
 * OauthWaiting when one waits for approval, NotSeen otherwise.
 */
final class Connection_Check
{
    public function for_password(Connection_Evidence $evidence, bool $mcp_on): Check_Result
    {
        if (null !== $evidence->activity) {
            return new Check_Result(
                Verdict::Connected,
                $mcp_on,
                null,
                $evidence->password_last_used,
                $evidence->password_last_ip,
                $evidence->tool_call,
                null,
                $evidence->activity
            );
        }

        if (null === $evidence->password_last_used) {
            return new Check_Result(Verdict::NotSeen, $mcp_on, null, null, null, null, null);
        }

        return new Check_Result(
            Verdict::PasswordOnly,
            $mcp_on,
            null,
            $evidence->password_last_used,
            $evidence->password_last_ip,
            $evidence->tool_call,
            null
        );
    }

    public function for_oauth(Connection_Evidence $evidence, bool $mcp_on): Check_Result
    {
        $signed_in = array_values(array_filter(
            $evidence->grants,
            static fn (Oauth_Grant $grant): bool => $grant->token_issued_at > 0
        ));
        usort($signed_in, static fn (Oauth_Grant $a, Oauth_Grant $b): int => $b->token_issued_at <=> $a->token_issued_at);

        if ([] !== $signed_in) {
            $sessions = $this->newest_first($evidence->sessions);

            return new Check_Result(Verdict::OauthSignedIn, $mcp_on, $sessions[0] ?? null, null, null, $evidence->tool_call, $signed_in[0]);
        }

        foreach ($evidence->grants as $grant) {
            if ($grant->is_pending()) {
                return new Check_Result(Verdict::OauthWaiting, $mcp_on, null, null, null, null, $grant);
            }
        }

        return new Check_Result(Verdict::NotSeen, $mcp_on, null, null, null, null, null);
    }

    /**
     * @param list<Session_Evidence> $sessions
     * @return list<Session_Evidence>
     */
    private function newest_first(array $sessions): array
    {
        usort($sessions, static fn (Session_Evidence $a, Session_Evidence $b): int => $b->created_at <=> $a->created_at);

        return $sessions;
    }
}
