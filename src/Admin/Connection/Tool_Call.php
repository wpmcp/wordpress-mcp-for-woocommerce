<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One tool call from the request log: which tool, when, and whether it
 * finished without an error.
 */
final class Tool_Call
{
    public function __construct(
        public readonly string $tool,
        public readonly int $at,
        public readonly bool $ok,
    ) {
    }
}
