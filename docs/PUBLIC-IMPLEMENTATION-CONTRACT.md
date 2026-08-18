# Public implementation contract

This is the current target contract for the public Lars Möller site. The legacy source and [LEGACY-PUBLIC-CONTRACT.md](LEGACY-PUBLIC-CONTRACT.md) provide evidence for content, visual identity and intended behaviour; they do not make legacy PHP/query routing or broken implementation details target requirements.

## Canonical public routes

All canonical public URLs use HTTPS on the approved canonical host.

| Public area | Canonical path | Availability |
| --- | --- | --- |
| Home | `/` | public |
| Artwork category | `/{category-slug}` | published category only |
| Artwork detail | `/artworks/{slug}` | published artwork only |
| CV / Vita / Contact surface | `/cv` | when CV is enabled |
| Exhibitions | `/exhibitions` | when Exhibitions are enabled |
| Contact direct route | `/contact` | according to Contact state |
| Blog index | `/blog` | when Blog is enabled |
| Blog post | `/blog/{slug}` | enabled Blog + publicly available post |
| Sitemap | `/sitemap.xml` | generated from current public state |
| Robots | `/robots.txt` | public discovery policy |

Artwork category routing is generic and data-driven. A category name or slug never requires a dedicated controller branch or route definition. Reserved application paths cannot be category slugs.

CV and Exhibitions are independent destinations. Contact belongs to the CV/biography experience and does not become another primary-navigation item. Blog is absent from navigation, public content and sitemap until explicitly enabled.

Legacy `/index.php?site=...` forms are source evidence only. They are not preserved unless a separate, concrete SEO/external-link decision requires a redirect. Generic redirects remain valid for canonical URLs created by the new application when an editorial slug/path is deliberately changed.

## Public visual system

The reviewed legacy site is the visual baseline, not loose inspiration for a redesign.

Initial acceptance requires close real-browser parity in:

- desktop header/name/navigation geometry;
- continuous header separator;
- artwork/content column widths and offsets;
- typography and metadata hierarchy;
- category headings and navigation spacing;
- artwork image scale and vertical rhythm;
- small-screen navigation and content behaviour around the reviewed legacy breakpoints.

Do not introduce generic portfolio cards, panels, dashboard styling, shadows or unrelated accent treatment. Accessibility, responsive and interaction improvements are allowed when they preserve the artistic composition.

Browser comparison at representative desktop, tablet and mobile widths remains the authority for final visual acceptance.

## Artwork ordering and home selection

Category galleries use the persisted editorial `artwork.position` order. The migrated baseline positions reproduce the approved legacy sequence; later artist reordering may intentionally differ from chronology.

- published positions must be unambiguous within a category;
- no slug, ID, timestamp, insertion order, database order or `work_date` fallback may hide an ordering invariant violation;
- viewer previous/next uses the same canonical category sequence;
- unpublished/draft/hidden artwork is absent from public listings, direct views, sitemap and viewer sequences.

The Home surface selects the newest eligible artwork by canonical `work_date` across categories explicitly marked for Home eligibility. Category identity is persisted data, not a hard-coded slug set. If multiple eligible artworks share the newest date and no explicit accepted rule makes the winner unique, the ambiguity is an invariant failure rather than an invitation to use an accidental secondary sort key.

## Shared artwork presentation

Home, category galleries and artwork detail use one canonical artwork-label/placard presentation for factual artwork metadata:

- title;
- year when represented by normalized artwork date data;
- medium/material when authored;
- dimensions when authored.

Parenthetical title qualifiers may receive restrained typographic treatment without changing stored title data.

Gallery/Home presentation remains compact. Artwork detail may render additional editorial description/narrative separately from the shared compact label.

Every meaningful public artwork image requires canonical ALT data. An explicit usage-specific ALT override takes precedence over the asset-level ALT value; otherwise the asset-level value is required. Title, filename, legacy metadata or placeholder text is never a silent substitute for missing required ALT.

Canonical originals remain authoritative. Listing thumbnails and other public derivatives are generated assets. A required missing/corrupt derivative must fail explicitly rather than silently serving the original as an alternate thumbnail.

## Artwork detail

`/artworks/{slug}` is a first-class public page, not a fallback for the fullscreen viewer.

It provides:

- the primary artwork image as a viewer trigger;
- the same canonical artwork label used by Home/gallery;
- optional richer artwork-specific narrative;
- a data-driven return action to the owning category;
- previous/next artwork navigation following the canonical category order.

The category return action must not hard-code a specific category identity. Home may use the same restrained action language as a category CTA.

## Fullscreen artwork viewer

The enhanced viewer is an immersive artwork surface rather than a framed modal/card presentation.

### Presentation

