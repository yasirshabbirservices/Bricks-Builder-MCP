# Web Optimization — Accessibility, SEO, AEO & GEO

> Use this skill when building, reviewing, or optimizing any page, template, or component for accessibility, search engine visibility, answer engine presence, or generative AI discoverability. Read the relevant reference file before starting work in that area.

## Quick Rules (apply to EVERY page and component)

### Accessibility — every user matters

Accessibility is not an afterthought or a checklist to run at the end. Build it into every element from the start.

- Every image needs meaningful `alt` text (or `alt=""` if purely decorative) — set via Bricks element settings or `_attributes`
- Every interactive element must be keyboard-accessible — focus states are required, not optional
- Color contrast must meet WCAG AA minimum: 4.5:1 for normal text, 3:1 for large text. The dark theme with orange accent needs careful checking — orange on dark backgrounds can fail contrast
- Use semantic HTML elements: `<nav>`, `<main>`, `<article>`, `<section>`, `<aside>`, `<header>`, `<footer>` — Bricks lets you set the tag on containers
- Heading hierarchy must be logical: one `<h1>` per page, never skip levels (h1 → h3 is wrong)
- Form inputs need associated `<label>` elements or `aria-label` attributes
- Links must have descriptive text — never "click here" or bare URLs
- Touch targets minimum 44x44px on mobile
- Animations must respect `prefers-reduced-motion` — wrap motion in `@media (prefers-reduced-motion: no-preference)`
- Read `references/accessibility.md` for the full WCAG checklist and Bricks-specific patterns

### SEO — be found by search engines

Technical SEO is infrastructure. Get it right once and every page benefits.

- Every page needs a unique `<title>` and `meta description` — set via Yoast/RankMath or Bricks page settings
- Use proper heading hierarchy for content structure — search engines use headings to understand page organization
- Structured data (JSON-LD) for local business, products, reviews, FAQ, breadcrumbs — WordPress plugins can handle most of this, but verify output
- Images need descriptive filenames (`screen-protector-9h-tempered.webp` not `IMG_4521.jpg`), `alt` text, and modern formats (WebP/AVIF with fallback)
- Core Web Vitals: LCP under 2.5s, INP under 200ms, CLS under 0.1 — lazy-load below-fold images, preload above-fold, minimize layout shifts
- Internal linking between related content — every page should be reachable within 3 clicks
- Mobile-first indexing: Google indexes the mobile version — test mobile layout thoroughly
- Read `references/seo.md` for technical SEO checklist, structured data schemas, and WordPress/Bricks specifics

### AEO — be the answer

Answer Engine Optimization targets featured snippets, People Also Ask, voice search results, and knowledge panels.

- Structure content to directly answer questions — lead with the answer, then elaborate
- Use question-based headings that match how people search: "How much does screen repair cost in Multan?" not "Our Pricing"
- Provide concise, factual answers in the first 40–60 words after a question heading — this is the snippet sweet spot
- Use structured data (FAQ schema, HowTo schema, LocalBusiness) to help engines extract answers
- Create content around "People Also Ask" questions for your niche — these are the questions search engines already associate with your topic
- Lists, tables, and step-by-step formats get featured more often than paragraphs
- Read `references/aeo-geo.md` for full AEO patterns and implementation

### GEO — be cited by AI

Generative Engine Optimization is about being cited, quoted, and recommended by AI systems (Google AI Overviews, ChatGPT search, Perplexity, Bing Copilot).

- AI systems prefer authoritative, well-structured, fact-dense content — vague marketing copy gets ignored
- Include specific numbers, prices, specs, and verifiable claims — "₨650 Starter Pack includes TPU case + 9H glass + Type-C cable" is citable, "great deals" is not
- Author and source attribution builds trust signals AI systems look for — include business name, location, and expertise markers
- Structured data is even more important for GEO than traditional SEO — it's machine-readable by design
- Maintain topical authority: cover your niche thoroughly rather than surface-level across many topics
- Freshness matters: regularly update content with current prices, availability, and seasonal information
- E-E-A-T signals (Experience, Expertise, Authoritativeness, Trustworthiness) directly influence AI citation — show real experience with products, real customer stories, real local knowledge
- Read `references/aeo-geo.md` for the full GEO framework and implementation patterns

## How These Work Together

These four areas reinforce each other:

- **Accessible sites are better for SEO** — semantic HTML, proper headings, alt text, and fast performance all improve search rankings
- **SEO-optimized content is easier for AEO/GEO** — structured data, clear answers, and topical authority serve both search engines and AI systems
- **AEO-formatted content improves GEO** — question-answer structures and factual density are exactly what generative AI looks for
- **GEO demands the highest content quality** — which naturally improves everything else

When building a page, work through them in this order:
1. **Accessibility first** — semantic structure, keyboard access, contrast, alt text
2. **SEO infrastructure** — meta tags, structured data, performance, internal links
3. **AEO content patterns** — question headings, concise answers, list/table formats
4. **GEO authority signals** — specific facts, E-E-A-T markers, freshness, citations

## WordPress + Bricks Specific

- Use Bricks element `tag` setting to output semantic HTML (`<nav>`, `<main>`, `<section>`, etc.)
- Set `_attributes` on elements for ARIA roles, labels, and custom data attributes
- Use Bricks' built-in `loading` control on images for lazy/eager loading
- Add `fetchpriority="high"` via `_attributes` on above-fold images
- Generate structured data via a dedicated SEO plugin (Yoast, RankMath, SEOPress) — don't hand-code JSON-LD unless the plugin can't handle your schema
- Use Bricks' breadcrumbs element with structured data output enabled
- For SureCart products: ensure product schema includes price, availability, reviews, and images
- Test with: Lighthouse (accessibility + performance), Google Rich Results Test (structured data), PageSpeed Insights (Core Web Vitals)
