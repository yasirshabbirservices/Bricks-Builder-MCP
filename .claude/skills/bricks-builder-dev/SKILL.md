---
name: bricks-builder-dev
description: "Use when developing for Bricks Builder (WordPress): custom elements, controls, query loops, dynamic data tags, templates/element trees, Style Manager (global variables, color palettes, theme styles, breakpoints), custom fonts, global classes, interactions, popups, query filters, or debugging Bricks rendering/CSS quirks. Covers the element PHP API (base class, 83 built-in elements, 30+ control types), the exact DB storage map (all BRICKS_DB_* constants, options, CPTs, meta keys), CSS generation system, template conditions, seeding Bricks data programmatically, and known rendering gotchas. All verified against Bricks 2.3.7 source. Also trigger when working with Bricks child themes, Bricks REST API, or any page builder task in a Bricks-powered WordPress site."
---

# Bricks Builder Development (verified against 2.3.7)

**Verify against real source, never guess**: locate the Bricks parent theme in the project
(typically `wp-content/themes/bricks/`). Grep it for any API question.
Key source files: `includes/elements/base.php` (5457 lines, the element API), `includes/database.php`,
`includes/assets.php` (CSS generation), `includes/query.php`, `includes/templates.php`,
`includes/theme-styles.php`, `includes/ajax.php`, `includes/conditions.php`, `includes/frontend.php`.
Official docs: https://academy.bricksbuilder.io/developer/

## Custom element anatomy

```php
class My_Element extends \Bricks\Element {
    public $category = 'custom';        // label via 'bricks/builder/i18n' filter
    public $name     = 'my-element';    // unique, used in trees as element 'name'
    public $icon     = 'ti-star';       // themify icon (ti-*) — grep parent assets/fonts to confirm icon exists
    public $scripts  = [ 'myInitFn' ]; // optional: Bricks calls window.myInitFn() on frontend
    public $nestable = false;           // true = allow child elements (needs $vue_component = 'bricks-nestable')
    public $block    = '';              // optional: Gutenberg block mapping (e.g. 'core/heading')
    public $draggable   = true;         // false = can't drag in builder
    public $deprecated  = false;        // true = hidden from panel

    public function get_label() { return esc_html__( 'My Element', 'textdomain' ); }
    public function get_keywords() { return [ 'search', 'terms' ]; }

    public function set_control_groups() {
        $this->control_groups['my_group'] = [
            'title' => esc_html__( 'My Group', 'textdomain' ),
            'tab'   => 'content', // or 'style'
        ];
    }

    public function set_controls() {
        $this->controls['my_text'] = [
            'tab'   => 'content',
            'group' => 'my_group',
            'label' => esc_html__( 'Text', 'textdomain' ),
            'type'  => 'text',
            'default' => 'Hello',
        ];
    }

    public function enqueue_scripts() {
        // runs ONLY when element is on the page — conditional loading for free
        wp_enqueue_style( 'my-el', get_stylesheet_directory_uri() . '/css/my-el.css' );
    }

    public function render() {
        $settings = $this->settings;
        $this->set_attribute( '_root', 'class', 'my-class' );
        echo "<div {$this->render_attributes('_root')}>";
        echo $this->render_dynamic_data( $settings['my_text'] ?? '' );
        echo "</div>";
        // empty state in builder:
        // return $this->render_element_placeholder(['title' => 'Configure me']);
    }
}
```

**Register:** `\Bricks\Elements::register_element( $file );` on `init` priority 11. It `require_once`s the file and uses the LAST DECLARED CLASS — one class per file.

**Nestable elements** need `$nestable = true` + `$vue_component = 'bricks-nestable'`. Override `get_nestable_item()` for the child blueprint and `get_nestable_children()` for default children.

**Sharing code** across elements: use a PHP `trait` in a separate file. The element auto-loader only globs `class-*.php`, so trait files need explicit `require_once` at the top of each element.

