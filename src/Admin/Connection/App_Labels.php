<?php

declare(strict_types=1);

namespace WPMCP\Admin\Connection;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The names and one-line hints of the apps on the Connection screen. App
 * names are brand names and stay as they are; "Other MCP client" and the
 * hints are translated.
 */
final class App_Labels
{
    public function label(Client_App $app): string
    {
        return match ($app) {
            Client_App::ClaudeDesktop => 'Claude Desktop',
            Client_App::ClaudeCode => 'Claude Code',
            Client_App::Chatgpt => 'ChatGPT',
            Client_App::Cursor => 'Cursor',
            Client_App::Antigravity => 'Antigravity',
            Client_App::Other => __('Other MCP client', 'safe-mcp-for-woocommerce'),
        };
    }

    /** The app as the reader's text names it: "your app" for Other, "Claude" on the connector route. */
    public function in_text(Client_App $app, bool $connector): string
    {
        if (Client_App::Other === $app) {
            return __('your app', 'safe-mcp-for-woocommerce');
        }

        return Client_App::ClaudeDesktop === $app && $connector ? 'Claude' : $this->label($app);
    }

    public function hint(Client_App $app): string
    {
        return match ($app) {
            Client_App::ClaudeDesktop => __('Mac and Windows app. Install a one-click bundle.', 'safe-mcp-for-woocommerce'),
            Client_App::ClaudeCode => __('Works in your terminal. Paste one command.', 'safe-mcp-for-woocommerce'),
            Client_App::Chatgpt => __('Web and desktop app. Signs in through your browser.', 'safe-mcp-for-woocommerce'),
            Client_App::Cursor => __('Code editor. Paste one JSON block.', 'safe-mcp-for-woocommerce'),
            Client_App::Antigravity => __('Google\'s editor, with Gemini. Paste one JSON block.', 'safe-mcp-for-woocommerce'),
            Client_App::Other => __('VS Code, or any app that takes a URL and a header.', 'safe-mcp-for-woocommerce'),
        };
    }
}
