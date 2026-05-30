---
name: bricks-dynamic-data
title: Bricks Dynamic Data — ACF, JetEngine & Query Loops
description: Dynamic tag syntax for ACF and JetEngine, query loop configuration, Bricks conditions, and how to check the active framework before writing.
when_to_use: Any page using ACF fields, JetEngine meta, custom post types, query loops, or dynamic content conditions.
---

## Step 0 — Check Active Framework First

Before writing any dynamic tags, call `bricks_get_session_context` and check `framework.detected`:
- `CoreFramework` — uses CoreFramework CSS variables, no ACF/JetEngine special handling
- `ACF` / `ACF Pro` — use ACF dynamic tags (see below)
- `JetEngine` — use JetEngine dynamic tags (see below)
- `None` — use native WordPress dynamic tags only

If both ACF and JetEngine are active, prefer ACF for simple field output and JetEngine for relational/repeater/meta box fields.

## ACF Dynamic Tags

**Text, number, textarea, select fields:**
```
{acf_field_name}
```
Replace `field_name` with the ACF field name (not label).

**Image fields (returning image array):**
```
URL:   {acf_image_url:field_name}
Alt:   {acf_image_alt:field_name}
Title: {acf_image_title:field_name}
```

**Link fields:**
```
URL:  {acf_link_url:field_name}
Text: {acf_link_title:field_name}
```

**Relationship / Post Object (returns post ID — wrap in a query loop):**
Use a query loop with `source: ACF Relationship` and the field name.

**Repeater fields:**
Use a query loop with `source: ACF Repeater` and the repeater field name. Inside the loop, use `{acf_sub_field_name}` syntax.

**True/False (boolean):**
Use Bricks conditions to show/hide elements based on `{acf_field_name}` returning `1` (true) or `0` (false).

## JetEngine Dynamic Tags

**Meta fields:**
```
{jet_engine:field_name}
```

**Calculated fields / custom callbacks:**
```
{jet_engine_callback:function_name}
```

**Inside a JetEngine Listing Grid / query loop:**
```
{jet_engine_listing:field_name}
```

**Relation fields (connected posts):**
Use a JetEngine query loop with `source: JetEngine Relations` + relation name.

## Bricks Native Dynamic Tags (no plugin required)

```
{post_title}         — current post title
{post_content}       — current post content
{post_excerpt}       — current post excerpt
{post_date}          — formatted publish date
{post_author_name}   — author display name
{featured_image_url} — featured image URL
{featured_image_alt} — featured image alt text
{site_title}         — WordPress site name
{site_tagline}       — WordPress tagline
{current_year}       — current year (for copyright)
```

## Query Loop Configuration

Required settings for a correctly configured query loop:

```json
{
  "object_type": "post",
  "post_type": "post",
  "posts_per_page": 12,
  "orderby": "date",
  "order": "DESC",
  "no_found_rows": true
}
```

- `object_type`: `"post"` for standard posts/CPTs, `"term"` for taxonomies, `"user"` for users
- `post_type`: the registered post type slug (e.g. `"post"`, `"page"`, `"product"`, or custom CPT slug)
- `posts_per_page`: always set a reasonable limit (12–24 for grids, 5–10 for featured sections)
- `no_found_rows: true`: skip the pagination COUNT(*) query when you don't need pagination
- `orderby` + `order`: always explicit — never rely on database defaults

**For taxonomy queries:**
```json
{
  "object_type": "term",
  "taxonomy": "category",
  "orderby": "name",
  "order": "ASC"
}
```

## Bricks Conditions

Use conditions to show/hide elements based on dynamic values:
- Show a CTA only when `{acf_is_featured}` equals `1`
- Hide an element when `{post_author_name}` is empty
- Show a badge when a post has a specific taxonomy term

**Rules:**
- Always prefer Bricks conditions over JS/CSS show-hide tricks
- Conditions are evaluated server-side — no extra JavaScript needed
- Nest conditions within the element that should be shown/hidden, not a parent container

## Where Dynamic Tags Work

Dynamic tags (`{...}`) are supported in:
- Text content and heading text
- Link `href` values
- Image `src` and `alt` settings
- Custom HTML attributes (via `_attributes`)
- Certain `_cssCustom` property values (use sparingly)

Dynamic tags do NOT work directly in:
- Element IDs
- CSS class names in `_cssClasses`
- Settings that expect numeric or boolean values

## Quick Reference

| Field Type | Plugin | Tag |
|---|---|---|
| Text/Number | ACF | `{acf_field_name}` |
| Image URL | ACF | `{acf_image_url:field_name}` |
| Meta field | JetEngine | `{jet_engine:field_name}` |
| Post title | Native | `{post_title}` |
| Featured image | Native | `{featured_image_url}` |
| Current year | Native | `{current_year}` |
