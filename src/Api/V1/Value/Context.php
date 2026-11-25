<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Value;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The kind of request being served, detected once per request by
 * WPMCP\Kernel\Request_Context. Mcp is a REST request on the plugin's own
 * MCP route.
 */
enum Context: string
{
    case Cli = 'cli';
    case Cron = 'cron';
    case Ajax = 'ajax';
    case Admin = 'admin';
    case Rest = 'rest';
    case Mcp = 'mcp';
    case Frontend = 'frontend';
}
