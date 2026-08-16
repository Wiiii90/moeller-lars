# Public implementation contract

This is the implementation contract for the first public-site slice. It is
derived from the repository documentation and the reviewed legacy source in
`P:/larsmoeller`. It defines public behavior and content semantics, not the
application or presentation technology.

## 1. Canonical public route map

All canonical public URLs are HTTPS URLs on the approved canonical host. The
paths below are the target paths; legacy query URLs are mapped in section 2.

| Public area | Canonical path | Content and behavior |
| --- | --- | --- |
| Home / latest work | `/` | The latest artwork across the legacy landing-page set: paintings, drawings, and prints. |
| Paintings | `/paintings` | Published paintings, newest first. |
| Prints | `/prints` | Published prints, newest first. |
| Drawings | `/drawings` | Published drawings, newest first. |
| Cyanotype | `/cyanotype` | Published cyanotype work, newest first. |
| Bichromate | `/bichromate` | Published bichromate work, newest first. |
| Litho | `/litho` | Published lithography/etching work, newest first. |
| Photo | `/photo` | Published photography, newest first. |
| Ignis | `/ignis` | Published Ignis-serial work, newest first. |
| Other | `/other` | Published other-photography work, newest first. |
| CV and exhibitions | `/cv` | The public CV/Vita and exhibition presentation, retaining the legacy page meaning and order. |
| Contact | `/contact` | Public contact form and its successful-delivery outcome. |
| Artwork direct view | `/artworks/{slug}` | Stable, shareable public artwork URL. It opens the same artwork presentation/viewer context as the gallery. |

The legacy navigation order is Paintings, Prints, Drawings, and CV &
Exhibitions. Contact is part of the intended public surface even though the
reviewed legacy header does not expose a working contact navigation route.
The additional print and photography routes are public dispatcher routes
verified in the legacy configuration and sitemap, even where they are not in
the main navigation.

There are no public blog routes while the blog is disabled. A disabled blog
must not appear in navigation, generated metadata, or the sitemap.

## 2. Legacy route and redirect mapping

Redirects from the legacy dispatcher must be permanent redirects to the
canonical target, preserving HTTPS and the canonical host. Query-string
routes must not remain the target site's public URL format.

| Legacy URL | Target | Classification |
| --- | --- | --- |
| `/` | `/` | Preserve entry point. |
| `/index.php` | `/` | Permanent redirect; the legacy Apache rule already intends the direct entry point to resolve to the root. |
| `/index.php?site=paintings` | `/paintings` | Permanent redirect. |
| `/index.php?site=prints` | `/prints` | Permanent redirect. |
| `/index.php?site=drawings` | `/drawings` | Permanent redirect. |
| `/index.php?site=cyanotype` | `/cyanotype` | Permanent redirect. |
| `/index.php?site=bichromate` | `/bichromate` | Permanent redirect. |
| `/index.php?site=litho` | `/litho` | Permanent redirect. |
| `/index.php?site=photo` | `/photo` | Permanent redirect. The legacy internal category is `photos`. |
| `/index.php?site=ignis` | `/ignis` | Permanent redirect. |
| `/index.php?site=other` | `/other` | Permanent redirect. |
| `/index.php?site=vita` | `/cv` | Permanent redirect; the legacy navigation labels this page “CV & Exhibitions”. |
| `/index.php?site=links` | none | Broken legacy route: advertised by the sitemap/config metadata but has no dispatcher case. It is a defect, not a compatibility requirement. |
| `/index.php?site=contact` | `/contact` | No working legacy dispatcher route was verified. The new route implements the intended contact behavior; this is not a claim that the legacy URL worked. |
| `/workshop/...` and administrative paths | none | Non-public/development or admin paths. Do not expose or redirect them into public content. |

The nine category URLs listed above are the required legacy-compatible initial
paths, not the maximum category set. Additional admin-published artwork
categories use `/{category-slug}`. Reserved application namespaces cannot be
category slugs. The legacy main navigation remains Paintings, Prints, Drawings,
and CV & Exhibitions; #45 does not add additional categories to that
navigation. Home latest-work remains exclusively paintings, drawings, and
prints.

Unknown `site` values and malformed dispatcher requests are not public
content. They must produce a safe not-found response or a documented redirect,
never a PHP warning, directory include, database error, or debug output.

### Contact behavior

