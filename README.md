# WooCommerce MCP Server (Safe MCP for WooCommerce)

A free WooCommerce MCP plugin that lets Claude, ChatGPT and Cursor manage products, orders and coupons on your store, with a snapshot before every write and one-click undo.

**[Download the plugin (safe-mcp-for-woocommerce.zip)](https://github.com/wpmcp/wordpress-mcp-for-woocommerce/releases/latest/download/safe-mcp-for-woocommerce.zip)**

1. In WordPress (with WooCommerce active), go to Plugins > Add New > Upload Plugin, choose the zip, click Install Now, then Activate.
2. Open the WP MCP menu and follow the connection wizard.
3. Connect Claude, ChatGPT or Cursor and ask it to update a product. Undo it from the History screen.

The plugin installs as **Safe MCP for WooCommerce** (slug `safe-mcp-for-woocommerce`). It is the WooCommerce build of [WP MCP](https://github.com/wpmcp/wordpress-mcp).

If this saves you time, please star the repo. It helps other store owners find it.

## What it does

Safe MCP for WooCommerce turns your store into an MCP (Model Context Protocol) server, built on the official WordPress Abilities API. Connect Claude Code, Claude Desktop, ChatGPT, Cursor, VS Code or any MCP client and let an AI agent manage products, look after orders, and edit your store's pages and content.

Every mutating operation takes a snapshot first. A wrong bulk price update, a bad product edit, a page the agent got wrong: each is one click from undone in the History screen.

### What agents can do

- Products: create, update, delete, list, bulk update, categories, stock and pricing, variations, low-stock reports, and bulk product imports (planned first, then applied)
- Orders: list, read, update status, add order notes, sales reports
- Coupons: create, update, delete, list, read and validate
- Tax rates: list, create, update, delete
- Store content: pages, posts, Gutenberg blocks, menus, media
- SEO metadata through Yoast SEO, Rank Math, or SEOPress
- Site context, diagnostics, exports, and scheduled maintenance
- Media library work, including stock image search and import
- Security scans, performance analysis, and plugin/theme installs and updates from WordPress.org

### Safety model

- Automatic snapshot before every write, with browsable history and one-click restore
- Session rollback: undo everything an agent did in one conversation
- Layered governance where permissions can only be narrowed, never widened
- Full audit log of every tool call
- No remote code execution tools of any kind
- No file browser or editor, no SQL console and no code snippets

### What it does not do

It has no tool to read, list, search, write, edit, delete or quarantine files, run SQL or write database rows directly, search and replace across the whole site, store or create code snippets, move a site to or from another server, restore the whole site from a backup archive, reinstall core files, or rewrite wp-config.php. An agent that asks for one of those tools by name is told it is not in this build.

Snapshots are per-operation undo, not full-site backups. Keep your backup plugin.

## Requirements

- WordPress 6.9 or later
- PHP 8.1 or later
- WooCommerce active

## Want Elementor, Bricks, Divi and unlimited undo history?

The full WP MCP plugin adds page-builder depth (Elementor, Bricks, Divi), more integrations, and custom widget and block building. With a Pro licence it keeps unlimited undo history (this plugin keeps your last 20 undo points). It replaces this plugin in place.

**[See WP MCP Pro plans](https://wpmcp-pro.com/pricing?coupon=WPMCPGIT&utm_source=github&utm_medium=readme&utm_content=woocommerce)** (code `WPMCPGIT` takes 10% off) | [Full plugin repo: wpmcp/wordpress-mcp](https://github.com/wpmcp/wordpress-mcp)

## Privacy

The plugin collects nothing about you and sends nothing to the author. Every external host it can reach, and when, is listed under "External services" in [readme.txt](readme.txt).

## About this repo

This repo holds the released plugin source, exactly as it ships in the release zip. Development happens in [wpmcp/wordpress-mcp](https://github.com/wpmcp/wordpress-mcp); please open issues there. The release zip is the recommended download: GitHub's "Download ZIP" button also produces an installable plugin, but its folder is named after the branch.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