## Element base class — key methods (base.php)

| Method | Purpose |
|---|---|
| `set_controls()` | Define element controls |
| `set_control_groups()` | Define control group tabs/sections |
| `render()` | Output element HTML (abstract, must override) |
| `enqueue_scripts()` | Register/enqueue JS/CSS per element |
| `set_attribute($key, $attr, $value)` | Set HTML attribute on a named node (`_root`, custom keys) |
| `render_attributes($key)` | Output HTML attribute string for a node |
| `remove_attribute($key, $attr, $value)` | Remove an attribute |
| `render_element_placeholder($data)` | Builder placeholder (gray box with icon/title) |
| `render_dynamic_data($content)` | Parse `{tags}` in strings |
| `render_dynamic_data_tag($tag, $ctx, $args)` | Render a single dynamic tag |
| `set_link_attributes($key, $link_settings)` | Parse link control → set href/target/rel |
| `get_tag()` | Determine HTML tag (theme style > setting > default) |
| `lazy_load()` | Check if lazy loading applies |
| `get_loop_builder_controls($group)` | Returns hasLoop + query controls for query loop support |
| `render_query_loop_trail($query, $key)` | Render AJAX pagination/infinite scroll trail |
| `is_layout_element()` | Check if element is Section/Container/Block/Div |

## Control types (30+ types, from includes/setup.php)

See `references/element-api.md` for full control shapes and properties.

**Text/input:** `text`, `textarea`, `code` (CSS/JS/HTML modes), `html`, `editor` (TinyMCE)
**Numeric:** `number` (min/max/step/units), `spacing` (4-value margin/padding), `dimensions`
**Selection:** `select`, `checkbox`, `radio`, `align-items`, `justify-content`, `direction`
**Media:** `image`, `gallery`, `icon`, `svg`, `color` (with alpha), `video`
**Link:** `link` (internal page, external URL, taxonomy, media, lightbox image/video)
**Complex:** `typography`, `background`, `border`, `box-shadow`, `gradient`, `transform`, `filters`
**Repeater:** `repeater` (with `fields` and `titleProperty`)
**Layout:** `separator`, `info` (message box), `query` (query builder)

**`checkbox` gotcha**: PRESENCE-based — checked = key set (truthy), unchecked = key ABSENT. No stored `false`. A value equal to its `default` is stripped on save. For a default-ON toggle, use an opt-OUT checkbox: no `default`, name it `hideX`/`noX`, read via `empty($s['hideX'])`.

### Control properties

Every control supports these keys:
```php
[
    'tab'            => 'content' | 'style',
    'group'          => 'groupKey',
    'label'          => 'Label text',
    'type'           => 'control_type',
    'default'        => 'value',
    'placeholder'    => 'hint',
    'inline'         => true,          // display inline (row)
    'small'          => true,          // reduce size
    'required'       => [['other_key', '=', 'value']], // conditional visibility
    'css'            => [['property' => 'color', 'selector' => '.child']], // auto CSS
    'units'          => true,          // enable unit selector (px/em/%/vh/vw)
    'min' / 'max' / 'step' => ...,
    'options'        => ['val' => 'Label'], // for select/radio
    'hasVariables'   => true,          // allow CSS variables
    'hasDynamicData' => true,          // allow {dynamic_tags}
    'rerender'       => true,          // rerender element on change in builder
    'description'    => 'help text',
    // Repeater-specific:
    'fields'         => [...],
    'titleProperty'  => 'fieldKey',
]
```

## Element settings keys (inside element trees)

- `_cssClasses` (string), `_cssId`, `_cssGlobalClasses` (array of global class IDs)
- `_attributes` → repeater `[ ['id'=>'abc123','name'=>'data-x','value'=>'y'], ... ]`
- `_conditions` → visibility conditions array (AND/OR groups)
- `tag` / `customTag` — custom HTML tags (`'tag'=>'custom','customTag'=>'address'`)
- Element tree node shape: `['id'=>6char, 'name'=>element, 'parent'=>0|'id', 'children'=>[ids], 'settings'=>[...], 'label'=>'optional']`