The reviewed `html/contact.html` defines the intended fields: required Name,
required Email, optional Website, and required Comment, followed by a send
action. The form points at `inc/contact.php`, which is not present in the
reviewed root source, and no `site=contact` dispatcher case was found. The
missing route/handler is therefore a known legacy defect. The target must
provide the intended contact outcome with server-side validation and a safe
delivery/error result; it must not copy the legacy mail/configuration
implementation.

## 3. Public content ordering

- Category gallery listings order published artwork by artwork.position ASC,
  then work_date DESC NULLS LAST, then slug ASC. Explicit editorial ordering
  may intentionally place an older artwork before a newer one. The displayed
  year is derived from that date; the exact day is not part of the public
  display contract.
- The home page combines paintings, drawings, and prints and selects the
  newest record by date descending, matching the verified legacy landing query.
  It does not silently broaden the home query to the other subcategory routes.
- Duplicate or gapped legacy positions are tolerated. Explicit editorial
  reorder normalizes a complete category to contiguous positions; slug is the
  final stable tie-breaker when position and date are equal. The process must
  never silently substitute source ID, target ID, insertion order, or database
  order.
- The migration/reconciliation fixture must compare category counts, complete
  ordered result sets, same-date groups, and the home-page winner.
- Only published content is public. Draft/unpublished content is absent from
  listings, direct views, navigation, sitemap, and viewer previous/next
  sequences. Within a category, the viewer previous/next sequence follows the
  same curated order: position ASC, work_date DESC NULLS LAST, slug ASC.

## 4. Artwork presentation contract

Each gallery item renders, when available:

- a thumbnail;
- title;
- year;
- medium/material;
- dimensions; and
- optional description/comment.

The legacy source displays title and year on one line, medium followed by
dimensions when dimensions are non-empty, and the optional comment below.
The target may improve markup and spacing subtly, but must retain this
information and its meaning.

Every meaningful artwork image requires ALT text derived from the artwork
title, with a safe fallback only when the title is genuinely unavailable.
Loading indicators, close controls, and decorative interface images must have
appropriate accessible names or be hidden from assistive technology; they
must not use artwork titles as decorative ALT text.

The thumbnail and original are two references to the same artwork asset
relationship: the legacy source uses the same logical filename under a
category thumbnail directory and a category original-media directory. The
target retains the original/full-resolution asset and may generate derivatives;
a derivative must never replace the original or become the only copy. Opening
an artwork from a thumbnail loads the corresponding original/full-resolution
media, subject to safe media failure handling.

Missing, corrupt, oversized, or unavailable media must not break the gallery
page. The item needs a stable fallback state and the rest of the page must
remain usable.

## 5. Viewer behavioral contract

The viewer is an artwork-first overlay/presentation with a loading state and
an explicit close control.

### Required interactions

- Open by activating an artwork thumbnail or artwork direct-view link.
- Close with the visible close control and `Escape`; preserve the legacy
  double-click close behavior only if it does not conflict with touch or
  assistive input.
- Zoom with mouse wheel and trackpad wheel gestures.
- Provide visible `+` and `−` zoom controls. They must be operable by keyboard
  and have accessible names.
- Support pinch zoom and touch pan on touch devices.
- Support pointer/mouse drag pan for an enlarged image. The legacy script
  currently restricts dragging to one axis in places; two-dimensional panning
  is an allowed usability improvement where needed to keep the full artwork
  reachable.
- Provide previous/next artwork navigation within the current published
  ordered sequence, with disabled/clear boundary behavior. This is a tested
  improvement over the legacy source, which has no verified previous/next
  controls.
- Support keyboard focus, activation, and `Escape`; do not require a mouse or
  hover-only gesture.
- Recalculate containment and usable zoom/pan bounds after viewport resize and
  orientation changes.

### Focus and accessibility

- Opening moves focus into the viewer and exposes it as a named modal/dialog or
  equivalent application state.
- Focus remains usable within the viewer controls while it is open and returns
  to the activating thumbnail/link on close.
- The close, zoom, previous, and next controls have visible focus styles and
  accessible names. The image has the artwork ALT text and the current artwork
  title is available to assistive technology.
- Background page interaction and scroll are controlled while the viewer is
  open, then restored on close.
- The page remains usable without JavaScript: thumbnails/direct-view links must
  still expose the artwork and metadata, even if enhanced zoom/pan is absent.

