# Bricks Builder MCP

A WordPress plugin that exposes a [Model Context Protocol (MCP)](https://modelcontextprotocol.io) HTTP server, letting Claude Code and any MCP-compatible AI client design and build your Bricks Builder website programmatically — no drag-and-drop required.

---

## Features

| Tool Group | What the AI can do |
|---|---|
| **Pages** | Create, read, update, delete pages + write Bricks element layouts |
| **Templates** | Header / footer / section templates with display conditions |
| **Global Design** | Color palette, global CSS classes, theme styles |
| **Posts & CPTs** | Manage any WordPress post type |
| **Media** | Browse and import media assets |
| **WooCommerce** | Browse products and categories (read-only) |
| **Nav Menus** | Create and manage WordPress navigation menus |
| **Components** | Manage Bricks reusable components |
| **AI Memory** | Persistent site knowledge injected into every AI session |
| **History** | Auto-snapshot before every write — restore any previous state |

**47 MCP tools** across all groups.

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
4. Go to **Settings → Bricks MCP** to get your API key and endpoint URL

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

### Cursor / Trae AI

Add to `~/.cursor/mcp.json` or `~/.trae/mcp.json`:

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

The **General** tab in the Connection panel also provides a universal plain-text block you can paste directly into any AI chat to get started.

---

## First Steps (ask your AI)

Once connected, have the AI run these tools in order before building anything:

1. `bricks_get_system_prompt` — loads the full Bricks element format guide
2. `bricks_get_site_info` — WordPress/Bricks versions, active plugins, theme
3. `bricks_memory_list` — loads all saved site knowledge
4. `bricks_get_color_palette` — brand colors and design tokens
5. `bricks_get_global_classes` — reusable CSS utility classes

---

## Settings

| Tab | Description |
|---|---|
| **Connection** | API key, endpoint URL, per-client config snippets |
| **Instructions** | Site-specific rules appended to the AI's system prompt |
| **Capabilities** | Per-tool enable/disable toggles (granular control over all 47 tools) |
| **Memory** | View, add, and edit persistent AI memories |
| **History** | Browse and restore auto-snapshots |
| **Activity** | Last 20 MCP tool calls |
| **Advanced** | Uninstall data cleanup, activity logging, debug mode |

---

## How Auto-Updates Work

This repo uses a GitHub Actions workflow (`.github/workflows/release.yml`) that runs on every push to `main`. It reads the version from the plugin header, creates a properly-structured `bricks-builder-mcp.zip`, and publishes (or updates) a GitHub Release.

The plugin checks for new releases every 12 hours and shows the standard WordPress "Update available" notice when a newer version is found.

**To release a new version:** bump `BMCP_VERSION` in `bricks-builder-mcp/bricks-builder-mcp.php`, then commit and push to `main`.

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE)

---

Developed by [Yasir Shabbir](https://yasirshabbir.com)
