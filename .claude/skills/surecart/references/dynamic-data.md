# SureCart Dynamic Data Tags for Bricks Builder

Source: `surecart/app/src/Integrations/Bricks/BricksDynamicDataService.php`

Dynamic data tags are registered via `bricks/dynamic_tags_list` filter and rendered via `bricks/dynamic_data/render_tag` filter. Use them in any Bricks text element with `{tag_name}` syntax.

## Product Tags

### Static tags (server-rendered, no JS needed)

| Tag | Purpose | Filters |
|---|---|---|
| `{sc_product_price}` | Default/first price, formatted | `:value` (number only), `:raw` (unformatted) |
| `{sc_product_scratch_price}` | Original price (before discount), formatted | `:value`, `:raw` |
| `{sc_product_price_range}` | Min–max price range | `:value`, `:raw` |
| `{sc_product_description}` | Product excerpt/description | `:<N>` (word count limit, e.g. `:20`) |
| `{sc_product_stock}` | Stock quantity | `:available`, `:on_hand`, `:held` (meta_key filter) |
| `{sc_product_sku}` | Product SKU | — |
| `{sc_product_on_sale}` | Boolean — is product on sale | — |
| `{sc_product_trial}` | Trial period text (e.g. "14-day free trial") | — |
| `{sc_product_billing_interval}` | Billing interval text (e.g. "/month") | — |
| `{sc_product_setup_fee}` | Setup fee amount text | — |

### Interactive tags (rendered via WP Interactivity API)

These tags update dynamically when the customer selects a different price option or variant:

| Tag | Purpose |
|---|---|
| `{sc_product_selected_price}` | Currently selected price, updates on price choice change |
| `{sc_product_selected_scratch_price}` | Selected price's original/crossed-out amount |
| `{sc_product_selected_trial}` | Selected price's trial period |
| `{sc_product_selected_billing_interval}` | Selected price's billing interval |
| `{sc_product_selected_setup_fee}` | Selected price's setup fee |

These render as `<span>` elements with `data-wp-text` bindings. In the Bricks editor, they show preview/fallback values since the interactivity runtime doesn't run in the builder canvas.

## Price Tags

Used inside PriceChoiceTemplate elements (within a PriceChooser). They resolve per-price-option:

| Tag | Purpose |
|---|---|
| `{sc_price_name}` | Price option name (e.g. "Monthly", "Annual") |
| `{sc_price_amount}` | Price amount with interval (e.g. "$9.99/mo") |
| `{sc_price_trial}` | Price-specific trial period text |
| `{sc_price_setup_fee}` | Price-specific setup fee text |

## Review Tags

| Tag | Purpose |
|---|---|
| `{sc_product_review_average_ratings}` | Average rating value (0–5, decimal) |
| `{sc_product_review_total_ratings}` | Total number of reviews (integer) |

## How tags are resolved

1. Bricks encounters `{sc_product_*}` or `{sc_price_*}` in content
2. `BricksDynamicDataService::render()` matches the tag via regex
3. For product tags: calls `sc_get_product()` with the current `$post->ID`
4. For price tags: resolves via the price context (inside PriceChooser loops)
5. For review tags: fetches from product review metadata
6. Filters (`:value`, `:raw`, `:<N>`) are parsed from the tag suffix
7. Static tags return server-rendered HTML
8. Interactive (`_selected_`) tags return `<span>` with WP Interactivity data bindings

## Editor preview behavior

In the Bricks editor (`bricks_is_builder()`):
- Static tags show real product data (fetched from the current post)
- Interactive tags show fallback/preview values
- If no product context exists (e.g. editing a template without preview post), tags may show placeholder text

## Usage examples

**Simple price display:**
```
{sc_product_selected_price}
```

**Price with scratch (sale) display:**
```
<s>{sc_product_selected_scratch_price}</s> {sc_product_selected_price}
```

**Description with word limit:**
```
{sc_product_description:30}
```

**Stock display (specific metric):**
```
{sc_product_stock:available} available
```

**Conditional sale badge (use with Bricks conditions):**
Set element condition: Dynamic data `{sc_product_on_sale}` equals `true`

## Tag group in Bricks UI

All SureCart tags appear under the "SureCart" group in the Bricks dynamic data picker. They are listed with descriptive labels (e.g. "Product - Selected Price", "Product - Description").
