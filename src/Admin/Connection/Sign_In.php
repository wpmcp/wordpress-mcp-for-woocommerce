<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * How an app signs in to this site: with a WordPress application password,
 * or through the browser with OAuth (ChatGPT, the Claude custom connector).
 */
enum Sign_In: string
{
    case Password = 'password';
    case Oauth = 'oauth';
}
