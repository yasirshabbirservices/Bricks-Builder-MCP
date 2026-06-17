# Bricks Element API — Control Shapes & Base Class (verified 2.3.7)

Source: `includes/elements/base.php` (5457 lines), `includes/setup.php` (control options)

Read any stock element in the Bricks parent theme's `includes/elements/` directory for live examples — that beats docs.

## Base class public properties

```php
// Set in your element class:
$name              // string - Element identifier (e.g., 'heading')
$label             // string - Display name
$icon              // string - Icon class (ti-* for themify)
$category          // string - 'basic', 'layout', 'advanced', or custom
$keywords          // array  - Search keywords
$block             // string - Gutenberg block mapping (e.g., 'core/heading')
$scripts           // array  - Frontend JS library names (Bricks calls window.fnName())
$nestable          // bool   - Allow child elements (default: false)
$vue_component     // string - Custom Vue component ('bricks-nestable' for nestables)
$draggable         // bool   - Allow dragging (default: true)
$deprecated        // bool   - Hide from panel (default: true)
$panel_condition   // array  - Conditions to show in panel
$css_selector      // string - Default CSS selector for controls
$custom_attributes // bool   - Render custom attrs on _root (default: true)
$tag               // string - Default HTML tag (default: 'div')

// Populated at render time:
$element           // array  - Full element data from builder
$settings          // array  - Element-specific control values
$id                // string - Element ID (unique 6-char)
$post_id           // int    - Current post context
$is_frontend       // bool   - True if rendering on frontend (not builder)
$theme_styles      // array  - Theme style overrides for this element
$attributes        // array  - HTML attributes by key (_root, custom-key)
```

## Control types — full shapes (proven)

### Text/input controls

```php
'text'     => ['type' => 'text', 'inline' => true, 'placeholder' => '...', 'hasDynamicData' => true]
'textarea' => ['type' => 'textarea', 'placeholder' => '...']
'code'     => ['type' => 'code', 'mode' => 'css|javascript|html']  // code editor
'html'     => ['type' => 'html']                                    // HTML editor
'editor'   => ['type' => 'editor']                                  // TinyMCE rich text
```

### Numeric controls

```php
'number'   => ['type' => 'number', 'min' => 0, 'max' => 100, 'step' => 1, 'placeholder' => 50,
               'inline' => true, 'units' => true]  // units: px/em/%/vh/vw
'spacing'  => ['type' => 'spacing', 'css' => [['property' => 'margin']]]  // 4-value margin/padding
'dimensions' => ['type' => 'dimensions']  // width/height
```

### Selection controls

```php
'select'   => ['type' => 'select', 'options' => ['a' => 'A', 'b' => 'B'],
               'placeholder' => 'Choose...', 'inline' => true, 'searchable' => true,
               'multiple' => true]  // allow multiple selections
'checkbox' => ['type' => 'checkbox']  // PRESENCE-based: no stored false!
'radio'    => ['type' => 'radio', 'options' => ['a' => 'A'], 'inline' => true]
'align-items'      => ['type' => 'align-items']       // flex align-items visual selector
'justify-content'  => ['type' => 'justify-content']   // flex justify-content selector
'direction'        => ['type' => 'direction']          // flex direction selector
```

### Media controls

```php
'image'    => ['type' => 'image']
// value: ['id' => int, 'url' => '...', 'size' => 'full']
// or dynamic: ['useDynamicData' => '{featured_image}', 'size' => 'large']

'gallery'  => ['type' => 'gallery']  // multiple image selector

'icon'     => ['type' => 'icon', 'default' => ['library' => 'themify', 'icon' => 'ti-star']]

'svg'      => ['type' => 'svg']  // SVG selector/uploader

'color'    => ['type' => 'color']  // color picker with alpha
// value: ['hex' => '#fff', 'rgb' => 'rgba(255,255,255,1)'] or ['raw' => 'var(--token)']

'video'    => ['type' => 'video']  // video URL input
```

### Link control

```php
'link'     => ['type' => 'link']
// value: ['type' => 'external|internal|lightbox_image|lightbox_video',
//         'url' => '...', 'newTab' => true, 'rel' => 'noopener', 'ariaLabel' => '...']
// Use: $this->set_link_attributes('_root', $settings['link'])
```

