# Programmatic Bricks Seeding Cookbook (verified 2.3.7)

Working recipes for programmatically seeding Bricks Builder data (templates, pages, styles, fonts).
Adapt the helper patterns below to your project's seeding infrastructure.

All constants referenced below are defined in `bricks/functions.php`.

## Header/Footer template

```php
$id = wp_insert_post([
    'post_type'   => BRICKS_DB_TEMPLATE_SLUG,  // 'bricks_template'
    'post_title'  => 'My Header',
    'post_status' => 'publish',
]);
update_post_meta( $id, BRICKS_DB_TEMPLATE_TYPE, 'header' );          // 'footer' for footers
update_post_meta( $id, BRICKS_DB_PAGE_HEADER, $elements_array );     // BRICKS_DB_PAGE_FOOTER for footers
update_post_meta( $id, BRICKS_DB_TEMPLATE_SETTINGS, [
    'templateConditions' => [ [ 'main' => 'any' ] ],
]);
```

**Template types:** header, footer, content, archive, section, popup, search, error, password_protection, + WooCommerce types (wc_archive, wc_product, wc_cart, wc_checkout, wc_account_*)

**Template conditions examples:**
```php
[ 'main' => 'any' ]                                    // all pages
[ 'main' => 'frontpage' ]                              // front page only
[ 'main' => 'ids', 'ids' => [42, 55] ]                // specific post IDs
[ 'main' => 'terms', 'terms' => ['category:5'] ]      // specific terms
[ 'main' => 'postType', 'postType' => 'product' ]     // all of a post type
[ 'main' => 'archiveType', 'archiveType' => 'date' ]  // archive types
[ 'main' => 'search' ]                                 // search results
[ 'main' => 'error' ]                                  // 404 page
```

## Bricks-editable page

```php
update_post_meta( $page_id, BRICKS_DB_EDITOR_MODE, 'bricks' );   // '_bricks_editor_mode'
update_post_meta( $page_id, BRICKS_DB_PAGE_CONTENT, $elements_array );  // '_bricks_page_content_2'
```

## Element node builder pattern

Deterministic 6-char IDs (`substr(md5(counter.name),0,6)`) keep re-seeding stable.
Root nodes: `parent => 0`. Parent's `children[]` must list child IDs in order.

```php
$elements = [
    [
        'id'       => 'abc123',
        'name'     => 'section',      // element type
        'parent'   => 0,              // 0 = root level
        'children' => ['def456'],
        'settings' => [
            // element-specific settings
        ],
        'label'    => 'My Section',   // optional display label
    ],
    [
        'id'       => 'def456',
        'name'     => 'heading',
        'parent'   => 'abc123',
        'children' => [],
        'settings' => [
            'text' => 'Hello World',
            'tag'  => 'h1',
        ],
    ],
];
```

## Run context

`wp --user=<admin> eval-file seed.php` — without a builder-capable user the element-tree meta write is
silently replaced with the previous (empty) value by Bricks' sanitize filter (`Helpers::security_check_elements_before_save()`).

## Style Manager (variables/palette/theme style/breakpoints)

### Global variables
Parse a `:root{}` tokens file with `/--([a-z0-9-]+)\s*:\s*([^;]+);/i`, map names → categories, MERGE into
`bricks_global_variables` + `bricks_global_variables_categories` (match by name; deterministic IDs).

```php
// Variable shape:
['id' => '6char', 'name' => 'color-primary', 'value' => '#2196f3', 'category' => 'cat_id']
// Note: name has NO -- prefix (Bricks adds it)
```

### Color palette
Palette colors as `['raw' => 'var(--token)']` keeps palette bound to the stylesheet.
```php
// Palette shape:
['id' => '6char', 'name' => 'Brand Colors', 'colors' => [
    ['id' => '6char', 'name' => 'Primary', 'raw' => 'var(--color-primary)'],
]]
```

### Theme style
Seed a complete theme style with site bg, body/headings typography, links, container width, section padding.

```php
// Theme style shape (stored in bricks_theme_styles option):
[
    'style_id' => [
        'label'      => 'My Theme Style',
        'conditions' => [['main' => 'any']],
        'settings'   => [
            'general'   => ['siteBackground' => ['color' => ['hex' => '#fff']]],
            'typography' => [
                'typographyBody'     => ['font-family' => 'custom_font_{id}', 'font-size' => '16px'],
                'typographyHeadings' => ['font-family' => 'custom_font_{id}', 'font-weight' => '700'],
            ],
            'links'     => [...],
            'container' => ['width' => '1200px'],
            'section'   => ['padding' => ['top' => '60px', 'bottom' => '60px']],
            // Per-element styles:
            'element-heading' => [...],
            'element-button'  => [...],
        ],
        'css' => '/* optional custom CSS */',  // since Bricks 2.0
    ],
]
```

### Breakpoints
Mobile-first: store ordered width-DESC with `base => true` on the SMALLEST (last) entry.

