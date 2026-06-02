---
name: bricks-elements
title: Bricks Elements — Complete Catalog & Best Practices
description: Full element type catalog sourced from Bricks Builder 2.3.6 theme source code, structure rules, element-first priority, nestable elements, preferred newer elements, and correct parent-child patterns.
when_to_use: Any new page build, element creation, element array editing, or when deciding which element type to use for a UI pattern.
---

## Element Structure

Every Bricks element is a flat object with exactly these fields:

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

**ID** — 6-character alphanumeric string, unique across the page. Generate randomly (never sequential like `elem1`, `elem2`).
**`parent`** — ID string of direct parent, or `0` (integer) for root-level elements.
**`children`** — ordered array of child ID strings only. Never nested objects.
**Flat array rule** — the full page is always one flat array. `children` holds IDs only.

---

## Element Priority Rules — Always Follow This Order

Before creating any layout or UI, choose the **most specific native Bricks element** available.

```
Priority 1 — Bricks nestable/interactive/purpose-built element
             (accordion-nested, tabs-nested, slider-nested, offcanvas, form, posts …)
Priority 2 — Bricks component (reusable template embedded via "template" element)
Priority 3 — Bricks layout element (section → container → div → block)
Priority 4 — Custom HTML via "code" element  ← only when no native element exists
```

**CRITICAL: Prefer the NESTABLE version when both exist:**
| Older element | Preferred nestable version |
|---|---|
| `accordion` | **`accordion-nested`** |
| `tabs` | **`tabs-nested`** |
| `nav-menu` | **`nav-nested`** |

The nestable versions offer full HTML/content flexibility and are the modern approach. The older flat versions still work but should not be used for new builds.

**Examples of correct element selection:**
| User request | Correct element | Wrong choice |
|---|---|---|
| Logo carousel / infinity slider | `slider-nested` | sections/containers/divs in a row |
| Scrolling ticker / marquee | `slider-nested` (autoplay + loop) | CSS animation on divs |
| FAQ list | `accordion-nested` | manually coded `<details>` |
| Tabbed content | `tabs-nested` | custom JS tab switcher |
| Expandable section | `accordion-nested` | `code` element with JS |
| Light/dark mode toggle | `toggle-mode` | custom JS toggle |
| Single collapsible | `toggle` | div with click handler |
| Modal / lightbox | `offcanvas` or Bricks popup | absolute-positioned div |
| Contact form | `form` | third-party shortcode |
| Repeating post cards | `posts` (query loop) | manually duplicated cards |
| Mobile navigation | `nav-nested` + `offcanvas` | custom list of links |
| Reusable section | `template` element | copy-pasting elements |
| Alert/notification | `alert` | styled div |
| Animated number | `counter` | JS counter script |
| Animated text | `animated-typing` | typewriter JS library |

---

## Complete Element Catalog

> Source: Bricks Builder 2.3.6 (`includes/elements/`, `includes/woocommerce/elements/`)

### Layout (nestable)

| `name` | Nestable | Description | Typical tag |
|---|---|---|---|
| `section` | ✅ | Full-width section wrapper — backgrounds, padding | `section` |
| `container` | ✅ | Constrained-width inner wrapper, flex/grid layout | `div` |
| `div` | ✅ | Generic block element | `div` |
| `block` | ✅ | Inline-block or block utility wrapper | `div` |

**Standard container pattern (always use):**
```
section  [tag: section, full-width, bg/padding here]
  └─ container  [max-width: var(--container-width), margin: 0 auto]
       └─ [content elements]
```

### Basic

| `name` | Description |
|---|---|
| `heading` | `<h1>`–`<h6>`. Always set `tag` to the correct heading level. |
| `text` | Rich-text block with formatting controls |
| `text-basic` | Simple paragraph — lighter weight, no rich editor |
| `text-link` | Inline or block link element |
| `button` | CTA button with icon, link, style controls |
| `image` | `<img>` with resize, lazy-load, alt, srcset controls |
| `icon` | Single SVG icon from the Bricks icon library |
| `video` | Self-hosted, YouTube, or Vimeo embed |

### Interactive & Components — Use These First

