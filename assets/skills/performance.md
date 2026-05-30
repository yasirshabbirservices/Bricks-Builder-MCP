---
name: performance
title: Frontend Performance for Bricks Pages
description: Image lazy loading, CLS prevention, above-fold optimisation, query loop limits, font loading, and CSS output guidelines.
when_to_use: Image-heavy sections, query loops, above-fold/hero content, or any page where load speed matters.
---

## Core Web Vitals Targets

- **LCP** (Largest Contentful Paint): < 2.5s — hero image or headline is usually the LCP element
- **CLS** (Cumulative Layout Shift): < 0.1 — caused by images without dimensions, late-loading fonts
- **INP** (Interaction to Next Paint): < 200ms — minimize JS blocking the main thread

## Images

**Below-fold images — always lazy load:**
```
_loading: lazy
```
Set this in the Bricks image element settings for any image not visible on initial page load.

**Above-fold hero image — do NOT lazy-load:**
- Remove `_loading` or set to `eager`
- Add custom attribute `fetchpriority="high"` to the hero `<img>` element
- This tells the browser to prioritize this resource for a faster LCP

**Always set explicit dimensions:**
- Set `width` and `height` in image settings to match the image's natural aspect ratio
- This reserves space in the layout before the image loads, preventing CLS
- Use consistent aspect-ratio containers (CSS `aspect-ratio` property) as an alternative

**Image format:**
- Use WebP for photos and complex images (better compression than JPEG/PNG)
- Use SVG for logos, icons, and illustrations
- Compress all images before uploading — target under 200KB for standard images, under 50KB for thumbnails

## Query Loops

**Never use unlimited results:**
```
posts_per_page: -1   ← WRONG in production
posts_per_page: 12   ← Correct — reasonable cap
```

**Skip pagination counting when not needed:**
- Add `no_found_rows: true` via the query loop's `meta_query` or custom WP_Query args
- This skips a COUNT(*) query, speeding up the loop significantly

**Avoid nested query loops:**
- One level of query loops only — nested loops multiply database queries exponentially
- If cross-referencing is needed, use ACF relationship fields or JetEngine relations instead

**Orderby consistency:**
- Always set `orderby` and `order` explicitly — avoid relying on database defaults
- `orderby: date, order: DESC` is the most common correct setting

## Fonts

**Preconnect to font providers** (add to page `<head>` via a code element or Bricks settings):
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```

**Use `font-display: swap`** to prevent invisible text during font load (FOIT):
- In the font CSS `@font-face`, set `font-display: swap`
- Google Fonts: append `&display=swap` to the font URL

**System font fallback:**
- Always define a system font stack as fallback: `font-family: 'MyFont', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;`

## CSS Output

- Consolidate repeated styles into global classes — avoid the same `_cssCustom` block on multiple elements
- Each unique `_cssCustom` block generates a separate `<style>` tag — keep them minimal
- Use CSS variables for repeated values (colors, spacing) — one source of truth

## Scripts

- Defer non-critical JavaScript — use `async` or `defer` attributes on script tags
- Avoid loading unused JavaScript plugins — check if Bricks interactions can replace them
- Prefer Bricks native interactions over custom JS for hover, scroll, click behaviors

## Above-the-Fold Checklist

For the first viewport worth of content:
- [ ] Hero image: not lazy-loaded, has `fetchpriority="high"`, has explicit width/height
- [ ] LCP element identified and optimized (usually the hero image or H1)
- [ ] No layout shift from late-loading fonts (font-display: swap applied)
- [ ] No large render-blocking scripts above the fold
- [ ] Hero section has explicit height (not 100svh without fallback on older browsers)
