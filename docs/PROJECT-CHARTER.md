# Project charter

## Goal

Replace the public Lars Möller website while replacing its administration and operating model completely. The public result must preserve Lars Möller's artistic identity and overall appearance; subtle visual and UX improvements are allowed when they support clarity, accessibility, or reliability.

## Non-negotiable public contract

- Existing public URLs remain reachable or redirect permanently to an equivalent page.
- The artistic visual language, typography, layout, artwork presentation, and ordering remain recognisably unchanged, with room for reviewed subtle improvements.
- Existing images, ALT text, page titles, SEO metadata, sitemap, and contact behaviour are inventoried before switching traffic.
- HTTPS is canonical; HTTP redirects to HTTPS in one hop.
- Broken legacy behaviour is not a compatibility requirement; intended behaviour is documented and tested explicitly.
- The artwork viewer is rebuilt reliably, including tested image loading, close/open behaviour, and previous/next navigation. Keyboard and touch navigation may improve the experience without changing its character.

## Editorial backend

`/admin` is a completely new, consistent artist-facing application. It should support:

1. Artworks: image upload, title, year, medium, dimensions, description, category, visibility, ordering, and draft/publish state.
2. Exhibitions: title, venue, location, dates, image, external links, text, and past/current/upcoming state. Exhibitions are a separate content type from CV entries.
3. CV: structured entries with date ranges, sections, ordering, links, and rich text restricted to a safe editor.
4. Blog: drafts, scheduled or immediate publishing, cover image, tags, preview, and stable public slug. Blog content is publicly invisible by default and becomes visible only after Lars explicitly enables it.
5. Media: deduplicated original asset, generated derivatives, ALT text, copyright/credit, and safe deletion checks.
6. Statistics: visits, landing pages, referrers, device category, and top content, without storing unnecessary personal data.

## Cost constraint

Additional software, licence, plugin, and SaaS cost must be 0 EUR. Server and hosting cost is allowed, but must be minimized, documented, and justified against reliability, TLS, backup, analytics, and maintenance requirements.

## Explicit exclusions for the first release

- No general-purpose website builder or free-form layout editor.
- No public registration, customer accounts, marketplace, or social feed.
- No migration of legacy authentication or database credentials.
- No paid analytics plugin or SaaS service; the analytics target is self-hosted Matomo Community/Core with zero licence/SaaS cost.

## Definition of done

The new site passes a route/content comparison against the live reference, reliable viewer and admin acceptance tests, a lossless migration reconciliation, and an editorial acceptance pass by Lars. It is deployed first to staging under HTTPS with tested backups, restore, monitoring, rollback, and the Matomo deployment path. Production server/runtime work, hosting cost, TLS, deployment, backups, and a possible server replacement are all part of the project scope; only after those are accepted does the new site replace production traffic.

## Acceptance and test requirements

- Public route, artwork, metadata, and media inventories are machine-checkable before and after migration.
- Viewer tests cover loading, navigation, keyboard/touch input where implemented, missing media, and mobile layouts.
- Admin tests cover authentication, authorization, drafts, publication state, separate exhibitions/CV editing, and the blog-disabled default.
- Migration tests reconcile record counts, original-media checksums, required fields, and representative rendered content.
- Deployment tests cover HTTPS, HTTP redirect, backup/restore, rollback, and a staging-to-production rehearsal.
- Analytics tests confirm Matomo collection and dashboard visibility without introducing a paid service or storing unnecessary raw identifiers. They also cover traffic sources, geography, devices, content interaction, and separate bot/operational metrics.
- The chosen deployment documents all recurring costs and demonstrates that additional software, licence, plugin, and SaaS cost remains 0 EUR.