| `name` | Nestable | Description | When to use |
|---|---|---|---|
| `accordion-nested` | ✅ | Expand/collapse panels (PREFERRED) | FAQs, product specs, content drawers |
| `accordion` | ❌ | Older flat accordion (repeater-based) | Legacy only — use `accordion-nested` instead |
| `tabs-nested` | ✅ | Tabbed panels (PREFERRED) | Pricing tables, feature comparisons |
| `tabs` | ❌ | Older flat tabs (repeater-based) | Legacy only — use `tabs-nested` instead |
| `toggle` | ❌ | Single collapsible content block | Single spoiler, inline help |
| `toggle-mode` | ❌ | Light/dark mode switcher button | Dark mode toggle in header |
| `slider-nested` | ✅ | Multi-item carousel (Splide.js) | Logo sliders, galleries, testimonials |
| `slider` | ❌ | Single-item hero slider (Swiper.js) | Full-width hero banner rotation |
| `offcanvas` | ✅ | Slide-in panel — mobile nav, filter drawer | Mobile navigation, sidebar filters |
| `dropdown` | ✅ | Hover/click dropdown container | Mega menus, dropdowns in nav |
| `nav-nested` | ✅ | Modern nestable navigation (PREFERRED) | All new navigation builds |
| `nav-menu` | ❌ | WordPress registered menu widget | Simple menu from WP Menus only |
| `form` | ❌ | Bricks native form with built-in actions | Contact, lead, registration forms |
| `countdown` | ❌ | Countdown timer (date or duration) | Sale endings, event launches |
| `counter` | ❌ | Animated number counter | Stats sections, milestones |
| `animated-typing` | ❌ | Typewriter text animation | Hero headlines |
| `back-to-top` | ✅ | Back-to-top button | Footer area |
| `alert` | ❌ | Styled notification/alert box | Info banners, notices |
| `progress-bar` | ❌ | Animated progress/skill bar | Skills sections, loading indicators |
| `rating` | ❌ | Visual star rating display | Testimonials, reviews |
| `divider` | ❌ | Decorative horizontal rule with optional icon/text | Section separators |
| `pie-chart` | ❌ | Animated circular chart | Data visualisation |
| `social-icons` | ❌ | Social media icon links row | Footer, about page |

### Content & Media

| `name` | Nestable | Description |
|---|---|---|
| `image-gallery` | ❌ | Lightbox gallery grid |
| `carousel` | ❌ | Old Swiper.js carousel (use `slider-nested` for new builds) |
| `audio` | ❌ | HTML5 `<audio>` player |
| `svg` | ❌ | Inline SVG markup |
| `shortcode` | ❌ | WordPress shortcode output |
| `code` | ❌ | Raw HTML/JS/CSS block — **last resort only** |
| `html` | ❌ | **DEPRECATED** — do not use |

### Navigation & Site Structure

| `name` | Description |
|---|---|
| `nav-nested` | Nestable modern navigation (preferred for new builds) |
| `nav-menu` | WordPress registered menu — renders WP nav menu widget |
| `logo` | Site logo from WordPress Customizer |
| `search` | WordPress search form |
| `breadcrumbs` | Breadcrumb trail |
| `pagination` | Post loop pagination links |
| `sidebar` | WordPress registered widget area |
| `back-to-top` | Scroll-to-top button (nestable) |

### Map Elements

| `name` | Description |
|---|---|
| `map` | Google Maps embed |
| `map-leaflet` | OpenStreetMap/Leaflet embed (no API key required) |
| `map-connector` | Connects map to query loop for dynamic markers |

### Dynamic Content

| `name` | Description |
|---|---|
| `posts` | Query loop — renders any post type in a repeatable template |
| `template` | Embeds a saved Bricks template — use for reusable sections/headers/footers |
| `query-results-summary` | Shows "X of Y results" for a query loop |
| `related-posts` | Posts related to the current single post |
| `pagination` | Pagination for the current query loop |

### Single Post Elements (use inside single post templates)

| `name` | Description |
|---|---|
| `post-title` | Post title |
| `post-content` | Post content body |
| `post-excerpt` | Post excerpt |
| `post-author` | Author name/bio/avatar |
| `post-meta` | Custom post meta fields |
| `post-taxonomy` | Categories, tags, or any taxonomy |
| `post-navigation` | Previous/next post links |
| `post-comments` | Comments list and form |
| `post-toc` | Auto-generated table of contents |
| `post-reading-time` | Estimated reading time |
| `post-reading-progress-bar` | Reading progress indicator |
| `post-sharing` | Social sharing buttons |

