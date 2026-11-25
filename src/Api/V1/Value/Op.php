<?php

declare(strict_types=1);

namespace WPMCP\Api\V1\Value;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The operation an ability performs. Its hints are the MCP annotation
 * defaults WPMCP\MCP\Ability derives from the same strings.
 */
enum Op: string
{
    case Read = 'read';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';

    /**
     * [read_only, destructive, idempotent].
     *
     * @return array{bool, bool, bool}
     */
    public function hints(): array
    {
        return [
            self::Read === $this,
            self::Delete === $this,
            self::Read === $this || self::Update === $this,
        ];
    }
}
