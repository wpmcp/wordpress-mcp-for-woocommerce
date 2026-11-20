<?php

namespace WPMCP\Memory;

use WPMCP\MCP\Opt_In_Required;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The enforcement switch of the agent project-memory feature (issue #131).
 * The tools that author memory entries are not part of this build; the
 * admin screen that publishes them and the server-side enforcement are:
 *
 *  - wpmcp_memory_enforce (DEFAULT TRUE) gates ENFORCEMENT of the
 *    admin-published block rules inside Registrar::is_permitted(). It is on
 *    by default and is intentionally NOT tied to the tools switch or to
 *    the connecting identity: a guardrail an administrator published
 *    must keep denying writes even after the memory tools are switched
 *    back off. A guardrail that quietly stops
 *    guarding is worse than no guardrail at all, so the only way to stop
 *    enforcement is an explicit filter or unpublishing the entry.
 */
class Memory_Config
{
    /** Whether published block rules are enforced. Default ON. */
    public static function enforcement_enabled(): bool
    {
        return (bool) apply_filters('wpmcp_memory_enforce', true);
    }

    /** The WP_Error every memory tool returns while the opt-in gate is closed. */
    public static function disabled_error(): \WP_Error
    {
        return Opt_In_Required::error(
            'Agent project memory is disabled.',
            'wpmcp_enable_memory',
            '',
            '',
            ['status' => 403]
        );
    }
}
