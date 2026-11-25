<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * An application password the Connection screen just created: its name,
 * uuid, the user it signs in as, the plaintext (this request only, never
 * stored) and the configs Client_Config_Generator filled with it.
 */
final class Provisioned
{
    /**
     * @param array<string, array{label: string, config_file: string, snippet: string, note: string, command?: string}> $configs
     */
    public function __construct(
        public readonly string $name,
        public readonly string $uuid,
        public readonly string $user_login,
        public readonly string $password,
        public readonly string $auth_header,
        public readonly array $configs,
        public readonly int $created,
    ) {
    }
}
