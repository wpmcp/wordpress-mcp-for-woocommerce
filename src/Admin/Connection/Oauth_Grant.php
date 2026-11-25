<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One OAuth client that belongs to the app being checked: its registered
 * name, its approval status (approved, pending, denied, seen, or empty for a
 * dynamically registered client), when it registered, when a user approved
 * it, when its newest token was issued (0 when it holds none), who approved
 * it and the access level they chose.
 */
final class Oauth_Grant
{
    public function __construct(
        public readonly string $client_name,
        public readonly string $status,
        public readonly int $registered_at,
        public readonly int $approved_at,
        public readonly int $token_issued_at,
        public readonly string $approved_by,
        public readonly string $access,
    ) {
    }

    public function is_pending(): bool
    {
        return 'pending' === $this->status;
    }
}
