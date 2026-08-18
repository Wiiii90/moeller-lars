# Public implementation contract

This is the implementation contract for the first public-site slice. It is
derived from the repository documentation and the reviewed legacy source in
`P:/larsmoeller`. It defines public behavior and content semantics, not the
application or presentation technology.

## 1. Canonical public route map

All canonical public URLs are HTTPS URLs on the approved canonical host. The
new application uses modern path-based routes; legacy dispatcher/query syntax
is historical evidence, not a required public interface.

| Public area | Canonical path | Content and behavior |
| --- | --- | --- |
| Home | `/` | Home artwork selected from persisted category presentation data. |
| Artwork category | `/{category-slug}` | Any published category, ordered by its persisted presentation data. |
| Artwork direct view | `/artworks/{slug}` | Stable, shareable public artwork URL with the gallery viewer context. |
| CV | `/cv` | Biography/Vita, portrait, public contact details, Contact surface and liability disclaimer. |
| Exhibitions | `/exhibitions` | Independently managed exhibition history with optional media, links and directions. |
| Contact | `/contact` | Optional direct route to the same public Contact feature when enabled. |

Navigation is generated from published categories marked for navigation plus
independently enabled CV, Exhibitions and later Blog destinations. Home
eligibility is generated from persisted category presentation data. Imported
data may initially reproduce familiar Paintings, Prints, and Drawings labels,
but those are editorial records, not application route definitions. A category
created and published in admin must use the same route, navigation and home
pipeline without a code change.

There are no public blog routes while the blog is disabled. A disabled blog
must not appear in navigation, generated metadata, or the sitemap.

The legacy artwork shell is the initial visual baseline rather than inspiration
for a redesign. Desktop header/name/navigation geometry, the continuous header
separator, artwork/content widths, typography, spacing, category headings and
artwork metadata should first be restored as closely as practical to the
reviewed legacy presentation. Subsequent nuance or modernization is a separate
editorial decision. Reliability/accessibility improvements must not silently
replace the legacy composition with cards, panels or a generic portfolio theme.

## 2. Legacy routes and new-application redirects

Legacy dispatcher and query forms are historical source evidence. They do not
need to remain reachable, and this contract contains no compatibility map for
`/index.php?site=...`. Any redirect lifecycle in the new application is
separate: when a canonical new-application category or artwork slug changes,
generic redirect records may preserve that new-application URL.

`/workshop/...` and administrative paths remain non-public and must not be
exposed or redirected into public content.

Unknown legacy selector values and malformed dispatcher requests are migration
evidence, not new-application routes. Unknown new category slugs must produce
a safe not-found response, never a PHP warning, directory include, database
error, or debug output.

### Contact behavior

The reviewed `html/contact.html` defines the intended fields: required Name,
required Email, optional Website, and required Comment, followed by a send
action. The form points at `inc/contact.php`, which is not present in the
reviewed root source, and no `site=contact` dispatcher case was found. The
missing route/handler is therefore a known legacy defect. The target provides
the intended contact outcome with server-side validation and a safe
delivery/error result; it does not copy the legacy mail/configuration
implementation. Contact belongs to the CV/biography public experience and does
not require another primary-navigation item. Persisting submitted messages in
an admin inbox is a separate privacy/retention product decision and is not
implied by the delivery form.

## 3. Public content ordering

- Category gallery listings order published artwork by the explicit persisted
  `artwork.position` value. For the migrated legacy baseline those positions
  reproduce the reconciled legacy date-descending display sequence; later
  editorial reordering may intentionally override chronology. `work_date`,
  slug, IDs, timestamps, insertion order, and database order are never runtime
  secondary ordering fallbacks.
- Published artwork positions must be unique within a category. Legacy
  duplicate positions are migration/reconciliation input and must be resolved
  before the affected category is considered publish-ready. Gaps are harmless;
  explicit editorial reorder normalizes a complete category to contiguous
  positions.
- The home page selects the newest eligible record by `work_date` from
  categories whose persisted presentation data marks them for the home surface.
  The imported data may initially reproduce the familiar legacy landing
  selection, but the selection is not based on hardcoded category slugs.
- If more than one eligible artwork has the same newest canonical `work_date`,
  the home winner is ambiguous and the invariant must fail explicitly. Position,
  slug, IDs, timestamps, insertion order, or database order must not silently
  decide the winner.
- The migration/reconciliation fixture must compare category counts, complete
  ordered result sets, same-date groups, and the home-page winner.
- Only published content is public. Draft/unpublished content is absent from
  listings, direct views, navigation, sitemap, and viewer previous/next
  sequences. Within a category, the viewer previous/next sequence follows the
  same canonical `position` order and no secondary tie-break ordering.

## 4. Artwork presentation contract

Each public gallery item renders:

- its required canonical thumbnail;
- title;
- year when a normalized `work_date` exists;
- medium/material when authored;
- dimensions when authored; and
- optional description/comment.

The legacy source displays title and year on one line, medium followed by
dimensions when dimensions are non-empty, and the optional comment below.
Initial public acceptance targets a close visual reproduction of those entries,
including their width, positioning, typography and spacing. Markup may be safer
and more semantic internally, but the listing must not acquire a new card/grid
visual language.

