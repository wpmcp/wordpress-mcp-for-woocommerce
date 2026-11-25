<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Value;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * How an ability is exposed in tools/list: by default, in the compact core
 * set, or as a meta tool.
 */
enum Exposure: string
{
    case Default = 'default';
    case Compact_Core = 'compact_core';
    case Meta = 'meta';
}