```php
// Breakpoint shape:
[
    ['key' => 'desktop',          'label' => 'Desktop',          'width' => 1280, 'icon' => 'desktop_icon'],
    ['key' => 'tablet_portrait',  'label' => 'Tablet Portrait',  'width' => 1024, 'icon' => 'tablet_icon'],
    ['key' => 'mobile_landscape', 'label' => 'Mobile Landscape', 'width' => 768,  'icon' => 'mobile_icon'],
    ['key' => 'mobile',           'label' => 'Mobile',           'width' => 480,  'icon' => 'mobile_icon', 'base' => true],
]
```

Set `customBreakpoints => true` in `bricks_global_settings`; then:
```php
\Bricks\Breakpoints::init_breakpoints();
\Bricks\Breakpoints::regenerate_bricks_css_files();
```

`paused => true` disables a breakpoint without deleting it. `edited => true` marks a modified default.

## Global classes (builder-editable styling)

Option `bricks_global_classes`: array of `[id => 6char, name, settings, category]`. MERGE by
name (other plugins/frameworks store classes here too); save via `\Bricks\Helpers::save_global_classes_in_db()`
(bumps timestamp + user id — run with `--user`). Categories in `bricks_global_classes_categories`.
Trash in `bricks_global_classes_trash` (30-day retention).

Elements attach via `'_cssGlobalClasses' => [ids]` (Bricks prints the names into the class attribute).

Settings use the SAME control keys/formats as elements:

**Breakpoint keys (mobile-first trap):**
- UNSUFFIXED keys = the `desktop` key = `min-width:1280` ALWAYS
- Base values need `:{base_key}` (e.g. `_padding:mobile`)
- Ladder up with `:mobile_portrait`(640) / `:mobile_landscape`(768) / `:tablet_portrait`(1024) / custom keys
- Pseudo states: `:hover`

**Nestable elements** (section/container/block/div) expose `_direction`/`_columnGap`/`_rowGap` — `_flexDirection`/`_gap` exist only on non-nestables and silently generate nothing.

Colors: `['raw' => 'var(--token)']`. `_cssCustom:key` wraps in that breakpoint's media query.

**`%root%` is resolved by builder JS at SAVE time** — seeded `_cssCustom` must use literal `.class-name` selectors.

## Custom fonts

Sideload woff2 (scoped `upload_mimes` filter) → `bricks_fonts` post per family → `bricks_font_faces` meta.

```php
// Font face meta shape (supports multiple subsets per weight):
[
    '400' => [
        ['woff2' => attachment_id, 'woff' => attachment_id, 'unicode-range' => 'U+0000-00FF'],
        ['woff2' => attachment_id, 'unicode-range' => 'U+0100-024F'],  // additional subset
    ],
    '700' => [['woff2' => attachment_id]],
    '700italic' => [['woff2' => attachment_id]],
]
```

One weight per key (ranges get mangled). Variable font = same attachment for all weights.

**@font-face is CACHED** in option `bricks_font_face_rules` — rebuild after code-seeding:
```php
\Bricks\Custom_Fonts::$fonts = null;
\Bricks\Custom_Fonts::$font_face_rules = null;
\Bricks\Custom_Fonts::get_custom_fonts();
// Save regenerated rules to option
```

Preload: set `customFontsPreload => true` in global settings for `<link rel="preload">` in `wp_head`.

## Importing Bricks pages/templates

Element trees (`_bricks_page_*_2` meta) are portable EXCEPT for IDs that differ per install:
- **Image attachment IDs** embedded in `image`/`background` settings and raw HTML (`<img>` src, `wp-image-{id}`)
- Global-class IDs + variable IDs ARE stable if re-seeded identically

**Strategy:**
1. Import images with a stable seed key, build a `seed-key → new attachment ID` map
2. Walk each element tree, swapping image IDs/URLs before saving
3. Use URL tokens for asset paths, swap to real URI at import time
4. Set `_bricks_editor_mode = 'bricks'` on pages
5. Set `_bricks_template_type` + `_bricks_template_settings.templateConditions` on templates
6. Save tree meta as a builder-capable user (security gate)

## After seeding

```php
flush_rewrite_rules();                               // if CPTs/taxonomies changed
\Bricks\Assets_Files::regenerate_css_files();         // after theme-style/variable changes
\Bricks\Breakpoints::regenerate_bricks_css_files();   // after breakpoint changes
```

Verify in a real browser: rendered DOM + computed styles. Bricks generates per-post CSS on save;
element `enqueue_scripts()` handles conditional asset loading automatically.

## Components (reusable blocks)

Stored in `bricks_components` option. Components can be inserted as elements and maintain a link
to the source — changes propagate. Use the `template` element for one-off embeds, components for
design-system pieces.

## Interactions (animations/scroll effects)

Handled by `includes/interactions.php`. Stored as part of element settings. Support:
- Scroll-triggered animations
- Click/hover interactions
- Timed animations
- Custom CSS transitions

80+ built-in animation types (bounce, fade, slide, zoom variants).
