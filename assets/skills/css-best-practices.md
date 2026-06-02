---
name: css-best-practices
title: CSS Best Practices for Bricks Builder
description: Style priority order, CSS custom properties, modern CSS techniques, cross-browser support, global classes, variable usage, naming conventions, and when _cssCustom is acceptable.
when_to_use: Any styling work — applying classes, setting colors, spacing, writing custom CSS, or using CSS variables.
---

## Style Priority Order

Apply styles in this order — use the highest available option first:

1. **Global classes** (`selectors` array) — reusable, already defined on the site
2. **CSS variables** (`var(--token)` in settings values) — from the site's design system
3. **Theme styles** (`themeStyles` array) — predefined theme style IDs
4. **Palette color IDs** — from `bricks_get_color_palette`
5. **Inline settings** — only when none of the above apply
6. **`_cssCustom`** — absolute last resort (rules below)

---

## CSS Custom Properties (Variables)

CSS custom properties are the single most important tool for a maintainable design system. **Every repeatable value must be a variable.**

### Required Variable Categories

```css
/* Colors */
--color-primary:     #0d6efd;
--color-secondary:   #6c757d;
--color-accent:      #fd7e14;
--color-text:        #212529;
--color-heading:     #0a0a0a;
--color-muted:       #6c757d;
--color-background:  #ffffff;
--color-surface:     #f8f9fa;
--color-border:      #dee2e6;
--color-success:     #198754;
--color-error:       #dc3545;

/* Typography */
--font-heading:      'YourFont', system-ui, sans-serif;
--font-body:         'YourFont', system-ui, sans-serif;
--font-size-xs:      clamp(0.75rem,  1.5vw, 0.875rem);
--font-size-sm:      clamp(0.875rem, 2vw,   1rem);
--font-size-base:    clamp(1rem,     2.5vw, 1.125rem);
--font-size-lg:      clamp(1.125rem, 3vw,   1.375rem);
--font-size-xl:      clamp(1.375rem, 4vw,   1.75rem);
--font-size-2xl:     clamp(1.75rem,  5vw,   2.5rem);
--font-size-3xl:     clamp(2rem,     6vw,   3.5rem);
--font-size-4xl:     clamp(2.5rem,   8vw,   5rem);

/* Spacing scale */
--spacing-xs:        0.25rem;
--spacing-sm:        0.5rem;
--spacing-md:        1rem;
--spacing-lg:        1.5rem;
--spacing-xl:        2rem;
--spacing-2xl:       3rem;
--spacing-section:   clamp(4rem, 8vw, 8rem);

/* Layout */
--container-width:   1200px;
--border-radius-sm:  4px;
--border-radius-md:  8px;
--border-radius-lg:  16px;
--border-radius-pill: 9999px;

/* Effects */
--shadow-sm:  0 1px 3px rgba(0,0,0,.08);
--shadow-md:  0 4px 12px rgba(0,0,0,.12);
--shadow-lg:  0 8px 32px rgba(0,0,0,.16);
--transition: 200ms ease;
```

### Using Variables in Bricks Settings

In any Bricks settings field that accepts a CSS value, use the variable directly:
```
_color → var(--color-text)
_background → var(--color-surface)
_padding → var(--spacing-lg)
_borderRadius → var(--border-radius-md)
_boxShadow → var(--shadow-md)
_fontFamily → var(--font-heading)
_fontSize → var(--font-size-2xl)
```

**Never hardcode hex values** like `#1a73e8` in color settings. If a variable doesn't exist yet, create it first in Global Settings → Custom CSS, then use it.

### Variable Scope & Fallbacks

Always provide a fallback for critical variables:
```css
color: var(--color-primary, #0d6efd);
```

For component-level overrides, scope the override to the component:
```css
/* In _cssCustom of a card component */
.card-dark {
  --color-text: #ffffff;
  --color-background: var(--color-primary);
}
```

---

## Modern CSS — Use These Features

### Logical Properties (Use for All Spacing & Borders)

Logical properties adapt to writing direction automatically:
```css
/* Use this */
padding-block: var(--spacing-lg);         /* top + bottom */
padding-inline: var(--spacing-md);        /* left + right */
margin-block-start: var(--spacing-sm);    /* margin-top */
border-inline-start: 3px solid var(--color-primary); /* border-left */

/* Instead of */
padding-top: 24px; padding-bottom: 24px;
```

### Container Queries (For Component-Level Responsiveness)

When a component needs to respond to its container size rather than the viewport:
```css
/* In _cssCustom on the container */
container-type: inline-size;

/* On the child element */
@container (min-width: 400px) {
  .card { flex-direction: row; }
}
```

Use `@supports (container-type: inline-size)` for progressive enhancement:
```css
@supports (container-type: inline-size) {
  .card-grid { container-type: inline-size; }
}
```

### `clamp()` for Fluid Values

Always prefer `clamp()` for font sizes, spacing, and dimensions that should scale fluidly:
```css
font-size: clamp(1rem, 2.5vw, 1.25rem);
padding: clamp(1rem, 3vw, 2rem);
gap: clamp(1rem, 2vw, 1.5rem);
```

### CSS Grid — Modern Patterns

**Auto-responsive grid (no breakpoints needed):**
```css
grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
```
`min(280px, 100%)` prevents overflow on small screens.

**Subgrid for aligned card content:**
```css
.card-grid { display: grid; grid-template-rows: subgrid; }
.card       { display: grid; grid-row: span 3; grid-template-rows: subgrid; }
```

### Modern Color — `oklch()` (Progressive Enhancement)

