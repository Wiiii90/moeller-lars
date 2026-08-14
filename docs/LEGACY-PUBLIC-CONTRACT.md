# Legacy public contract

This document records the public behaviour that the rebuild must preserve or intentionally improve. It is based on the completed source review of `larsmoeller`; it is not a claim that every legacy defect is desirable behaviour. A final browser crawl and editorial review must turn the inventory below into executable fixtures before cutover.

## Visitor routes and query routes

- The public entrypoint is the site root, served through the legacy `index.php` dispatcher. A direct `index.php`/HTML entry is redirected by the Apache rules to the canonical root form.
- Public content is selected through the legacy dispatcher and query-driven page/category selection. The concrete query-to-page map must be captured in the route fixture before migration; the target must preserve every intended public URL or provide a permanent redirect.
- The visible public areas include the landing/latest-work view, artwork category views (paintings, drawings, and prints), the Vita/CV view, and the contact view.
- `/admin` and its tool query parameters are administration routes, not part of the public contract. They are replaced by a new admin application.
- Workshop/development copies and administrative paths are not public content and must not be exposed by the target deployment.

## Visible navigation and page identity

- The public navigation exposes the artwork categories together with Vita/CV and Contact. Labels, ordering, active-state treatment, and link destinations are to be captured from the approved reference screenshots/crawl.
- The landing view presents the newest artwork across the artwork categories, followed by its visible description fields.
- Page title, language markers, artwork headings, footer/contact information, and the overall visual language are part of the visitor-facing identity.

## Rendered artwork and CV fields

Artwork records render, where populated:

- image/thumbnail;
- title;
- year derived from the stored date;
- material/medium;
- dimensions;
- optional comment/description.

The Vita/CV page renders a portrait and the legacy `txt/vita.txt` content, including its authored formatting/links. The target may store CV content structurally, but the migrated rendered text and meaning must remain lossless.

## Categories and ordering

- The factual legacy artwork categories are `paintings`, `drawings`, and `prints`.
- Category pages query their category's records and order them by date descending.
- The landing page combines the three categories and selects the newest record by date descending.
- The date controls chronology and displayed year; the legacy date is not necessarily a visitor-visible day. Equal-date ordering is undefined in the legacy behaviour and must not be silently invented during migration; use a documented deterministic tie-breaker in the target.

## Image and viewer behaviour

- Artwork thumbnails are served from category-specific thumbnail directories; originals are served from the corresponding category media directory.
- The existing viewer supports opening/resizing artwork imagery and mouse-wheel/loupe-style interaction. The rebuild must make image loading, close/open state, and navigation reliable.
- Previous/next artwork and keyboard/touch navigation are allowed as subtle improvements, provided they preserve the artwork-first presentation and are tested at desktop and mobile sizes.
- Missing, corrupt, oversized, or unavailable media must fail gracefully and must not break the surrounding page.

## Responsive behaviour

The source uses responsive/resizable artwork presentation and separate styling for the public pages. The target must preserve the established composition and readable artwork metadata across desktop and mobile widths. Responsive improvements are permitted when they do not change the artistic identity. The acceptance fixture must cover the landing page, a category page, the CV, Contact, and the viewer at representative desktop and mobile sizes.

## Metadata, sitemap, robots, and contact

- Preserve the verified page titles, language/ALT metadata, favicon, canonical URL policy, and other SEO metadata that are present in the approved crawl.
- Preserve the sitemap and robots intent, but regenerate them from the target route model so removed or redirected legacy paths are not advertised.
- HTTPS is canonical and HTTP must redirect to HTTPS in one hop. The legacy HTTP redirect rule is a defect to correct, not a contract to preserve.
- Preserve the intended Contact page fields, validation, and delivery outcome. Do not preserve unsafe implementation details such as direct legacy mail/database configuration.

## Intended contract versus known defects

The intended contract is the artistic presentation, public content, meaningful navigation, artwork metadata, CV meaning, contact outcome, and discoverability metadata described above. Known legacy defects are not compatibility requirements: insecure authentication, direct SQL construction, missing request protections, debug/error disclosure, unsafe uploads, HTTP-only asset/redirect behaviour, and broken placeholder analytics must be replaced or contained. If a source behaviour is not represented in this contract, it requires an explicit product decision before migration rather than automatic compatibility.
