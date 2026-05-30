---
name: bricks-elements
title: Bricks Elements — Structure & Best Practices
description: Element structure (8 fields), flat array rules, ID generation, parent-child relationships, responsive suffix syntax, and _cssCustom guidelines.
when_to_use: Any new page build, element creation, or element array editing.
---

## Element Structure

Every Bricks element is an object with exactly these 8 fields:

```json
{
  "id": "abc123",
  "name": "text",
  "parent": "xyz789",
  "children": [],
  "settings": {},
  "selectors": [],
  "label": "",
  "themeStyles": []
}
```

## Rules

**ID**
- 6-character alphanumeric string (e.g. `"abc123"`)
- Must be unique across the entire page — never reuse an ID
- Generate randomly; do not use sequential numbers or guessable patterns

**`parent`**
- ID of the direct parent element, or `0` (integer) for root-level elements
- This is how hierarchy is expressed — elements are stored as a flat array, NOT nested

**`children`**
- Array of child element IDs (strings), in display order
- Must exactly match the child elements that have this element's ID as their `parent`
- Empty array `[]` if the element has no children

**Flat array — critical rule**
- The full page is one flat array of element objects
- Do NOT nest element objects inside `children` — `children` holds ID strings only
- Parent-child relationships are expressed via `parent` + `children` fields

**`name`**
- Bricks element type: `"section"`, `"container"`, `"div"`, `"block"`, `"text"`, `"heading"`, `"image"`, `"button"`, `"icon"`, `"video"`, `"form"`, `"nav-menu"`, `"code"`, etc.

**`selectors`**
- Array of CSS class strings applied to this element (e.g. `[".my-card", ".is-featured"]`)
- Use global class IDs here (from `bricks_get_global_classes`), not arbitrary class names

**`label`**
- Human-readable label shown in the Bricks panel — optional but recommended for complex layouts
- Example: `"Hero Section"`, `"Card Grid"`, `"CTA Block"`

**`themeStyles`**
- Array of theme style IDs applied to this element — leave as `[]` unless explicitly applying a theme style

## Settings — Responsive Syntax

Responsive variants use a suffix appended to the setting key:

```
_background                    → default (all breakpoints)
_background:tablet_portrait    → tablet portrait only
_background:mobile_landscape   → mobile landscape only
_background:mobile_portrait    → mobile portrait only
```

Pseudo-class variants:
```
_color:hover                         → hover state, all breakpoints
_background:tablet_portrait:hover    → hover on tablet portrait
```

Breakpoint order (desktop-first cascade): `default → tablet_landscape → tablet_portrait → mobile_landscape → mobile_portrait`

## `_cssCustom` — Use Sparingly

Only use `_cssCustom` for:
- Pseudo-elements (`::before`, `::after`)
- Complex CSS selectors (`:nth-child`, `:not()`, attribute selectors)
- `@keyframes` animations
- Multi-rule blocks that can't be expressed via individual settings

Do NOT use `_cssCustom` for properties that have a dedicated Bricks setting (background, color, padding, margin, font, border, etc.).

## Workflow

1. Fetch current design system: `bricks_get_session_context` (palette, global classes, CSS variables)
2. Build element array following the rules above
3. Validate before writing: `bricks_validate_payload` — fix all errors before proceeding
4. Write: `bricks_write_page_content` / `bricks_write_template_content`