### Complex/style controls

```php
'typography' => ['type' => 'typography',
    'css' => [['property' => 'font', 'selector' => '.my-text']]]
// Includes: font-family, weight, style, size, line-height, letter-spacing,
//           text-transform, text-decoration

'background' => ['type' => 'background',
    'css' => [['property' => 'background', 'selector' => '']]]
// Includes: image, color, position, repeat, attachment, size, parallax, video

'border' => ['type' => 'border',
    'css' => [['property' => 'border', 'selector' => '']]]
// Includes: width, style, color, radius

'box-shadow' => ['type' => 'box-shadow',
    'css' => [['property' => 'box-shadow', 'selector' => '']]]
// Multiple shadows: blur, spread, offset-x/y, color, inset

'gradient' => ['type' => 'gradient',
    'css' => [['property' => 'background-image', 'selector' => '']]]
// Linear/radial, angle, color stops with positions

'transform' => ['type' => 'transform',
    'css' => [['property' => 'transform', 'selector' => '']]]
// Translate, rotate, scale, skew (2D and 3D)

'filters' => ['type' => 'filters',
    'css' => [['property' => 'filter', 'selector' => '']]]
// blur, brightness, contrast, saturate, hue-rotate, grayscale, invert, sepia, opacity
```

### Repeater control

```php
'repeater' => [
    'type' => 'repeater',
    'titleProperty' => 'name',       // field key shown as row title
    'fields' => [
        'name'  => ['type' => 'text', 'label' => 'Name'],
        'image' => ['type' => 'image', 'label' => 'Image'],
        'link'  => ['type' => 'link', 'label' => 'Link'],
    ],
]
// value: [['name' => 'Item 1', 'image' => [...]], ['name' => 'Item 2', ...]]
// NOTE: default array does NOT render as editable rows in builder — seed as actual settings
// Bricks can NOT nest repeaters — use flat repeaters with category fields instead
```

### Query control

```php
'query' => [
    'type' => 'query',
    'popup' => true,                  // show in popup dialog
]
// Includes: type (post/term/user/api/array), postType, postsPerPage, orderBy, order,
//           offset, sticky_posts, where conditions, pagination, infinite_scroll, load_more
```

### Layout/info controls

```php
'separator' => ['type' => 'separator', 'label' => 'Section Title']  // visual divider
'info'      => ['type' => 'info', 'content' => 'Info message']      // blue info panel
```

## CSS property mapping

Controls with a `css` key auto-generate CSS. The `css` value is an array of rule objects:

```php
'css' => [
    [
        'property'  => 'color',           // CSS property
        'selector'  => '.child-element',  // CSS selector (empty = root element)
        'value'     => 'override-value',  // Optional: override computed value
        'important' => true,              // Optional: add !important
        'required'  => 'condition',       // Optional: only generate if condition met
    ],
]
```

## Render-side helpers

```php
// Attribute management
$this->set_attribute( '_root', 'class', 'my-class' );
$this->set_attribute( '_root', 'data-count', $count );
$this->set_attribute( 'inner', 'class', 'inner-wrap' );  // named attribute groups
echo "<div {$this->render_attributes('_root')}>";
echo "  <div {$this->render_attributes('inner')}>";

// Remove attribute
$this->remove_attribute( '_root', 'class', 'unwanted-class' );

// Link handling
$this->set_link_attributes( '_root', $settings['link'] );  // parses link control → href/target/rel

// Dynamic data
$text = $this->render_dynamic_data( $settings['text'] );   // parse {tags}
$tag_value = $this->render_dynamic_data_tag( $tag, 'text', $args );  // single tag

// Builder placeholder
return $this->render_element_placeholder( ['title' => 'Configure this element'] );

// HTML tag
$tag = $this->get_tag();  // resolves from theme style > setting > default

// Query loop
$controls = $this->get_loop_builder_controls( 'my_group' );  // hasLoop + query controls
$this->render_query_loop_trail( $query, 'trail' );           // pagination/infinite scroll

// Context
$this->is_frontend;    // true on live site, false in builder
$this->post_id;        // current post context
$this->settings;       // all control values for this element instance
```

