# Project charter

## Goal

Replace the public Lars Möller website without a visible regression for visitors, while replacing its administration and operating model completely.

## Non-negotiable public contract

- Existing public URLs remain reachable or redirect permanently to an equivalent page.
- The artistic visual language, typography, layout, artwork presentation, and ordering remain recognisably unchanged.
- Existing images, ALT text, page titles, SEO metadata, sitemap, and contact behaviour are inventoried before switching traffic.
- HTTPS is canonical; HTTP redirects to HTTPS in one hop.

## Editorial backend

One artist-facing backend should support:

1. Artworks: image upload, title, year, medium, dimensions, description, category, visibility, ordering, and draft/publish state.
2. Exhibitions: title, venue, location, dates, image, external links, text, and past/current/upcoming state.
3. CV: structured entries with date ranges, sections, ordering, links, and rich text restricted to a safe editor.
4. Blog: drafts, scheduled or immediate publishing, cover image, tags, preview, and stable public slug.
5. Media: deduplicated original asset, generated derivatives, ALT text, copyright/credit, and safe deletion checks.
6. Statistics: visits, landing pages, referrers, device category, and top content, without storing unnecessary personal data.

## Explicit exclusions for the first release

- No general-purpose website builder or free-form layout editor.
- No public registration, customer accounts, marketplace, or social feed.
- No migration of legacy authentication or database credentials.

## Definition of done

The new site passes a route/content comparison against the live reference, has an editorial acceptance pass by Lars, is deployed first to staging under HTTPS, has tested backups and rollback, and only then replaces production traffic.