### Query Filter Elements (for dynamic query filtering)

Use these inside a filter wrapper alongside a `posts` query loop. Always pair with `pagination` and `query-results-summary`.

| `name` | Description |
|---|---|
| `filter-search` | Text search input |
| `filter-checkbox` | Checkbox filter (taxonomy, meta) |
| `filter-radio` | Radio button filter |
| `filter-select` | Dropdown/select filter |
| `filter-range` | Slider range filter (price range, etc.) |
| `filter-datepicker` | Date range filter |
| `filter-submit` | Apply/reset filter button |
| `filter-active-filters` | Shows currently active filters with remove buttons |

### Social & Embeds

| `name` | Description |
|---|---|
| `social-icons` | Row of social media icon links |
| `instagram-feed` | Instagram feed embed |
| `facebook-page` | Facebook page embed |

### WordPress Widgets

| `name` | Description |
|---|---|
| `wordpress` | Any registered WordPress widget |
| `sidebar` | WordPress registered sidebar/widget area |
| `shortcode` | WordPress shortcode |

### WooCommerce — Product Page Elements

Use only inside product (`single-product`) templates.

| `name` | Description |
|---|---|
| `product-title` | Product name heading |
| `product-price` | Product price display |
| `product-gallery` | Product image gallery with zoom |
| `product-short-description` | Product short description |
| `product-content` | Full product description |
| `product-add-to-cart` | Add to cart button + quantity |
| `product-rating` | Star rating display |
| `product-stock` | Stock status |
| `product-meta` | SKU, categories, tags |
| `product-tabs` | Description / Reviews / Additional info tabs |
| `product-additional-information` | Attributes table |
| `product-reviews` | Reviews list + form |
| `product-upsells` | Upsell products |
| `product-related` | Related products |

### WooCommerce — Cart / Checkout / Account

| `name` | Description |
|---|---|
| `woocommerce-cart-items` | Cart items table |
| `woocommerce-cart-coupon` | Coupon input |
| `woocommerce-cart-collaterals` | Cart totals + shipping calculator |
| `woocommerce-checkout-customer-details` | Billing/shipping address fields |
| `woocommerce-checkout-order-review` | Order summary |
| `woocommerce-checkout-order-payment` | Payment methods + place order button |
| `woocommerce-checkout-coupon` | Checkout coupon input |
| `woocommerce-checkout-login` | Return customer login |
| `woocommerce-checkout-order-table` | Order confirmation table |
| `woocommerce-checkout-thankyou` | Thank you message |
| `woocommerce-account-page` | Full My Account page |
| `woocommerce-account-orders` | Order history table |
| `woocommerce-account-form-login` | Login form |
| `woocommerce-account-form-register` | Registration form |
| `woocommerce-account-form-edit-account` | Edit account details |
| `woocommerce-account-addresses` | Addresses overview |
| `woocommerce-account-form-edit-address` | Edit address form |
| `woocommerce-account-downloads` | Downloadable products |
| `woocommerce-account-payment-methods` | Saved payment methods |
| `woocommerce-account-view-order` | Single order detail |
| `woocommerce-mini-cart` | Mini cart popup content |
| `woocommerce-notice` | WooCommerce notices/alerts |
| `woocommerce-breadcrumbs` | WooCommerce breadcrumb trail |

---

## Slider-Nested — Logo Infinity Slider Pattern

When the user asks for a brand logo carousel, scrolling logo strip, testimonial slider, or any multi-item infinite carousel — **always** use `slider-nested` (uses Splide.js, nestable).

**Never use** sections, containers, or divs in a row for this purpose.

```json
[
  {
    "id": "aaa111",
    "name": "slider-nested",
    "parent": "container_id",
    "children": ["slide001", "slide002", "slide003", "slide004", "slide005"],
    "settings": {
      "type": "loop",
      "perPage": 5,
      "perMove": 1,
      "autoplay": true,
      "interval": 2500,
      "pauseOnHover": false,
      "arrows": false,
      "pagination": false,
      "gap": "40px",
      "speed": 800
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
    "settings": {
      "_display": "flex",
      "_alignItems": "center",
      "_justifyContent": "center"
    },
    "selectors": [".logo-slide"],
    "label": "Slide",
    "themeStyles": []
  },
  {
    "id": "img001",
    "name": "image",
    "parent": "slide001",
    "children": [],
    "settings": {
      "_src": { "url": "LOGO_URL", "alt": "Partner logo name" },
      "_objectFit": "contain",
      "_width": "120px",
      "_height": "auto"
    },
    "selectors": [],
    "label": "Logo",
    "themeStyles": []
  }
]
```