Every meaningful public artwork image requires canonical ALT data. A
usage-specific `artwork_media.alt_text_override`, when explicitly present,
intentionally overrides the asset-level ALT value; otherwise the asset-level
ALT value is required. This is an explicit editorial precedence rule, not a
recovery fallback. Artwork title, filename, legacy metadata, or empty
placeholder text must never be substituted for missing required ALT data.

The thumbnail and original are two required references derived from the same
artwork asset relationship. The legacy source uses the same logical filename
under a category thumbnail directory and a category original-media directory.
The target retains the original/full-resolution asset and generates controlled
derivatives; a derivative never replaces the original or becomes the only copy.
Opening an artwork from a thumbnail loads the corresponding canonical original.
A missing required derivative must not silently fall back to the original.

Missing, corrupt, unavailable, ambiguous, or otherwise invalid required public
media is an invariant failure and must be surfaced explicitly rather than
replaced with another media source, empty URL, placeholder content, or legacy
value. Transient delivery failures after valid public data has been selected may
still be represented by the viewer's explicit loading/error state; that error
state is diagnostic behavior, not alternate successful content.

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
previous/next navigation; those are deliberate, subtle improvements.

## 6. CV, Exhibitions and responsive public shell

`/cv` contains the biography/Vita only, with the verified portrait, biography
content, public email/approved social links, Contact form/status when enabled,
and the liability disclaimer. Historical exhibition rows from the legacy Vita
must not remain duplicated as CV entries after normalization.

`/exhibitions` is independently enabled and managed. Exhibition records support
title, date/date range, kind, venue, location/address, description, external
links, optional Directions URL, and multiple ordered media assets with at most
one hero designation. Richer exhibition presentation must remain visually
compatible with the site rather than becoming an unrelated card design.

The public shell preserves the legacy composition at desktop and mobile widths.
The header is not constrained to the narrower artwork column: on desktop the
artist name and navigation occupy the top header region and the separator spans
the window width. At the reviewed small-screen breakpoint navigation stacks
vertically and artwork content becomes full width. Browser comparison remains
the authority for final spacing/position tuning.

## 7. SEO and public metadata

- Every indexable public page has one canonical HTTPS URL on the canonical
  host. Canonical links must use the target path, never the legacy query form.
- Artwork direct views use stable slugs. A deliberate published slug change
  creates a permanent redirect from the old slug; a published artwork does not
  silently lose its shareable URL.
- Generate the sitemap from the target public route/content model. Include the
  home, public category routes, independently enabled CV and Exhibitions,
  Contact when available, and published artwork direct views. Exclude admin,
  workshop/development, drafts, broken legacy routes, and disabled blog routes.
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

- The public root and artwork navigation identity.
- The verified artwork categories and their public route meanings.
- Artwork-first listings with title, displayed year, medium, dimensions, and
  optional comment.
- The reconciled legacy gallery sequence and the chronological home latest-work
  behavior.
- Thumbnail-to-original artwork relationship and image loading/viewer intent.
- The CV/Vita portrait and biography meaning plus all verified historical
  exhibition information, normalized into its separate Exhibition domain.
- The intended Contact fields and successful-delivery outcome.
- Page identity, language/ALT metadata, favicon, sitemap intent, and public
  discoverability.

### Subtle improvements allowed

- Clean path-based canonical URLs; redirects are created only for deliberate
  new-application slug/path changes or a separately evidenced external-link
  need.
- Explicit normalized positions for the complete curated category order after
  legacy reconciliation.
- Reliable viewer loading/error states, two-dimensional pan, touch/pinch,
  keyboard/Escape support, focus management, and previous/next navigation.
- Responsive handling that improves readability and touch operation without
  changing the artistic composition.
- Stable artwork slugs and direct sharing.
- Separate CV and Exhibitions destinations, because they are independent target
  editorial concepts even though the legacy Vita displayed them together.

### Known bugs not to preserve

- The legacy HTTP-only redirect/asset behavior; HTTPS is canonical.
- The sitemap's HTTP/query URLs and its `links` entry with no working route.
- The unreachable Contact dispatcher path and missing `inc/contact.php`
  handler; preserve the intended outcome, not the broken implementation.
- Ambiguous or duplicate public ordering masked by dates, slugs, IDs,
  insertion order, or database order.
- Missing normalized dates or media metadata masked by legacy/raw values,
  alternate media sources, generated placeholders, or unrelated fields.
- PHP warnings, directory includes, database errors, debug output, or exposed
  internal details for invalid routes or failed media.
- Unsafe legacy SQL construction, upload handling, formatting/parser behavior,
  authentication/session behavior, third-party asset assumptions, and legacy
  credentials/configuration.
- Public workshop/development or administrative surfaces.
- Any public blog route while blog enablement is disabled.

## Implementation readiness

The public implementation is not accepted merely because route/data tests pass.
Cutover requires browser comparison against the legacy site for the artwork
shell, header/navigation and representative gallery entries. CV and Exhibitions
are deliberate target divergences and are reviewed against their explicitly
separated product contracts rather than pixel-matched to the old combined Vita.
