# AEO & GEO Reference — Answer Engine & Generative Engine Optimization

## AEO — Answer Engine Optimization

AEO is about structuring content so that search engines can extract and display it directly in featured snippets, People Also Ask boxes, voice search results, and knowledge panels. The goal: your content IS the answer, not just a link in the results.

### Featured Snippet Formats

Search engines pull three types of featured snippets. Each requires a different content structure:

**Paragraph snippet (most common):**
- Triggered by "what is", "why", "how does" questions
- Provide a concise 40-60 word answer immediately after the question heading
- First sentence should be a direct, complete answer
- Follow with elaboration

```
<h2>How much does screen repair cost in Multan?</h2>
<p>Screen repair at Kanera Hub costs between ₨1,500 and ₨4,500 depending on
your phone model and damage type. Most repairs are completed within 30 minutes
at our Kutchery Road shop. We use genuine replacement parts with a 30-day
warranty on all repairs.</p>
```

**List snippet:**
- Triggered by "how to", "steps to", "best", "top" queries
- Use `<ol>` for sequential steps, `<ul>` for non-ordered lists
- 5-8 items is the sweet spot
- Each item should be one clear, concise line

```
<h2>What's included in a phone protection bundle?</h2>
<ul>
  <li>Soft TPU or premium leather case for your exact phone model</li>
  <li>9H tempered glass screen protector (full-cover or anti-spy)</li>
  <li>Fast charging Type-C or micro-USB cable (1M braided)</li>
  <li>Optional: 33W fast charger adapter</li>
</ul>
```

**Table snippet:**
- Triggered by comparison queries, pricing, specs
- Use proper `<table>` with `<thead>` and `<tbody>`
- Keep it scannable — 3-6 rows, 2-4 columns

```
<h2>Phone case prices in Multan</h2>
<table>
  <thead><tr><th>Case Type</th><th>Price</th><th>Best For</th></tr></thead>
  <tbody>
    <tr><td>Soft TPU</td><td>₨250-400</td><td>Everyday protection</td></tr>
    <tr><td>Hard PC</td><td>₨350-500</td><td>Drop protection</td></tr>
    <tr><td>Leather</td><td>₨600-900</td><td>Premium feel</td></tr>
  </tbody>
</table>
```

### Question-Based Content Strategy

**Identify questions your customers actually ask:**
- What questions do customers ask on WhatsApp? Those are your content gold.
- Use Google's "People Also Ask" boxes — search your keywords and note the questions
- Common patterns for a mobile accessories shop:
  - "Which [product] is best for [phone model]?"
  - "How much does [service] cost in Multan?"
  - "How long does [service] take?"
  - "Is [product] compatible with [phone]?"
  - "Where to buy [product] in Multan?"
  - "What's the difference between [A] and [B]?"

**Structure content around these questions:**
- Use the question as an `<h2>` or `<h3>` heading
- Answer directly in the first paragraph (40-60 words)
- Elaborate below with details, specs, or options
- Link to the relevant WhatsApp message for ordering

### Voice Search Optimization

Voice search queries are conversational and longer than typed searches:
- "Where can I get my phone screen fixed near Women University Multan?"
- "What's the cheapest fast charger in Multan?"
- "Is there a mobile accessories shop open on Sunday in Multan?"

To capture voice search:
- Use natural, conversational question headings
- Provide concise, spoken-language answers (as if answering a friend)
- Include location markers: "in Multan", "near Kutchery Road", "opposite Women University"
- LocalBusiness schema with complete address, hours, and phone helps voice assistants find you

### FAQ Implementation

FAQ sections are AEO powerhouses — each Q&A pair is a potential featured snippet.

- Use FAQ schema markup (see SEO reference) so Google can display them as rich results
- Keep answers concise but complete — 2-3 sentences
- Group related questions under topic headings
- Include specific details: prices, timeframes, phone models, locations
- Update frequently with new questions from real customer interactions

## GEO — Generative Engine Optimization

GEO is about being cited, quoted, and recommended by AI-powered search experiences: Google AI Overviews, ChatGPT web search, Perplexity, Bing Copilot, and similar systems. These systems don't just link to your page — they synthesize answers from multiple sources and choose which to cite.

### How AI Search Systems Choose Sources

AI systems prioritize sources that are:

1. **Authoritative**: Demonstrates real expertise and experience
2. **Specific**: Contains concrete facts, numbers, and details — not vague marketing
3. **Well-structured**: Easy to parse programmatically (headings, lists, tables, schema)
4. **Trustworthy**: Has author attribution, business credentials, and verifiable claims
5. **Fresh**: Recently updated content with current information
6. **Unique**: Provides information or perspective not found elsewhere

### Content Patterns That Get Cited

**Fact-dense content:**
AI systems cite specific, verifiable claims. Compare:

