---
name: typography
title: Typography Best Practices for Bricks Pages
description: Fluid type with clamp(), line-height, max line length, typeface limits, font loading, and responsive text rules.
when_to_use: Any text styling, font selection, heading sizing, or readability work.
---

## Type Scale — Use CSS Variables

Never hardcode font sizes on individual elements. Define a scale as CSS variables, then reference those variables in element settings:

```css
--font-size-xs:   clamp(0.75rem,  1.5vw,  0.875rem);
--font-size-sm:   clamp(0.875rem, 2vw,    1rem);
--font-size-base: clamp(1rem,     2.5vw,  1.125rem);
--font-size-lg:   clamp(1.125rem, 3vw,    1.375rem);
--font-size-xl:   clamp(1.375rem, 4vw,    1.75rem);
--font-size-2xl:  clamp(1.75rem,  5vw,    2.5rem);
--font-size-3xl:  clamp(2rem,     6vw,    3.5rem);
--font-size-4xl:  clamp(2.5rem,   8vw,    5rem);
```

In Bricks element settings, set `_typography.font-size` to `var(--font-size-2xl)` rather than a fixed `px` or `rem` value.

**`clamp(min, preferred, max)` breakdown:**
- `min`: smallest value on mobile
- `preferred`: fluid value using `vw` units
- `max`: largest value on desktop

## Line Height

| Use | Value |
|---|---|
| Body text | 1.5–1.7 |
| Large body / lead | 1.4–1.6 |
| Headings (h1, h2) | 1.1–1.3 |
| Subheadings (h3, h4) | 1.2–1.4 |
| Buttons / UI labels | 1.1–1.2 |

- Set line-height unitless (e.g. `1.6`, not `1.6rem` or `160%`) — unitless values scale with font size
- Tighter line-height for larger text, looser for smaller text

## Line Length (Measure)

- Optimal: **60–75 characters** per line for body text
- Maximum: **85 characters** (wider becomes hard to track line-by-line)
- Set `max-width` on text containers: `max-width: 65ch` is a reliable shorthand
- Full-width text blocks on desktop should be avoided — use a constrained inner column

## Typeface Limits

- **Maximum 2 typefaces per page** — one for headings, one for body text
- Decorative/display fonts: use for headings only, never body text
- System font stack as fallback: `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`
- Check Business Profile `typography` settings for designated heading and body fonts before selecting

## Font Weights

- Only use weight values the loaded font actually supports
- Common: 400 (regular), 500 (medium), 600 (semibold), 700 (bold)
- Avoid declaring `font-weight: 300` (light) if the font doesn't include a 300 variant — browser will fake it badly
- Check the Business Profile `font_heading` and `font_body` entries for the fonts in use

## Font Loading — Best Practices

**Preconnect to the font CDN** (in page `<head>` or site-wide):
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```

**Always use `font-display: swap`:**
- Prevents invisible text (FOIT) while the font loads
- The browser shows a fallback font immediately, swaps to the web font when ready

**Limit variants:**
- Load only the weights and styles you actually use
- Each additional weight is a separate network request
- For example: load 400 + 600 + 700, not all 9 weights

## Text Alignment

- Body text: `left` aligned (not `justify`) — justification causes uneven word spacing on narrow screens
- Centered text: use sparingly — short headings and CTAs only; never long body paragraphs
- Right alignment: only for specific UI contexts (e.g. table numbers, RTL languages)

## Responsive Typography

- Apply base font-size changes per breakpoint using the responsive suffix:
  ```
  _typography:mobile_portrait → font-size: var(--font-size-base)
  ```
- Heading sizes on mobile should be noticeably smaller — a `clamp()` scale handles this automatically
- Ensure sufficient tap target size for linked text on mobile (minimum 44×44px touch area)

## Hierarchy Signals

Use these CSS properties to establish clear visual hierarchy:
- **Size** — most powerful signal (h1 significantly larger than body)
- **Weight** — bold for headings, regular for body
- **Color** — use `var(--color-heading)` for headings vs `var(--color-text)` for body
- **Spacing** — more space above a heading than below (pull headings toward their content)
- **Letter spacing** — only for all-caps labels or UI elements (avoid on long text)

## Quick Checklist

- [ ] Font sizes use CSS variables with `clamp()`, not hardcoded `px` values
- [ ] Line-height: 1.5–1.7 for body, 1.1–1.3 for headings
- [ ] Body text containers: `max-width: 65ch`
- [ ] Maximum 2 typefaces in use
- [ ] Font weights match those available in the loaded font files
- [ ] Fonts loaded with `font-display: swap` and preconnect
- [ ] No `text-align: justify` on body text
