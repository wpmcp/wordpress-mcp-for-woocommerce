<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * What the Connection screen knows when it draws the four steps: the chosen
 * app and route, whether a password was just created and the check saw it,
 * the application password state (null when available, otherwise the reason
 * from App_Passwords_Status: https_required, disabled_by_plugin or
 * disabled), the OAuth state, and whether the endpoint is a public HTTPS
 * address.
 */
final class Flow_Input
{
    public function __construct(
        public readonly ?Client_App $app,
        public readonly bool $connector,
        public readonly bool $created,
        public readonly bool $verified,
        public readonly ?string $passwords_unavailable,
        public readonly bool $oauth_on,
        public readonly bool $oauth_locked,
        public readonly bool $public_address,
    ) {
    }
}