**Key `slider-nested` settings:**
- `type: "loop"` — true infinite loop (not `"rewind"` which snaps back)
- `type: "slide"` — standard slide (no loop)
- `type: "fade"` — crossfade transition
- `autoplay: true` + `interval: 2500` — auto-advances every 2.5 s
- `pauseOnHover: false` — keeps scrolling when user hovers (standard for logo strips)
- `arrows: false` + `pagination: false` — clean look for logo/testimonial strips
- `gap` — space between slides
- `perPage` — number of visible slides
- `perMove` — how many slides to advance per click/swipe
- `speed` — transition speed in ms
- For responsive perPage: use breakpoints object with Splide.js breakpoint widths (px values, not Bricks keys)

---

## Accordion-Nested — FAQ Pattern

**Use `accordion-nested` for all new accordion/FAQ builds.** It is nestable — you can put any Bricks element inside each panel.

Key settings:
- `expandItem` — index(es) to open on page load, comma-separated, 0-based
- `independentToggle` — allow opening multiple items simultaneously
- `transition` — animation duration in ms (default 200)
- `faqSchema` — enable FAQPage JSON-LD structured data

---

## Tabs-Nested — Tabbed Content Pattern

**Use `tabs-nested` for all new tab builds.** Nestable — any content inside tabs.

Key settings:
- `direction` — `row` (horizontal tabs) or `column` (vertical tabs)
- `openTabOn` — `click` or `hover`
- Set `ID` on the tab menu `Div` to open a tab via anchor link

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
      "post_type": ["post"],
      "posts_per_page": 6,
      "orderby": "date",
      "order": "DESC",
      "no_found_rows": true
    },
    "_display": "grid",
    "_gridTemplateColumns": "repeat(auto-fill, minmax(min(300px, 100%), 1fr))",
    "_gap": "var(--spacing-md)"
  },
  "label": "Blog Posts Loop",
  "selectors": [],
  "themeStyles": []
}
```

- `no_found_rows: true` — disables SQL `FOUND_ROWS()` for performance when pagination isn't needed
- `post_type` accepts an array of strings
- Children of the `posts` element are the card template — they repeat for each post

---

## Responsive Syntax

```
_padding                     → all screens (default)
_padding:tablet_portrait     → tablet portrait and smaller (≤ 991px)
_padding:mobile_landscape    → mobile landscape and smaller (≤ 767px)
_padding:mobile_portrait     → mobile portrait only (≤ 478px)
_color:hover                 → hover state, all screens
_color:mobile_portrait:hover → hover on mobile portrait
```

Breakpoint cascade (desktop-first by default): `default → tablet_portrait → mobile_landscape → mobile_portrait`

> Breakpoint widths are configurable in Bricks → Settings → Builder → Breakpoints. Default widths: desktop = base, tablet_portrait = 991px, mobile_landscape = 767px, mobile_portrait = 478px.

---

## `_cssCustom` Rules

Only use for:
- Pseudo-elements (`::before`, `::after` with `content`)
- Complex selectors (`:nth-child`, `:not()`, `[data-attr]`)
- `@keyframes` animation blocks
- `@supports` feature detection blocks
- Container query definitions (`container-type`)
- Multi-rule cascade overrides Bricks UI cannot express

**Never use** for: color, padding, margin, font-size, border, background, display, flexbox/grid — all have dedicated Bricks settings.

---

## Build Workflow

1. **Decide the element type** — use the priority table above; never default to `div` or `code`
2. **Check existing components** — call `bricks_list_templates` for reusable templates
3. **Fetch design tokens** — `bricks_get_session_context` for palette, classes, CSS variables
4. **Build the flat array** — all elements at root level, linked via `parent`/`children`
5. **Validate** — `bricks_validate_payload` before every write; fix all errors
6. **Write** — `bricks_update_page` / `bricks_update_template`