## Query loops

Put `hasLoop => true` + `query => [...]` on the REPEATING element.

```php
'query' => [
    'post_type'      => ['post'],       // or any CPT
    'posts_per_page' => 3,
    'orderby'        => 'date',         // ID/author/title/date/modified/rand/comment_count/meta_value
    'order'          => 'DESC',
    'offset'         => 0,
    'sticky_posts'   => false,
    // Filtering:
    'where' => [['key'=>'meta_key','compare'=>'=','value'=>'val']],
    // Pagination:
    'pagination'       => true,
    'infinite_scroll'  => false,
    'load_more'        => false,
]
```

**Query object types:** `post`, `term`, `user`, `api`, `array`

Children use dynamic tags: `{post_title}`, `{post_excerpt}`, `{post_url}`, `{post_terms_TAXONOMY:plain}`.
Dynamic featured image: `image => ['useDynamicData' => '{featured_image}', 'size' => 'large']`.

**JSON in attributes breaks inside query loops** — braces collide with `{dynamic_tag}` parsing. Build JSON payloads in JS from the DOM instead.

## DB storage map (all BRICKS_DB_* constants)

| Constant | Value | Shape |
|---|---|---|
| `BRICKS_DB_PAGE_HEADER` | `_bricks_page_header_2` | post meta, element node array |
| `BRICKS_DB_PAGE_CONTENT` | `_bricks_page_content_2` | post meta, element node array |
| `BRICKS_DB_PAGE_FOOTER` | `_bricks_page_footer_2` | post meta, element node array |
| `BRICKS_DB_PAGE_SETTINGS` | `_bricks_page_settings` | post meta, page-level settings |
| `BRICKS_DB_TEMPLATE_SLUG` | `bricks_template` | CPT slug |
| `BRICKS_DB_TEMPLATE_TYPE` | `_bricks_template_type` | meta: header/footer/content/archive/section/popup/search/error/password_protection + WooCommerce types |
| `BRICKS_DB_TEMPLATE_SETTINGS` | `_bricks_template_settings` | meta: `templateConditions`, `templatePreviewType`, `templatePreviewPostId`, `headerPosition` |
| `BRICKS_DB_EDITOR_MODE` | `_bricks_editor_mode` | meta: `bricks` = page uses Bricks |
| `BRICKS_DB_GLOBAL_SETTINGS` | `bricks_global_settings` | option, merge never replace |
| `BRICKS_DB_GLOBAL_CLASSES` | `bricks_global_classes` | option: `[{id, name, settings, category}]` |
| `BRICKS_DB_GLOBAL_CLASSES` trash | `bricks_global_classes_trash` | 30-day retention |
| `BRICKS_DB_GLOBAL_ELEMENTS` | `bricks_global_elements` | option |
| `BRICKS_DB_GLOBAL_VARIABLES` | `bricks_global_variables` | option: `[{id, name (no --), value, category}]` + `_categories` |
| `BRICKS_DB_COLOR_PALETTE` | `bricks_color_palette` | option: `[{id, name, colors: [{id, name, raw}]}]` |
| `BRICKS_DB_THEME_STYLES` | `bricks_theme_styles` | option: `{styleId: {label, conditions, settings, css}}` |
| `BRICKS_DB_BREAKPOINTS` | `bricks_breakpoints` | option: `[{key, label, width, widthBuilder?, icon, base?, custom?, paused?, edited?}]` |
| `BRICKS_DB_GLOBAL_QUERIES` | `bricks_global_queries` | option (since 2.x) |
| `BRICKS_DB_STYLE_MANAGER` | `bricks_style_manager` | option (since 2.2) |
| `BRICKS_DB_COMPONENTS` | `bricks_components` | option |
| `BRICKS_DB_PSEUDO_CLASSES` | `bricks_global_pseudo_classes` | option |
| `BRICKS_DB_CUSTOM_FONTS` | `bricks_fonts` | CPT slug |
| `BRICKS_DB_CUSTOM_FONT_FACES` | `bricks_font_faces` | post meta on font CPT |
| `BRICKS_DB_ELEMENT_MANAGER` | `bricks_element_manager` | option |

