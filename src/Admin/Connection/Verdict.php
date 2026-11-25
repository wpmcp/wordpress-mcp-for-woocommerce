<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * What "Check connection" concluded from the evidence WordPress recorded.
 */
enum Verdict: string
{
    case Connected = 'connected';
    case PasswordOnly = 'password_only';
    case NotSeen = 'not_seen';
    case OauthSignedIn = 'oauth_signed_in';
    case OauthWaiting = 'oauth_waiting';
}