The legacy source verifies click-to-open, a loading indicator, wheel scaling,
`+`/`−` key scaling, drag behavior, a close cross, and double-click close. It
does not verify pinch zoom, touch navigation, `Escape`, focus management, or
previous/next navigation; those are deliberate, subtle improvements and
must be covered by responsive and interaction tests.

## 6. Responsive public shell

- Preserve the established public composition, artwork-first presentation,
  navigation labels/order, typography intent, and readable metadata at desktop
  and mobile widths.
- The shell must adapt without clipping artwork metadata or making the viewer
  unusable. The legacy source has a distinct small-screen image sizing path;
  mobile behavior must be tested rather than inferred from desktop layout.
- Navigation, artwork listings, CV content, Contact, and viewer controls must
  remain operable with touch and keyboard input.
- Responsive improvements are allowed when they preserve the site's artistic
  identity and information hierarchy. No generic site-builder or unrelated
  public layout is implied by this contract.

## 7. SEO and public metadata

- Every indexable public page has one canonical HTTPS URL on the canonical
  host. Canonical links must use the target path, never the legacy query form.
- Artwork direct views use stable slugs. A deliberate published slug change
  creates a permanent redirect from the old slug; a published artwork does not
  silently lose its shareable URL.
- Generate the sitemap from the target public route/content model. Include the
  home, public category routes, CV, Contact when available, and published
  artwork direct views. Exclude admin, workshop/development, drafts, broken
  legacy routes, and disabled blog routes.
- Preserve the intent of the legacy permissive `robots.txt`, but do not allow
  it to advertise or expose admin, workshop/development, draft, or disabled
  blog surfaces. The sitemap URL must be the canonical HTTPS sitemap URL.
- Preserve verified page title, language, author, description, keywords/other
  metadata intent, favicon, and artwork ALT metadata where semantically
  applicable. The legacy metadata values are source material for the route
  fixtures; unsafe or malformed legacy output is not a requirement to copy.
- There are no public blog routes, blog navigation items, or blog sitemap URLs
  while blog enablement is disabled.

## 8. Preserve, improve, and do not preserve

### Behavior to preserve

- The public root and artwork/CV navigation identity.
- The verified artwork categories and their public route meanings.
- Artwork-first listings with title, displayed year, medium, dimensions, and
  optional comment.
- Category date-descending order and the home latest-work behavior.
- Thumbnail-to-original artwork relationship and image loading/viewer intent.
- The CV/Vita portrait, authored content meaning, links, and intended order.
- The intended Contact fields and successful-delivery outcome.
- Page identity, language/ALT metadata, favicon, sitemap intent, and public
  discoverability.

### Subtle improvements allowed

- Clean path-based canonical URLs and permanent redirects from query URLs.
- Deterministic explicit position for equal-date ordering after reconciliation.
- Reliable viewer loading/error states, two-dimensional pan, touch/pinch,
  keyboard/Escape support, focus management, and previous/next navigation.
- Responsive handling that improves readability and touch operation without
  changing the artistic composition.
- Stable artwork slugs and direct sharing.

### Known bugs not to preserve

- The legacy HTTP-only redirect/asset behavior; HTTPS is canonical.
- The sitemap's HTTP/query URLs and its `links` entry with no working route.
- The unreachable Contact dispatcher path and missing `inc/contact.php`
  handler; preserve the intended outcome, not the broken implementation.
- Undefined same-date ordering or reliance on database/insertion order.
- PHP warnings, directory includes, database errors, debug output, or exposed
  internal details for invalid routes or failed media.
- Unsafe legacy SQL construction, upload handling, formatting/parser behavior,
  authentication/session behavior, third-party asset assumptions, and legacy
  credentials/configuration.
- Public workshop/development or administrative surfaces.
- Any public blog route while blog enablement is disabled.

## Implementation readiness

This contract is sufficient to begin the public implementation slice for
issues #9–#16: route/shell work, category and latest-work rendering, artwork
metadata/media presentation, viewer behavior, CV/Contact surface, responsive
behavior, and SEO/redirect fixtures. The local repository does not contain the
issue titles, so this document intentionally maps the implementation scope
without inventing issue-specific titles.

The remaining browser crawl, screenshot comparison, and migration
reconciliation are acceptance and cutover work, not blockers to implementing
against these routes and behaviors.
