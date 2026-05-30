---
name: css-best-practices
title: CSS Best Practices for Bricks Builder
description: Style priority order, global classes vs inline settings, CSS variable usage, naming conventions, and when _cssCustom is acceptable.
when_to_use: Any styling work — applying classes, setting colors, spacing, or writing custom CSS.
---

## Style Priority Order

Apply styles in this order — use the highest available option first:

1. **Global classes** (`_cssClasses`) — reusable, already defined on the site
2. **CSS variables** (`var(--token-name)` in color/size settings) — from the site's design system
3. **Theme styles** (`themeStyles` array) — predefined theme style IDs
4. **Palette colors** — colors from `bricks_get_color_palette`
5. **Inline settings** — only when none of the above apply
6. **`_cssCustom`** — absolute last resort (see rules below)

## Rules

**Global classes**
- Always check `bricks_get_global_classes` before writing inline styles
- Apply via `selectors` array on the element: `"selectors": [".card", ".is-featured"]`
- Never duplicate a style inline if a global class already provides it

**Colors**
- Never hardcode hex values (e.g. `"#1a73e8"`) in `_color`, `_background`, `_borderColor`, etc.
- Use CSS variables: `"var(--color-primary)"`, `"var(--color-text)"` — check session context for available tokens
- If no matching variable exists, use a palette color ID, not a raw hex

**Spacing**
- Use spacing tokens: `"var(--spacing-xs)"`, `"var(--spacing-sm)"`, `"var(--spacing-md)"`, `"var(--spacing-lg)"`, `"var(--spacing-xl)"`
- Avoid mixing arbitrary `px` values (e.g. `"13px"`, `"27px"`) — they break design consistency
- For section-level vertical spacing, prefer `padding-block: var(--spacing-section)`

**Naming conventions for new classes**
- Use kebab-case: `.hero-section`, `.card-grid`, `.cta-button`
- BEM-lite pattern: `.block`, `.block__element`, `.block--modifier`
- Be descriptive and purpose-based, not appearance-based: `.primary-nav` not `.blue-nav`
- Prefix project-specific classes with a short namespace to avoid conflicts: `.site-`, `.bp-`

**`_cssCustom` — use sparingly**

Acceptable:
- Pseudo-elements: `::before`, `::after` with `content` property
- Complex selectors: `:nth-child(2n+1)`, `:not(.active)`, `[data-attr]`
- `@keyframes` animation definitions
- Cascade overrides that Bricks UI cannot express

Not acceptable:
- Simple properties with a Bricks setting equivalent (color, background, padding, margin, font-size, border, etc.)
- Duplicating styles already in a global class
- Vendor-prefix hacks for properties Bricks already handles

**Responsive CSS**
- Use the Bricks responsive suffix on settings keys, not `@media` queries in `_cssCustom`
- `_padding:tablet_portrait` is correct; `@media (max-width: 768px) { padding: ... }` in `_cssCustom` is not

## Before Writing Any Styles

1. Call `bricks_get_session_context` — check `color_palette`, `css_variables`, `global_classes`
2. Match brand colors from Business Profile to CSS variable names
3. Use the design system's existing spacing/typography scale
4. Only introduce new values when the design system has a genuine gap
