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
| **Search & Replace** | Find and replace colors, classes, or text across all pages and templates |
| **SEO** | Read/write meta title, description, OG data (Yoast or Rank Math) |
| **Cache** | Clear site cache after writes (WP Rocket, LiteSpeed, W3TC, etc.) |
| **WooCommerce** | Browse products and categories (read-only) |
| **AI Memory** | Persistent site knowledge injected into every AI session |
| **History** | Auto-snapshot before every write — restore any previous state |
| **Business Profile** | Brand colors, typography, design style, contact, social, services — full AI project context |
| **Design System** | Apply or inspect BricksTemplate design system presets |
| **Template Library** | Search and retrieve built-in wireframe templates by category |
| **Session Context** | Single startup call: site info, palette, classes, fonts, framework, business profile, and memories |
| **Validation** | Validate element arrays before writing — catches corrupt payloads early |
| **Agent Skills** | On-demand best-practice guides: accessibility, SEO, CSS, performance, typography, layout, dynamic data |
| **Design Audit** | Scan all pages for design inconsistencies — hardcoded colors, mismatched fonts, spacing drift |
| **Element Search** | Find elements by type, class, setting key/value, or text content across all pages |
| **Preview Mode** | Staged editing: AI writes to draft copies, you review, then commit or discard in one step |

**82 MCP tools** across all groups.

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

## First Steps

Once connected, start with a single call that loads everything in one shot:

```
bricks_get_session_context
```

This returns site info, color palette, global classes, CSS variables, fonts, active design framework, business profile (including brand colors, typography, design style tokens), and high-priority memories — all in one response.

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
| **Business Profile** | Brand identity, brand colors (with color pickers), typography, design style, contact, social media links, assets, and services — all returned to the AI in every session |
| **Capabilities** | Per-tool enable/disable toggles (granular control over all tools) |
| **Memory** | View, add, and edit persistent AI memories |
| **History** | Browse and restore auto-snapshots |
| **API Keys** | Manage scoped secondary API keys (read / write / delete) per AI client |
| **Activity** | Last 20 MCP tool calls |
| **Advanced** | Uninstall data cleanup, activity logging, debug mode |

### Business Profile — What the AI receives

The `bricks_get_business_profile` tool (also included in every `bricks_get_session_context`) returns structured data the AI uses automatically:

- **Brand** — name, tagline, type, audience, tone, about text
- **Colors** — 10 hex tokens (primary, secondary, accent, text, heading, background, surface, border, success, error)
- **Typography** — heading font, body font, base font size
- **Design Style** — style preset, border radius, spacing scale, button style
- **Contact** — email, phone, address, plus custom extra entries (repeater)
- **Social** — any platform/URL pairs (dynamic repeater, not fixed fields)
- **Navigation** — nav items, CTA text/URL, copyright
- **Assets** — logo URL, dark logo URL
- **Services** — one per line

---

## Security

- **Bearer token auth** with constant-time comparison (`hash_equals`) — safe against timing attacks
- **Multi-key auth** — create scoped secondary API keys (read / write / delete); revoke individually without disrupting other clients
- **Optional HMAC-SHA256 request signing** — timestamp-bound signatures prevent replay attacks (Advanced → Security toggle)
- **Rate limiting** — 120 requests per minute per authenticated user
- **Capability checks** on every write operation (`edit_pages`, `delete_pages`, `edit_posts`, etc.)
- **Input sanitization** on all admin form fields (`sanitize_text_field`, `sanitize_hex_color`, `sanitize_url`, `sanitize_email`, allowlist checks for select fields)
- **Prepared statements** for all database queries (`$wpdb->prepare`)
- **Output escaping** on all admin-rendered values (`esc_html`, `esc_attr`, `esc_textarea`)
- **AJAX nonce verification** on all admin AJAX handlers

---

## Auto-Snapshots & History

Every write operation automatically saves a snapshot of the affected content area before modifying it. If the AI makes a mistake, you can restore any previous state from the **History** tab — or the AI can do it itself via `bricks_snapshot_restore`. Restoring is also undoable (the current state is snapshotted before the restore).

---

## How Auto-Updates Work

This repo uses a GitHub Actions workflow (`.github/workflows/release.yml`) that runs on every push to `main`. It reads the version from the plugin header, creates a properly-structured `bricks-builder-mcp.zip`, and publishes (or updates) a GitHub Release.

The plugin checks for new releases every 15 minutes and shows the standard WordPress "Update available" notice when a newer version is found.

**To release a new version:** bump `BMCP_VERSION` in `bricks-builder-mcp.php`, then commit and push to `main`. The plugin URI now points to the GitHub repository — the WordPress update checker links directly to the release page.

---

## Agent Skills

The plugin ships 8 on-demand best-practice guides for AI agents. When building a page, the AI checks the `available_skills` index in `bricks_get_session_context` and loads the relevant guide with `bricks_get_skill(slug)` before starting work — not all at once, only what the current task requires.

| Skill | When the AI loads it |
|---|---|
| `bricks-elements` | Any element array creation or editing |
| `css-best-practices` | Any styling work — classes, variables, inline settings |
| `accessibility` | Forms, navs, modals, images, interactive elements |
| `seo-html` | Any page with headings, metadata, or content structure |
| `performance` | Image-heavy sections, query loops, above-fold content |
| `bricks-dynamic-data` | ACF, JetEngine, or any query loop |
| `typography` | Text styling, font selection, readability |
| `layout-patterns` | New sections, heroes, grids, responsive layouts |

Skills are markdown files in `assets/skills/` — add your own and they appear automatically in `bricks_list_skills` with no code changes.

---

## Template Library

Drop Bricks Builder template JSON exports into `assets/templates/{category}/` and they become instantly searchable by the AI via `bricks_search_templates` and retrievable via `bricks_get_template_library`. Categories are discovered automatically from folder names — no code changes needed.

**Format:** standard Bricks Builder export (`{"content": [...], "globalClasses": [...]}`) — export directly from Bricks and save as `template-name.json` in the matching folder.

---

## License

MIT — see [LICENSE](LICENSE)

---

Developed by [Yasir Shabbir](https://yasirshabbir.com)
