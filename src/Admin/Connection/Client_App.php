<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The apps the Connection screen walks a user through, by the value the
 * screen's links and forms carry (?client=claude-code).
 */
enum Client_App: string
{
    case ClaudeDesktop = 'claude-desktop';
    case ClaudeCode = 'claude-code';
    case Chatgpt = 'chatgpt';
    case Cursor = 'cursor';
    case Antigravity = 'antigravity';
    case Other = 'other';

    /** The app a request value names, or null for anything else. */
    public static function from_request(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * ChatGPT signs in with OAuth; so does Claude Desktop on its custom
     * connector route. The rest use an application password.
     */
    public function sign_in(bool $connector): Sign_In
    {
        if (self::Chatgpt === $this || (self::ClaudeDesktop === $this && $connector)) {
            return Sign_In::Oauth;
        }

        return Sign_In::Password;
    }

    /**
     * Whether an OAuth client belongs to this app: one of its redirect hosts
     * is the app's domain or a subdomain of it, or its registered name names
     * the app. Only the OAuth apps match anything.
     *
     * @param string[] $redirect_hosts
     */
    public function matches_oauth_client(string $client_name, array $redirect_hosts): bool
    {
        [$domains, $names] = match ($this) {
            self::Chatgpt => [['chatgpt.com', 'openai.com'], ['chatgpt', 'openai']],
            self::ClaudeDesktop => [['claude.ai', 'claude.com', 'anthropic.com'], ['claude']],
            default => [[], []],
        };

        foreach ($redirect_hosts as $host) {
            $host = strtolower(trim($host));
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                    return true;
                }
            }
        }

        $name = strtolower($client_name);
        foreach ($names as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }
}
