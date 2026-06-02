---
name: mobile-first
title: Mobile-First Design & Layout in Bricks Builder
description: Mobile-first design strategy, Bricks breakpoint cascade, touch targets, fluid layout techniques, and how to build pages that look right on mobile before scaling up to desktop.
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

Bricks uses a **desktop-first CSS cascade**. The default settings apply to all screen sizes, and each breakpoint is a `max-width` media query that overrides the default for that breakpoint and smaller.

| Bricks suffix | Media query generated | Default breakpoint |
|---|---|---|
| (none — default) | No query — applies everywhere | All screens |
| `:tablet_landscape` | `max-width: 1279px` | 1280px |
| `:tablet_portrait` | `max-width: 1023px` | 1024px |
| `:mobile_landscape` | `max-width: 767px` | 768px |
| `:mobile_portrait` | `max-width: 479px` | 480px |

> Breakpoint values are configurable in Bricks → Settings → Builder → Breakpoints.

### Implementing Mobile-First in Bricks

**Strategy:** Set default settings to values that work for all screens (especially mobile), then add overrides for larger screens using breakpoints.

**Practical approach for most layouts:**

1. **Default** → Base styles that look good on mobile (single column, smaller sizes, stacked layout)
2. **`:tablet_portrait`** → Transition to 2-column, slightly larger sizes
3. **No suffix (default)** → Can serve as the largest size if the cascade works in your favour

For explicit mobile-specific overrides, use `:mobile_portrait` or `:mobile_landscape`.

---

## Mobile-First Layout Rules

### Single Column by Default

Start every multi-column layout as a single column. Add columns at larger breakpoints:

```json
{
  "settings": {
    "_display": "grid",
    "_gridTemplateColumns": "1fr",
    "_gap": "var(--spacing-md)",
    "_gridTemplateColumns:tablet_portrait": "repeat(2, 1fr)",
    "_gridTemplateColumns": "repeat(3, 1fr)"
  }
}
```

The `auto-fill` + `minmax` pattern handles this automatically without breakpoints:
```
grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr))
```
`min(280px, 100%)` prevents overflow when the container is narrower than 280px.

### Stack Navigation on Mobile

- Mobile: hamburger menu / off-canvas (`off-canvas` element)
- Tablet+: horizontal `nav-menu` element
- Use Bricks conditions or display settings to show/hide per breakpoint:
  ```
  _display:mobile_portrait → none  (hide desktop nav)
  ```

### Fluid Widths Over Fixed Pixel Widths

```
✅ width: 100%          — fills container
✅ max-width: 65ch      — responsive constraint for text
✅ width: min(600px, 100%) — capped but never overflows
❌ width: 600px         — overflows on small screens
```

---

## Touch Targets

Every interactive element on mobile must meet minimum touch target sizes:

| Element | Minimum size | Notes |
|---|---|---|
| Buttons | 44×44px | WCAG 2.5.5 Success Criterion |
| Navigation links | 44×44px | Use padding, not just font size |
| Form inputs | 44px height | `min-height: 44px` |
| Icon-only buttons | 44×44px | Add visible label or `aria-label` |
| Checkboxes/radios | 44×44px touch area | Expand clickable area with padding |

In Bricks, achieve this with padding settings:
```
_padding:mobile_portrait → 12px 20px (makes button 44px+ tall)
```

---

## Mobile Typography

### Font Sizes on Small Screens

Use `clamp()` to scale fonts fluidly — they'll be smaller on mobile automatically:
```css
--font-size-base: clamp(1rem, 2.5vw, 1.125rem);
--font-size-2xl:  clamp(1.75rem, 5vw, 2.5rem);
--font-size-4xl:  clamp(2rem, 8vw, 5rem);
```

For headings that need specific mobile sizes, use the breakpoint suffix:
```
_typography.font-size:mobile_portrait → var(--font-size-2xl)
```

### Line Length on Mobile

- Remove `max-width: 65ch` constraints on mobile — let text fill the container
- Keep `max-width` only for desktop/tablet where line length would become too long
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

Use `padding-inline` to prevent content from touching viewport edges on small screens:
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
- Use `aspect-ratio` to maintain proportion on all screens
- Set explicit `width` and `height` attributes for CLS prevention
- Do NOT lazy-load — set `fetchpriority: high` for LCP images

---

## Viewport Units — Use `svh` Not `vh`

`100vh` on mobile includes browser chrome (address bar) causing content to be hidden:

```
✅ min-height: 100svh   (small viewport height — accounts for browser chrome)
✅ min-height: 100dvh   (dynamic viewport height — updates as chrome shows/hides)
❌ min-height: 100vh    (can cause content overflow on mobile)
```

In Bricks, set `_minHeight: 100svh` for full-height hero sections.

---

## Navigation Patterns for Mobile

### Off-Canvas Menu (Recommended)

Use the Bricks `off-canvas` element for mobile navigation:
```
Desktop: show nav-menu (horizontal)
Mobile:  show off-canvas (hamburger → slide-in drawer)
```

Visibility control:
```
nav-menu settings: _display:mobile_portrait → none
off-canvas settings: _display → none, _display:tablet_portrait → block
```

### Bottom Navigation (Mobile-Specific)

For app-like experiences, a fixed bottom nav is more accessible than a top hamburger:
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
- `input[type="email"]` → set `inputmode="email"` for mobile keyboard
- `input[type="tel"]` → set `inputmode="tel"` for numeric keyboard
- `input[type="search"]` → set `inputmode="search"` + `enterkeyhint="search"`
- Minimum `font-size: 16px` on inputs to prevent iOS auto-zoom
- Use `autocomplete` attributes: `email`, `tel`, `given-name`, `family-name`, `postal-code`

---

## Performance on Mobile Networks

Mobile devices often run on slower networks. Apply these rules strictly:

1. **Above-fold images**: `fetchpriority="high"`, not lazy-loaded, WebP format
2. **Below-fold images**: `_loading: lazy`
3. **Fonts**: preconnect + `font-display: swap`, load only needed weights
4. **Animations**: respect `prefers-reduced-motion`
5. **Scripts**: defer non-critical JS
6. **CSS**: avoid large `_cssCustom` blocks — each generates a `<style>` tag

```css
/* Always respect reduced motion preference */
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

1. **Define the mobile layout** — widths, stacking, font sizes, spacing for 390px viewport
2. **Set default settings** to match the mobile layout (or use `:mobile_portrait` for smallest screens)
3. **Add `:tablet_portrait` overrides** — transition to 2-column, medium sizing
4. **Add desktop defaults** — full multi-column, maximum sizes
5. **Test in Bricks preview** at `mobile_portrait` breakpoint first, then scale up
6. **Check touch targets** — all interactive elements ≥ 44×44px
7. **Verify no horizontal scroll** at any breakpoint — content must not overflow viewport width

---

## Mobile-First Checklist

- [ ] Default layout is single-column (stacked) for mobile
- [ ] All images are 100% width with `height: auto`
- [ ] Hero uses `100svh`, not `100vh`
- [ ] Touch targets are ≥ 44×44px for all interactive elements
- [ ] Font sizes use `clamp()` or breakpoint overrides — no fixed px on small text
- [ ] Section padding reduces on mobile (`spacing-section` → `spacing-xl`)
- [ ] No horizontal overflow at any breakpoint
- [ ] Navigation has a mobile-friendly pattern (off-canvas or hamburger)
- [ ] Form inputs have `font-size ≥ 16px` to prevent iOS auto-zoom
- [ ] `prefers-reduced-motion` respected for all animations
- [ ] Tested on actual mobile viewport (390px width) before desktop review
