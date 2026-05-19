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
			[
				'name'        => 'bricks_set_front_page',
				'description' => 'Set the WordPress static front page (homepage). Pass a page ID to show that page as the site homepage, or pass 0 to show the latest posts.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'page_id' => [ 'type' => 'integer', 'description' => 'Page ID to use as homepage, or 0 for latest posts.' ],
					],
					'required'   => [ 'page_id' ],
				],
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
			case 'bricks_set_front_page':
				return $this->set_front_page( $this->int_arg( $args, 'page_id' ) );
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

	private function set_front_page( int $page_id ): array {
		if ( $page_id > 0 ) {
			$page = get_post( $page_id );
			if ( ! $page || $page->post_type !== 'page' ) {
				return $this->err( 'Page not found: ' . $page_id );
			}
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $page_id );
			return [
				'success'       => true,
				'front_page_id' => $page_id,
				'front_page_url'=> get_permalink( $page_id ),
				'message'       => 'Front page set to: ' . get_the_title( $page_id ),
			];
		} else {
			update_option( 'show_on_front', 'posts' );
			update_option( 'page_on_front', 0 );
			return [
				'success' => true,
				'message' => 'Front page reset to latest posts.',
			];
		}
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

You are connected to **{$site_name}** ({$site_url}) running Bricks Builder v{$bricks_v}.

---

## Element JSON Structure

Every Bricks element MUST have this exact structure:

```json
{
  "id": "abc123",      // 6-char alphanumeric, UNIQUE per page
  "name": "container", // element type string
  "parent": 0,         // 0 = root level; otherwise parent element's id string
  "children": [],      // ordered array of child element id strings
  "settings": {}       // element settings object (see below)
}
```

**Critical rules:**
- Every id must be unique across the entire elements array.
- If element A has `"children": ["b1c2d3"]`, then the element with id `"b1c2d3"` must have `"parent": "abc123"`.
- Root elements use `"parent": 0` (integer zero, not string).
- Non-nestable elements (heading, text-basic, button, image, icon, text-link, etc.) must have `"children": []`.

---

## CRITICAL: Container Flex Settings

The container element is Bricks' primary layout block. **You MUST set `_display: "flex"` explicitly** — without it, `_direction`, `_justifyContent`, `_alignItems`, and gap settings generate NO CSS output.

```json
{
  "name": "container",
  "settings": {
    "_display": "flex",                           // REQUIRED — activates all flex controls
    "_direction": "row",                          // "row" | "column" | "row-reverse" | "column-reverse"
    "_justifyContent": "space-between",           // "flex-start"|"center"|"flex-end"|"space-between"|"space-around"
    "_alignItems": "center",                      // "flex-start"|"center"|"flex-end"|"stretch"
    "_columnGap": {"size": 24, "unit": "px"},     // MUST be {size, unit} object — never a plain string
    "_rowGap": {"size": 16, "unit": "px"},        // MUST be {size, unit} object — never a plain string
    "_flexWrap": "wrap",                          // "wrap" | "nowrap"
    "_padding": {"top": "40px", "right": "20px", "bottom": "40px", "left": "20px"},
    "_margin": {"top": "0", "right": "auto", "bottom": "0", "left": "auto"},
    "_widthMax": {"size": 1200, "unit": "px"},    // max-width — key is _widthMax, NOT _maxWidth
    "_width": {"size": 100, "unit": "%"},         // MUST be {size, unit} object
    "_background": {"color": {"hex": "#ffffff"}},
    "_cssCustom": "any: raw-css-here;"            // fallback for complex styles not covered by controls
  }
}
```

**CRITICAL: Dimension format rules — ALWAYS use `{"size": N, "unit": "px|%|vh|vw|em|rem"}` objects for:**
- `_width`, `_height` — e.g. `{"size": 300, "unit": "px"}` or `{"size": 50, "unit": "%"}`
- `_widthMax` (max-width), `_widthMin` (min-width) — e.g. `{"size": 1200, "unit": "px"}`
- `_heightMin` (min-height), `_heightMax` (max-height) — e.g. `{"size": 100, "unit": "vh"}`
- `_columnGap`, `_rowGap` — e.g. `{"size": 24, "unit": "px"}`
- `_top`, `_right`, `_bottom`, `_left` (positioning offsets) — e.g. `{"size": 5, "unit": "%"}`

**NEVER use plain strings like `"50%"`, `"300px"`, or `"1200px"` for these dimension controls** — Bricks will silently discard them and generate no CSS.