**Seeding rule: always MERGE into Bricks options (index by name/id), never blind `update_option`** — other frameworks/plugins own entries too.

## CSS generation system (includes/assets.php)

| File type | Path | When generated |
|---|---|---|
| Global CSS | `assets/css/global.min.css` | Theme activation |
| Per-post CSS | `wp-content/bricks/posts/{post_id}.min.css` | On save |
| Theme styles | `wp-content/bricks/theme-styles/style-{id}.min.css` | On theme style save |
| Global custom CSS | `wp-content/bricks/custom-css.min.css` | On settings save |
| Global variables | `wp-content/bricks/variables.min.css` | On variable change |
| Color palettes | `wp-content/bricks/color-palettes.min.css` | On palette change |
| Global elements | `wp-content/bricks/global-elements.min.css` | On global element save |

Regenerate all: `\Bricks\Assets_Files::regenerate_css_files()`

**CSS variable format:** `--brx-custom-var: value;` in `:root {}`

**Per-post CSS loads AFTER child-theme CSS** — theme styles have higher specificity. Own the theme style rather than fighting specificity from child theme.

## Template system

**Template types:** header, footer, content, archive, section, popup, search, error, password_protection, + WooCommerce types (wc_archive, wc_product, wc_cart, wc_checkout, wc_account_*)

**Template conditions** (`templateConditions` in template settings meta):
```php
[
    ['main' => 'any'],                                    // all pages
    ['main' => 'frontpage'],                              // front page only
    ['main' => 'ids', 'ids' => [42, 55]],                // specific post IDs
    ['main' => 'terms', 'terms' => ['category:5']],      // specific terms
    ['main' => 'postType', 'postType' => 'product'],     // all of a post type
    ['main' => 'archiveType', 'archiveType' => 'date'],  // archive types
    ['main' => 'search'],                                 // search results
    ['main' => 'error'],                                  // 404 page
]
```

Templates can nest other templates via the `template` element.

## Builder/editor detection functions

```php
bricks_is_builder()           // Frontend with ?bricks=1 (canvas/iframe initial paint)
bricks_is_builder_iframe()    // Builder iframe only (?brickspreview=1) — too narrow for first paint
bricks_is_builder_main()      // Builder main window (not iframe)
bricks_is_builder_call()      // AJAX or REST in builder context (element re-render)
bricks_is_frontend()          // Not in builder (live site)
bricks_is_ajax_call()         // WordPress AJAX request
bricks_is_rest_call()         // REST API request
bricks_render_dynamic_data()  // Render {tags} in content string
```

## Frontend rendering pipeline

1. `template_redirect` hook fires
2. `Database::set_active_templates()` — find best matching template via conditions + scoring
3. `Frontend::enqueue_scripts()` — enqueue CSS/JS
4. Render header from `BRICKS_DB_PAGE_HEADER` meta
5. Render content from `BRICKS_DB_PAGE_CONTENT` meta
6. Render footer from `BRICKS_DB_PAGE_FOOTER` meta
7. Each element: `Element_Base::init()` → `enqueue_scripts()` → `set_root_attributes()` → `render()`
8. Dynamic data tags resolved via `bricks_render_dynamic_data()`
9. Query loops handled by `Query` class (post/term/user/api loops)

## Hooks & filters (key ones)

See `references/hooks.md` for the complete list.

