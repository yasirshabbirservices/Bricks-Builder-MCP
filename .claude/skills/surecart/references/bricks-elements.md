# SureCart Bricks Elements — Full Catalog

All elements are in `surecart/app/src/Integrations/Bricks/Elements/`. They use the `ConvertsBlocks` trait for rendering.

## Layout Elements

### Product (Product.php)
- **Block:** `surecart/product-page`
- **Category:** SureCart Layout
- **Icon:** `ti-tag`
- **Nestable:** Yes
- **Purpose:** Main product page container. Default 2-column layout: media (left, 50%) + details (right, 50%).
- **Default children structure:**
  - Column 1: Media element
  - Column 2: ProductData, PriceChooser (with PriceChoiceTemplate), VariantPills, Quantity, BuyButton
- **Notes:** Must be the outermost SureCart element on product pages. Other product elements rely on the product context it provides.

### ProductCard (ProductCard.php)
- **Block:** `surecart/product-page` (reuses product-page block)
- **Category:** SureCart Layout
- **Icon:** `ion-md-list-box`
- **Controls:**
  - `linkToPost` (checkbox, default true) — Wraps card in a link to the product page
- **Purpose:** Card wrapper for product grids/listings.

### ProductReviews (ProductReviews.php)
- **Block:** `surecart/product-reviews`
- **Category:** SureCart Layout
- **Icon:** `ti-comments`
- **Nestable:** Yes
- **Default children:** Review summary, breakdown chart, review list

## Product Content Elements

### Media (Media.php)
- **Block:** `surecart/product-media`
- **Category:** SureCart Product
- **Icon:** `ti-layout-slider-alt`
- **Controls:**
  - `desktop_gallery` (select: slider/gallery) — Display mode
  - `auto_height` (checkbox) — Auto-adjust height
  - `lightbox` (checkbox) — Enable image lightbox
  - `thumbnails_per_page` (number) — Thumbnail count
  - `gallery_spacing` (number) — Gap between images
  - Height, max image width controls
- **Enqueues:** `image-slider` or `image-gallery` styles based on mode

### ProductData (ProductData.php)
- **Block:** Custom rendering (no specific block)
- **Category:** SureCart Product
- **Controls:**
  - `items` (repeater) — Array of dynamic data tag items
    - Each item: `dynamic_tag` (text) — e.g. `{sc_product_selected_price}`
  - `direction` (select: row/column)
  - `justifyContent`, `alignItems` (select)
  - `gap` (number)
- **Default items:** scratch price, selected price, billing interval, trial, setup fee
- **CSS:** `product-data.css` — Hides children if all are empty

### PriceData (PriceData.php)
- **Block:** Custom rendering
- **Category:** SureCart Product
- **Controls:** Same as ProductData
- **Default items:** price name, amount, trial, setup fee
- **Purpose:** Display price-level info (used inside PriceChoiceTemplate)

### ProductContent (ProductContent.php)
- **Block:** Uses ConvertsBlocks trait
- **Purpose:** Renders product content/description

## Form/Interactive Elements

### PriceChooser (PriceChooser.php)
- **Block:** `surecart/product-price-chooser`
- **Category:** SureCart Product
- **Icon:** `fas fa-money-bills`
- **Nestable:** Yes — children are PriceChoiceTemplate elements
- **Rendering:** Label + radio/select list of price options
- **Purpose:** Lets customers choose between pricing plans (monthly/yearly, tier, etc.)

### PriceChoiceTemplate (PriceChoiceTemplate.php)
- **Block:** `surecart/product-price-choice-template`
- **Category:** SureCart Product
- **Nestable:** Yes
- **Default layout:** Two columns — name (50%) + details (50%)
- **Purpose:** Template for each price option inside PriceChooser

### VariantPills (VariantPills.php)
- **Block:** `surecart/product-variant-pills`
- **Category:** SureCart Product
- **Icon:** `ion-md-options`
- **Nestable:** Yes — children are VariantPill elements
- **Purpose:** Shows variant option selectors (size, color, etc.) as pill buttons

### VariantPill (VariantPill.php)
- **Block:** `surecart/product-variant-pill`
- **Category:** SureCart Product
- **Purpose:** Individual variant option pill

### Quantity (Quantity.php)
- **Block:** `surecart/product-quantity`
- **Category:** SureCart Product
- **Icon:** `ti-plus`
- **Controls:**
  - `label` (text) — Input label
- **Rendering:** Number input with +/- stepper buttons

### SelectedPriceAdHocAmount (SelectedPriceAdHocAmount.php)
- **Block:** `surecart/product-selected-price-ad-hoc-amount`
- **Category:** SureCart Product
- **Purpose:** Custom/donation amount input field