- cover the entire visual viewport with a consistently black surface;
- leave no exposed strip of the underlying page;
- scale the artwork as large as practical within a small, even safety margin;
- do not render a visible artwork title/footer inside the fullscreen surface;
- keep controls neutral black/white/grey rather than introducing a blue/turquoise UI theme.

Top-right controls are compact and icon-only:

- Open artwork page;
- Close.

Bottom controls are compact:

- Previous;
- Zoom out;
- Reset/current zoom level;
- Zoom in;
- Next.

### Interaction

- open from artwork thumbnails/images while retaining usable no-JavaScript artwork links;
- close through the visible control and `Escape`;
- zoom using mouse/trackpad wheel, visible controls and keyboard;
- support pinch zoom and touch pan;
- support pointer/mouse drag pan when enlarged;
- allow a small, consistent pan overshoot beyond the image edge on each side so edge inspection does not feel artificially clamped;
- previous/next respects the current canonical published sequence and disables correctly at boundaries;
- double-click may be a desktop zoom shortcut when it does not conflict with other input;
- recalculate containment and pan/zoom geometry after resize/orientation changes;
- expose explicit loading and missing-media/error states rather than fallback content.

### Accessibility and state

The fullscreen surface is a controlled application state with usable focus management. Opening moves focus into viewer controls, background interaction/scroll is controlled while open, and closing returns focus to the activating element where practical. Controls have accessible names and visible focus states. The current artwork remains available to assistive technology through the canonical image ALT/context even though no visible title footer is displayed.

## CV, Contact and Exhibitions

### CV / Vita

`/cv` contains the biography/Vita surface, portrait, approved public email/social links, Contact presentation when applicable and liability disclaimer.

Historical legacy Exhibition rows are not duplicated as CV entries after normalization. Draft/unpublished biography data is never rendered publicly. CV has an independent public enabled state.

### Contact

The enabled form preserves the intended legacy fields:

- Name — required;
- Email — required;
- Website — optional;
- Comment — required.

Server-side validation, CSRF protection, rate limiting, abuse controls, safe mail handling, failure handling and logging minimization are required. Contact supports enabled, under-construction and hidden presentation states. Contact does not make CV public when CV is disabled and does not create another main-navigation item.

### Exhibitions

`/exhibitions` is independently enabled and managed. Exhibition records may include title, date/date range, kind, venue, location/address text, description, validated external/directions links and ordered media with at most one hero presentation image.

Exhibition presentation remains restrained and visually compatible with the public shell. Rich text uses the shared constrained safe-rendering boundary; arbitrary iframe/embed HTML is not accepted.

## Blog

Blog is a separately gated public feature.

When disabled:

- `/blog` and post routes are intentionally unavailable;
- Blog is absent from navigation and sitemap;
- draft/scheduled/editorial data remains intact in admin.

When enabled, publicly eligible posts use stable slugs and the same legacy-derived visual system as the rest of the site. Draft, unpublished and not-yet-public scheduled posts are excluded from public rendering and discovery. Blog rich text and media use the shared safe-content and MediaAsset boundaries.

## SEO and discovery

- every indexable page has one canonical HTTPS URL;
- canonical metadata never points to legacy query-form URLs;
- sitemap content follows actual feature/category/publication state;
- admin, workshop/development, draft, hidden and disabled-feature surfaces are excluded;
- robots policy preserves public discoverability without advertising internal surfaces;
- route-specific title/description/metadata and meaningful artwork ALT semantics are preserved or safely normalized from reviewed source evidence;
- a deliberate canonical new-application slug change may create a permanent generic redirect;
- unknown category/artwork/blog slugs return a safe not-found response without debug/database/internal disclosure.

## Legacy evidence and deliberate divergences

Preserve:

- artistic identity and public composition;
- verified content and factual artwork metadata;
- reconciled artwork ordering;
- original-media provenance;
- biography/portrait meaning and complete historical Exhibition information;
- intended Contact outcome;
- metadata/discoverability intent.

Deliberately improve or normalize:

- clean path-based canonical routes;
- generic data-driven categories;
- separate CV and Exhibitions destinations;
- reliable fullscreen artwork viewer with keyboard/touch/pointer navigation;
- stable direct artwork pages and richer artwork-specific context;
- safe rich text, links, media delivery and form handling.

Do not preserve:

- legacy insecure HTTP behaviour;
- broken Contact/links routes;
- unsafe SQL/auth/upload/parser behaviour;
- debug/warning/internal-error disclosure;
- public workshop/admin/development surfaces;
- legacy PHP dispatcher/query routing merely for compatibility;
- silent ordering/media/date fallbacks.

## Acceptance rule

Passing route/data tests is not sufficient public acceptance. Final acceptance requires real-browser comparison of the shell, representative galleries, artwork detail, fullscreen viewer, CV/Contact, Exhibitions and any enabled Blog surface, together with the release-candidate regression gate tracked in GitHub Issues.