**Elements:** `bricks/builder/elements`, `bricks/elements/{name}/controls`, `bricks/element/render`, `bricks/element/settings`, `bricks/element/set_root_attributes`
**Frontend:** `bricks/frontend/render_element`, `bricks/frontend/render_data`, `bricks/content/tag`
**Templates:** `bricks/active_templates`, `bricks/screen_conditions/scores`
**Query:** `bricks/query/before_loop`, `bricks/query/after_loop`, `bricks/frontend/render_loop`
**Dynamic data:** `bricks/dynamic_data/register_providers`, `bricks/dynamic_data/tag_value_parsed`
**CSS:** `bricks/generate_css_file`, `bricks/assets/load_webfonts`
**Builder:** `bricks/builder/i18n`, `bricks/builder/data_post_id`

## Built-in elements (83 total)

**Layout:** container, section, block, div, slot
**Basic:** heading, text-basic, text, text-link, button, icon, image, video, audio, svg, divider, code, html, shortcode
**Navigation:** nav-menu, nav-nested, dropdown, offcanvas, toggle, toggle-mode, breadcrumbs, search, logo, back-to-top
**Content:** accordion, accordion-nested, tabs, tabs-nested, form, map, map-leaflet, alert, animated-typing, countdown, counter, pricing-tables, progress-bar, pie-chart, team-members, testimonials, rating, icon-box, social-icons, list, image-gallery
**Query:** posts, carousel, slider, slider-nested, pagination, query-results-summary
**Post elements:** post-title, post-excerpt, post-meta, post-content, post-sharing, related-posts, post-author, post-comments, post-taxonomy, post-navigation, post-reading-time, post-reading-progress-bar, post-toc
**Query filters:** filter-checkbox, filter-datepicker, filter-radio, filter-range, filter-search, filter-select, filter-submit, filter-active-filters
**WooCommerce:** 28 elements (product-gallery, product-add-to-cart, product-price, etc.)
**Other:** sidebar, template, wordpress, block, instagram-feed, facebook-page, map-connector

## Global classes

Option `bricks_global_classes`: `[{id: 6char, name, settings, category}]`.
MERGE by name — other plugins store classes here too.
Save via `\Bricks\Helpers::save_global_classes_in_db()` (bumps timestamp + user id — run with `--user`).
Categories in `bricks_global_classes_categories`.

Elements attach via `_cssGlobalClasses => [ids]` (Bricks prints names into the class attribute).

**Breakpoint keys in global class settings (mobile-first trap):**
- UNSUFFIXED keys = desktop = `min-width:1280` ALWAYS
- Base values need `:{base_key}` suffix (e.g. `_padding:mobile`)
- Ladder up: `:mobile_portrait`(640) / `:mobile_landscape`(768) / `:tablet_portrait`(1024) / custom keys
- Pseudo states: `:hover`
- Nestable elements (section/container/block/div) use `_direction`/`_columnGap`/`_rowGap` — NOT `_flexDirection`/`_gap`
- Colors as `['raw' => 'var(--token)']`
- `%root%` in `_cssCustom` is resolved by builder JS at SAVE time — seeded CSS must use literal `.class` selectors

## Custom fonts

CPT `bricks_fonts` → meta `bricks_font_faces`:
```php
[
    '400' => [['woff2' => attachment_id, 'woff' => attachment_id, 'unicode-range' => '...']], // multiple subsets supported
    '700' => [...],
    '700italic' => [...],
]
```
One weight per key (ranges get mangled). Variable font = same attachment per weight.
`@font-face` is CACHED in option `bricks_font_face_rules` — rebuild after code-seeding: reset `Custom_Fonts::$fonts/$font_face_rules`, call `get_custom_fonts()`, save option.
Preload: setting `customFontsPreload` enables `wp_head` link preload.

## Breakpoints

Default: desktop, tablet, mobile (max-width approach).
Mobile-first: smallest breakpoint carries `base => true` and is LAST in stored order.
Custom breakpoints need `customBreakpoints => true` in `bricks_global_settings`.

