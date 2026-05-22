# Bricks Builder MCP

A WordPress plugin that exposes a [Model Context Protocol (MCP)](https://modelcontextprotocol.io) HTTP server, letting Claude Code and any MCP-compatible AI client design and build your Bricks Builder website programmatically — no drag-and-drop required.

---

## Features

| Tool Group | What the AI can do |
|---|---|
| **Pages** | Create, read, update, delete, and duplicate pages + write Bricks element layouts |
| **Templates** | Header / footer / section templates with display conditions |
| **Global Design** | Color palette, global CSS classes, theme styles, CSS variables |
| **Posts & CPTs** | Manage any WordPress post type |
| **Media** | Browse, import, and delete media assets |
| **Nav Menus** | Create and manage WordPress navigation menus |
| **Components** | Manage Bricks reusable components |
| **Search & Replace** | Find and replace colors, classes, or text across all pages |
| **SEO** | Read/write meta title, description, OG data (Yoast or Rank Math) |
| **Cache** | Clear site cache after writes (WP Rocket, LiteSpeed, W3TC, etc.) |
| **WooCommerce** | Browse products and categories (read-only) |
| **AI Memory** | Persistent site knowledge injected into every AI session |
| **History** | Auto-snapshot before every write — restore any previous state |
| **Business Profile** | Brand, contact, services, and assets used as AI project context |
| **Design System** | Apply or inspect BricksTemplate design system presets |
| **Session Context** | Single startup call: site info, palette, classes, fonts, framework, business profile, template categories, and memories |
| **Validation** | Validate element arrays before writing — catches corrupt payloads early |

**71 MCP tools** across all groups.

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- [Bricks Builder](https://bricksbuilder.io) theme (any recent version)

---

## Installation

1. Download `bricks-builder-mcp.zip` from the [latest release](https://github.com/yasirshabbirservices/Bricks-Builder-MCP/releases/latest)
2. In WordPress admin: **Plugins → Add New → Upload Plugin**
3. Upload the zip and activate
4. Go to **Settings → Bricks MCP** (or **Bricks → MCP** if Bricks is active) to get your API key and endpoint URL

WordPress will notify you automatically when a new version is available.

---

## Connecting an AI Client

After activation, copy the endpoint URL and API key from **Settings → Bricks MCP → Connection**.

### Claude Code

Add to `~/.claude/settings.json`:

```json
{
  "mcpServers": {
    "bricks-builder": {
      "type": "http",
      "url": "https://yoursite.com/wp-json/bricks-mcp/v1/mcp",
      "headers": { "Authorization": "Bearer YOUR_API_KEY" }
    }
  }
}
```

### Claude Desktop

Add to `claude_desktop_config.json` and restart Claude Desktop.

- **Mac:** `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "bricks-builder": {
      "type": "http",
      "url": "https://yoursite.com/wp-json/bricks-mcp/v1/mcp",
      "headers": { "Authorization": "Bearer YOUR_API_KEY" }
    }
  }
}
```

### VS Code (Copilot Agent / Continue)

Add to `.vscode/mcp.json`:

```json
{
  "servers": {
    "bricks-builder": {
      "type": "http",
      "url": "https://yoursite.com/wp-json/bricks-mcp/v1/mcp",
      "headers": { "Authorization": "Bearer YOUR_API_KEY" }
    }
  }
}
```

### Gemini CLI

Add to `~/.gemini/settings.json`:

```json
{
  "mcpServers": {
    "bricks-builder": {
      "httpTransport": {
        "url": "https://yoursite.com/wp-json/bricks-mcp/v1/mcp",
        "headers": { "Authorization": "Bearer YOUR_API_KEY" }
      }
    }
  }
}
```

The **General** tab in the Connection panel also provides a universal plain-text block you can paste into any AI chat to get started.

---

## First Steps (ask your AI)

Once connected, start with a single call that loads everything in one shot:

```
bricks_get_session_context
```

This returns site info, color palette, global classes, CSS variables, fonts, active design framework, business profile, and high-priority memories — all in one response.

Then run:

1. `bricks_get_system_prompt` — full Bricks element format guide and site rules
2. `bricks_snapshot_list` — see available restore points before making changes

Before writing any elements, always validate first:

```
bricks_validate_payload  (pass your elements array)
```

---

## Settings

| Tab | Description |
|---|---|
| **Connection** | API key, endpoint URL, per-client config snippets |
| **Instructions** | Site-specific rules appended to the AI's system prompt |
| **Business Profile** | Brand, contact, services, and assets — AI project context available in every session |
| **Capabilities** | Per-tool enable/disable toggles (granular control over all tools) |
| **Memory** | View, add, and edit persistent AI memories |
| **History** | Browse and restore auto-snapshots |
| **Activity** | Last 20 MCP tool calls |
| **Advanced** | Uninstall data cleanup, activity logging, debug mode |

---

## How Auto-Updates Work

This repo uses a GitHub Actions workflow (`.github/workflows/release.yml`) that runs on every push to `main`. It reads the version from the plugin header, creates a properly-structured `bricks-builder-mcp.zip`, and publishes (or updates) a GitHub Release.

The plugin checks for new releases every 15 minutes and shows the standard WordPress "Update available" notice when a newer version is found.

**To release a new version:** bump `BMCP_VERSION` in `bricks-builder-mcp.php`, then commit and push to `main`.

---

## License

MIT — see [LICENSE](LICENSE)

---

Developed by [Yasir Shabbir](https://yasirshabbir.com)
