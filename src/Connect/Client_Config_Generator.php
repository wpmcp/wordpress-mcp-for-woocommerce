<?php

namespace WPMCP\Connect;

use WPMCP\Auth\OAuth_Config;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pure generator for ready-to-paste MCP client configs (issue #76).
 *
 * Given a username and an Application Password, produces filled configs for
 * Claude Code, Claude Desktop, Cursor, Antigravity, VS Code, and a generic
 * MCP JSON block, all pointing at this site's MCP endpoint with HTTP Basic
 * auth.
 *
 * Credential handling: both inputs live only in this call's memory. The
 * generator never reads or writes options, files, or logs; snippets carry
 * the credential solely as the encoded Basic header value. The Connection
 * admin screen calls this exactly once, in the same request that created
 * the password, to render the one-time reveal.
 *
 * The endpoint is this site's REST base plus the route the WordPress 6.9+
 * Abilities/MCP integration mounts for a plugin named "wpmcp"
 * (wp-json/mcp/wpmcp-server), matching the README's documented connection
 * URL. If a future core version mounts a different route, this constant is
 * the one place to update (Get_Connection_Info reuses it).
 */
class Client_Config_Generator
{
    public const ROUTE = '/wp-json/mcp/wpmcp-server';

    /** The env var the Claude Desktop entry hands its Authorization value in. */
    public const DESKTOP_AUTH_ENV = 'WPMCP_AUTH_HEADER';

    public static function endpoint(): string
    {
        return home_url(self::ROUTE);
    }

    /** Reserved or local-only name suffixes no cloud client can reach (issue #1149). */
    private const LOCAL_SUFFIXES = [ '.localhost', '.test', '.local', '.example', '.invalid', '.internal', '.lan', '.home.arpa' ];

    /**
     * Why a cloud client (ChatGPT, the Claude custom connector) cannot reach
     * $url, or null when it looks like a public HTTPS address (issue #1149):
     * not https, localhost or a local-only name, a single-label host, or a
     * loopback, private, link-local or reserved IP. A name check only: a
     * public name on a firewalled host still passes.
     */
    public static function public_address_warning(?string $url = null): ?string
    {
        $url    = $url ?? self::endpoint();
        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        $host   = strtolower(trim((string) wp_parse_url($url, PHP_URL_HOST), '[]'));

        $reason = null;
        if ('https' !== $scheme) {
            $reason = __('it does not use HTTPS', 'safe-mcp-for-woocommerce');
        } elseif ('' === $host || 'localhost' === $host) {
            $reason = __('its host is localhost', 'safe-mcp-for-woocommerce');
        } elseif (false !== filter_var($host, FILTER_VALIDATE_IP)) {
            if (false === filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $reason = __('its host is a private or loopback IP address', 'safe-mcp-for-woocommerce');
            }
        } elseif (false === strpos($host, '.')) {
            $reason = __('its host is a local name with no domain', 'safe-mcp-for-woocommerce');
        } else {
            foreach (self::LOCAL_SUFFIXES as $suffix) {
                if (substr($host, -strlen($suffix)) === $suffix) {
                    /* translators: %s: a domain suffix such as .test */
                    $reason = sprintf(__('its host ends in %s, which only resolves locally', 'safe-mcp-for-woocommerce'), $suffix);
                    break;
                }
            }
        }

        if (null === $reason) {
            return null;
        }
        return sprintf(
            /* translators: 1: the MCP endpoint URL, 2: why it is not public */
            __('This client connects from the internet and needs a public HTTPS address for this site, and %1$s is not one (%2$s). It will fail to connect until the site is reachable at a public HTTPS address, for example through a tunnel or the live domain.', 'safe-mcp-for-woocommerce'),
            $url,
            $reason
        );
    }

    public static function auth_header(string $username, string $password): string
    {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- RFC 7617 HTTP Basic Authorization header encoding, not obfuscation.
        return 'Basic ' . base64_encode($username . ':' . $password);
    }

    /**
     * @return array<string, array{label: string, config_file: string, snippet: string, note: string, command?: string}>
     */
    public function configs(string $username, string $password): array
    {
        $endpoint = self::endpoint();
        $auth     = self::auth_header($username, $password);

        return [
            'claude-code'    => [
                'label'       => 'Claude Code',
                'config_file' => '.mcp.json',
                'snippet'     => $this->mcp_servers_snippet($endpoint, $auth),
                'command'     => sprintf(
                    'claude mcp add --transport http wpmcp %s --header "Authorization: %s"',
                    $endpoint,
                    $auth
                ),
                'note'        => __('Run the one-line command in your project, or paste the JSON block into .mcp.json.', 'safe-mcp-for-woocommerce'),
            ],
            'claude-desktop' => [
                'label'       => 'Claude Desktop',
                'config_file' => 'claude_desktop_config.json',
                'snippet'     => self::desktop_snippet($endpoint, $auth),
                'note'        => __('Easiest: download the wpmcp.mcpb bundle from the Connection screen and double-click it. Alternatively, merge this block into claude_desktop_config.json under "mcpServers", then fully quit and reopen Claude Desktop. Claude Desktop\'s config file only runs local servers, so this block starts the mcp-remote bridge to the endpoint, which needs Node.js installed.', 'safe-mcp-for-woocommerce'),
            ],
            'cursor'         => [
                'label'       => 'Cursor',
                'config_file' => '.cursor/mcp.json',
                'snippet'     => $this->mcp_servers_snippet($endpoint, $auth),
                'note'        => __('Paste into .cursor/mcp.json (project) or ~/.cursor/mcp.json (global).', 'safe-mcp-for-woocommerce'),
            ],
            'antigravity'    => [
                'label'       => 'Antigravity',
                'config_file' => 'mcp_config.json',
                'snippet'     => $this->mcp_servers_snippet($endpoint, $auth),
                'note'        => self::antigravity_note(),
            ],
            'vscode'         => [
                'label'       => 'VS Code',
                'config_file' => '.vscode/mcp.json',
                'snippet'     => (string) wp_json_encode(
                    [
                        'servers' => [
                            'wpmcp' => [
                                'type'    => 'http',
                                'url'     => $endpoint,
                                'headers' => ['Authorization' => $auth],
                            ],
                        ],
                    ],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                ),
                'note'        => __('VS Code reads a top-level "servers" key from .vscode/mcp.json.', 'safe-mcp-for-woocommerce'),
            ],
            'generic'        => [
                'label'       => __('Any MCP client (generic JSON)', 'safe-mcp-for-woocommerce'),
                'config_file' => 'mcp.json',
                'snippet'     => $this->mcp_servers_snippet($endpoint, $auth),
                'note'        => __('The common "mcpServers" shape: a streamable-HTTP server with an Authorization header.', 'safe-mcp-for-woocommerce'),
            ],
        ];
    }

    /** The line that switches OAuth on in wp-config.php. */
    public const OAUTH_CONSTANT_LINE = "define('WPMCP_OAUTH_ENABLED', true);";

    /** The name of the switch on the Connection screen (issue #842). */
    public const OAUTH_SWITCH_LABEL = 'Allow OAuth sign-in';

    /**
     * How to switch OAuth on, in words that read the same on the Connection
     * screen, in get-connection-info and on the authorization address while
     * OAuth is off (issues #720, #581 and #842). It names the real switch
     * and where it lives, never a key of the tool's result. On the
     * Connection screen itself the switch is right below the note, so there
     * it says "below" instead of naming the screen the reader is on (#751).
     * When the constant or the filter fixes the value, the switch cannot
     * help, so the text says what does.
     *
     * @param bool $on_connection_screen True when the text is shown on the Connection screen.
     */
    public static function oauth_switch_on(bool $on_connection_screen = false): string
    {
        $locked = OAuth_Config::locked_by();
        if ('constant' === $locked) {
            return sprintf(
                /* translators: %s: the PHP line, define('WPMCP_OAUTH_ENABLED', true); */
                __('OAuth is fixed by WPMCP_OAUTH_ENABLED in wp-config.php, so the switch on the Connection screen cannot change it: a site administrator changes that line to %s. Or use the desktop bundle or config file instead, which use an application password.', 'safe-mcp-for-woocommerce'),
                self::OAUTH_CONSTANT_LINE
            );
        }
        if ('filter' === $locked) {
            return __('OAuth is fixed by the wpmcp_oauth_enabled filter in a plugin or mu-plugin, so the switch on the Connection screen cannot change it: whoever added that filter changes it. Or use the desktop bundle or config file instead, which use an application password.', 'safe-mcp-for-woocommerce');
        }

        if ($on_connection_screen) {
            return sprintf(
                /* translators: 1: the switch label, Allow OAuth sign-in; 2: the PHP line, define('WPMCP_OAUTH_ENABLED', true); */
                __('An administrator who manages WPMCP switches it on with "%1$s" below. Or add %2$s to wp-config.php, above the line "That\'s all, stop editing!". Or use an app that signs in with an application password instead, such as Claude Desktop with the bundle.', 'safe-mcp-for-woocommerce'),
                self::OAUTH_SWITCH_LABEL,
                self::OAUTH_CONSTANT_LINE
            );
        }

        return sprintf(
            /* translators: 1: the switch label, Allow OAuth sign-in; 2: the PHP line, define('WPMCP_OAUTH_ENABLED', true); */
            __('An administrator who manages WPMCP switches it on in wp-admin under WPMCP, Connection, on the Settings tab, with "%1$s". Or add %2$s to wp-config.php, above the line "That\'s all, stop editing!". Or connect an app that signs in with an application password instead, such as Claude Desktop with the bundle from that screen.', 'safe-mcp-for-woocommerce'),
            self::OAUTH_SWITCH_LABEL,
            self::OAUTH_CONSTANT_LINE
        );
    }

    /**
     * The ways to switch OAuth on, listed under "To switch OAuth on".
     *
     * @return string[]
     */
    private static function enable_oauth_steps(): array
    {
        return [
            /* translators: %s: the switch label, Allow OAuth sign-in */
            sprintf(__('In wp-admin, open WPMCP, Connection, the Settings tab, and turn on "%s" (administrators who can manage WPMCP).', 'safe-mcp-for-woocommerce'), self::OAUTH_SWITCH_LABEL),
            /* translators: %s: the PHP line to add, define('WPMCP_OAUTH_ENABLED', true); */
            sprintf(__('Or add %s to wp-config.php, above the line "That\'s all, stop editing!". The constant decides instead of the switch.', 'safe-mcp-for-woocommerce'), self::OAUTH_CONSTANT_LINE),
            __('Or, from a plugin or mu-plugin: add_filter(\'wpmcp_oauth_enabled\', \'__return_true\');', 'safe-mcp-for-woocommerce'),
            __('Then reload the Connection screen: its Settings tab shows "OAuth is on", and Advanced lists the OAuth discovery addresses clients use.', 'safe-mcp-for-woocommerce'),
        ];
    }

    /**
     * The Claude Desktop / claude.ai custom connector (issue #581).
     *
     * The connector signs in with OAuth and cannot send an Authorization
     * header, and OAuth is off unless the site opts in, so on a default
     * install it fails with "Couldn't register with WPMCP's sign-in
     * service". OAuth stays off by default; this states the requirement,
     * the current state, and exactly how to switch it on, for the
     * get-connection-info tool and the Connection screen alike.
     *
     * @param bool $on_connection_screen True when rendered on the Connection screen (#751).
     *
     * @return array{label: string, url: string, public_address_warning: ?string, oauth_enabled: bool, note: string, enable_oauth: string[], steps: string[]}
     */
    public static function custom_connector(bool $on_connection_screen = false): array
    {
        $enabled = OAuth_Config::is_enabled();

        return [
            'label'         => __('Claude Desktop and claude.ai: custom connector', 'safe-mcp-for-woocommerce'),
            'url'           => self::endpoint(),
            // Issue #1149: null on a public HTTPS site.
            'public_address_warning' => self::public_address_warning(),
            'oauth_enabled' => $enabled,
            'note'          => $enabled
                ? __('OAuth is on for this site, so the custom connector can sign in: add it with the steps below and approve the sign-in when your browser opens.', 'safe-mcp-for-woocommerce')
                : __('OAuth is off for this site (the default). The custom connector requires OAuth: it cannot send an application password, so on this site it fails with "Couldn\'t register with WPMCP\'s sign-in service".', 'safe-mcp-for-woocommerce') . ' ' . self::oauth_switch_on($on_connection_screen),
            'enable_oauth'  => self::enable_oauth_steps(),
            'steps'         => [
                __('In Claude Desktop or on claude.ai, open Settings, then Connectors (under Customize in current versions), and choose "Add custom connector".', 'safe-mcp-for-woocommerce'),
                /* translators: %s: the MCP endpoint URL */
                sprintf(__('Enter %s as the URL. Leave the OAuth Client ID and secret empty.', 'safe-mcp-for-woocommerce'), self::endpoint()),
                __('Click Connect, sign in to WordPress in the browser window that opens, and approve the access.', 'safe-mcp-for-woocommerce'),
            ],
        ];
    }

    /**
     * ChatGPT (issue #842). ChatGPT adds a remote MCP server by URL in
     * Developer mode and signs in only with OAuth (dynamic client
     * registration and PKCE, redirecting to chatgpt.com), so like the
     * custom connector it needs OAuth on. Menu names follow the ChatGPT
     * web UI at the time of writing; the note says where to look if they
     * have moved.
     *
     * @param bool $on_connection_screen True when rendered on the Connection screen (#751).
     *
     * @return array{label: string, url: string, public_address_warning: ?string, oauth_enabled: bool, note: string, enable_oauth: string[], steps: string[]}
     */
    public static function chatgpt(bool $on_connection_screen = false): array
    {
        $enabled  = OAuth_Config::is_enabled();
        $endpoint = self::endpoint();

        return [
            'label'         => __('ChatGPT (web and desktop app): custom MCP connector', 'safe-mcp-for-woocommerce'),
            'url'           => $endpoint,
            // Issue #1149: null on a public HTTPS site.
            'public_address_warning' => self::public_address_warning($endpoint),
            'oauth_enabled' => $enabled,
            'note'          => ($enabled
                ? __('OAuth is on for this site, so ChatGPT can sign in: add it with the steps below and approve the sign-in when the WordPress window opens.', 'safe-mcp-for-woocommerce')
                : __('OAuth is off for this site (the default). ChatGPT signs in only with OAuth, so it cannot connect until OAuth is on.', 'safe-mcp-for-woocommerce') . ' ' . self::oauth_switch_on($on_connection_screen))
                . ' ' . self::chatgpt_developer_mode_note(),
            'enable_oauth'  => self::enable_oauth_steps(),
            'steps'         => [
                __('On chatgpt.com, open Settings, then Apps & Connectors, open Advanced settings and turn on Developer mode.', 'safe-mcp-for-woocommerce'),
                /* translators: %s: the MCP endpoint URL */
                sprintf(__('Back in Apps & Connectors, choose Create and name the connector, for example "WPMCP". Enter %s as the MCP server URL and choose OAuth as the authentication. Leave any client ID and secret empty: ChatGPT registers itself.', 'safe-mcp-for-woocommerce'), $endpoint),
                __('Confirm that you trust the connector and click Create, then sign in to WordPress in the window that opens and approve the access. In a chat, open the + menu, choose Developer mode and turn on WPMCP for that chat. The desktop app uses the connectors you added on the web.', 'safe-mcp-for-woocommerce'),
            ],
        ];
    }

    /**
     * ChatGPT's Developer mode requirement, said the same way on the
     * Connection screen and in get-connection-info.
     */
    public static function chatgpt_developer_mode_note(): string
    {
        return __('Custom MCP connectors need ChatGPT\'s Developer mode, on the plans that offer it; in a workspace, an admin may have to allow it. ChatGPT\'s menu names change from time to time: if a step does not match, look for Developer mode under Settings.', 'safe-mcp-for-woocommerce');
    }

    /**
     * Where Antigravity reads MCP servers from, in the order the Connection
     * screen walks it (checked on Antigravity 2.33.0): the generic
     * "mcpServers" block goes into its mcp_config.json.
     */
    public static function antigravity_note(): string
    {
        return __('In Antigravity, open Customizations, then Installed, scroll to MCP Servers and click Copy Config File Path. Paste this block into that file (mcp_config.json) and save, then quit and reopen Antigravity and start a new conversation so the WPMCP tools load.', 'safe-mcp-for-woocommerce');
    }

    /**
     * The claude_desktop_config.json entry for this site (issue #580).
     *
     * Claude Desktop's config file only runs local stdio servers: an entry
     * with "type", "url" and "headers" is skipped as not a valid MCP server
     * configuration. Remote servers are added in the app as a custom
     * connector, which signs in with OAuth and cannot send an Authorization
     * header. So the config-file route runs mcp-remote, the stdio-to-HTTP
     * bridge, through npx. The header value travels in env and the argument
     * names it without a space: mcp-remote expands ${VAR} in --header, and a
     * space inside an argument is split on Windows.
     *
     * @param string $auth The Authorization header value (or a placeholder).
     */
    public static function desktop_snippet(string $endpoint, string $auth): string
    {
        return (string) wp_json_encode(
            [
                'mcpServers' => [
                    'wpmcp' => [
                        'command' => 'npx',
                        'args'    => [ '-y', 'mcp-remote', $endpoint, '--header', 'Authorization:${' . self::DESKTOP_AUTH_ENV . '}' ],
                        'env'     => [ self::DESKTOP_AUTH_ENV => $auth ],
                    ],
                ],
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    private function mcp_servers_snippet(string $endpoint, string $auth): string
    {
        return (string) wp_json_encode(
            [
                'mcpServers' => [
                    'wpmcp' => [
                        'type'    => 'http',
                        'url'     => $endpoint,
                        'headers' => ['Authorization' => $auth],
                    ],
                ],
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}