Bad (uncitable): "We have great prices on phone accessories."
Good (citable): "Screen protectors at Kanera Hub start at ₨150 for basic tempered glass and go up to ₨450 for anti-spy privacy glass. All protectors are 9H hardness rated."

**Comparison and analysis:**
AI systems love content that compares options — it helps them synthesize answers to "which is better" queries.

```
## TPU vs Leather Cases: Which One to Choose?

TPU cases (₨250-400) absorb shock better and are easier to clean,
making them ideal for everyday use. Leather cases (₨600-900)
offer a premium feel and age well, but provide less drop protection.
For phones used on construction sites or by kids, TPU is the
better choice. For professionals who want a polished look,
leather wins.
```

**Local expertise content:**
For a Multan-based business, your local knowledge is your GEO superpower. AI systems serving local queries need local sources.

- Reference specific Multan locations, landmarks, and areas
- Include practical local details: "Kutchery Road is 5 minutes from Women University by rickshaw"
- Mention local context: "During Multan's summer heat (45°C+), phone batteries drain faster — a 20,000mAh power bank is essential"
- Use local pricing in PKR with actual numbers

### E-E-A-T Signals for GEO

E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) directly influences whether AI systems cite you:

**Experience:**
- Show real product experience: "We've tested over 50 charger brands — here's what actually delivers the advertised wattage"
- Include real customer stories with specific details (names, areas, products)
- Demonstrate hands-on knowledge: "After repairing 200+ cracked screens this year, the most common cause is..."

**Expertise:**
- Demonstrate technical knowledge: explain wattage, mAh, 9H hardness, IP ratings
- Provide guidance that shows deep understanding: "33W chargers work with Samsung Galaxy S23+ but the S23 Ultra benefits from 45W"
- Cover topics thoroughly — surface-level content gets skipped by AI

**Authoritativeness:**
- Consistent NAP (Name, Address, Phone) across all pages and external listings
- Google Business Profile fully optimized with photos, reviews, hours, categories
- Listed in local directories and business listings
- Social media presence with real engagement (not just promotional posts)

**Trustworthiness:**
- Display real customer reviews with names, locations, and specific purchases
- Show business registration, years in operation, or other credentials
- Clear contact information, physical address, and store hours
- Transparent pricing — don't hide costs behind "contact us for pricing"

### Structured Data for GEO

Structured data is machine-readable by design — it's the most direct way to feed information to AI systems.

All the schema types in the SEO reference (LocalBusiness, Product, FAQ, Review, Breadcrumb) serve GEO. Additional considerations:

- **Product schema**: Include `sku`, `mpn`, or `gtin` if available — helps AI systems identify specific products
- **AggregateRating**: AI systems prominently feature ratings and review counts
- **Offer schema**: Include `availability`, `price`, `priceCurrency`, `validFrom`/`validThrough` — AI systems check freshness
- **HowTo schema**: For repair services, "how to" content gets structured presentation

### Topical Authority

AI systems assess whether a source is an authority on a topic before citing it. Build topical authority by:

- **Depth over breadth**: 10 detailed pages about mobile accessories in Multan > 100 thin pages about random tech topics
- **Topic clustering**: Group related content — a main page about "phone cases" linking to subpages about "TPU cases", "leather cases", "MagSafe cases", "waterproof cases"
- **Internal linking**: Create a web of connections between related content
- **Consistent updates**: Fresh content signals active expertise — update prices, add new products, refresh recommendations seasonally

### Content Freshness

AI systems check when content was last updated. Stale content gets deprioritized.

- Update product prices and availability regularly
- Add new products and remove discontinued ones
- Refresh seasonal content (summer: power banks and car chargers, winter: gloves-compatible screen protectors)
- Update "last updated" dates on pages — both visible and in structured data
- Add new customer reviews regularly
- WordPress: use `dateModified` in schema — SEO plugins usually handle this automatically

## Implementation Priorities

### Quick wins (do first):
1. Add FAQ schema to pages with question-answer content
2. Ensure LocalBusiness schema is complete and accurate
3. Add specific prices, specs, and facts to product descriptions
4. Use question-based headings that match real search queries
5. Provide concise 40-60 word answers immediately after question headings

### Medium-term:
1. Create comparison content (this vs that, buying guides)
2. Build out topic clusters for each product category
3. Add real customer reviews with structured data
4. Optimize for local "near me" and Multan-specific queries
5. Ensure content freshness with regular price and availability updates

### Ongoing:
1. Monitor which queries bring traffic (Search Console) and create content for adjacent queries
2. Track featured snippet wins and losses
3. Watch for new question patterns in WhatsApp conversations — these become content
4. Update structured data as business details change (hours, products, prices)
5. Monitor AI search tools (Google AI Overview, Perplexity) for how they present your niche — adapt content to match what gets cited