For vivid, perceptually uniform colors with a fallback:
```css
/* In _cssCustom or Global Settings CSS */
:root {
  --color-primary: #0d6efd;                              /* fallback */
  --color-primary: oklch(55% 0.2 260);                   /* modern */
}
```

### `@layer` for Style Organization

When writing substantial `_cssCustom`, use cascade layers to control specificity:
```css
@layer base, components, utilities;

@layer components {
  .btn { padding: var(--spacing-sm) var(--spacing-lg); }
}
@layer utilities {
  .text-center { text-align: center; }
}
```

---

## Cross-Browser Support

### Check Before Using

Before using any CSS feature in `_cssCustom`, verify browser support:
- **Baseline 2024 and older** — safe to use without fallbacks (95%+ support)
- **Newly available** — use with `@supports` fallback
- **Limited availability** — avoid or use as progressive enhancement only

Reference: [caniuse.com](https://caniuse.com) / [baseline status on MDN](https://developer.mozilla.org)

### Feature Detection with `@supports`

Never assume a feature is available. Always provide a fallback:

```css
/* Grid with flexbox fallback */
.card-grid {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-md);
}
@supports (display: grid) {
  .card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  }
}

/* Container queries with fallback */
@supports not (container-type: inline-size) {
  .component { /* fallback responsive styles */ }
}
```

### Vendor Prefixes — When Still Needed

Most modern CSS does NOT need vendor prefixes. The following still may:
```css
/* Scrollbar styling */
.scrollable::-webkit-scrollbar { width: 6px; }
.scrollable { scrollbar-width: thin; }          /* Firefox */

/* Backdrop filter (still needs -webkit on some browsers) */
.glass {
  -webkit-backdrop-filter: blur(10px);
  backdrop-filter: blur(10px);
}
```

Never add `-webkit-`, `-moz-`, `-ms-` prefixes to: `flex`, `grid`, `transform`, `transition`, `animation`, `border-radius`, `box-shadow`, `opacity` — all are universally supported without prefixes.

### Safe Modern Features (No Fallback Needed)

These are safe to use without `@supports`:
- `display: grid` and `display: flex`
- `gap` in grid and flex contexts
- `clamp()`, `min()`, `max()`
- CSS custom properties (`var()`)
- `aspect-ratio`
- `object-fit`
- Logical properties (`padding-block`, `margin-inline`)
- `position: sticky`
- `font-display`
- `will-change`
- CSS Grid `subgrid` (Baseline 2023)

### Progressive Enhancement Features (Use `@supports`)

- Container queries (`container-type`) — use `@supports`
- `:has()` pseudo-class — Baseline 2023, safe but add fallback for old browsers
- `oklch()` color — use hex fallback first
- `@layer` — safe but complex; test before production use
- `text-wrap: balance/pretty` — add as enhancement only

---

## Global Classes

- Always check `bricks_get_global_classes` before writing inline styles
- Apply via `selectors` array: `"selectors": [".card", ".is-featured"]`
- Never duplicate a style inline if a global class already covers it
- Use BEM-lite naming: `.block`, `.block__element`, `.block--modifier`
- Prefix project-specific classes: `.site-header`, `.bp-card`

---

## Naming Conventions for New Classes

- Kebab-case: `.hero-section`, `.card-grid`, `.cta-button`
- Purpose-based, not appearance-based: `.primary-nav` not `.blue-nav`
- State classes use `is-` prefix: `.is-active`, `.is-loading`, `.is-hidden`
- JS hooks use `js-` prefix (never style these): `.js-toggle`, `.js-slider`

---

## Spacing

- Use spacing tokens: `var(--spacing-xs)` through `var(--spacing-section)`
- Avoid arbitrary values like `13px`, `27px` — they break consistency
- For vertical section spacing: `padding-block: var(--spacing-section)`
- Between flex/grid items: always `gap`, never child margins

---

## `_cssCustom` — Use Sparingly

**Acceptable:**
- Pseudo-elements (`::before`, `::after` with `content`)
- Complex selectors (`:nth-child(2n+1)`, `:not(.active)`, `[data-attr]`)
- `@keyframes` animation definitions
- `@layer` organization blocks
- `@supports` feature detection blocks
- Container query definitions (`container-type`)
- Multi-rule cascade overrides Bricks UI cannot express

**Not acceptable:**
- Simple properties with a Bricks setting equivalent (color, background, padding, margin, font-size, border, etc.)
- Duplicating styles already in a global class
- `@media` queries for responsive styles — use the Bricks breakpoint suffix instead

---

## Responsive CSS — Bricks Suffix, Not `@media`

Use the Bricks responsive suffix on settings keys:
```
_padding:mobile_portrait → { padding: var(--spacing-md) }
```
**Not:**
```css
/* _cssCustom — WRONG for basic responsive settings */
@media (max-width: 480px) { padding: 16px; }
```

The exception: use `@media` in `_cssCustom` only for breakpoints that don't align with Bricks's built-in breakpoints, or for complex multi-rule blocks.

---

## Pre-Write Checklist

- [ ] Check `bricks_get_session_context` for existing CSS variables and global classes
- [ ] All colors reference `var(--color-*)` tokens, no raw hex values
- [ ] All spacing references `var(--spacing-*)` tokens
- [ ] Font sizes use `clamp()` through `var(--font-size-*)` tokens
- [ ] Modern CSS features wrapped in `@supports` fallbacks where needed
- [ ] `_cssCustom` used only for things with no Bricks setting equivalent
- [ ] Cross-browser support verified for any new CSS features used
