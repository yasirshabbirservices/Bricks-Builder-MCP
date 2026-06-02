---
name: bricks-elements
title: Bricks Elements — Complete Catalog & Best Practices
description: Full element type catalog, structure rules, element-first priority, nested/interactive elements, slider-nested for carousels, and correct parent-child patterns.
when_to_use: Any new page build, element creation, element array editing, or when deciding which element type to use for a UI pattern.
---

## Element Structure

Every Bricks element is a flat object with exactly these 8 fields:

```json
{
  "id": "abc123",
  "name": "text",
  "parent": "xyz789",
  "children": [],
  "settings": {},
  "selectors": [],
  "label": "",
  "themeStyles": []
}
```

**ID** — 6-character alphanumeric, unique across the entire page, random (never sequential).
**`parent`** — ID string of direct parent, or `0` (integer) for root-level elements.
**`children`** — ordered array of child ID strings (never nested objects).
**Flat array rule** — the page is always one flat array; `children` holds IDs only, never element objects.

---

## Element Priority Rules — Always Follow This Order

Before creating any layout or UI, choose the **most specific native Bricks element** available. Never reach for a `div` or `code` element when a purpose-built element exists.

```
Priority 1 — Bricks interactive/purpose-built element  (accordion, slider-nested, tabs, toggle, popup, form …)
Priority 2 — Bricks component (reusable template embedded via "template" element)
Priority 3 — Bricks layout element (section, container, div, block)
Priority 4 — Custom HTML via "code" element  ← only when no native element exists
```

**Examples of correct element selection:**
| User request | Correct element | Wrong choice |
|---|---|---|
| Logo carousel / infinity slider | `slider-nested` | sections/containers in a row |
| Scrolling ticker / marquee | `slider-nested` (autoplay + no-pause) | custom CSS animation on divs |
| FAQ list | `accordion` | manually coded details/summary |
| Tabbed content | `tabs` | custom JS tab switcher |
| Expandable/collapsible | `toggle` | `code` element with JS |
| Modal / lightbox | `popup` | absolute-positioned div |
| Contact form | `form` | third-party shortcode when unnecessary |
| Repeating post cards | `posts` (query loop) | manually duplicated card elements |
| Navigation | `nav-menu` | custom list of links |
| Reusable section | `template` element | copy-pasting elements |

---

## Complete Element Catalog

### Layout

| `name` value | Description | Common `tag` setting |
|---|---|---|
| `section` | Full-width section wrapper — backgrounds, padding | `section` |
| `container` | Constrained-width inner container, flex/grid layout | `div` |
| `div` | Generic block element | `div` |
| `block` | Inline-block or block utility wrapper | `div` |

**Container pattern (always use):**
```
section  [tag:section, full-width, bg/padding here]
  └─ container  [max-width: var(--container-width), margin:0 auto]
       └─ [content elements]
```

### Text & Content

| `name` | Description |
|---|---|
| `heading` | `<h1>`–`<h6>`. Set `tag` to the correct heading level. |
| `text` | Rich text block (paragraphs, formatted content) |
| `text-basic` | Simple paragraph — no rich editor overhead |
| `list` | `<ul>` or `<ol>` list |
| `icon-list` | List items with leading icons |
| `code` | Raw HTML/JS/CSS block — last resort only |
| `shortcode` | WordPress shortcode output |

### Media

| `name` | Description |
|---|---|
| `image` | `<img>` with Bricks image control (resize, lazy-load, alt) |
| `video` | Self-hosted or YouTube/Vimeo embed |
| `audio` | HTML5 `<audio>` player |
| `svg` | Inline SVG markup |
| `lottie` | Lottie JSON animation |
| `icon` | Single SVG icon from the Bricks icon library |

### Interactive & Components — **Use These First**

| `name` | Description | When to use |
|---|---|---|
| `slider` | Single-item slider/hero carousel | Full-width hero rotator, 1 item at a time |
| `slider-nested` | Multi-item carousel (Splide.js) — supports autoplay, loop, infinite scroll | Logo sliders, testimonial carousels, image galleries, product grids |
| `accordion` | Expand/collapse content panels (native `<details>` or ARIA) | FAQs, product specs, content drawers |
| `tabs` | Tabbed content panels | Pricing tables, feature comparisons, content categories |
| `toggle` | Single collapsible content block | Spoilers, optional details, inline help |
| `popup` | Triggered modal/lightbox overlay | Newsletter signups, image lightboxes, confirmation dialogs |
| `countdown` | Countdown timer (date or duration) | Sale endings, event launches |
| `animated-typing` | Typewriter text animation | Hero headlines |
| `off-canvas` | Slide-in panel (mobile nav, filter drawer) | Mobile navigation, sidebar filters |
| `mega-menu` | Complex multi-column dropdown navigation | Desktop mega nav |
| `progress-bar` | Animated progress/skill bar | Skills sections, loading indicators |
| `star-rating` | Visual star rating display | Testimonials, product reviews |
| `divider` | Decorative horizontal rule with optional icon/text | Section separators |

### Navigation & Site Structure

| `name` | Description |
|---|---|
| `nav-menu` | WordPress registered navigation menu |
| `logo` | Site logo (pulls from WordPress customizer) |
| `search` | WordPress search form |
| `breadcrumbs` | Breadcrumb navigation trail |
| `pagination` | Post loop pagination links |
| `sidebar` | WordPress registered widget area |

### Forms

| `name` | Description |
|---|---|
| `form` | Bricks native contact/lead form with built-in actions |
| `login` | WordPress login form |
| `register` | WordPress registration form |

