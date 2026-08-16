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
| CV and exhibitions | `/cv` | Public CV and exhibition presentation with the intended authored meaning and order. |
| Contact | `/contact` | Public contact form and its successful-delivery outcome. |

Navigation is generated from published categories marked for navigation. Home
eligibility is generated from persisted category presentation data. Imported
data may initially reproduce familiar Paintings, Prints, and Drawings labels,
but those are editorial records, not application route definitions. A category
created and published in admin must use the same route, navigation and home
pipeline without a code change.

There are no public blog routes while the blog is disabled. A disabled blog
must not appear in navigation, generated metadata, or the sitemap.

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
missing route/handler is therefore a known legacy defect. The target must
provide the intended contact outcome with server-side validation and a safe
delivery/error result; it must not copy the legacy mail/configuration
implementation.

## 3. Public content ordering

- Category gallery listings order published artwork solely by the persisted
  `artwork.position` value. Explicit editorial ordering may intentionally place
  an older artwork before a newer one. `work_date`, slug, IDs, timestamps,
  insertion order, and database order are never secondary ordering fallbacks.
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
The target may improve markup and spacing subtly, but must retain this
information and its meaning.

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
- The artist-curated category order and the chronological home latest-work
  behavior.
- Thumbnail-to-original artwork relationship and image loading/viewer intent.
- The CV/Vita portrait, authored content meaning, links, and intended order.
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

This contract is sufficient to begin the public implementation slice for
issues #9–#16: route/shell work, category and latest-work rendering, artwork
metadata/media presentation, viewer behavior, CV/Contact surface, responsive
behavior, and SEO/redirect fixtures. The local repository does not contain the
issue titles, so this document intentionally maps the implementation scope
without inventing issue-specific titles.

The remaining browser crawl, screenshot comparison, and migration
reconciliation are acceptance and cutover work, not blockers to implementing
against these routes and behaviors.
