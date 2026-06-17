---
name: business-profile
description: "Use when onboarding a new site, setting up the business profile, or when the AI needs to populate real content into templates. Covers: brand identity fields, color tokens, typography selection, design style presets, contact info, social media links, navigation, services, and logo assets. Also covers how to check if a business profile exists and how to prompt the user for missing info."
---

# Business Profile Onboarding

## Overview

The business profile is a centralized store of brand identity, design tokens, contact details, social links, navigation structure, logo assets, and services. It lives in the WordPress options table under the `bmcp_business_profile` option key.

The AI uses the business profile to replace placeholder content (Lorem ipsum, dummy emails, stock phone numbers, generic colors, placeholder logos) with the real business data whenever it builds or edits a page, applies a library template, or generates new sections. A configured profile means every page the AI produces is immediately on-brand and ready for review, rather than full of dummy content that needs manual replacement.

The profile is managed through the WordPress admin at **Settings > Bricks MCP > Business Profile** or programmatically through the MCP tools described below.

## Business Profile Fields

### Brand Identity

| DB Key | API Response Key | Type | Description |
|---|---|---|---|
| `business_name` | `brand.business_name` | text | Company or brand name |
| `tagline` | `brand.tagline` | text | Short slogan or tagline |
| `business_type` | `brand.business_type` | text | Industry or business category (e.g. "SaaS", "Restaurant", "Law Firm") |
| `target_audience` | `brand.target_audience` | text | Who the site is for (e.g. "Small business owners aged 25-45") |
| `tone_of_voice` | `brand.tone_of_voice` | text | Brand voice descriptor (e.g. "Professional but friendly", "Bold and playful") |
| `about_text` | `brand.about_text` | textarea | About-page-length description of the business |

### Colors

All colors are stored as hex values (e.g. `#1a2b3c`). Sanitized via `sanitize_hex_color()`.

| DB Key | API Response Key | Purpose |
|---|---|---|
| `color_primary` | `colors.primary` | Primary brand color (buttons, links, accents) |
| `color_secondary` | `colors.secondary` | Secondary brand color (supporting elements) |
| `color_accent` | `colors.accent` | Accent/highlight color (badges, alerts, hover states) |
| `color_text` | `colors.text` | Body text color |
| `color_heading` | `colors.heading` | Heading text color |
| `color_background` | `colors.background` | Page background color |
| `color_surface` | `colors.surface` | Card/panel surface color |
| `color_border` | `colors.border` | Border and divider color |
| `color_success` | `colors.success` | Success state color |
| `color_error` | `colors.error` | Error state color |

### Typography

| DB Key | API Response Key | Type | Description |
|---|---|---|---|
| `font_heading` | `typography.font_heading` | text | Heading font family name (e.g. "Inter", "Playfair Display") |
| `font_body` | `typography.font_body` | text | Body text font family name |
| `font_size_base` | `typography.font_size_base` | text | Base font size (e.g. "16px", "1rem") |

### Design Style

All design style fields are allowlisted selects. Invalid values are silently discarded.

| DB Key | API Response Key | Allowed Values |
|---|---|---|
| `design_style` | `design_style.style` | `modern`, `minimal`, `bold`, `elegant`, `playful`, `corporate`, `creative`, `luxury` |
| `border_radius` | `design_style.border_radius` | `none`, `small`, `medium`, `large`, `rounded`, `pill` |
| `spacing_scale` | `design_style.spacing_scale` | `compact`, `normal`, `spacious` |
| `button_style` | `design_style.button_style` | `filled`, `outline`, `ghost`, `soft` |

### Contact Information

| DB Key | API Response Key | Type | Description |
|---|---|---|---|
| `email` | `contact.email` | email | Primary business email (sanitized via `sanitize_email()`) |
| `phone` | `contact.phone` | text | Primary phone number |
| `address` | `contact.address` | text | Street address |
| `city_country` | `contact.city_country` | text | City, state/province, country |
| `contact_extra` | `contact.extra` | repeater | Additional contact entries, each with `label` and `value` (e.g. "Fax" / "+1-555-0199", "WhatsApp" / "+44 7700 900000") |

### Social Media Links