### BuyButton (BuyButton.php)
- **Block:** `surecart/product-buy-button`
- **Category:** SureCart Product
- **Icon:** `ti-shopping-cart`
- **Controls:**
  - `content` (text) — Button label
  - `buy_now` (checkbox) — Skip cart, go to checkout
  - `show_sticky_purchase_button` (checkbox) — Sticky bottom bar on mobile
  - `size` (select: sm/md/lg/xl)
  - `style` (select: primary/secondary/etc.)
  - `circle` (checkbox) — Round button
  - `outline` (checkbox) — Outline style
  - Icon controls (icon picker + position)
- **Interactivity:** Uses WP Interactivity API for busy state, unavailable detection, cart redirect
- **Enqueues:** `spinner`, `wp-button` styles

### ProductQuickAddButton (ProductQuickAddButton.php)
- **Block:** `surecart/product-quick-add-button`
- **Category:** SureCart Product
- **Controls:**
  - `label` (text) — Button label
  - `icon_position` (select)
  - `quick_view_button_type` (select) — Button appearance
  - `direct_add_to_cart` (checkbox) — Add without opening product page

### ProductLineItemNote (ProductLineItemNote.php)
- **Block:** `surecart/product-line-item-note`
- **Purpose:** Customer note/instruction field

## Collection/Taxonomy Elements

### CollectionTags (CollectionTags.php)
- **Block:** `surecart/product-collection-tags`
- **Category:** SureCart Product
- **Nestable:** Yes — wraps CollectionTag children
- **Purpose:** Container for collection badges on product cards

### CollectionTag (CollectionTag.php)
- **Block:** `surecart/product-collection-tag`
- **Category:** SureCart Product
- **Purpose:** Single collection badge/chip

## Sale/Status Elements

### SaleBadge (SaleBadge.php)
- **Block:** `surecart/product-sale-badge`
- **Category:** SureCart Product
- **Icon:** `ti-tag`
- **Purpose:** "On Sale" visual indicator, conditionally shown

## Review Elements

### ProductReviewAverageRatingStars
- **Block:** `surecart/product-review-average-rating-stars`
- **Controls:** `fill_color` (color picker) — Star fill color

### ProductReviewAverageRatingValue
- **Block:** `surecart/product-review-average-rating-value`
- **Controls:**
  - `format_style` (select: slash/percent/text) — "4.5/5" vs "90%" vs "Excellent"
  - `link_to_reviews` (checkbox) — Scroll to reviews section

### ProductReviewTotalRating
- **Block:** `surecart/product-review-total-rating`
- **Controls:**
  - `show_label` (checkbox) — Show "reviews" label
  - `link_to_reviews` (checkbox)
  - `show_for_zero_reviews` (checkbox)

### ProductReviewBreakdown
- **Block:** `surecart/product-review-breakdown`
- **Controls:**
  - `columns` (number) — Layout columns
  - `row_gap`, `column_gap` (number) — Spacing
  - `fill_color` (color) — Bar background
  - `bar_fill_color` (color) — Filled bar color

### ProductReviewContent
- **Block:** `surecart/product-review-content`
- **Purpose:** Conditional wrapper — only renders if reviews exist

### ProductReviewList
- **Block:** `surecart/product-review-list`
- **Controls:**
  - `show_header` (checkbox)
  - `show_sidebar` (checkbox)
  - `show_add_button` (checkbox) — "Write a review" button
  - `show_review_date` (checkbox)
  - `show_content` (checkbox)
  - `show_pagination` (checkbox)

### ProductReviewRating
- **Block:** `surecart/product-review-rating`
- **Purpose:** Individual review star rating display

### ProductReviewItem
- **Purpose:** Single review card within the list

## Navigation Elements

### CartMenuIcon (CartMenuIcon.php)
- **Block:** `surecart/cart-menu-icon-button`
- **Category:** SureCart Product
- **Icon:** `ti-bag`
- **Controls:**
  - `cart_icon` (icon picker) — Custom cart icon
  - `cart_menu_always_shown` (checkbox) — Show even when cart is empty
- **Interactivity:** WP Interactivity API for live cart count badge updates
- **Usage:** Place in header template for persistent cart access

## Element hierarchy for product pages

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

## Element hierarchy for product cards

```
ProductCard (linkToPost wrapper)
├── Featured Image / Media
├── CollectionTags
│   └── CollectionTag (per collection)
├── SaleBadge (conditional)
├── Heading (product title)
├── ProductData (price display)
└── ProductQuickAddButton
```

## Element hierarchy for reviews section

```
ProductReviews (nestable container)
├── ProductReviewAverageRatingStars
├── ProductReviewAverageRatingValue
├── ProductReviewTotalRating
├── ProductReviewBreakdown
├── ProductReviewContent (conditional)
│   └── ProductReviewList
│       └── ProductReviewItem (per review)
│           └── ProductReviewRating
```
