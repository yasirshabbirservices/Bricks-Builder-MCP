---
title: SureCart Integration
description: SureCart ecommerce elements, dynamic data tags, template patterns, and product page building in Bricks Builder
when_to_use: When building SureCart product pages, collection archives, checkout forms, cart icons, or any ecommerce layout using SureCart elements in Bricks
---

# SureCart Integration for Bricks Builder

## Overview

SureCart is a headless ecommerce platform — products, orders, and customers live on api.surecart.com. The WordPress plugin provides custom post types (`sc_product`, `sc_form`, `sc_cart`), taxonomies (`sc_collection`), 31+ Bricks elements, and 21 dynamic data tags.

## SureCart Bricks Elements

### Product Page Elements (use inside Product container)

| Element | Purpose | Key Controls |
|---|---|---|
| Product | Main container, nestable, 2-column default | Root container for product pages |
| Media | Image gallery/slider with lightbox | desktop_gallery (slider/gallery), auto_height, lightbox, thumbnails_per_page |
| ProductData | Multi-field display using dynamic tags | items (repeater of dynamic tags), direction, gap |
| PriceChooser | Price option selector (nestable) | Contains PriceChoiceTemplate children |
| PriceChoiceTemplate | Template per price option (nestable) | Default: 2 columns (name + details) |
| VariantPills | Variant selectors (nestable) | Contains VariantPill children |
| Quantity | Number input with +/- | label |
| BuyButton | Add to cart / buy now | content, buy_now, show_sticky_purchase_button, size, style |
| ProductLineItemNote | Customer note field | — |

### Product Card Elements (for grids/listings)

| Element | Purpose | Key Controls |
|---|---|---|
| ProductCard | Card wrapper | linkToPost (default true) |
| ProductQuickAddButton | Quick add without opening product | label, direct_add_to_cart |
| SaleBadge | "On Sale" indicator | — |
| CollectionTags/CollectionTag | Collection badges | — |

### Review Elements

| Element | Purpose | Key Controls |
|---|---|---|
| ProductReviews | Container (nestable) | — |
| ProductReviewAverageRatingStars | Star display | fill_color |
| ProductReviewAverageRatingValue | Rating number | format_style (slash/percent/text) |
| ProductReviewTotalRating | Review count | show_label, link_to_reviews |
| ProductReviewBreakdown | Distribution chart | columns, fill_color, bar_fill_color |
| ProductReviewList | Review list | show_header, show_pagination |

### Navigation

| Element | Purpose | Key Controls |
|---|---|---|
| CartMenuIcon | Header cart icon with count | cart_icon, cart_menu_always_shown |

## Dynamic Data Tags

Use in any Bricks text element with `{tag_name}` syntax.

### Product Tags (static — server-rendered)

| Tag | Purpose | Filters |
|---|---|---|
| `{sc_product_price}` | Default price, formatted | `:value`, `:raw` |
| `{sc_product_scratch_price}` | Original price (before discount) | `:value`, `:raw` |
| `{sc_product_price_range}` | Min–max price range | `:value`, `:raw` |
| `{sc_product_description}` | Product description | `:<N>` word limit |
| `{sc_product_stock}` | Stock quantity | `:available`, `:on_hand`, `:held` |
| `{sc_product_sku}` | SKU | — |
| `{sc_product_on_sale}` | Boolean sale flag | — |
| `{sc_product_trial}` | Trial period text | — |
| `{sc_product_billing_interval}` | Billing interval | — |
| `{sc_product_setup_fee}` | Setup fee amount | — |

### Product Tags (interactive — update on selection)

| Tag | Purpose |
|---|---|
| `{sc_product_selected_price}` | Currently selected price |
| `{sc_product_selected_scratch_price}` | Selected scratch price |
| `{sc_product_selected_trial}` | Selected trial period |
| `{sc_product_selected_billing_interval}` | Selected billing interval |
| `{sc_product_selected_setup_fee}` | Selected setup fee |

Interactive tags render as `<span>` with WP Interactivity data bindings. In the Bricks editor, they show preview values.

### Price Tags (inside PriceChooser)

| Tag | Purpose |
|---|---|
| `{sc_price_name}` | Price option name |
| `{sc_price_amount}` | Price with interval |
| `{sc_price_trial}` | Price trial period |
| `{sc_price_setup_fee}` | Price setup fee |

### Review Tags

| Tag | Purpose |
|---|---|
| `{sc_product_review_average_ratings}` | Average rating (0–5) |
| `{sc_product_review_total_ratings}` | Total review count |

## Template Types

SureCart registers two Bricks template types:
- **sc_product** — "SureCart - Single Product" (auto-applied to product pages)
- **sc_collection** — "SureCart - Collection Archive" (auto-applied to collection archives)

Create in Bricks → Templates → Add New → Template Type dropdown.

## Building a Product Page in Bricks

```
Product (nestable container)
├── Column 1
│   └── Media (gallery/slider)
└── Column 2
    ├── ProductData (price display)
    ├── PriceChooser (nestable)
    │   └── PriceChoiceTemplate (per price option)
    │       └── PriceData (price details)
    ├── VariantPills (nestable)
    │   └── VariantPill (per variant)
    ├── Quantity
    ├── ProductLineItemNote
    └── BuyButton
```

1. Create template with type "SureCart - Single Product"
2. Add Product element as root container
3. Arrange Media, ProductData, PriceChooser, VariantPills, Quantity, BuyButton
4. Use dynamic tags: `{sc_product_selected_price}`, `{sc_product_description}`
5. Add ProductReviews section below

## Building a Product Grid

1. Use Bricks query loop on `sc_product` post type
2. Inside: ProductCard wrapper
3. Add: featured image, heading with `{post_title}`, ProductData for pricing
4. Add SaleBadge and CollectionTags as needed
5. Alternative: use `[sc_product_list]` shortcode in Code element

## Cart Icon in Header

Use CartMenuIcon element in header template. Controls: cart_icon (icon picker), cart_menu_always_shown. Auto-updates via WP Interactivity API.

## Key Shortcodes

| Shortcode | Purpose |
|---|---|
| `[sc_product_list]` | Product grid |
| `[sc_form id="<post_id>"]` | Checkout/payment form |
| `[sc_cart_menu_icon]` | Cart icon with badge |
| `[sc_customer_dashboard]` | Customer portal |
| `[sc_customer_orders]` | Order history |
| `[sc_customer_subscriptions]` | Subscription management |
| `[sc_customer_downloads]` | Digital downloads |
| `[sc_product_review_list]` | Product reviews |

Prefer native SureCart Bricks elements over shortcodes when building in Bricks.

## SureCart + Accessibility

- Product images need descriptive alt text via element settings
- BuyButton with icon-only needs aria-label
- PriceChooser renders as radio/select — verify keyboard accessibility
- CartMenuIcon badge updates use aria-live for screen readers
- Ensure price display has sufficient color contrast

## SureCart + SEO

- Product pages need unique title and meta description
- Use Product schema (JSON-LD) with name, offers, price, availability
- Product images need alt text with product name
- Use BreadcrumbList schema on product pages
- SureCart integrates with: Yoast, RankMath, SEOPress, AIOSEO, TheSEOFramework
