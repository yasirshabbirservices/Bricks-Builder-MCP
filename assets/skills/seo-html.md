---
name: seo-html
title: SEO & Semantic HTML for Bricks Pages
description: Heading hierarchy, semantic landmarks, meta title/description, Open Graph, structured data, internal linking, and image SEO rules.
when_to_use: Any page with headings, content structure, metadata, or social sharing intent.
---

## Semantic Document Structure

Every page must have these landmarks:

```
<header>  — site header (logo, nav)
<main>    — primary page content (one per page)
<footer>  — site footer
```

- Use `tag` settings in Bricks to output `<main>`, `<header>`, `<footer>`, `<nav>`, `<article>`, `<section>`
- `<main>` must contain the unique page content — not repeated nav or footer
- `<section>` elements should have a heading or `aria-label` to give them an accessible name

## Heading Hierarchy

- **One `<h1>` per page** — the primary page title, typically in the hero or above-the-fold area
- H2: major page sections (Services, About Us, Testimonials, Pricing)
- H3: subsections within H2 sections (individual service names, FAQ items)
- H4–H6: use only when genuinely needed for sub-subsections
- **Never skip levels** — h1 → h3 is wrong; h1 → h2 → h3 is correct
- **Never use headings for styling** — set the heading element type for structure, adjust size via CSS

## Meta — Title & Description

Set via Yoast/RankMath SEO meta fields using `bricks_update_seo`:
- **Meta title**: 50–60 characters, unique per page, primary keyword near the start
- **Meta description**: 140–160 characters, compelling summary, includes primary keyword naturally
- **Every page** must have a unique title and description — no duplicates

## Open Graph

Set for all pages intended to be shared on social media:
- `og:title` — typically same as meta title (can differ slightly for social context)
- `og:description` — typically same as meta description
- `og:image` — 1200×630px, clear and relevant, HTTPS URL
- `og:type` — `"website"` for pages, `"article"` for blog posts

Use `bricks_update_seo` to set these fields if Yoast/RankMath is active.

## Images

- All `<img>` elements must have descriptive `alt` text
- Alt text should describe the image content and its purpose in context
- Filename should be descriptive: `team-photo-london-office.jpg` not `IMG_4829.jpg`
- Use `width` and `height` attributes (or Bricks image size settings) to prevent layout shift (CLS)
- Compress images appropriately — use WebP format when supported

## Internal Linking

- Use descriptive anchor text that tells users and search engines where the link goes
- `"View our pricing plans"` — correct
- `"Click here"` or `"Read more"` — avoid (not descriptive enough)
- Link to relevant internal pages naturally within page content
- Navigation links should use clear, concise labels that match page titles

## Structured Data (JSON-LD)

Add structured data via a Bricks `code` element (type: `text/html`) with a `<script type="application/ld+json">` block for:
- **Local business pages**: `LocalBusiness` schema with name, address, phone, opening hours
- **Blog posts**: `Article` schema with headline, author, datePublished, image
- **Products**: `Product` schema with name, image, description, price, availability
- **FAQs**: `FAQPage` schema when a page has a FAQ section

## Canonical URLs

- Let Yoast/RankMath manage canonical tags automatically
- Do not add manual `<link rel="canonical">` tags unless redirecting or consolidating duplicate content
- Ensure no duplicate content exists across URLs (www vs non-www, trailing slash vs none)

## URL & Content

- Ensure the page URL is set before building content (`bricks_set_front_page` if it's the homepage)
- Page slug should be short, descriptive, and keyword-relevant
- Content should be substantive — avoid thin pages with minimal text

## Quick Checklist Before Writing

- [ ] One `<h1>` defined for the page, content is the primary keyword/topic
- [ ] Heading levels are logical and sequential (no skips)
- [ ] `<main>` landmark wraps the primary content
- [ ] Meta title (50–60 chars) and description (140–160 chars) set or planned
- [ ] OG image defined for social sharing pages
- [ ] All images have descriptive `alt` text and explicit dimensions
- [ ] Internal links use descriptive anchor text
