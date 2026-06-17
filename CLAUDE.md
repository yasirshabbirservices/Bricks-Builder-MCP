# Bricks Builder MCP — Development Guide

## Before You Start

**IMPORTANT: Before working on any project that uses this plugin, you MUST:**

1. Create a `CLAUDE.md` file in the project root (if it doesn't already exist)
2. Copy the `.claude/skills/` folder from this repo into the project's `.claude/` directory
3. Check if `.claude/DESIGN.md` exists — if not, ask the user to provide a design system file or share brand details (see Onboarding below)

This ensures the AI has access to all Bricks Builder, SureCart, Business Profile, and Web Optimization skill references.

```bash
# From your project root:
cp -r /path/to/Bricks-Builder-MCP/.claude/skills/ .claude/skills/
```

## Onboarding (First Build Task Only)

When the user requests to **start building or designing** (not during connection setup or read-only operations), run these checks once per session:

### Design System File
Check if `.claude/DESIGN.md` exists in the project root. If missing, ask the user:
1. **Upload a design system file** — save it to `.claude/DESIGN.md`
2. **Share details manually** — brand colors, fonts, style preferences (use for the session, save key tokens to AI memory)

User can skip — proceed with the site's existing palette and globals from `bricks_get_session_context`.

### Business Profile
Call `bricks_get_business_profile`. If the profile is empty/mostly blank, ask:
1. **Provide business details** — name, tagline, colors, contact, services, social links → save via `bricks_import_business_profile`
2. **Skip** — use placeholder content

### Child Theme
Check if the active theme is a Bricks child theme. If the site is using the parent Bricks theme directly:
1. **Yes, set it up** — create and activate a Bricks child theme (style.css + functions.php)
2. **No, skip** — continue with parent theme

If a child theme is active, all custom CSS, functions.php changes, and template overrides MUST go in the child theme directory.

Do NOT trigger onboarding during connection setup, MCP config sharing, page listing, or read-only operations.

## Project Structure

```
bricks-builder-mcp/
├── bricks-builder-mcp.php          # Main plugin file, constants, autoloader
├── includes/
│   ├── class-admin.php             # WordPress admin UI (settings page)
│   ├── class-auth.php              # API key auth + scoped secondary keys
│   ├── class-history-manager.php   # Auto-snapshot before writes, restore
│   ├── class-memory-manager.php    # Persistent AI memory system
│   ├── class-rest-api.php          # MCP REST endpoint handler
│   ├── class-tools-registry.php    # Tool loader, group map, dispatch
│   ├── class-updater.php           # GitHub-based auto-updater
│   └── tools/
│       ├── class-tool-base.php     # Abstract base class for all tools
│       ├── class-tool-pages.php    # Page CRUD (create/read/update/delete)
│       ├── class-tool-templates.php # Template CRUD + conditions
│       ├── class-tool-settings.php  # Global settings, palette, classes, breakpoints
│       ├── class-tool-elements.php  # Element catalog reference
│       ├── class-tool-posts.php     # Generic post type CRUD
│       ├── class-tool-media.php     # Media library management
│       ├── class-tool-woocommerce.php # WooCommerce products (auto-disables)
│       ├── class-tool-surecart.php  # SureCart products, collections, references
│       ├── class-tool-menus.php     # Navigation menu management
│       ├── class-tool-components.php # Reusable component management
│       ├── class-tool-site.php      # Site info, system prompt, front page
│       ├── class-tool-memory.php    # Memory CRUD tools
│       ├── class-tool-history.php   # Snapshot list/get/restore/delete
│       ├── class-tool-cache.php     # Cache clearing
│       ├── class-tool-search.php    # Search & replace across elements
│       ├── class-tool-seo.php       # SEO meta + audit + heading structure
│       ├── class-tool-validator.php # Payload validation before writes
│       ├── class-tool-context.php   # Session context (one-call bootstrap)
│       ├── class-tool-template-library.php # Built-in template library
│       ├── class-tool-business-profile.php # Business profile management
│       ├── class-tool-design-system.php    # Design system import/export
│       ├── class-tool-skills.php    # Agent skill guides (assets/skills/*.md)
│       ├── class-tool-audit.php     # Design consistency audit
│       ├── class-tool-accessibility.php # WCAG 2.2 AA accessibility audit
│       ├── class-tool-performance.php   # Core Web Vitals audit
│       ├── class-tool-schema.php    # JSON-LD structured data management
│       └── class-tool-preview.php   # Staged preview mode
├── assets/
│   ├── skills/                     # Built-in skill guides (served via bricks_get_skill)
│   ├── design-systems/             # Design system presets (JSON exports)
│   └── templates/                  # Template library JSON files
├── .claude/
│   └── skills/                     # Claude Code skill references
│       ├── bricks-builder-dev/     # Bricks element API, hooks, seeding, DB map
│       ├── surecart/               # SureCart elements, dynamic tags, integration
│       ├── business-profile/       # Onboarding, brand fields, content substitution
│       └── web-optimization/       # Accessibility, SEO, AEO, GEO
└── CLAUDE.md                       # This file
```

## Architecture

### Tool System
- Every tool extends `Tool_Base` (abstract class with helpers)
- Tools are registered in `Tools_Registry::load_all()` 
- Each tool class defines tools via `define()` and handles execution via `execute()`
- Tools are grouped for permission control: pages, templates, settings, posts, media, woocommerce, site
- Auto-snapshot runs before every write operation (configured in `get_snapshot_info()`)

### Adding a New Tool
1. Create `includes/tools/class-tool-{name}.php` extending `Tool_Base`
2. Add `new Tools\Tool_{Name}()` to `Tools_Registry::load_all()`
3. Add tool names to `get_tool_group_map()` in the registry
4. If the tool writes data, add snapshot config to `get_snapshot_info()`
5. Run `php -l` syntax check on the new file

### Key Patterns
- **Self-disabling tools**: WooCommerce and SureCart tools check for plugin presence in constructor and return empty `define()` when inactive
- **Reference tools**: Some tools return static knowledge (element catalogs, dynamic tag lists) — these always register regardless of plugin state
- **Capability checks**: Use `$this->require_cap('edit_posts')` before any write operation
- **Never expose sensitive data**: No payment credentials, API keys, or customer financial information in any tool response

### DB Constants (Bricks)
- `BMCP_DB_PAGE_CONTENT` = `_bricks_page_content_2` (element tree post meta)
- `BMCP_DB_PAGE_HEADER` = `_bricks_page_header_2`
- `BMCP_DB_PAGE_FOOTER` = `_bricks_page_footer_2`
- `BMCP_DB_TEMPLATE_SLUG` = `bricks_template` (CPT slug)

### REST API
- Namespace: `bricks-mcp/v1`
- Auth: API key via `X-API-Key` header or `api_key` query param
- MCP endpoint: `POST /bricks-mcp/v1/mcp` (JSON-RPC 2.0)

## Skills System

### Built-in Skills (assets/skills/)
Markdown files with YAML frontmatter served via `bricks_get_skill` tool. The AI loads these on-demand before relevant tasks.

### Claude Code Skills (.claude/skills/)
Full skill references for Claude Code sessions. Include:
- **bricks-builder-dev**: Element API (base.php), hooks, seeding cookbook, DB storage map
- **surecart**: Bricks elements, dynamic data tags, template patterns
- **business-profile**: Onboarding flow, brand fields, content substitution, import/export
- **web-optimization**: WCAG 2.2 AA, technical SEO, AEO/GEO strategies

## Code Style
- WordPress PHP coding standards
- Namespace: `BricksMCP` (sub-namespace `BricksMCP\Tools`)
- PSR-4-ish autoloading via `spl_autoload_register` in main plugin file
- File naming: `class-{kebab-case}.php`
- No comments unless explaining a non-obvious constraint
- Type hints on all method signatures (PHP 8.0+: union types with `\WP_Error`)

## Testing
- `php -l <file>` for syntax validation on every new/modified PHP file
- No unit test framework currently — validate via syntax check + live site testing
- Deploy to live site: user manages zipping and uploading (never create zips or deployment artifacts)

## Version Bumping
Update version in TWO places:
1. Plugin header comment in `bricks-builder-mcp.php` (`Version: X.Y.Z`)
2. `BMCP_VERSION` constant in the same file

## Security Rules
- Sanitize all inputs, escape all outputs
- Use WordPress nonce verification for admin AJAX
- API key auth for REST endpoints with scoped secondary keys
- `wp_kses_post()` for any HTML content storage
- Never store or transmit payment credentials, API keys, or customer financial data
- Capability checks before every write operation
