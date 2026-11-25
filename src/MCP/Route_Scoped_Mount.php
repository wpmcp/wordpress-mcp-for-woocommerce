<?php

declare(strict_types=1);

namespace WPMCP\MCP;

use Closure;
use WPMCP\Api\V1\Value\Context;
use WPMCP\Kernel\Request_Context;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Route-scoped mounting of the MCP adapter (decision D8), behind
 * WPMCP_ROUTE_SCOPED_MCP, off unless that constant is defined and true.
 *
 * The adapter's init on rest_api_init fires mcp_adapter_init, whose
 * create_server() reads wp_get_abilities() and so registers every ability
 * on every REST request (block editor, Store API, any other plugin's
 * routes). With the flag on the adapter is instantiated only where it can
 * be used: a request for this plugin's own route, and WP-CLI for the stdio
 * transport. Anything else still gets the abilities lazily from the
 * Abilities API when it asks for them.
 *
 * Without the flag, or when the kernel is absent from a build, nothing is
 * read from the request and the adapter mounts as it always has.
 */
final class Route_Scoped_Mount
{
    public const FLAG = 'WPMCP_ROUTE_SCOPED_MCP';

    public function __construct(private readonly bool $enabled)
    {
    }

    public static function enabled_by_constant(): bool
    {
        return defined(self::FLAG) && true === (bool) constant(self::FLAG);
    }

    /**
     * Whether the adapter mounts on this request.
     *
     * @param Closure(): Request_Context $context Read only when the flag is on.
     */
    public function mounts(Closure $context): bool
    {
        if (! $this->enabled || ! class_exists(Request_Context::class)) {
            return true;
        }

        return self::serves($context());
    }

    /** Whether a route-scoped mount serves $context: the MCP route or WP-CLI. */
    public static function serves(Request_Context $context): bool
    {
        return in_array($context->context(), [Context::Mcp, Context::Cli], true);
    }
}