| DB Key | API Response Key | Type | Description |
|---|---|---|---|
| `social_links` | `social` | repeater | Array of `{ platform, url }` objects. Platform is a text label (e.g. "Facebook", "X", "LinkedIn", "Instagram", "YouTube", "TikTok"). URL is the full profile URL, sanitized via `sanitize_url()`. |

### Navigation

| DB Key | API Response Key | Type | Description |
|---|---|---|---|
| `nav_items` | `navigation.nav_items` | text | Comma-separated list of navigation menu labels (e.g. "Home, About, Services, Blog, Contact") |
| `cta_text` | `navigation.cta_text` | text | Call-to-action button text (e.g. "Get Started", "Book a Demo") |
| `cta_url` | `navigation.cta_url` | url | CTA button destination URL |
| `copyright_text` | `navigation.copyright_text` | text | Footer copyright line (e.g. "2026 Acme Inc. All rights reserved.") |

### Assets

| DB Key | API Response Key | Type | Description |
|---|---|---|---|
| `logo_url` | `assets.logo_url` | url | Primary logo URL (for light backgrounds) |
| `logo_dark_url` | `assets.logo_dark_url` | url | Dark/inverted logo URL (for dark backgrounds, footer, etc.) |

### Services

| DB Key | API Response Key | Type | Description |
|---|---|---|---|
| `services` | `services` | textarea (one per line) | List of services or offerings. Stored as a newline-separated string in the DB. Returned as a flat array of strings in the API response (empty lines are filtered out). |

## Checking If a Profile Exists

Call the `bricks_get_business_profile` tool. The response includes a `configured` boolean:

- `configured: true` — profile exists and has data. All field groups are returned.
- `configured: false` — no profile has been saved. The response includes a `note` telling the user to configure it via the admin UI.

To check whether specific fields are populated, inspect the returned values. Empty strings and empty arrays mean the field has not been set.

```
// Pseudocode check
profile = call bricks_get_business_profile
if profile.configured == false:
    -> prompt the user for business info
else:
    if profile.brand.business_name == "":
        -> ask for the business name
    if profile.colors.primary == "":
        -> ask for brand colors or suggest defaults
    // ... etc.
```

## Onboarding Flow

When starting work on a new site or when the profile is empty/incomplete, walk through these steps:

### Step 1: Check current profile state
Call `bricks_get_business_profile`. If `configured: false`, begin the full onboarding. If `configured: true` but key fields are empty, ask only for the missing ones.

### Step 2: Gather brand identity
Ask the user for:
- Business name (required)
- Tagline (optional, can suggest based on business type)
- Business type / industry
- Target audience
- Tone of voice
- About text (or offer to draft one based on the other answers)

Let the user skip any field. Never block on optional fields.

### Step 3: Gather colors
Ask if the user has brand colors. If yes, collect hex values for at least primary and secondary. Offer to suggest a complementary palette to fill remaining slots (accent, text, heading, background, surface, border, success, error).

If the user has no brand colors, suggest a palette based on the chosen design style or industry conventions.

### Step 4: Typography and design style
Ask for preferred fonts (heading + body). If unknown, suggest pairings based on the design style.

Ask for design style preset (modern/minimal/bold/elegant/playful/corporate/creative/luxury) and sub-options (border radius, spacing, button style). Defaults are fine here.

### Step 5: Contact and social
Collect email, phone, address. Ask for social media links (platform + URL pairs). Let the user list them naturally ("we're on Instagram at @acme and LinkedIn at linkedin.com/company/acme") and parse into the repeater format.

### Step 6: Navigation and CTA
Ask what pages should appear in the main navigation. Collect CTA button text and URL. Ask for copyright text.

### Step 7: Logos
Ask the user to provide logo URLs. If logos are already in the WordPress media library, the user can provide the media URL directly. If they need to upload, direct them to Media > Add New in the WordPress admin.

### Step 8: Services
Ask the user to list their services or key offerings.

### Step 9: Save the profile
Once gathered, save the profile via the WordPress admin UI or via the `bricks_import_business_profile` tool. Confirm back to the user what was saved.

## Using the Profile in Templates

When building or editing any page content, follow this substitution approach:

1. **Call `bricks_get_business_profile` at the start of every build/edit session** to get the latest profile data.

