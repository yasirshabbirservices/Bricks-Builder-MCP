---
name: mobile-first
title: Mobile-First Design & Layout in Bricks Builder
description: Mobile-first design strategy, Bricks breakpoint system (sourced from Bricks 2.3.6), touch targets, fluid layout techniques, and how to build pages that look right on mobile before scaling up to desktop.
when_to_use: Every page build and layout task — mobile-first is not optional, it is the default design approach.
---

## The Mobile-First Mindset

Design for the smallest screen first, then progressively enhance for larger screens. This approach:
- Forces you to prioritize essential content and actions
- Results in faster baseline performance (less CSS to override)
- Ensures the mobile experience is intentional, not an afterthought
- Aligns with how Google indexes pages (mobile-first indexing)

**The rule:** Before writing a single desktop style, ask: "Does this look correct and usable on a 390px-wide screen?"

---

## Bricks Breakpoint System

> Source: `Bricks::Breakpoints::get_default_breakpoints()` (Bricks 2.3.6)

Bricks uses a **desktop-first CSS cascade** by default. The default (no suffix) setting applies everywhere, and smaller breakpoints are generated as `max-width` media queries.

| Bricks suffix | Media query generated | Default breakpoint width |
|---|---|---|
| *(none — default)* | No query — applies to all screen sizes | Base / Desktop |
| `:tablet_portrait` | `max-width: 991px` | 991px |
| `:mobile_landscape` | `max-width: 767px` | 767px |
| `:mobile_portrait` | `max-width: 478px` | 478px |

> **No `tablet_landscape` breakpoint exists in default Bricks.** Only three non-base breakpoints: tablet_portrait, mobile_landscape, mobile_portrait.

> Breakpoint widths are configurable in Bricks → Settings → Builder → Breakpoints. Default values are as shown above (991, 767, 478px).

### Implementing Mobile-First in Bricks

Although Bricks generates desktop-first CSS, you can implement mobile-first thinking by:

**Strategy:** Set default settings to values that work on mobile (single column, smaller sizes, stacked layout), then add overrides for larger screens using NO breakpoint suffix or `:tablet_portrait`.

**Practical build order for most layouts:**

1. **Default settings** → Values that look good on mobile (single column, smaller sizes)
2. **`:tablet_portrait`** overrides → Transition to 2-column, medium sizes (applies at ≤ 991px so you override up from desktop default)

Wait — this needs clarification. Since Bricks is desktop-first:
- **Default (no suffix)** = applies to ALL screens including mobile (mobile inherits this)
- **`:tablet_portrait`** = applies at 991px and smaller
- **`:mobile_portrait`** = applies at 478px and smaller

**Effective mobile-first approach in Bricks:**
- Set the DEFAULT to what desktop should look like
- Add `:tablet_portrait` and `:mobile_portrait` overrides to **reduce** complexity for smaller screens

OR (simpler approach for many cases):
- Set DEFAULT to the mobile baseline (single column)
- Desktop will also be single column unless you add a default-level grid/flex override without a breakpoint suffix

---

## Mobile-First Layout Rules

### Single Column by Default

Start every multi-column layout as a single column. The `auto-fill` + `minmax` pattern works without breakpoints:

```json
{
  "settings": {
    "_display": "grid",
    "_gridTemplateColumns": "repeat(auto-fill, minmax(min(280px, 100%), 1fr))",
    "_gap": "var(--spacing-md)"
  }
}
```

`min(280px, 100%)` prevents overflow when the container is narrower than 280px.

For explicit breakpoint-based columns:
```json
{
  "settings": {
    "_display": "grid",
    "_gridTemplateColumns": "1fr",
    "_gap": "var(--spacing-md)",
    "_gridTemplateColumns:tablet_portrait": "repeat(2, 1fr)"
  }
}
```

(The no-suffix `_gridTemplateColumns` = desktop. The `:tablet_portrait` version kicks in at ≤ 991px.)

### Stack Navigation on Mobile

- Mobile (≤ 478px or ≤ 767px): off-canvas drawer via `offcanvas` element
- Desktop: horizontal `nav-nested` or `nav-menu` element
- Visibility control:
  ```
  nav-nested: _display:mobile_landscape → none   (hide desktop nav on mobile)
  offcanvas:  _display → none            (hide by default)
              _display:mobile_landscape → block  (show on mobile)
  ```

### Fluid Widths Over Fixed Pixel Widths

```
✅ width: 100%              — fills container
✅ max-width: 65ch          — responsive constraint for text
✅ width: min(600px, 100%)  — capped but never overflows
❌ width: 600px             — overflows on small screens
```

---

## Touch Targets

Every interactive element on mobile must meet minimum touch target sizes (WCAG 2.5.5):

| Element | Minimum size | How to achieve in Bricks |
|---|---|---|
| Buttons | 44×44px | `_padding:mobile_portrait → 12px 20px` |
| Navigation links | 44×44px | padding on nav items |
| Form inputs | 44px height | `_minHeight → 44px` |
| Icon-only buttons | 44×44px | add `aria-label` + padding |
| Checkboxes/radios | 44×44px touch area | expand with padding |

---

## Mobile Typography

### Font Sizes on Small Screens

Use `clamp()` variables — they scale fluidly from mobile upward:
```css
--font-size-base: clamp(1rem, 2.5vw, 1.125rem);
--font-size-2xl:  clamp(1.75rem, 5vw, 2.5rem);
--font-size-4xl:  clamp(2rem, 8vw, 5rem);
```

