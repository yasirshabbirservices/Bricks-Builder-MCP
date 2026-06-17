# Accessibility Reference — WCAG 2.2 for WordPress + Bricks Builder

## WCAG 2.2 AA Checklist (Practical)

This is not the full WCAG spec — it's the subset that matters most for a Bricks Builder ecommerce site. Organized by what you're building, not by abstract principles.

## Perceivable

### Images & Media
- Every `<img>` needs `alt` text. In Bricks: use the element's alt field, or `_attributes` → `alt`
- Decorative images (backgrounds, orbs, dividers): `alt=""` or use CSS `background-image` instead
- Product images: alt text should describe the product — "Black TPU phone case for Samsung Galaxy S24" not "product image"
- Icons used as the only content in a button/link need `aria-label` on the parent — icon-only WhatsApp buttons must say what they do
- Video content needs captions or a text transcript
- Don't convey information through color alone — a "Sale" badge needs text, not just a green dot

### Text & Contrast
- **Normal text** (under 18px or under 14px bold): minimum 4.5:1 contrast ratio
- **Large text** (18px+ or 14px+ bold): minimum 3:1 contrast ratio
- **UI components and graphics**: minimum 3:1 against adjacent colors

#### Kanera Hub contrast checks (verify these):
| Foreground | Background | Ratio | Pass AA? |
|-----------|-----------|-------|----------|
| `--text` (#F0F0F0) | `--bg` (#111214) | ~15.5:1 | Yes |
| `--lead` (#E4E6E9) | `--bg` (#111214) | ~13.8:1 | Yes |
| `--muted` (#888E99) | `--bg` (#111214) | ~5.3:1 | Yes (normal) |
| `--muted2` (#BDC3CC) | `--bg` (#111214) | ~9.5:1 | Yes |
| `--dim` (#5b6068) | `--bg` (#111214) | ~3.2:1 | Fails normal text — use for decorative/non-essential only |
| `--orange` (#F26522) | `--bg` (#111214) | ~4.6:1 | Borderline — check |
| `--orange` (#F26522) | `--bg2` (#17191C) | ~4.2:1 | Fails for normal text |
| `#fff` on `--orange` (#F26522) | — | ~3.1:1 | Fails for normal text — use bold/large |

**Orange on dark is the critical risk.** Small orange text on `--bg2` may fail. Options:
- Use orange only for large/bold text (18px+ or 14px+ bold meets 3:1)
- Use `--orange2` (#FF7A35) which has slightly better contrast
- Use orange as an accent with sufficient surrounding text context
- For critical information, pair orange with white text

### Text Resizing
- Text must remain readable when zoomed to 200% — use `clamp()` and relative units (rem/em), never fixed px
- No horizontal scrolling at 320px viewport width (WCAG 1.4.10 Reflow)
- Line height at least 1.5x font size for body text (the design system uses 1.65–1.72, which passes)

## Operable

### Keyboard Navigation
- Every interactive element must be reachable via Tab key
- Focus order must follow visual/logical order — don't use `tabindex` > 0
- Visible focus indicators on ALL focusable elements — the default browser outline is acceptable, but a custom `:focus-visible` style using `var(--orange)` is better
- Skip-to-content link as the first focusable element on every page — hidden until focused
- Dropdown/mobile menus must be keyboard-operable (Escape to close, arrow keys to navigate)
- Modal/popup interactions: trap focus inside while open, return focus to trigger on close

```css
/* Focus visible style pattern */
:focus-visible {
  outline: 2px solid var(--orange);
  outline-offset: 2px;
}

/* Skip link pattern */
.skip-link {
  position: absolute;
  top: -100%;
  left: 0;
  padding: 1rem;
  background: var(--orange);
  color: #fff;
  z-index: 9999;
}
.skip-link:focus {
  top: 0;
}
```

### Touch Targets
- Minimum 44x44px touch target size for all interactive elements on mobile
- At least 8px spacing between adjacent touch targets
- This applies to: buttons, links, filter chips, card tap areas, WhatsApp links, close buttons, nav items
- Bricks tip: set min-height and min-width on button/link elements, not just padding

### Motion & Animation
- All animations must be wrapped in `prefers-reduced-motion` media query
- Essential animations (loading spinners) can remain, but decorative ones (scroll reveals, orb breathing, typewriter, auto-scroll) must stop

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```

### Timing
- Auto-scrolling content (tickers, carousels) must have a pause mechanism
- Session timeouts must warn and allow extension (relevant for SureCart checkout)
- No content that flashes more than 3 times per second

## Understandable

### Language
- Set `lang="en"` on `<html>` element (WordPress handles this, but verify)
- If Urdu text appears, wrap in `<span lang="ur" dir="rtl">`

### Forms
- Every input needs a visible `<label>` or `aria-label`
- Error messages must be specific: "Phone number must be 11 digits" not "Invalid input"
- Required fields marked with both visual indicator AND `aria-required="true"`
- Error state: use color AND icon/text — not color alone (red border is not enough)
- Group related fields with `<fieldset>` and `<legend>`
- SureCart checkout forms: verify the plugin outputs accessible markup — if not, add ARIA via `_attributes` or filters

### Navigation
- Consistent navigation across all pages (same order, same labels)
- Current page indicated in navigation (via `aria-current="page"`)
- Breadcrumbs use `<nav aria-label="Breadcrumb">` with `<ol>` markup

## Robust

### ARIA Usage
- Use semantic HTML first — ARIA is a supplement, not a replacement
- `role="navigation"`, `role="main"`, `role="banner"`, `role="contentinfo"` — only needed if you can't use native `<nav>`, `<main>`, `<header>`, `<footer>` elements
- Landmark regions: every page should have `<main>`, plus `<nav>` for navigation areas
- `aria-label` for multiple navigations: `<nav aria-label="Main">`, `<nav aria-label="Footer">`
- `aria-expanded` on toggles (mobile menu, accordions, dropdowns)
- `aria-hidden="true"` on decorative elements (noise overlay, orbs, decorative icons)
- Live regions for dynamic content: `aria-live="polite"` for cart updates, `aria-live="assertive"` for errors

### Bricks-Specific Implementation

**Setting semantic tags:**
- Section element → set tag to `<section>` with `aria-labelledby` pointing to its heading
- Container for nav → set tag to `<nav>` with `aria-label`
- Container for main content → set tag to `<main>`
- Container for footer → set tag to `<footer>`

**Custom attributes in Bricks:**
Use the `_attributes` repeater on any element:
- Key: `aria-label`, Value: `Main navigation`
- Key: `role`, Value: `img` (for decorative SVG containers)
- Key: `aria-hidden`, Value: `true` (for decorative elements)

**Global class approach:**
Create global classes for common accessibility patterns:
- `.sr-only` — visually hidden but screen-reader accessible
- `.skip-link` — skip to content link

```css
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
```

## Testing

- **Keyboard test**: Unplug mouse, navigate entire page with Tab/Shift+Tab/Enter/Escape/Arrow keys
- **Screen reader**: Test with NVDA (Windows, free) or VoiceOver (Mac, built-in)
- **Lighthouse accessibility audit**: Aim for 90+ score, but remember it only catches ~30% of issues
- **axe DevTools** browser extension: catches more issues than Lighthouse
- **Color contrast**: Use browser DevTools color picker or WebAIM Contrast Checker
- **Zoom test**: Ctrl/Cmd + to 200%, verify no content is lost or overlapping
- **Reduced motion**: Enable in OS settings, verify animations stop