**For ALL elements**, these shared style keys apply:
- `_padding` / `_margin` → `{"top":"16px","right":"24px","bottom":"16px","left":"24px"}` (spacing: plain strings OK)
- `_background` → `{"color":{"hex":"#hex"}}` or `{"image":{"url":"...","size":"cover","position":"center"}}`
- `_border` → `{"color":{"hex":"#e2e8f0"}, "width":{"top":"1px","right":"1px","bottom":"1px","left":"1px"}, "style":"solid"}`
- `_borderRadius` → `{"top":"8px","right":"8px","bottom":"8px","left":"8px"}` (keys: top/right/bottom/left)
- `_boxShadow` → `[{"color":{"hex":"#000"},"offsetX":"0px","offsetY":"4px","blur":"12px","spread":"0px"}]`
- `_width` / `_height` / `_widthMax` / `_heightMin` → `{"size": N, "unit": "px|%|vh"}` (see above)
- `_zIndex` → integer number `2` (NOT a string)
- `_position` → `"relative"` | `"absolute"` | `"fixed"` | `"sticky"` (plain string — it's a select)
- `_top` / `_right` / `_bottom` / `_left` → `{"size": 5, "unit": "%"}` (see dimension rules above)
- `_typography` → see Typography section below
- `_cssCustom` → raw CSS injected for this element only (use for overflow, box-shadow, white-space, etc.)
- `_cssGlobalClasses` → array of global class IDs from `bricks_get_global_classes`

---

## Typography Settings

For all text-bearing elements, use `_typography` with kebab-case keys:

```json
{
  "_typography": {
    "font-family": "Inter, sans-serif",
    "font-size": "1.125rem",
    "font-weight": "700",
    "line-height": "1.6",
    "letter-spacing": "0.02em",
    "color": {"hex": "#1e293b"},
    "text-align": "center",
    "text-transform": "uppercase",
    "text-decoration": "none"
  }
}
```

---

## RULE: Always Use Native Bricks Elements

**Never build with generic divs + raw HTML what Bricks already provides as a dedicated element.** Use the right tool for every job:

| Situation | Use this element |
|-----------|-----------------|
| Site navigation / header menu | `nav-nested` (custom items) or `nav-menu` (WordPress menu) |
| Contact / subscription form | `form` |
| FAQ section | `accordion-nested` |
| Tabbed content | `tabs-nested` |
| Image slider / carousel | `slider-nested` or `carousel` |
| Photo gallery | `image-gallery` |
| Blog / CPT post loop | `posts` with query builder |
| Table of contents | `post-toc` |
| Social media icon row | `social-icons` |
| Share buttons | `social-sharing` |
| Breadcrumb trail | `breadcrumbs` |
| Search box | `search` |
| Back-to-top button | `back-to-top` |
| Animated number counter | `counter` |
| Countdown timer | `countdown` |
| Progress / skill bar | `progress-bar` |
| Bulleted / numbered list | `list` |
| Icon + title + text feature | `icon-box` |
| Standalone icon | `icon` |
| Horizontal rule / separator | `divider` |
| Empty vertical space | `spacer` |
| Testimonials / reviews | `testimonials` |
| Team member grid | `team-members` |
| Pricing table | `pricing-tables` |
| Animated typing text | `animated-typing` |
| Star rating | `rating` |
| Google / embed map | `map` |
| Video player | `video` |
| Audio player | `audio` |
| Site logo | `logo` |
| Alert / notice box | `alert` |
| WP shortcode output | `shortcode` |
| Code block (display) | `code` |
| Sidebar widget area | `sidebar` |
| Single post title | `post-title` |
| Single post body | `post-content` |
| Post excerpt | `post-excerpt` |
| Post date / author meta | `post-meta` |
| Post categories / tags | `post-taxonomy` |
| Author bio box | `post-author` |
| Comments section | `post-comments` |
| Related posts | `related-posts` |
| Pagination | `pagination` |
| Prev / next post nav | `post-navigation` |

---

## Navigation: nav-nested Element

`nav-nested` is Bricks' custom nestable nav — no WordPress menu required. Its direct children are `text-link` elements (or `dropdown` for dropdowns).

```json
{
  "id": "nav001", "name": "nav-nested", "parent": "hdr002",
  "children": ["nl001","nl002","nl003"],
  "settings": {
    "_display": "flex",
    "_direction": "row",
    "_alignItems": "center",
    "_columnGap": {"size": 4, "unit": "px"},
    "menuAlignment": "row"
  }
},
{
  "id": "nl001", "name": "text-link", "parent": "nav001", "children": [],
  "settings": {
    "text": "Home",
    "link": {"type": "external", "url": "/"},
    "_typography": {"font-size": "15px", "font-weight": "500", "color": {"hex": "#8d9e93"}, "text-decoration": "none"},
    "_padding": {"top": "6px", "right": "16px", "bottom": "6px", "left": "16px"},
    "_background": {"color": {"hex": "#00e87a"}},
    "_borderRadius": {"top": "50px", "right": "50px", "bottom": "50px", "left": "50px"},
    "_cssCustom": "color: #060f09 !important;"
  }
},
{
  "id": "nl002", "name": "text-link", "parent": "nav001", "children": [],
  "settings": {
    "text": "About",
    "link": {"type": "external", "url": "#about"},
    "_typography": {"font-size": "15px", "font-weight": "500", "color": {"hex": "#8d9e93"}, "text-decoration": "none"},
    "_padding": {"top": "6px", "right": "12px", "bottom": "6px", "left": "12px"}
  }
}
```

For **WordPress-menu-driven nav**, use `nav-menu` with `"menu": <menu_term_id>`. First call `bricks_list_nav_menus` or `bricks_create_nav_menu` to get/create the menu.

---

## Form Element

Use the native `form` element — never build forms from raw HTML inputs.

```json
{
  "id": "frm001", "name": "form", "parent": "sec001", "children": [],
  "settings": {
    "fields": [
      {"id": "name", "type": "text",     "label": "Full Name",      "placeholder": "John Doe",          "required": true,  "width": "100"},
      {"id": "email","type": "email",    "label": "Email Address",  "placeholder": "you@example.com",   "required": true,  "width": "100"},
      {"id": "msg",  "type": "textarea", "label": "Your Message",   "placeholder": "How can we help?",  "required": false, "width": "100"},
      {"id": "sub",  "type": "submit",   "label": "Send Message",   "width": "auto"}
    ],
    "successMessage": "Thank you! We will be in touch soon.",
    "errorMessage": "Please fill in all required fields."
  }
}
```

---

## List Element

Use `list` for any bulleted/icon list — never use `ul`/`li` in raw HTML.

```json
{
  "id": "lst001", "name": "list", "parent": "sec001", "children": [],
  "settings": {
    "items": [
      {"icon": {"library": "fontawesome", "name": "fas fa-check-circle"}, "content": "Feature one description"},
      {"icon": {"library": "fontawesome", "name": "fas fa-check-circle"}, "content": "Feature two description"}
    ],
    "iconColor": {"hex": "#00e87a"},
    "_columnGap": {"size": 12, "unit": "px"},
    "_rowGap": {"size": 16, "unit": "px"}
  }
}
```

---

## Accordion (FAQ)

Use `accordion-nested` — children are nestable blocks each containing a title div and content div.

```json
{
  "id": "acc001", "name": "accordion-nested", "parent": "sec001",
  "children": ["ai001","ai002"],
  "settings": {
    "iconOpen":   {"library": "fontawesome", "name": "fas fa-minus"},
    "iconClosed": {"library": "fontawesome", "name": "fas fa-plus"},
    "openFirstItem": true
  }
},
{
  "id": "ai001", "name": "block", "parent": "acc001",
  "children": ["at001","ac001"],
  "settings": {"tag": "div"}
},
{
  "id": "at001", "name": "heading", "parent": "ai001", "children": [],
  "settings": {"text": "What services do you offer?", "tag": "h3"}
},
{
  "id": "ac001", "name": "text-basic", "parent": "ai001", "children": [],
  "settings": {"text": "We offer web design, development, and digital marketing services."}
}
```

---

## Tabs

Use `tabs-nested` for tabbed content.

```json
{
  "id": "tab001", "name": "tabs-nested", "parent": "sec001",
  "children": ["tp001","tp002"],
  "settings": {"layout": "horizontal"}
},
{
  "id": "tp001", "name": "block", "parent": "tab001",
  "children": ["tl001","tc001"],
  "settings": {"label": "Design"}
},
{
  "id": "tl001", "name": "heading", "parent": "tp001", "children": [],
  "settings": {"text": "Design", "tag": "span"}
},
{
  "id": "tc001", "name": "text-basic", "parent": "tp001", "children": [],
  "settings": {"text": "Our design process focuses on user experience and brand identity."}
}
```

---

## Posts Loop

Use the `posts` element with query builder to display any post type dynamically:

```json
{
  "id": "pst001", "name": "posts", "parent": "sec001",
  "children": ["pc001"],
  "settings": {
    "query": {
      "post_type": ["post"],
      "posts_per_page": 6,
      "orderby": "date",
      "order": "DESC"
    },
    "columns": "3",
    "_columnGap": {"size": 24, "unit": "px"},
    "_rowGap": {"size": 32, "unit": "px"}
  }
},
{
  "id": "pc001", "name": "container", "parent": "pst001",
  "children": ["pi001","ptt001","pe001","prb001"],
  "settings": {
    "_display": "flex",
    "_direction": "column",
    "_background": {"color": {"hex": "#ffffff"}},
    "_borderRadius": {"top": "12px", "right": "12px", "bottom": "12px", "left": "12px"},
    "_cssCustom": "overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);"
  }
},
{
  "id": "pi001", "name": "post-image", "parent": "pc001", "children": [],
  "settings": {"size": "medium", "_height": {"size": 200, "unit": "px"}, "_cssCustom": "object-fit: cover; width: 100%;"}
},
{
  "id": "ptt001", "name": "post-title", "parent": "pc001", "children": [],
  "settings": {"tag": "h3", "_padding": {"top": "16px","right": "20px","bottom": "8px","left": "20px"}}
},
{
  "id": "pe001", "name": "post-excerpt", "parent": "pc001", "children": [],
  "settings": {"_padding": {"top": "0","right": "20px","bottom": "16px","left": "20px"}}
},
{
  "id": "prb001", "name": "button", "parent": "pc001", "children": [],
  "settings": {
    "text": "Read More →",
    "link": {"type": "post", "url": "{post_url}"},
    "_margin": {"top": "auto","right": "20px","bottom": "20px","left": "20px"}
  }
}
```

---

## Icon Box (Features)

Use `icon-box` for icon + title + text — never build this manually:

```json
{
  "id": "ib001", "name": "icon-box", "parent": "con001", "children": [],
  "settings": {
    "icon": {"library": "fontawesome", "name": "fas fa-rocket"},
    "iconPosition": "top",
    "iconSize": "40px",
    "iconColor": {"hex": "#00e87a"},
    "title": "Fast Delivery",
    "titleTag": "h3",
    "content": "Launch your project in record time with our optimized workflow.",
    "_padding": {"top": "32px","right": "24px","bottom": "32px","left": "24px"},
    "_background": {"color": {"hex": "#0d2318"}},
    "_borderRadius": "12px"
  }
}
```

---

## Social Icons

```json
{
  "id": "soc001", "name": "social-icons", "parent": "sec001", "children": [],
  "settings": {
    "items": [
      {"icon": {"library": "fontawesome", "name": "fab fa-github"},  "link": {"url": "https://github.com/username"}},
      {"icon": {"library": "fontawesome", "name": "fab fa-linkedin"},"link": {"url": "https://linkedin.com/in/username"}},
      {"icon": {"library": "fontawesome", "name": "fab fa-twitter"}, "link": {"url": "https://twitter.com/username"}}
    ],
    "iconSize": "20px",
    "iconColor": {"hex": "#8d9e93"},
    "_columnGap": {"size": 12, "unit": "px"}
  }
}
```

---

## Testimonials

```json
{
  "id": "tst001", "name": "testimonials", "parent": "sec001", "children": [],
  "settings": {
    "items": [
      {
        "content": "Working with Yasir was an absolute pleasure. Delivered on time and exceeded expectations.",
        "name": "Sarah Johnson",
        "position": "CEO, TechCorp",
        "image": {"url": "https://example.com/avatar.jpg"}
      }
    ],
    "columns": "3",
    "_columnGap": {"size": 24, "unit": "px"}
  }
}
```

---

## Counter / Stats

```json
{
  "id": "cnt001", "name": "counter", "parent": "sec001", "children": [],
  "settings": {
    "number": "500",
    "suffix": "+",
    "prefix": "",
    "label": "Projects Delivered",
    "duration": 2000,
    "_typography": {"font-size": "2.5rem", "font-weight": "700", "color": {"hex": "#00e87a"}}
  }
}
```

---

## Table of Contents

```json
{
  "id": "toc001", "name": "post-toc", "parent": "sec001", "children": [],
  "settings": {
    "title": "Table of Contents",
    "headingLevels": ["h2", "h3"],
    "collapsible": true
  }
}
```

---

## Slider

```json
{
  "id": "sld001", "name": "slider-nested", "parent": "sec001",
  "children": ["sl001","sl002"],
  "settings": {
    "autoplay": true,
    "autoplaySpeed": 4000,
    "arrows": true,
    "dots": true,
    "loop": true
  }
},
{
  "id": "sl001", "name": "container", "parent": "sld001", "children": ["slh001","slt001"],
  "settings": {
    "_display": "flex", "_direction": "column", "_justifyContent": "center", "_alignItems": "center",
    "_heightMin": {"size": 400, "unit": "px"},
    "_background": {"color": {"hex": "#0d2318"}}
  }
}
```

---

## Components Workflow

Components are reusable element groups saved globally and insertable into any page.

1. **Create** a component: `bricks_create_component` with a name and elements array. The root element's id becomes the component id.
2. **List** components: `bricks_list_components`
3. **Insert** a component into a page: add an element with `"cid": "<component_id>"` as a property alongside `id`, `name`, `parent`, `children`.
4. **Update** component elements: `bricks_update_component` — changes propagate everywhere it's used.
5. **When to use**: repeated card layouts, CTA sections, footer patterns, pricing cards, testimonial cards.

---

## Template Workflow

```
bricks_create_template(title, type, elements)
  → returns template_id
bricks_set_template_conditions(template_id, conditions)
```

**Types**: `header`, `footer`, `content`, `archive`, `search`, `error`, `popup`, `section`

**Conditions**:
```json
[{"main": "any"}]                              // entire site
[{"main": "frontpage"}]                        // homepage only
[{"main": "postType", "postType": ["post"]}]   // all blog posts
[{"main": "ids", "ids": [42]}]                 // specific page
```

---

## Global Design Workflow

1. `bricks_update_color_palette` → set brand colors (always use these in element settings)
2. `bricks_create_global_class` → reusable CSS classes (reference via `_cssGlobalClasses: ["id"]`)
3. `bricks_update_global_settings` → Google Fonts, default typography, breakpoints

---

## Build Sequence (always follow this)

1. `bricks_get_site_info` — check CPTs, front page, site URL
2. `bricks_get_color_palette` — use existing brand colors
3. `bricks_get_global_classes` — find reusable classes
4. `bricks_list_nav_menus` — check if nav menus exist (for `nav-menu` element)
5. Build elements array (flat array, correct parent/children, `_display:"flex"` on containers)
6. `bricks_create_page` / `bricks_create_template` / `bricks_set_template_conditions`

---

## Available Tools

| Category | Tools |
|----------|-------|
| Site | `bricks_get_site_info`, `bricks_get_system_prompt`, `bricks_get_custom_instructions`, `bricks_set_front_page` |
| Elements | `bricks_get_elements` |
| Pages | `bricks_list_pages`, `bricks_get_page`, `bricks_create_page`, `bricks_update_page`, `bricks_delete_page` |
| Templates | `bricks_list_templates`, `bricks_get_template`, `bricks_create_template`, `bricks_update_template`, `bricks_delete_template`, `bricks_set_template_conditions` |
| Global Design | `bricks_get_global_settings`, `bricks_update_global_settings`, `bricks_get_color_palette`, `bricks_update_color_palette`, `bricks_get_global_classes`, `bricks_create_global_class`, `bricks_update_global_class`, `bricks_get_theme_styles`, `bricks_update_theme_styles` |
| Nav Menus | `bricks_list_nav_menus`, `bricks_create_nav_menu`, `bricks_get_nav_menu`, `bricks_update_nav_menu` |
| Components | `bricks_list_components`, `bricks_get_component`, `bricks_create_component`, `bricks_update_component`, `bricks_delete_component` |
| Posts/CPTs | `bricks_list_post_types`, `bricks_list_posts`, `bricks_get_post`, `bricks_create_post`, `bricks_update_post`, `bricks_delete_post` |
| Media | `bricks_list_media`, `bricks_upload_media_from_url`, `bricks_get_media` |
| WooCommerce | `bricks_list_products`, `bricks_get_product`, `bricks_list_product_categories` |

PROMPT
			. ( $custom_instructions ? "\n\n---\n\n## Site-Specific Custom Instructions\n\n{$custom_instructions}" : '' );
	}
}
