---
name: surecart
description: "Use when working with the SureCart WordPress ecommerce plugin — building product pages, collections, checkout forms, customer dashboards, or cart functionality. Covers SureCart's Bricks Builder integration (31+ custom elements, 21 dynamic data tags, product/collection templates), shortcodes, REST API endpoints, data models, hooks/filters, and the headless API architecture. Also use when debugging SureCart rendering, styling product cards/grids, configuring price choosers or variant selectors, or integrating SureCart with themes, page builders, or third-party plugins. Trigger on any mention of SureCart, sc_product, sc_collection, sc_form, product pricing, checkout forms, or ecommerce in a WordPress/Bricks context."
---

# SureCart WordPress Plugin Development

SureCart is a **headless ecommerce platform** — a WordPress plugin that syncs with `api.surecart.com`. Products, orders, customers, and subscriptions live on the SaaS backend; the plugin provides WordPress post types, blocks, shortcodes, REST endpoints, and page builder integrations for the storefront.

## Quick reference

| What | Where |
|---|---|
| Plugin root | `wp-content/plugins/surecart/` |
| PHP namespace | `SureCart\` (PSR-4 from `app/src/`) |
| Config/providers | `app/config.php` (70+ service providers) |
| Hooks | `app/hooks.php` |
| Models | `app/src/Models/` (124+ API-backed models) |
| REST controllers | `app/src/Controllers/Rest/` (90+ endpoints) |
| Blocks | `packages/blocks-next/build/blocks/` (153 blocks) |
| Bricks integration | `app/src/Integrations/Bricks/` |
| Shortcodes | `app/src/WordPress/Shortcodes/` |
| Routes | `app/routes/` (web, admin, ajax) |

## Post types & taxonomies

**CPTs:**
- `sc_product` — Product listings (synced from API). Supports: title, excerpt, custom-fields, editor, thumbnail, page-attributes.
- `sc_form` — Reusable checkout/payment forms.
- `sc_cart` — Cart page configuration.
- `sc_upsell_page` — Upsell funnel pages.

**Taxonomies:**
- `sc_collection` — Product collections/categories (non-hierarchical on `sc_product`).
- `sc_store` — Multi-store taxonomy.

### sc_product meta keys

| Meta key | Type | Purpose |
|---|---|---|
| `product` | object | Full product data from API |
| `sc_id` | string | SureCart platform product ID |
| `min_price_amount` / `max_price_amount` | string | Price range (cents) |
| `available_stock` | string | Stock quantity |
| `stock_enabled` | boolean | Whether stock tracking is on |
| `allow_out_of_stock_purchases` | boolean | Allow backorders |
| `featured` / `recurring` / `shipping_enabled` | boolean | Product flags |

Gallery data: REST field `gallery` (array of media objects). Falls back to product media from API if WP meta gallery is empty.

## Bricks Builder integration

SureCart ships a full Bricks integration with **31+ custom elements**, **21 dynamic data tags**, and **2 template types**. See `references/bricks-elements.md` for the complete element catalog and `references/dynamic-data.md` for all tags.

### Template types

Two custom Bricks template types are registered:
- `sc_product` — "SureCart - Single Product" (applied to product single pages)
- `sc_collection` — "SureCart - Collection Archive" (applied to collection archives)

These appear in Bricks → Templates → Add New → Template Type dropdown. Bricks auto-applies them when viewing SureCart posts/archives.

### Element registration

Elements register on `init` priority 11 via `\Bricks\Elements::register_element()`. The `Product` element loads first (other elements depend on it), then `ProductCard`, then all remaining elements.

**Element categories:** `SureCart Layout`, `SureCart Product`, `SureCart Review`

### Key Bricks elements for product pages

1. **Product** — Nestable container for single product pages. Default 2-column layout (media left, details right). Block: `surecart/product-page`.
2. **Media** — Image gallery/slider with lightbox. Controls: `desktop_gallery` (slider/gallery), `auto_height`, `lightbox`, thumbnails per page.
3. **PriceChooser** — Price option selector (nestable with PriceChoiceTemplate children).
4. **VariantPills** — Variant option pills (nestable with VariantPill children).
5. **BuyButton** — Add to cart / buy now. Controls: `content`, `buy_now`, `show_sticky_purchase_button`, size, style.
6. **ProductData** — Multi-field display using repeater of dynamic data tags (price, interval, trial, setup fee).
7. **Quantity** — Quantity input with +/- buttons.
8. **CartMenuIcon** — Header cart icon with item count badge. Uses WP Interactivity for live updates.

### Key Bricks elements for product listings

1. **ProductCard** — Card wrapper for grids. Control: `linkToPost` (checkbox, default true).
2. **ProductQuickAddButton** — Quick add button for cards.
3. **SaleBadge** — "On Sale" indicator.
4. **CollectionTags** / **CollectionTag** — Collection badges.

### Key Bricks elements for reviews

1. **ProductReviews** — Container for reviews section (nestable).
2. **ProductReviewAverageRatingStars** — Star display. Control: `fill_color`.
3. **ProductReviewAverageRatingValue** — Rating number. Controls: `format_style` (slash/percent/text).
4. **ProductReviewTotalRating** — Review count.
5. **ProductReviewBreakdown** — Rating distribution chart.
6. **ProductReviewList** — Review list with pagination.

### Dynamic data tags (Bricks)

All tags are prefixed with `sc_product_` or `sc_price_`. Use in Bricks text elements via `{tag_name}`.

**Product tags:** `{sc_product_price}`, `{sc_product_selected_price}`, `{sc_product_scratch_price}`, `{sc_product_price_range}`, `{sc_product_description}`, `{sc_product_stock}`, `{sc_product_sku}`, `{sc_product_on_sale}`, `{sc_product_trial}`, `{sc_product_billing_interval}`, `{sc_product_setup_fee}`, and `_selected_` variants for interactive display.

**Price tags:** `{sc_price_name}`, `{sc_price_amount}`, `{sc_price_trial}`, `{sc_price_setup_fee}`

**Review tags:** `{sc_product_review_average_ratings}`, `{sc_product_review_total_ratings}`

**Filters on tags:**
- `:value` / `:raw` — format variants
- `:20` on description — word count limit
- `:on_hand` / `:available` / `:held` on stock — specific stock metric

See `references/dynamic-data.md` for full details.

### ConvertsBlocks trait

All Bricks elements use the `ConvertsBlocks` trait for rendering:
- `$this->html()` — Render HTML with attributes, handles empty content preview
- `$this->raw()` — Output raw block HTML
- `$this->preview()` — Preview in editor with custom class/tag
- `$this->is_admin_editor()` — Check if in Bricks editor (uses `bricks_is_builder()`)

## Shortcodes

SureCart auto-generates ~80+ shortcodes from its blocks. Key ones for Bricks usage:

| Shortcode | Purpose |
|---|---|
| `[sc_product_list]` | Product grid |
| `[sc_product_collection collection_id="..."]` | Collection page |
| `[sc_form id="<post_id>"]` | Checkout/payment form |
| `[sc_cart_menu_icon]` | Cart icon with badge |
| `[sc_add_to_cart_button]` | Add to cart button |
| `[sc_customer_dashboard]` | Customer portal |
| `[sc_customer_orders]` | Order history |
| `[sc_customer_subscriptions]` | Subscription management |
| `[sc_customer_invoices]` | Invoice list |
| `[sc_customer_downloads]` | Digital downloads |
| `[sc_customer_payment_methods]` | Payment methods |
| `[sc_product_review_list]` | Product reviews |

In Bricks, embed these via Code elements or use the native SureCart Bricks elements instead (preferred).

## REST API

Base: `/wp-json/surecart/v1/`

Major endpoints: `/products`, `/prices`, `/orders`, `/checkouts`, `/draft-checkouts`, `/customers`, `/subscriptions`, `/invoices`, `/downloads`, `/licenses`, `/coupons`, `/promotions`, `/reviews`, `/affiliations`, `/payment-methods`, `/payment-intents`, `/shipping-zones`, `/tax-zones`, `/variants`, `/line-items`, `/exports`, `/uploads`, and 40+ more.

All endpoints proxy to `api.surecart.com` with authentication.

## WordPress hooks

### Actions (webhook-triggered)
```
surecart/purchase_created
surecart/purchase_updated
surecart/purchase_invoked
surecart/purchase_revoked
surecart/customer_updated
surecart/account_updated
surecart/subscription_renewed
surecart/checkout_confirmed
surecart/integrations/create
surecart/integrations/delete
surecart/product_created
surecart/product_updated
surecart/product_deleted
surecart/product_stock_adjusted
surecart/price_created
surecart/price_updated
surecart/price_deleted
```

### Filters
```
surecart/checkout/validate
surecart/blocks/patterns
surecart/blocks/pattern_categories
surecart/integrations/providers/{$provider}
surecart/shortcode/render
surecart/request/model
surecart/checkout/finduser
surecart_product_page_query_args
```

## Third-party integrations (24)

**LMS:** LearnDash, LifterLMS, TutorLMS, MemberPress
**Community:** BuddyBoss
**Affiliate:** AffiliateWP
**Page builders:** Bricks (native), Elementor, Beaver, Avada, Divi
**SEO:** AIOSEO, RankMath, SEOPress, TheSEOFramework, Yoast, SureRank
**Automation:** Thrive Automator

## Data architecture

SureCart is headless — most data lives on `api.surecart.com`. The WordPress plugin stores:

**Custom tables:**
- `surecart_integrations` — Maps SureCart items to third-party systems (LearnDash courses, MemberPress plans, etc.)
- `surecart_incoming_webhooks` — Webhook event log
- `surecart_variant_option_values` — Variant option relationships

**wp_options:**
- `sc_uninstall` — Delete data on uninstall
- `surecart_mcp_abilities_enabled` — WordPress Abilities API toggle
- Permalink/slug settings, brand colors, currency, processor configs

**wp_postmeta:** See `sc_product` meta keys above.

## Frontend architecture

- 153 Gutenberg blocks (in `packages/blocks-next/`)
- Web Components (Stencil-based, `sc-*` prefix: `sc-button`, `sc-input`, `sc-card`, etc.)
- WP Interactivity API for reactive state (cart count, button busy state, price selection)
- React 18 for admin UI components
- JS loaded from `js.surecart.com` CDN + local bundles

## Common patterns

### Building a custom product page in Bricks
1. Create a Bricks template with type "SureCart - Single Product"
2. Add the **Product** element as the root container
3. Inside, arrange: **Media**, **ProductData**, **PriceChooser**, **VariantPills**, **Quantity**, **BuyButton**
4. Use dynamic data tags for text elements: `{sc_product_selected_price}`, `{sc_product_description}`
5. Add **ProductReviews** section below

### Building a product grid in Bricks
1. Use a Bricks query loop on `sc_product` post type
2. Inside, use **ProductCard** as the wrapper
3. Add featured image, heading with `{post_title}`, **ProductData** for pricing
4. Add **SaleBadge** and **CollectionTags** as needed
5. Or use the `[sc_product_list]` shortcode in a Code element

### Adding cart icon to header
Use the **CartMenuIcon** Bricks element in your header template. Controls: `cart_icon` (icon picker), `cart_menu_always_shown`. It auto-updates via WP Interactivity.

### Checkout form
Use `[sc_form id="<form_post_id>"]` shortcode or build with SureCart's block editor. Forms are `sc_form` CPT — edit in SureCart's form builder, embed anywhere.

See `references/bricks-elements.md` for the full element catalog with controls and rendering details.
See `references/dynamic-data.md` for all dynamic data tags with filters and usage.
