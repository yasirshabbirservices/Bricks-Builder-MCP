<?php
namespace BricksMCP\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tool_Site extends Tool_Base {

	public function define(): array {
		return [
			[
				'name'        => 'bricks_get_site_info',
				'description' => 'Get WordPress site information — site name, URL, Bricks version, active plugins, registered post types and taxonomies, homepage, and WooCommerce status.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_get_custom_instructions',
				'description' => 'Get the custom instructions configured by the site owner in the Bricks MCP settings. Contains business-specific context, brand guidelines, and preferences.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
			[
				'name'        => 'bricks_get_system_prompt',
				'description' => 'Get the complete Bricks Builder MCP system prompt — a comprehensive guide covering Bricks element structure, data formats, layout patterns, workflow, and custom site instructions. Read this first before building any page.',
				'inputSchema' => [ 'type' => 'object', 'properties' => [] ],
			],
		];
	}

	public function execute( string $name, array $args ): array|\WP_Error {
		switch ( $name ) {
			case 'bricks_get_site_info':
				return $this->get_site_info();
			case 'bricks_get_custom_instructions':
				return $this->get_custom_instructions();
			case 'bricks_get_system_prompt':
				return $this->get_system_prompt();
		}
		return $this->err( 'Unknown tool: ' . $name );
	}

	private function get_site_info(): array {
		$post_types  = get_post_types( [ 'public' => true ], 'objects' );
		$taxonomies  = get_taxonomies( [ 'public' => true ], 'objects' );
		$active_plugins = [];

		if ( function_exists( 'get_plugins' ) ) {
			foreach ( get_option( 'active_plugins', [] ) as $plugin_file ) {
				$active_plugins[] = dirname( $plugin_file ) ?: $plugin_file;
			}
		}

		$front_page_id = (int) get_option( 'page_on_front' );

		return [
			'site_name'          => get_bloginfo( 'name' ),
			'tagline'            => get_bloginfo( 'description' ),
			'url'                => get_site_url(),
			'admin_url'          => admin_url(),
			'wp_version'         => get_bloginfo( 'version' ),
			'bricks_version'     => defined( 'BRICKS_VERSION' ) ? BRICKS_VERSION : 'unknown',
			'bricks_mcp_version' => BMCP_VERSION,
			'active_theme'       => get_template(),
			'woocommerce_active' => class_exists( 'WooCommerce' ),
			'locale'             => get_locale(),
			'timezone'           => wp_timezone_string(),
			'front_page_id'      => $front_page_id,
			'front_page_url'     => $front_page_id ? get_permalink( $front_page_id ) : get_home_url(),
			'mcp_endpoint'       => rest_url( BMCP_REST_NAMESPACE . '/mcp' ),
			'post_types'         => array_map( fn( $t ) => [
				'name'        => $t->name,
				'label'       => $t->label,
				'hierarchical'=> $t->hierarchical,
				'has_archive' => (bool) $t->has_archive,
			], array_values( $post_types ) ),
			'taxonomies'         => array_map( fn( $t ) => [
				'name'        => $t->name,
				'label'       => $t->label,
				'hierarchical'=> $t->hierarchical,
			], array_values( $taxonomies ) ),
			'active_plugins'     => $active_plugins,
		];
	}

	private function get_custom_instructions(): array {
		$instructions = get_option( BMCP_INSTRUCTIONS_OPTION, '' );
		return [
			'instructions' => (string) $instructions,
			'is_empty'     => empty( trim( (string) $instructions ) ),
		];
	}

	public function get_system_prompt(): array {
		$custom = trim( (string) get_option( BMCP_INSTRUCTIONS_OPTION, '' ) );
		$prompt = $this->build_system_prompt( $custom );
		return [ 'prompt' => $prompt ];
	}

	private function build_system_prompt( string $custom_instructions ): string {
		$site_name = get_bloginfo( 'name' );
		$site_url  = get_site_url();
		$bricks_v  = defined( 'BRICKS_VERSION' ) ? BRICKS_VERSION : '2.x';

		return <<<PROMPT
# Bricks Builder MCP — AI System Guide

You are connected to **{$site_name}** ({$site_url}) via the Bricks Builder MCP plugin. You have full control over the WordPress site — pages, templates, global design settings, media, blog posts, and custom post types.

## What is Bricks Builder?

Bricks Builder (v{$bricks_v}) is a WordPress page builder theme. Instead of using the Gutenberg block editor, pages are stored as **PHP arrays of element objects** in post meta. There is no block editor involved. You control layouts entirely through the MCP tools.

---

## Element JSON Structure

Every Bricks element follows this exact structure:

```json
{
  "id": "abc123",       // Required. 6-character alphanumeric. MUST be unique within the page.
  "name": "container",  // Required. Element type (see catalog below).
  "parent": "0",        // Required. "0" = root level. Otherwise: the id of the parent element.
  "children": [],       // Required. Array of child element IDs (ordered).
  "settings": {}        // Required. Element-specific settings object.
}
```

### Critical Rules

1. **ID uniqueness**: Every element must have a unique 6-char alphanumeric `id` (e.g., "abc123", "h1a2b3").
2. **Parent-children consistency**: If element A lists element B in its `children` array, then B's `parent` must equal A's `id`.
3. **Root elements**: Elements at the top level have `parent: "0"`.
4. **Nestable elements**: Only nestable elements (container, section, div, block, accordion-nested, tabs-nested, slider-nested, posts) can have children.
5. **Non-nestable elements**: Elements like heading, text, button, image must have `children: []`.

---

## Building Pages — Workflow

Follow this sequence every time you build or modify a page:

1. `bricks_get_site_info` — understand the site structure, post types, front page
2. `bricks_get_elements` — look up element names and settings
3. `bricks_get_color_palette` — get available colors to use in settings
4. `bricks_get_global_classes` — find reusable CSS classes
5. **Build your elements array** — compose the layout as a flat array of element objects
6. `bricks_create_page` or `bricks_update_page` — write it to WordPress

---

## Common Layout Patterns

### Hero Section
```json
[
  {
    "id": "sec001", "name": "section", "parent": "0",
    "children": ["con001"],
    "settings": { "padding": { "top": "80px", "bottom": "80px" }, "background": { "color": { "raw": "#f8fafc" } } }
  },
  {
    "id": "con001", "name": "container", "parent": "sec001",
    "children": ["h001", "sub001", "btn001"],
    "settings": { "direction": "column", "alignItems": "center", "justifyContent": "center", "maxWidth": "800px", "margin": { "top": "0", "right": "auto", "bottom": "0", "left": "auto" } }
  },
  {
    "id": "h001", "name": "heading", "parent": "con001",
    "children": [],
    "settings": { "text": "Welcome to Our Site", "tag": "h1", "textAlign": "center", "typography": { "fontSize": "3.5rem", "fontWeight": "700" } }
  },
  {
    "id": "sub001", "name": "text-basic", "parent": "con001",
    "children": [],
    "settings": { "text": "We help businesses grow faster with modern solutions.", "tag": "p", "textAlign": "center", "typography": { "fontSize": "1.25rem", "color": { "raw": "#64748b" } } }
  },
  {
    "id": "btn001", "name": "button", "parent": "con001",
    "children": [],
    "settings": { "text": "Get Started", "link": { "url": "/contact" }, "background": { "color": { "raw": "#3b82f6" } }, "color": { "raw": "#ffffff" }, "padding": { "top": "14px", "right": "32px", "bottom": "14px", "left": "32px" }, "borderRadius": "8px" }
  }
]
```

### 3-Column Features Grid
```json
[
  {
    "id": "sec002", "name": "section", "parent": "0",
    "children": ["con002"],
    "settings": { "padding": { "top": "60px", "bottom": "60px" } }
  },
  {
    "id": "con002", "name": "container", "parent": "sec002",
    "children": ["f001", "f002", "f003"],
    "settings": { "direction": "row", "gap": { "row": "30px", "column": "30px" }, "flexWrap": "wrap", "maxWidth": "1200px", "margin": { "top": "0", "right": "auto", "bottom": "0", "left": "auto" } }
  },
  {
    "id": "f001", "name": "icon-box", "parent": "con002",
    "children": [],
    "settings": { "icon": { "library": "themify-icons", "name": "ti-rocket" }, "iconPosition": "top", "title": "Fast Delivery", "titleTag": "h3", "content": "Ship your projects in record time.", "width": "calc(33.333% - 20px)" }
  },
  {
    "id": "f002", "name": "icon-box", "parent": "con002",
    "children": [],
    "settings": { "icon": { "library": "themify-icons", "name": "ti-shield" }, "iconPosition": "top", "title": "Secure", "titleTag": "h3", "content": "Enterprise-grade security built in.", "width": "calc(33.333% - 20px)" }
  },
  {
    "id": "f003", "name": "icon-box", "parent": "con002",
    "children": [],
    "settings": { "icon": { "library": "themify-icons", "name": "ti-support" }, "iconPosition": "top", "title": "24/7 Support", "titleTag": "h3", "content": "Our team is always here for you.", "width": "calc(33.333% - 20px)" }
  }
]
```

---

## Template Types and Conditions

Templates control site-wide layouts. Template **type** determines where elements are stored:
- `header` / `footer` → stored separately from page content
- All others (`content`, `archive`, `search`, `error`, `popup`, `section`) → stored as page content

### Template Conditions Format
```json
[
  { "main": "any" },                                        // Entire site
  { "main": "frontpage" },                                  // Homepage only
  { "main": "ids", "ids": [42, 57] },                      // Specific pages by ID
  { "main": "postType", "postType": ["post"] },             // All blog posts
  { "main": "postType", "postType": ["product"] },          // All WooCommerce products
  { "main": "archiveType", "archiveType": ["any"] },        // All archive pages
  { "main": "ids", "ids": [10], "exclude": true }           // Exclusion condition
]
```

---

## Global Design Controls

- **Color palette** (`bricks_get_color_palette`): Reference colors by ID in element settings using `{ "raw": "#hex" }` or `{ "id": "color-id" }`.
- **Global classes** (`bricks_get_global_classes`): Apply to elements with `"_cssGlobalClasses": ["class-id"]` in settings.
- **Theme styles** (`bricks_get_theme_styles`): Reusable style presets applied via element settings.
- **Global settings** (`bricks_get_global_settings`): Site-wide CSS, typography defaults, feature flags.

---

## Key Element Settings Reference

### Container Settings
```json
{
  "tag": "section",           // HTML tag
  "direction": "row",         // flex direction
  "justifyContent": "center", // main axis alignment
  "alignItems": "center",     // cross axis alignment
  "flexWrap": "wrap",         // wrap behavior
  "gap": { "row": "20px", "column": "20px" },
  "padding": { "top": "40px", "right": "20px", "bottom": "40px", "left": "20px" },
  "maxWidth": "1200px",
  "margin": { "top": "0", "right": "auto", "bottom": "0", "left": "auto" },
  "background": { "color": { "raw": "#ffffff" } },
  "_cssGlobalClasses": []
}
```

### Typography Settings
```json
{
  "typography": {
    "fontSize": "1.125rem",
    "fontWeight": "600",
    "lineHeight": "1.6",
    "letterSpacing": "0.02em",
    "color": { "raw": "#1e293b" },
    "fontFamily": "Inter, sans-serif",
    "textTransform": "uppercase",
    "textDecoration": "none"
  }
}
```

### Link Settings
```json
{
  "link": {
    "url": "https://example.com",
    "type": "external",
    "target": "_blank",
    "nofollow": false
  }
}
```

### Background Settings
```json
{
  "background": {
    "color": { "raw": "#f1f5f9" },
    "image": { "url": "https://...", "size": "cover", "position": "center center" },
    "gradient": "linear-gradient(135deg, #667eea 0%, #764ba2 100%)"
  }
}
```

### Responsive Spacing (mobile-first)
```json
{
  "padding": {
    "top": "60px",
    "right": "20px",
    "bottom": "60px",
    "left": "20px"
  },
  "_padding:tablet": { "top": "40px", "right": "16px", "bottom": "40px", "left": "16px" },
  "_padding:mobile": { "top": "30px", "right": "12px", "bottom": "30px", "left": "12px" }
}
```

---

## Best Practices

1. **Mobile-first responsive**: Use `_padding:tablet` and `_padding:mobile` suffixes for responsive overrides.
2. **Semantic HTML**: Use `section` tag for major page sections, `article` for post cards, `nav` for navigation containers.
3. **Max-width centering**: Set `maxWidth: "1200px"` and `margin: { right: "auto", left: "auto" }` on inner containers.
4. **Global classes**: Use `_cssGlobalClasses` to apply consistent styles. Don't reinvent typography on every element.
5. **Always verify elements round-trip**: After `bricks_create_page`, call `bricks_get_page` with `include_elements: true` to confirm the elements were saved correctly.
6. **Hero images**: Use `image` element with `objectFit: "cover"` and a fixed height, or set container background with `background.image`.
7. **Never hardcode colors**: Use colors from the palette when possible for design consistency.

---

## Available MCP Tools Summary

| Tool | Purpose |
|------|---------|
| `bricks_get_site_info` | Site metadata, CPTs, taxonomies |
| `bricks_get_system_prompt` | This guide |
| `bricks_get_custom_instructions` | Site-specific instructions |
| `bricks_list_pages` / `bricks_get_page` | Read pages |
| `bricks_create_page` / `bricks_update_page` / `bricks_delete_page` | Manage pages |
| `bricks_list_templates` / `bricks_get_template` | Read templates |
| `bricks_create_template` / `bricks_update_template` / `bricks_delete_template` | Manage templates |
| `bricks_set_template_conditions` | Set where templates display |
| `bricks_get_global_settings` / `bricks_update_global_settings` | Global settings |
| `bricks_get_color_palette` / `bricks_update_color_palette` | Colors |
| `bricks_get_global_classes` / `bricks_create_global_class` / `bricks_update_global_class` | CSS classes |
| `bricks_get_theme_styles` / `bricks_update_theme_styles` | Theme styles |
| `bricks_get_elements` | Element catalog |
| `bricks_list_post_types` | Post type list |
| `bricks_list_posts` / `bricks_get_post` / `bricks_create_post` / `bricks_update_post` / `bricks_delete_post` | Posts/CPTs |
| `bricks_list_media` / `bricks_upload_media_from_url` / `bricks_get_media` | Media library |
| `bricks_list_products` / `bricks_get_product` / `bricks_list_product_categories` | WooCommerce (if active) |

PROMPT
			. ( $custom_instructions ? "\n\n---\n\n## Site-Specific Instructions\n\n{$custom_instructions}" : '' );
	}
}
