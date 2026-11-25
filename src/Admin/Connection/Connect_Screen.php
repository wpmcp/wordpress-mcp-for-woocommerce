<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Everything the Connect tab draws from: the step states, the chosen app and
 * route, the endpoint, the nonce and request id of the create form, the
 * password just created (if any) or the name of one made earlier, why step 2
 * is blocked (the messages for it), the bundle download address, the password a check is about, and a
 * check result posted without JavaScript.
 */
final class Connect_Screen
{
    /**
     * @param list<array{id: string, label: string, ok: bool, detail: string}> $self_test
     */
    public function __construct(
        public readonly Flow_Model $model,
        public readonly ?Client_App $app,
        public readonly bool $connector,
        public readonly bool $generic,
        public readonly bool $public_address,
        public readonly string $endpoint,
        public readonly string $nonce,
        public readonly string $default_name,
        public readonly string $request_id,
        public readonly ?Provisioned $provisioned,
        public readonly string $created_name,
        public readonly string $passwords_message,
        public readonly string $oauth_locked_message,
        public readonly string $public_warning,
        public readonly string $bundle_url,
        public readonly string $check_uuid,
        public readonly ?Check_Result $check,
        public readonly array $self_test,
    ) {
    }
}
