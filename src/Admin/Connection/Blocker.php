<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Why step 2 cannot go ahead: application passwords are off because the site
 * has no HTTPS, or a plugin or filter switched them off; a cloud app cannot
 * reach a site that is not a public HTTPS address; or OAuth is fixed off in
 * code (WPMCP_OAUTH_ENABLED or the wpmcp_oauth_enabled filter).
 */
enum Blocker: string
{
    case HttpsRequired = 'https_required';
    case PasswordsDisabled = 'passwords_disabled';
    case NotPublic = 'not_public';
    case OauthLocked = 'oauth_locked';
}