For headings that need specific mobile sizes:
```
_typography.font-size:mobile_portrait → var(--font-size-2xl)
```

### Line Length on Mobile

- Remove `max-width: 65ch` constraints on mobile — let text fill the container
- Keep `max-width` only for desktop where lines would be too long
```
_maxWidth:mobile_portrait → 100%
_maxWidth → 65ch
```

---

## Mobile-First Spacing

Section padding on mobile should be noticeably smaller than desktop:
```
_padding:mobile_portrait → var(--spacing-xl) var(--spacing-md)
_padding → var(--spacing-section) var(--spacing-lg)
```

Use `paddingInline` to prevent content from touching viewport edges:
```
_paddingInline:mobile_portrait → var(--spacing-md)
_paddingInline → 0
```

---

## Fluid Images & Media

All images must be responsive by default:
```json
{
  "settings": {
    "_width": "100%",
    "_height": "auto",
    "_maxWidth": "100%",
    "_objectFit": "cover"
  }
}
```

For hero images:
- Set explicit `width` + `height` attributes for CLS prevention
- Use `aspect-ratio` to maintain proportion
- Do **NOT** lazy-load above-fold images — set `fetchpriority: high` for LCP

---

## Viewport Units — Use `svh` Not `vh`

`100vh` on mobile includes browser chrome (address bar) causing content to be cut off:

```
✅ min-height: 100svh   (small viewport height — accounts for browser chrome)
✅ min-height: 100dvh   (dynamic viewport height — updates as chrome shows/hides)
❌ min-height: 100vh    (can cause content overflow on mobile)
```

In Bricks, set `_minHeight: 100svh` for full-height hero sections.

---

## Navigation Patterns for Mobile

### Off-Canvas Menu (Recommended)

Use the Bricks `offcanvas` element for mobile navigation:
```
Desktop: show nav-nested (horizontal)
Mobile:  show offcanvas (hamburger → slide-in drawer)
```

Visibility control (using `:mobile_landscape` for phones in landscape + portrait):
```
nav-nested:  _display:mobile_landscape → none   (hide on mobile)
offcanvas:   _display → none                     (hide by default for desktop)
             _display:mobile_landscape → flex    (show on mobile)
```

The `offcanvas` element is **nestable** — put any content inside it including a `nav-nested` element.

### Bottom Navigation (Mobile-Specific)

For app-like experiences:
```json
{
  "name": "div",
  "settings": {
    "_position": "fixed",
    "_bottom": "0",
    "_left": "0",
    "_right": "0",
    "_display:tablet_portrait": "none",
    "_zIndex": "100",
    "_background": "var(--color-background)",
    "_borderBlockStart": "1px solid var(--color-border)"
  }
}
```

---

## Forms on Mobile

- Stack all form fields vertically (single column) — never side-by-side on mobile
- Minimum `font-size: 16px` on inputs to prevent iOS auto-zoom
- Set `inputmode` on inputs where relevant:
  - `email` → `inputmode="email"`
  - `tel` → `inputmode="tel"`
  - `search` → `inputmode="search"` + `enterkeyhint="search"`
- Use `autocomplete` attributes: `email`, `tel`, `given-name`, `family-name`, `postal-code`

---

## Performance on Mobile Networks

Mobile devices often run on slower networks. Apply strictly:

1. **Above-fold images**: `fetchpriority="high"`, NOT lazy-loaded, WebP format
2. **Below-fold images**: `_loading: lazy`
3. **Fonts**: preconnect + `font-display: swap`, load only needed weights
4. **Animations**: always respect `prefers-reduced-motion`
5. **Scripts**: defer non-critical JS

```css
/* Always add this to Global CSS or _cssCustom on root element */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```

---

## Mobile-First Build Order

Follow this sequence when building any page or section:

1. **Plan the mobile layout** — widths, stacking, font sizes, spacing for 390px viewport
2. **Set DEFAULT settings** to match the mobile/base layout
3. **Test at mobile_portrait** (478px) in Bricks preview — fix any issues
4. **Add desktop overrides** — for the default (no suffix) or `:tablet_portrait` level
5. **Check touch targets** — all interactive elements ≥ 44×44px
6. **Verify no horizontal scroll** at any breakpoint — content must not overflow viewport width
7. **Add `:mobile_portrait` refinements** — if mobile_landscape and portrait need different treatment

---

## Mobile-First Checklist

- [ ] Default layout works on mobile (single-column, stacked)
- [ ] All images are `width: 100%` with `height: auto`
- [ ] Hero sections use `min-height: 100svh` not `100vh`
- [ ] Touch targets are ≥ 44×44px for all interactive elements
- [ ] Font sizes use `clamp()` or breakpoint overrides — no fixed px on body text
- [ ] Section padding reduces on mobile (`--spacing-section` → `--spacing-xl`)
- [ ] No horizontal overflow at any breakpoint
- [ ] Navigation has a mobile-friendly pattern (`offcanvas` or hamburger)
- [ ] Form inputs have `font-size ≥ 16px` to prevent iOS auto-zoom
- [ ] `prefers-reduced-motion` respected for all animations
- [ ] Tested at 390px viewport width before desktop review
- [ ] No `tablet_landscape` breakpoint used (does not exist in default Bricks)