## Stock element control patterns

### heading.php
- `text` (text, default 'Heading', hasDynamicData)
- `tag` (select: h1-h6 + custom)
- `link` (link control)
- `separator` controls: style, width, height, color

### button.php
- `text` (text, default 'Button')
- `size` (select: sm/md/lg/xl)
- `style` (select: primary/secondary/light/dark/muted/info/success/warning/danger)
- `outline` (checkbox), `circle` (checkbox)
- `link` (link control)
- `icon` (icon control) + `iconPosition` (select: left/right)

### image.php
- `image` (image control)
- `link` (link control)
- `tag` (select: figure/div/custom)
- `_objectFit` (select: contain/cover/fill/scale-down/none)
- `_aspectRatio` (text)
- `loading` (select: lazy/eager)

### container.php (nestable)
- `link` (link control) — wraps entire container in a link
- `tag` (select: div/section/article/aside/header/footer/nav/custom)
- Flex/grid controls via common control groups (_layout)

### nav-menu.php
- `menu` (select: WP menu term IDs)
- `mobileMenu` (select: 'default'/'custom')
- `mobileMenuCustomBreakpoint` (number, px)
- `mobileMenuPosition` (select: left/right)
- Markup: `.bricks-nav-menu > li > a`, mobile toggle `.bricks-mobile-menu-toggle`
- **li has default margin-left: 30px** — reset explicitly

### posts.php (query loop)
- Uses `get_loop_builder_controls()` for hasLoop + query
- `columns` (number), `gap` (number)
- Post field controls via `get_post_fields()`
- Pagination controls

## Common control groups (added automatically to all elements)

These are added by `set_controls_before()` in the base class:

- `_layout` — display, flex direction, align, justify, gap, wrap, position
- `_typography` — font family, weight, size, line-height, letter-spacing, color, transform
- `_background` — color, image, gradient, video, parallax
- `_border` — width, style, color, radius
- `_gradient` — gradient overlays
- `_boxShadow` — box shadow
- `_transform` — translate, rotate, scale, skew
- `_css` — custom CSS (`_cssCustom` field)
- `_attributes` — custom HTML attributes repeater
- `_shapes` — shape dividers (layout elements only)

## Element conditions/visibility

```php
// In element settings
'_conditions' => [
    // AND group 1 (all must be true)
    [
        ['key' => 'post_id', 'compare' => '==', 'value' => '123'],
        ['key' => 'user_logged_in', 'compare' => '==', 'value' => '1'],
    ],
    // OR group 2 (alternative)
    [
        ['key' => 'user_role', 'compare' => '==', 'value' => 'administrator'],
    ],
]
```

**Condition keys:** post_id, post_title, post_parent, post_status, post_author, post_date, featured_image, user_logged_in, user_id, user_registered, user_role, user_capability, current date/time/day, device type, browser, query variable, referrer, WooCommerce conditions (if active)

**Compare operators:** ==, !=, >, <, >=, <=, contains, LIKE, IN, BETWEEN, EXISTS

## Control option presets (from Setup::get_control_options())

- `buttonSizes` — sm, md, lg, xl
- `styles` — primary, secondary, light, dark, muted, info, success, warning, danger
- `fontWeight` — 100-900
- `fontStyle` — normal, italic, oblique
- `iconPosition` — left, right
- `objectFit` — contain, cover, fill, scale-down, none
- `position` — static, relative, absolute, fixed, sticky
- `blendMode` — 16 CSS blend modes
- `queryTypes` — post, term, user, api, array
- `queryOrderBy` — ID, author, title, date, modified, rand, comment_count, meta_value
- `templateTypes` — all template type slugs
- `animationTypes` — 80+ animation types (bounce, fade, slide, zoom, etc.)
- `backgroundSize` — auto, cover, contain, custom
- `backgroundRepeat` — no-repeat, repeat, repeat-x, repeat-y
- `backgroundPosition` — top/center/bottom + left/center/right
- `borderStyle` — none, solid, dotted, dashed, double, groove, ridge, inset, outset
