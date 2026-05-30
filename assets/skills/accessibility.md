---
name: accessibility
title: Accessibility — WCAG 2.1 AA for Bricks Pages
description: Semantic HTML5 elements, ARIA usage, keyboard navigation, color contrast, image alt text, form labels, and focus management rules.
when_to_use: Any form, modal, navigation, image, interactive element, or page that humans will use.
---

## Core Principle

Every Bricks page must be usable without a mouse, usable with a screen reader, and meet WCAG 2.1 Level AA. These are not optional enhancements — they are baseline production requirements.

## Semantic HTML

Use the Bricks `tag` setting to output correct HTML5 elements:

| Purpose | Bricks `tag` setting |
|---|---|
| Page wrapper / main content | `main` |
| Site header | `header` |
| Site footer | `footer` |
| Navigation | `nav` |
| Blog post / card | `article` |
| Thematic section | `section` |
| Sidebar / complementary | `aside` |

- Use `div` only when no semantic element applies
- `<section>` must have an accessible name (via `aria-labelledby` pointing to a heading, or `aria-label`)
- Never use headings for visual styling — use them for document structure only

## Heading Hierarchy

- One `<h1>` per page — typically the page title in the hero
- Never skip levels: h1 → h2 → h3 (not h1 → h3)
- Headings are document structure, not font-size controls — use CSS for size adjustments
- All headings must describe the section content that follows them

## Images

- Every `<img>` needs `alt` text in the Bricks `_alt` setting
- Descriptive alt: describe what the image shows and its purpose (e.g. `"Team photo of three designers collaborating at a whiteboard"`)
- Decorative images (icons, dividers, backgrounds): set `_alt` to `""` and add custom attribute `aria-hidden="true"`
- Never use the filename or "image" as alt text

## Color Contrast

- Normal text (< 18pt / 14pt bold): minimum **4.5:1** contrast ratio against its background
- Large text (≥ 18pt / 14pt bold): minimum **3:1**
- UI components (borders, focus rings): minimum **3:1** against adjacent colors
- Check Business Profile brand colors against background colors before using them for text

## Keyboard Navigation

- All interactive elements (links, buttons, inputs, custom components) must be reachable via `Tab`
- Never set `tabindex="-1"` on a normally focusable element unless managing focus programmatically
- Tab order must follow the visual reading order (top-to-bottom, left-to-right)
- Modals/drawers: trap focus inside while open, restore focus to trigger element on close

## Focus Visibility

- Never remove focus outlines without a visible replacement: `outline: none` alone fails WCAG 2.4.7
- Provide a visible focus indicator with at least 3:1 contrast against the surrounding color
- In `_cssCustom`, a safe pattern: `:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px; }`

## Forms

- Every `<input>`, `<select>`, `<textarea>` must have an associated `<label>`
- Use `for`/`id` pairing, or `aria-label` if the label is visually hidden
- Error messages must be associated with the field via `aria-describedby`
- Required fields: use `required` attribute AND communicate visually (not color alone)
- Use `autocomplete` attributes on common fields: `name`, `email`, `tel`, `street-address`, `postal-code`

## ARIA — Use Minimally

- Use ARIA only when native HTML semantics are insufficient
- ARIA does not add behavior — it only communicates semantics to screen readers
- Never use `role="button"` on a `<div>` — use a `<button>` element instead
- Common valid uses: `aria-expanded` on accordions, `aria-current="page"` on nav items, `aria-live` for dynamic updates

## Links and Buttons

- Link text must be descriptive in context: `"View pricing plans"` not `"Click here"`
- Buttons must describe the action: `"Send message"` not `"Submit"`
- Links that open in a new tab: add `(opens in new tab)` as visually hidden text or `aria-label`
- Never use a link as a button (no `href="#"` with a click handler) — use `<button>` instead

## Quick Checklist Before Writing

- [ ] Correct semantic element for each section (`main`, `nav`, `header`, `footer`, `article`, `section`)
- [ ] One `<h1>`, logical heading hierarchy, no skipped levels
- [ ] All images have `_alt` text (empty string for decorative)
- [ ] All form inputs have labels
- [ ] Color contrast checked for all text/background combinations
- [ ] Focus styles visible for all interactive elements
- [ ] Interactive elements keyboard-reachable (no `tabindex="-1"`)