2. **Replace all placeholder content** with profile values:
   - "Your Company" / "Company Name" / "Acme Inc" -> `brand.business_name`
   - "Your tagline here" -> `brand.tagline`
   - "Lorem ipsum..." in about sections -> `brand.about_text`
   - `info@example.com` -> `contact.email`
   - `+1 (555) 000-0000` -> `contact.phone`
   - `123 Main St` -> `contact.address`
   - Placeholder social URLs -> `social[].url`
   - Placeholder logo `<img>` sources -> `assets.logo_url` / `assets.logo_dark_url`
   - Placeholder nav labels -> `navigation.nav_items`
   - "Get Started" button -> `navigation.cta_text` with `navigation.cta_url`
   - Footer copyright -> `navigation.copyright_text`
   - Placeholder service names -> `services[]`

3. **Apply color tokens** to Bricks elements:
   - Primary buttons, links, active states -> `colors.primary`
   - Secondary elements, badges -> `colors.secondary`
   - Hover highlights, accent details -> `colors.accent`
   - Body text -> `colors.text`
   - Headings -> `colors.heading`
   - Page/section backgrounds -> `colors.background`
   - Cards, panels, dropdowns -> `colors.surface`
   - Borders, dividers, HR elements -> `colors.border`

4. **Apply typography** to Bricks global settings or theme styles:
   - Heading elements -> `typography.font_heading`
   - Body/paragraph text -> `typography.font_body`
   - Base size -> `typography.font_size_base`

5. **Respect the design style** when making layout and styling decisions:
   - `design_style.style` guides the overall aesthetic (sharp vs rounded, dense vs airy)
   - `design_style.border_radius` controls corner rounding on buttons, cards, images
   - `design_style.spacing_scale` influences padding and margin choices
   - `design_style.button_style` determines button rendering (filled, outline, ghost, soft)

## Import and Export

### Exporting a profile

Call `bricks_export_business_profile`. Returns:

```json
{
  "configured": true,
  "exported_at": "2026-01-15 14:30:00",
  "site_url": "https://example.com",
  "bmcp_version": "1.11.0",
  "profile": { /* raw DB fields */ },
  "note": "Pass the \"profile\" value to bricks_import_business_profile..."
}
```

The `profile` key contains the raw option data (flat key-value map with DB keys like `business_name`, `color_primary`, `social_links`, etc.).

### Importing a profile

Call `bricks_import_business_profile` with the `profile` object from an export:

```json
{
  "profile": {
    "business_name": "Acme Inc",
    "tagline": "Building the future",
    "color_primary": "#2563eb",
    "color_secondary": "#7c3aed",
    "email": "hello@acme.com",
    "social_links": [
      { "platform": "LinkedIn", "url": "https://linkedin.com/company/acme" }
    ]
  }
}
```

Import behavior:
- **Merges** with the existing profile (existing fields not in the import are preserved).
- All values pass through the same sanitizer as the admin form (hex validation, URL sanitization, allowlisted selects).
- Requires the `manage_options` capability.
- Returns a success confirmation. Call `bricks_get_business_profile` afterward to verify.

Use import/export to:
- Clone a brand profile across multiple sites
- Back up profile data before major changes
- Share branding between staging and production environments
- Pre-load a profile during automated site setup

## Best Practices

1. **Always prefer profile data over dummy content.** If the profile has a value, use it. Never leave "Lorem ipsum" or "example@email.com" on a page when real data is available.

2. **Update the profile when new information is learned.** If the user mentions a new phone number, social media account, or service offering during conversation, offer to update the profile so future pages use the correct data.

3. **Check the profile at the start of every session.** Profile data may have been updated via the admin UI since the last AI interaction.

4. **Gracefully handle missing fields.** Not every business will fill out every field. When a field is empty, either skip the corresponding section (don't show an empty social icons row) or use a sensible placeholder and flag it for the user.

5. **Respect the design style preset.** The design style fields exist to keep AI-generated layouts visually consistent. A "minimal" site should not get heavy drop shadows and rounded pill buttons unless the user explicitly asks.

6. **Use export before destructive changes.** Before importing a new profile that may overwrite existing values, export the current profile first as a backup.

7. **Suggest completing incomplete profiles.** If the profile exists but critical fields (name, colors, contact) are missing, mention it to the user and offer to help fill them in.

8. **Keep services up to date.** The services list drives service cards, pricing tables, and footer columns. When the user adds or removes offerings, update this field.