Breakpoint shape: `{key, label, width, widthBuilder?, icon, base?, custom?, paused?, edited?}`
`paused` = disabled without deleting. After changes: `Breakpoints::init_breakpoints()` + `Breakpoints::regenerate_bricks_css_files()`.

## Security gates (will silently eat your data)

1. Writing element-tree meta runs `sanitize_post_meta_*` → `Helpers::security_check_elements_before_save()`. Without a builder-capable logged-in user it RETURNS THE OLD (empty) STRUCTURE. **WP-CLI seeding must run `--user=<admin>`.**
2. woff2/svg uploads are mime-blocked outside privileged screens — add a scoped `upload_mimes` filter during sideloads.
3. AJAX save uses nonce `bricks-nonce-builder` + `Capabilities::current_user_can_use_builder()`.

## Rendering gotchas (each verified the hard way)

- `.brxe-container` defaults to `flex-direction: column` — set `row` explicitly for rows.
- Sections are centered flex columns — full-width children need `width: 100%`.
- `.brxe-div` carries `max-width: 100%`; per-element `_width` renders as ID-level CSS beating classes.
- Image element: class lands on `<a>` when linked, on `<img>` when not — style both (`.cls img, img.cls`).
- Nav menu `li` has default `margin-left: 30px`; blockquotes get border/padding chrome — reset explicitly.
- Above-the-fold images: Bricks doesn't eager-load — add `_attributes` `loading=eager fetchpriority=high`.
- nav-menu mobile: `mobileMenu => 'custom'` + `mobileMenuCustomBreakpoint => px`.
- `bricks-lazy-hidden` class appearing in DOM is normal (no CSS attached) — not a bug signal.
- A repeater control's `default` array does NOT render as editable rows in the builder. Seed the data as actual settings, keep defaults as render fallback only.
- Bricks can't NEST repeaters → model category→items as two flat repeaters with a category field per item.
- Frontend scroll-reveal JS does NOT run in the builder canvas. Detect via `bricks_is_builder()` and add builder-only CSS to un-hide animated elements.
- **Conditions system**: elements support `_conditions` for visibility — AND/OR groups with operators (==, !=, >, <, contains, LIKE, IN, BETWEEN, EXISTS). Conditions on: post_id, post_title, user_logged_in, user_role, date/time, device type, browser, query vars.

## Child theme pattern

```php
// functions.php
if ( ! bricks_is_builder_main() ) {
    wp_enqueue_style( 'bricks-child', get_stylesheet_uri(), ['bricks-frontend'] );
}

// Register custom elements
add_action( 'init', function() {
    $files = glob( get_stylesheet_directory() . '/elements/class-*.php' );
    foreach ( $files as $file ) {
        \Bricks\Elements::register_element( $file );
    }
}, 11 );

// Add i18n for custom categories
add_filter( 'bricks/builder/i18n', function( $i18n ) {
    $i18n['custom'] = esc_html__( 'Custom', 'bricks' );
    return $i18n;
} );
```

## Multisite support

Constants for sharing data from main site:
`BRICKS_MULTISITE_USE_MAIN_SITE_COMPONENTS`, `_COLOR_PALETTE`, `_GLOBAL_CLASSES`, `_GLOBAL_QUERIES`, `_VARIABLES`, `_ICON_SETS`, `_FONT_FAVORITES`, `_CUSTOM_ICONS`

## Dynamic data system (includes/integrations/dynamic-data/)

**Providers:** WP (posts, users, terms, options, meta), ACF, CMB2, Meta Box, Pods, Toolset, JetEngine, WooCommerce
**Tag format:** `{provider:field_key|filter}`
**Rendering:** `bricks_render_dynamic_data( $content, $post_id, $context )`
**Context:** `text` (default), `image`, `link`, `media`

See `references/element-api.md` for control shapes, `references/hooks.md` for the full hooks list, and `references/seeding-cookbook.md` for programmatic data seeding.
