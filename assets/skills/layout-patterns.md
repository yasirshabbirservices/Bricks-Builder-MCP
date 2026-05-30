---
name: layout-patterns
title: Layout Patterns for Bricks Pages
description: Container pattern, section spacing, hero layout, grid vs flexbox, card grids, responsive mobile-first design, and common section templates.
when_to_use: Any new section, hero, grid layout, card collection, or responsive layout work.
---

## The Container Pattern

Every page section should follow the outer/inner container pattern:

```
<section>  ← full-width background (color, image, gradient)
  <div class="container">  ← constrained width, centered
    [content]
  </div>
</section>
```

In Bricks:
- Outer: `section` element with `tag: section`, full-width, background settings applied here
- Inner: `div` or `container` element with `max-width` set to the site's content width (e.g. `1200px` or `var(--container-width)`) and `margin: 0 auto`

**Never** apply the background directly to the constrained container — it will cut off on scroll.

## Standard Section Spacing

Consistent vertical rhythm across all sections:

```css
padding-block-start: var(--spacing-section);   /* top padding */
padding-block-end:   var(--spacing-section);   /* bottom padding */
padding-inline:      var(--spacing-md);        /* horizontal padding on mobile */
```

- `--spacing-section` is typically `4rem–8rem` depending on the design system
- Horizontal padding (`padding-inline`) prevents content touching viewport edges on mobile

## Hero Section

```
<section>  tag: section, min-height: 100svh
  <div class="container">  centered, display: flex, align-items: center
    <div class="hero__content">
      <h1>Page Title</h1>
      <p>Lead text</p>
      <a class="button">CTA</a>
    </div>
  </div>
</section>
```

- Use `100svh` (small viewport height) not `100vh` — `svh` accounts for mobile browser chrome (address bar)
- Vertically center content with `display: flex; align-items: center` on the container
- Hero image: use as `background-image` on the section, or an `<img>` positioned absolutely behind content
- Hero `<img>`: do NOT lazy-load, add `fetchpriority="high"`, set explicit `width` and `height`

## Grid vs Flexbox

**Use CSS Grid for 2D layouts** (rows AND columns simultaneously):
- Card grids, photo galleries, dashboard layouts, pricing tables
- Any layout where items must align in both directions

**Use Flexbox for 1D layouts** (single row OR single column):
- Navigation bars, button groups, icon + text pairs, header layouts
- Any layout where you need to space or align items along one axis

## Card Grids

Responsive card grid without breakpoints:
```css
display: grid;
grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
gap: var(--spacing-md);
```

- `auto-fill` + `minmax(280px, 1fr)` — cards reflow automatically based on container width
- `280px` is a good minimum card width; adjust based on card content
- `gap` instead of margins on child cards — cleaner and avoids edge margin issues

Fixed column grid (when you need exact column counts):
```css
/* 3 columns on desktop */
display: grid;
grid-template-columns: repeat(3, 1fr);
gap: var(--spacing-md);

/* 2 columns on tablet */
/* tablet_portrait: grid-template-columns: repeat(2, 1fr) */

/* 1 column on mobile */
/* mobile_portrait: grid-template-columns: 1fr */
```

## Spacing — Use `gap`, Not Margins

Between flex/grid items, always use `gap`:
```css
gap: var(--spacing-md);           /* equal gap in both directions */
gap: var(--spacing-lg) var(--spacing-sm);  /* row-gap col-gap */
```

- Margins on child elements interact unpredictably with flex/grid — avoid
- `gap` respects the grid/flex context and doesn't create extra space at the edges

## Sticky Header

```
Bricks header template → position: sticky, top: 0, z-index: 100
```

- Use `position: sticky` (not `fixed`) — sticky stays in flow; fixed removes from flow and can cause content jump
- Requires a wrapper with a defined height (Bricks header templates handle this)
- Set `z-index` high enough to appear above page content but below modals (100–200 is typical)

## Common Section Structures

**Features / Services (icon + text cards):**
```
section
  container
    heading (h2) — section title
    p — section subtitle
    div.card-grid (display: grid, auto-fill)
      article.card × N
        div.card__icon (SVG icon)
        h3.card__title
        p.card__text
```

**Testimonials (horizontal scroll or grid):**
```
section
  container
    heading (h2)
    div.testimonials-grid
      blockquote.testimonial × N
        p — quote text
        footer
          cite — author name
          span — company/role
```

**CTA Section:**
```
section (background: brand color)
  container (text-align: center)
    heading (h2) — compelling headline
    p — supporting text
    div.button-group (display: flex, gap, justify-content: center)
      a.button.button--primary — primary CTA
      a.button.button--outline — secondary CTA (optional)
```

**FAQ (accordion):**
```
section
  container
    heading (h2)
    div.faq-list
      details.faq-item × N (native HTML accordion)
        summary — question (clickable)
        div — answer content
```
Use native `<details>/<summary>` elements via Bricks code element for accessible accordions — no JS required.

## Mobile-First Responsive Rules

- Default settings = mobile layout (single column, smaller font, stacked elements)
- Add breakpoint variants to expand for larger screens:
  - `tablet_portrait`: 2-column grids, larger font sizes
  - Desktop default: full multi-column layout, maximum sizes

- Test that the section looks correct at all breakpoints before writing
- Avoid hiding large DOM trees with CSS `display: none` — prefer Bricks conditions to not render the element at all