### Dynamic Content

| `name` | Description |
|---|---|
| `posts` | Query loop — renders any post type in a repeatable template |
| `template` | Embeds a saved Bricks template — use for reusable sections |
| `map` | Google Maps embed |

### WooCommerce

| `name` | Description |
|---|---|
| `woocommerce-cart` | Cart page element |
| `woocommerce-checkout` | Checkout form |
| `woocommerce-account` | My Account area |
| `woocommerce-notices` | WooCommerce notices |
| `add-to-cart` | Add to Cart button |
| `cart-link` | Cart icon with item count |
| `product-images` | Product image gallery |
| `product-meta` | Product metadata |
| `product-price` | Product price display |
| `product-rating` | Product review stars |
| `product-stock` | Stock status indicator |
| `product-tabs` | Product description/reviews tabs |
| `upsells` | Upsell products grid |
| `related-products` | Related products grid |

---

## Slider-Nested — Logo Infinity Slider (Correct Pattern)

When the user asks for a brand logo carousel, scrolling logo strip, testimonial slider, or any multi-item infinite carousel — use `slider-nested`.

**Structure:**
```json
[
  {
    "id": "aaa111",
    "name": "slider-nested",
    "parent": "container_id",
    "children": ["slide001", "slide002", "slide003", "slide004", "slide005"],
    "settings": {
      "perPage": 5,
      "perMove": 1,
      "autoplay": true,
      "rewind": false,
      "type": "loop",
      "speed": 800,
      "interval": 2500,
      "pauseOnHover": false,
      "arrows": false,
      "pagination": false,
      "gap": "40px",
      "breakpoints": {
        "1024": { "perPage": 4 },
        "768":  { "perPage": 3 },
        "480":  { "perPage": 2 }
      }
    },
    "selectors": [".logo-slider"],
    "label": "Logo Carousel",
    "themeStyles": []
  },
  {
    "id": "slide001",
    "name": "div",
    "parent": "aaa111",
    "children": ["img001"],
    "settings": { "_display": "flex", "_alignItems": "center", "_justifyContent": "center" },
    "selectors": [".logo-slide"],
    "label": "Slide",
    "themeStyles": []
  },
  {
    "id": "img001",
    "name": "image",
    "parent": "slide001",
    "children": [],
    "settings": { "_src": { "url": "LOGO_URL", "alt": "Partner logo name" }, "_objectFit": "contain" },
    "selectors": [],
    "label": "Logo",
    "themeStyles": []
  }
]
```

**Key slider-nested settings:**
- `type: "loop"` — true infinite loop (not `"rewind"` which snaps back)
- `autoplay: true` + `interval: 2500` — auto-advances every 2.5 s
- `pauseOnHover: false` — keeps scrolling when user hovers (common for logo strips)
- `arrows: false` + `pagination: false` — clean look for logo sliders
- `gap` — space between slides
- `perPage` — number of visible slides
- `breakpoints` — responsive slide counts (adjust per design)

---

## Posts (Query Loop) — Correct Pattern

```json
{
  "id": "loop001",
  "name": "posts",
  "parent": "container_id",
  "children": ["card_template"],
  "settings": {
    "query": {
      "post_type": "post",
      "posts_per_page": 6,
      "orderby": "date",
      "order": "DESC",
      "no_found_rows": true
    },
    "_display": "grid",
    "_gridTemplateColumns": "repeat(auto-fill, minmax(300px, 1fr))",
    "_gap": "var(--spacing-md)"
  },
  "label": "Blog Posts Loop",
  "selectors": [],
  "themeStyles": []
}
```

The elements inside the loop (the `children`) are the card template — they repeat for each post.

---

## Accordion — FAQ Pattern

```json
[
  {
    "id": "acc001",
    "name": "accordion",
    "parent": "container_id",
    "children": ["item001", "item002"],
    "settings": { "closeOthers": true },
    "label": "FAQ Accordion",
    "selectors": [".faq-list"],
    "themeStyles": []
  },
  {
    "id": "item001",
    "name": "block",
    "parent": "acc001",
    "children": [],
    "settings": {
      "title": "What is your return policy?",
      "content": "We offer a 30-day money-back guarantee on all orders."
    },
    "label": "FAQ Item",
    "selectors": [".faq-item"],
    "themeStyles": []
  }
]
```

---

## Responsive Syntax

```
_padding                     → all screens (default)
_padding:tablet_portrait     → tablet portrait and smaller
_padding:mobile_portrait     → mobile portrait only
_color:hover                 → hover state, all screens
_color:mobile_portrait:hover → hover on mobile portrait
```

Breakpoint cascade (desktop-first): `default → tablet_landscape → tablet_portrait → mobile_landscape → mobile_portrait`

---

## `_cssCustom` Rules

Only use for:
- Pseudo-elements (`::before`, `::after`)
- Complex selectors (`:nth-child`, `:not()`, `[data-attr]`)
- `@keyframes` animation blocks
- Multi-rule blocks with no Bricks UI equivalent

**Never use** for: color, padding, margin, font-size, border, background, display, flexbox/grid — all have dedicated Bricks settings.

---

## Workflow

1. **Decide the element type** — use the priority table above; never default to `div` or `code`
2. **Check for existing components** — call `bricks_list_templates` for reusable templates
3. **Fetch design tokens** — `bricks_get_session_context` for palette, classes, CSS variables
4. **Build the flat array** — all elements at root level, linked via `parent`/`children`
5. **Validate** — `bricks_validate_payload` before every write; fix all errors
6. **Write** — `bricks_update_page` / `bricks_update_template`
