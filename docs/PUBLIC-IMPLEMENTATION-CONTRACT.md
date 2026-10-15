# Public implementation contract

This document defines durable public behavior for the current application. Detailed legacy observations used for cutover comparison live separately in [LEGACY-PUBLIC-CONTRACT.md](LEGACY-PUBLIC-CONTRACT.md).

## Canonical route model

Public routing is driven by persisted typed Site Nodes rather than hard-coded legacy page/category names.

| Surface | Canonical path | Rule |
| --- | --- | --- |
| Home | `/` | Singleton Home presentation. |
| Gallery | `/{section-slug}` | Published Gallery Site Node backed by Gallery persistence. |
| Journal | `/{section-slug}` | Published active Blog or Exhibitions Journal. |
| Journal entry | `/{section-slug}/{entry-slug}` | Public entry belonging to matching active Journal. |
| Custom Page | `/{section-slug}` | Published structured Custom Page. |
| Artwork detail | `/artworks/{slug}` | Stable shareable Artwork view. |
| Navigation Node | none | Structural navigation only. |

Protected preview equivalents live below `/preview` and do not make draft/private content publicly discoverable.

Protected media equivalents live below `/preview/media/...`; authenticated preview may render unavailable-to-public assets without changing ordinary public-media eligibility.

## Publication and discoverability

Only publicly eligible content appears in public rendering/navigation/sitemap/viewer sequences.

- Home is public according to its active presentation/gate behavior;
- content headings are opt-in on Home, Gallery, Journal (Blog and Exhibitions) and Custom Page. The shared `public/page-heading` component renders the inherited or overridden heading, alignment and optional divider. Page headings use one compact, readable serif editorial title in authored case, visually related to the menu and artist name without being a duplicate menu label. The shared heading has a reduced vertical footprint and no synthetic text-shadow. The saved alignment and divider choice remain authoritative; absent dividers create no implicit rule. The four existing divider styles (`thin`, `subtle`, `strong`, `dotted`) are drawn centrally in `custom-pages.css` for **both** headings and Custom Page Divider components: thin has a tapered graphite line, subtle a shorter faded impression, strong a single weightier stroke, dotted fine separated marks with soft ends. No redundant second navigation title, extra divider model or new admin controls. Journal index templates must not inject a second title or an implicit divider before their first item;
- Gallery/Journal/Custom availability follows Site Node publication state;
- Navigation Nodes have no content page;
- Journal entries satisfy active template + lifecycle requirements;
- draft/private content is only available through authenticated preview/admin boundaries;
- unknown/malformed routes fail safely.

A canonical media **reference** does not automatically mean the asset is publicly deliverable.

## Rights and privacy

Public HTML/media responses declare a TDM rights reservation with the
`tdm-reservation: 1` header and `/.well-known/tdmrep.json`. The public
`robots.txt` excludes documented model-training/data collectors while
preserving search indexing and the sitemap. These signals do not block
human access to high-quality Artwork Viewer originals. No additional
footer, menu entry, visual copyright overlay or quality downgrade is part
of this contract. Artist-authored texts may live on the About/Contact
Custom Page; Matomo consent is owned by the website, not the artist's
editable content. See `docs/PUBLIC-RIGHTS-AND-PRIVACY.md`.

## Site navigation

Public visits use the already installed Livewire 4 `wire:navigate` document-swap
runtime over the existing Blade routes. The public layout loads its own
Livewire 4 CSP-safe asset endpoint (`/site-assets/livewire-csp.js`) so that
the strict no-`unsafe-eval` public CSP does not block the client runtime;
this deliberately does not change the Admin/Filament Livewire bundle or
globally enable CSP-safe Alpine evaluation. Only eligible same-origin public links
are opted in, including dynamically rendered content links through the
document-level Livewire.navigate() delegate; downloads, cross-origin links, anchors and admin/system paths
retain native browser navigation. The canonical server routes remain the
source of content/publication/preview authority. JavaScript page initializers
must dispose global listeners/observers on each document swap; Matomo records
one page view per visit and does not inject a second tracker script. The
public CSP remains nonce-based and does not enable `unsafe-eval`. The entire
Public CSS cascade is bundled through the single `resources/js/app.js` Vite
entrypoint (foundation, content, presentation, custom pages and journal);
the Public layout no longer requests individual CSS entrypoints or their
preloads. The Admin Vite entrypoints and Filament bundle stay unchanged.
General
layout CSS variables are reapplied from server-rendered metadata at the swap
boundary, so new nonces cannot invalidate the artist's configured background
or widths. This SPA transport is independent of future contour animation.

Navigation is generated from the canonical Site Node tree and persisted order/publication status, not hard-coded page arrays, legacy category assumptions or a separate menu-visibility toggle. Published nodes appear automatically; unpublished nodes remain publicly unavailable. Published children of an unpublished parent are promoted to public root navigation entries. The configured Skip Home routing option remains an explicit exception.

Main and child navigation use **one visible row** in the common public shell: entering a parent switches the rail to that parent's child-level navigation rather than expanding an additional row. A contextual parent-name return control with a distinct corner-up-left SVG icon and a fine separator leads back to the main level. Its full parent name is retained for accessibility and truncated visually at limited widths. The separate left/right chevrons are reserved for cyclic whole-item navigation. If the parent has its own public URL, its page is the first item inside the child level; navigation-only parents do not invent a page. The active parent or active child branch is rendered in its correct level by Blade on initial response, with no opening/closing animation or transition timer. The central menu rail retains the configured content/artwork width and its chevrons remain in outer padding. Every level uses one shared cyclic window algorithm: display only complete Site Node items, show no arrows when all fit, otherwise evict opposite-side items and refill remaining space. Items align left at natural widths without overflow; overflowing windows distribute across the rail. Visible windows survive ordinary internal page navigations via the destination-scoped session handoff, including the previous main window while browsing children; only a real width change may refit it. Selecting a page does not move its window. Child browsing uses the same selected, hover, keyboard and focus rules as main navigation; Escape or the explicit parent return action brings back the main level. No global outside-click dismissal, duplicated DOM menus, polling or separate child-selection algorithm is introduced. Overwide single items may wrap their own text. Public icons use explicit inline SVG paths instead of platform-dependent glyphs.

The public paper animation has ONE canonical owner:
`resources/js/public-folio-motion.js`. Existing Menu/Content contour
pseudos and their single backings remain the only visual primitives;
there are no timers, new DOM planes or secondary animation systems.

Menu and Content share the reduced baseline of 3px left and 4px down
(and a matching 3px right gap). Menu's existing connected L-shaped
backing remains flush with its upper edge. The white outlines sit
closer to both white surfaces, and all pale lines and backings use
lower opacity. General's configured widths remain authoritative.

Each page gets six distinct, stable corner connection points. They
are generated deterministically from the server-side route path in
`layouts/app.blade.php`, rendered in the CSP-nonced initial style, and
carried through the existing `data-public-layout` metadata across
Livewire SPA swaps. Reopening the same URL recreates the same visual
pose without client randomness, timers, history state or hydration flash.

Page swaps share a viewport-bounded 480ms height transition and a
620ms Content-only build. Its contour ends contract no more than 6px
top and 8px bottom; the exterior backing moves less than 1px per
axis. All excursions end at the new page's canonical static pose,
even on rapid repeat navigation. Home Hero arrows reuse the same
Content-only animation. Menu never performs a page-build impulse.

Ambient corners move only 1–2px, and backing motion is under .5px
on slow 98–145-second loops with extensive stationary periods.
Menu's upper stroke attachments remain fixed to the top, and its
left stroke stays shorter than the right. Distinct static page
compositions—not conspicuous perpetual motion—are the main effect.
Native heights, viewer and history remain under their existing owners.
The public visual composition remains artist-specific. Menu and Content retain their neutral-white surfaces and independent shapes. The shared neutral folio-edge palette (`--public-folio-light`, `--public-folio-soft`) in `public-presentation.css` controls their accents. The menu has a left/bottom trace and a distinct upward-running right edge, both outside its measured rail. No independent corner stickers or nested menu rectangles are allowed. Main and child levels keep one face and the existing cyclic window, widths, arrows and selection mechanics. The 15px native-serif menu words use opaque, plain, readable ink without text-clipped gradients or angled baselines; the active/hover stroke is a fine horizontal underline. The artist name stays at its accepted position with neutral silver-to-white letter variation; the full-width header separator retains its position and light modulation. The artist-configurable outer solid/gradient background remains independent. These presentation changes await browser approval.

## Home presentation

Home content uses one of three templates:

- **Hero Artwork** — deterministic hero Artwork from eligible public Gallery/Artwork sources;
- **Under Construction** — explicit gated structured component presentation;
- **Custom** — ordered structured Home components.

**Skip Home is routing state, not a content template.** It can be enabled independently of the selected Home content template and may point at an explicit eligible published top-level page. When no explicit valid target is stored, the target is the next eligible published top-level public page after Home in canonical Site Node order. If no eligible target exists, `/` renders the selected Home template rather than redirecting into an invalid route or loop.

Changing the Home content template does not implicitly enable, disable or retarget Skip Home. Existing legacy `skip_home` template rows are normalized to Hero Artwork plus enabled Skip Home routing. **Show in public navigation** is a separate Home-only setting in Pages → Home Edit, using the retained `site_sections.show_in_navigation` field. It defaults to off: `/` remains accessible through the artist-title link without a Home menu item. Turning it on displays Home in the public menu only while Skip Home is disabled. Skip Home always suppresses the entry, independently of this stored switch. Other page types continue to use publication state alone for menu inclusion. This control never affects admin navigation, publication or the root URL.

Artwork selection uses canonical Gallery eligibility, Artwork date and explicit tie-break semantics. Ambiguous invalid state is surfaced rather than resolved by incidental DB order.

- Hero Artwork Home uses the existing resolved eligible Home group and shared `artwork-card` renderer. `show_details` controls the artwork information label; `show_gallery_link` controls Gallery-link visibility when only one Hero is displayed; when `show_navigation` enables cyclic previous/next buttons for a group with multiple eligible Artworks, the centered Gallery link is always shown and follows the category of the currently visible Artwork, even if the single-Hero toggle is off; and `enable_viewer` allows or disables clicking the Hero media to open the existing Artwork Viewer. Artwork information and viewer remain independent switches in the existing Home settings; Gallery-link visibility has the navigable-group exception above. Existing saved defaults remain unchanged (information on, Gallery link off when unset, navigation off, viewer on). Without viewer activation the Hero image/video is not an interactive link. When navigation is off, only the current resolved Hero member is rendered. No new publication filtering or Home selection algorithm is introduced.

Home Custom/Under Construction and Custom Page share a single ordered public
component renderer and the `CustomPageSetting` component validation contract:
Image, Text (Heading/Rich Text), List, Divider (Thin/Subtle/Strong/Dotted),
Contact and Legal Disclaimer. Published state is independent per component
and per List/Contact child. Preview may show unpublished rows; normal public
requests suppress them. Contact form submissions are permitted when a
published enabled Contact Form belongs to an active, non-skipped Home
presentation or an eligible published Custom Page. Home and Custom media
references (including List media and child Rich Text) use the canonical
MediaAsset reference/publication contracts.

## Gallery delivery and loading

Gallery index markup keeps its complete canonical Artwork sequence (including
viewer order), but only its first image has `loading=eager` and
`fetchpriority=high`. Every following image uses the browser's native
viewport-buffered lazy loading, rather than competing at high priority for
the initial network connection. This is a targeted initial-display improvement,
not server cursor pagination or incremental HTML rendering. A future
unbounded-inventory design must consider cursor batches, prefetch buffers,
SEO/indexability, Artwork Viewer ordering and SPA back/forward restoration
together; it must not silently truncate the canonical viewer's sequence.

Public thumbnail URLs are generated MediaVariant resources served through
`EnsureMediaVariantFile` and `PublicMediaController`; deleted-but-rebuildable
variants can be regenerated synchronously during the first request.
A slow image therefore cannot be diagnosed from lazy-loading attributes
alone. Confirm response timing / variant existence and sendfile delivery in
the running preview before changing media correctness, publication access,
regeneration, headers or transport.

## Gallery and Artwork ordering

Gallery presentation uses persisted Artwork `position` as authoritative order.

- deliberate reorder persists;
- IDs/timestamps/database order are not runtime fallbacks;
- moving Artwork preserves canonical media relations;
- unassigned Artwork is not part of public Gallery sequence;
- published Artwork cannot remain public through invalid/missing/unpublished Gallery relation.

Viewer previous/next follows the same public Gallery order.

## Artwork presentation/media/viewer

Public Artwork exposes approved authored metadata and valid media.

Canonical originals remain authoritative; missing/corrupt/unavailable required media is readiness/integrity failure, not permission to choose unrelated fallback.

Informative public images require canonical ALT; explicit Artwork usage override takes precedence only where that consumer supports it.

Gallery Artwork primary media may be image or supported video. Video uses manual/native controls/no autoplay and shares close/sequence context without image-specific zoom/pan.

Artwork cards, Home Hero Artworks and Artwork detail pages render one shared handwritten-style exhibition label from `artwork-label.blade.php`. Its compact, flatter (nominally 15.5rem) paper slip has an intrinsically sized TITLE prompt followed immediately by its ruled handwritten entry, the same treatment for MATERIAL, and a shared short YEAR/SIZE row when both are authored. There are no fixed-width label columns; each writing rule begins after a small intentional gap from its own printed word. Printed prompts and the smaller handwritten values share the first text baseline rather than compensating with an arbitrary top offset. The authentic artist-authored values continue to use a legible locally available handwriting font stack; an optional NOTE remains a full writing line. Long titles and MATERIAL may use additional writing lines; YEAR and SIZE never break inside their own fields and may shift as whole fields onto a further row when space is genuinely insufficient. Missing optional fields are omitted, not replaced with empty invented values. There is no table-like field grid, ornamental ARTWORK heading, heavy border, simulated handwriting content, rounded corner, shadow, or external font dependency. On Artwork cards the whole label remains a detail link, independently of media-viewer behavior.

Artwork detail and Home Hero navigation are independent from the exhibition label. The single shared `artwork-navigation.blade.php` component renders a quiet, unboxed, **three-part image-width folio above the artwork**. Previous is pinned to the left edge of the image, a real link named from the artist's category (for example "Painting Gallery") is exactly centered, and Next is pinned to the right edge. The distracting total-position counter has been removed from markup and props, not merely hidden. Equal elastic outside columns keep the gallery name centered; long category names ellipsize rather than moving the arrows or overflowing narrow artwork widths. Empty structural slots preserve geometry when a neighbor does not exist or the Home Gallery-link toggle is disabled; they are never focusable fake controls. On Home Hero, existing cyclic JS direction buttons remain; Artwork detail uses the server-resolved neighboring Artwork URLs and omits absent arrows. Gallery/list cards without configured navigation display only the artist-authored label below the image. The label itself remains independent and unchanged. The centered typographic folio adopts the established editorial serif used by the menu, but does not borrow the form-label grid, add card frames, expand artwork widths, use layout-jumping selections, or introduce CSS viewport-specific overrides. The artist's configurable outer solid/gradient remains authoritative; a neutral legacy-like grey is a candidate for the artist's cutover setting, not a CSS default override. No new admin controls, remote fonts, timers, CI or tests.

The image viewer supports close/Escape, visible zoom, wheel/trackpad zoom, touch pinch/pan where supported, pointer pan when enlarged, previous/next with boundaries, viewport recalculation, loading/error state and correct accessibility/focus behavior.

## Rich Text public rendering

Canonical Rich Text public rendering uses Markdown through `SafeRichTextRenderer`.

Embedded Storage media images use canonical `media:<id>` references resolved by central media rendering. Arbitrary external-image URLs and legacy Journal inline-token runtime syntax are not equivalent public formats.

## Custom Pages, CV and Contact

Custom Pages are ordered structured components validated by `CustomPageSetting`. CV/Vita is represented through Custom Page/CV composition rather than a special runtime node type. The root component sequence and eligible Image-child sequence are independently editable, publishable and reorderable in Admin.

An Image marked `image_decorative` can own one ordered level of supported children: Text, image-free List, Divider, Contact details (Email and Social links, not Form), and Legal Disclaimer. The Image controls only its own media settings. Each child remains a complete, independently editable component row with its own Position, Type, Content, five Actions and Selection, using the SAME row partial as root components. Storage uses `image.children` but this does not move child editing into Image Edit. Native parent/child sorting and its domain guards are owned by the [Admin Table Contract](ADMIN-TABLE-CONTRACT.md#component-sequences), not a second Public drag specification. Child publication, ordering and parent changes remain independent. Validation forbids child Images, nested Contact Forms, image-bearing Lists and second-level nested content; the Legal Disclaimer remains unique across the entire page.

The public image and its children render in ONE native float formatting context. The independent `image_alignment=left|right` setting applies to **both decorative and standalone** Images (historical Left is implicit and not redundantly stored). When there are children, the Image floats on the selected side; child Text/List/Contact/Legal content follows beside it and then below at the actual available inline width, with no forced columns. A child Divider is permitted, uses the existing central variant renderer and establishes its own flow root so its rule does not run underneath the float. Standalone Images use alignment without acquiring a float or a child group. A subsequent top-level Contact component (including its Contact Form) starts after that image group and uses fully wide, single-column fields. A regular Image remains an independent image block. An Image without children does not automatically absorb subsequent sibling content.

The CV List keeps Context and Title inline in semantic records so they naturally wrap around image floats. An undated entry has no synthetic context or empty column. List Details, publication filters and optional image remain owned by the existing generic List component. Contact is a reusable structured component on Custom Page and Home, not a standalone runtime page type. Its public presentation uses understated editorial type and a compact two-column Name/Email form when width permits; form validation, honeypot, delivery and reporting semantics remain unchanged. The Legal Disclaimer remains General-owned text, presented as a restrained colophon with a neutral title; the editorial component does not invent new legal copy.

Public Contact workflow validates server-side and enforces honeypot/rate-limit controls. It saves a ContactMessage before attempting mail delivery to the configured private recipient; unavailable or failed SMTP does not roll back the stored message and the visitor still sees the received outcome. Visitor email is Reply-To, not forged From. Retention, logs and consent boundaries belong to the [source-verified privacy flow](architecture/public-security-privacy-boundaries.md#6-public-datenschutz-getrennte-kontakt--tracking--und-kartendatenflüsse).

General owns public email/visibility, social links, favicon, private Contact recipient and truly global legal/public text. SMTP/DKIM/TLS remain runtime/platform secrets.

## Journals

### Template switching

Blog and Exhibitions are separate content products sharing Journal placement/settings mechanics.

Switching the active Journal template does not convert/delete retained inactive rows. Public rendering exposes only the active template's eligible content; retained inactive content remains canonical editorial/reference data and can reappear when switched back.

### Blog

A Blog Journal lists only public Posts belonging to it and satisfying lifecycle/schedule. Body uses canonical Rich Text Markdown/media references. Structured Cover/Gallery media use canonical Journal media policy.

### Exhibitions

An Exhibitions Journal lists public Exhibitions belonging to it.

Canonical editorial/public concepts include title, dates, vernissage/opening, venue/venue website, separate Street/City/Country, rich description, external link, optional Gallery and optional Map.

Street is stored/displayed separately from city/country; public address composition avoids duplication.

#### Gallery

Gallery is an explicit per-Exhibition presentation feature.

- `gallery_enabled=true` permits ordered structured Gallery usages to render publicly subject to normal entry/node/media readiness;
- `gallery_enabled=false` hides Gallery presentation without deleting usages;
- disabled Gallery assets remain referenced but are not publicly deliverable merely because the Exhibition is published;
- Cover publication is independent of Gallery enabled state.

#### Map

Map is an explicit per-Exhibition presentation feature.

- `map_enabled=true` requires valid geodata;
- public Map visibility is not inferred merely from coordinates or timing;
- there is one content-responsive public dialog, with no editable map aspect-ratio or per-entry shape;
- `ExhibitionMapPresentation` owns canonical map source/presentation data;
- public dialog and editor preview consume the same coordinates and URLs, though each has its own visual composition;
- a future Rich Text map embed must reuse that renderer/contract rather than create a second mapping technology.

### Public Blog Journal stream

Blog and Exhibitions share the canonical Journal owner, publication/preview
query, structured Cover/Gallery media, rich-text rendering and editorial
audit/notifications, not a forced identical public layout. Blog's index is
an editorial stream: publication date (when set), linked article title, optional
cover, persisted excerpt (or escaped text-only extract), and an explicit
**Read more** link. Blog detail is its own readable article with date, title,
uncropped structured media (ALT and attribution data retained without visible figure captions), sanitized body, ordered Gallery and a preview-safe return link. The Blog index is a reading sequence with fixed serif typography,
compact original-ratio covers, bounded excerpts and explicit article links.
Illustrated entries alternate image side by illustrated-entry order; prose
flows beside and beneath an uncropped work. Text-only entries create no empty
cover slots. A content-fit threshold stacks the layout without changing fonts.

Every public page, including Home, Gallery, Blog, Exhibitions, artwork detail
and Custom Page, shares the intentionally asymmetric *Atelier sheet* on its
existing neutral-white, artist-configured `#content` shell. The common hard-
edged external contours belong to the shell owner in `public-presentation.css`,
not to a Journal content selector or copied page-specific CSS. They consume no
Content width, disappear on narrow viewports, and never change artist-configured
background, reading insets or media size. Only the Blog's actual artworks have
small square-edged double-line mounts. The additional Gallery item corners have been rejected and entirely removed:
artwork images and existing labels are separated by their natural white space.
Repeated Exhibition entry separators and decorative timeline rules were rejected.
The revised native disclosure adds **exactly two clean lines only while an entry is open** (above and below its full contents), without permanent dividers between closed entries.
Neither surface gets framed records, date-axis timelines, decorative stickers or repeated corner ornaments.
No artwork is cropped or covered.
The existing artist name and navigation retain their independent layout owners.

The layered outer-sheet silhouette and the Blog's small angled corner strokes
on individual entries are the only accepted new interior accents. These Blog
strokes may enter the configured content padding but never displace text into
it; they alternate to the left or right with the cover composition. The
translucent headline washes, Exhibition date marks, heading backgrounds,
open-body corner strokes and tinted fact panels were browser-rejected and
removed. Do not restore them by accumulating overrides. Exhibition details now use one source-defined float/prose/visit composition, rather than the rejected paired/solo row variants; browser acceptance remains open. Do not revive numbering, date-axis nodes, graphical stickers,
clipped/castle corners, rounded corners, chips, repeated bordered entries,
fake old-paper textures, decorative bars, duplicated underlines or extra
Admin controls. Neither Journal view renders a timeline. The presentation is
reversible without changing Journal storage, publication or media contracts.

Blog listing and article Cover and shared Journal Gallery figures as well as Exhibition Cover/Gallery figures now **hide all visible figure captions** via `JournalMediaRenderer::render(..., showCaption: false)` (`dev@f58db29e`). This is a **presentation-only** choice: `MediaAsset` ALT, credit, copyright mode/override, rights projection, media references and Publication history are unchanged. Rich Text remains independently authored; do not generalize this flag to all public media or remove metadata. The Exhibition copyright footer remains absent; no new global footer or site-wide policy is implied. Real browser and artist acceptance remain separate.

Blog gallery presentation is now an authored per-post setting with four options:
`sequence` (the existing uncropped, vertical image sequence, preserved as the
default for old posts), `grid`, `mosaic`, and `slideshow`. This persisted
setting is validated and updated through the canonical BlogEditorialService,
exposed through the common Journal Editor, and stored in both tracked
Publication schemas. `JournalMosaicColumns`, the shared `journal-gallery`
Blade component and the existing slideshow JS own all gallery arrangements.
Artwork images remain uncropped. The complete Blog detail is now an editorial
folio with a framed cover, structured rich-text reading measure, optional
gallery and return link; the accepted external sheet silhouette is retained.

No second Blog model, route, content source, publication path, editorial
action or settings control is introduced. Share only the outer-sheet identity
between Blog and Exhibitions; their internal editorial layouts remain distinct.

Journal typography has one fixed scale independent of the administrator's
chosen content width: Exhibition date 0.8rem, Exhibition title 1.34rem;
Blog dates 0.8rem, index titles 1.58rem and full-article titles 1.88rem.
Do not use content-width or viewport-relative title/date font sizing. Layout
may stack at content-fit boundaries, but the text does not change size.

### Structured Exhibition addresses

Exhibition Venue includes optional Street address, Postal code, City and Country.
The canonical public address joins postal code and city without repeating
location fragments, and Map geocoding consumes the structured components.
The 29 imported Vita/Exhibition rows originally retained an unstructured
`location_text`; normalize them locally with the preview-first,
local-only `php artisan exhibitions:normalize-addresses` and explicit
`--apply` only after applying the forward schema migration. Ambiguous
locations remain unchanged for individual review. Historic geocoding
coordinates are not silently rewritten, and no Admin action or publication
snapshot is forged by this maintenance command.

### Exhibition chronology and detail disclosure

The public Exhibitions Journal is an exhibition chronology, not an unbounded
list of expanded media. Its entries preserve the artist's manual
`position` order, including historical records with a free-form display date;
temporal state labels (Current / Upcoming) do not reorder that editorial list.

The closed entry presents the authored date, exhibition title and venue/place
as an open **exhibition catalogue**, not a timeline or a set of rectangular
cards. Dates and titles share the measured reading area with generous whitespace
and no fabricated image substitutes, vertical date spine, connection nodes or
repeated horizontal separator rules. The artist's manual exhibition order and
the fixed typography remain authoritative.

Optional material uses independent native `details/summary` disclosures. For pointer interaction, **only the Exhibition title is the disclosure target**; neither the date nor venue/location triggers opening and there is **no visible Open/Close switch**. The native summary retains keyboard/focus semantics, entries without additional material are non-interactive, and multiple exhibitions may remain open. An open disclosure has **one clean rule above and one below** the complete folio, no extra internal borders or cards. These are public presentation changes, not edits to persisted dates, media or publication state.

Expanded content reuses the canonical cover, rich description, ordered
Gallery presentations (grid, mosaic or slideshow), vernissage, address and
external link contracts. Opened Exhibition folios compose their available
content rather than alternate images by row parity:

- The opened Exhibition feature uses **one flow-root composition**: a bounded, uncropped Cover floats left when its actual container is wide enough and text/visit facts naturally follow beside and below it. Narrow compositions stack. Text-only entries retain their reading measure. No arbitrary paired/solo row variants or empty columns.
- Grid preserves the authored source sequence; Mosaic distributes images across two measured vertical lanes, preserving lane order without cropping. **Neither mode displays image description, credit or copyright as a visible figure caption.** Stored credits/rights and real ALT remain intact.
- Slideshow uses a single bounded, uncropped image stage (one grid cell for the active image) with the **controls on a separate row underneath**, not overlaid or in a shifting caption region. Existing slideshow JS owns slide visibility/position; no new timer, Gallery setting or image cropping. The controls and stage must be checked in a real browser across portrait/landscape and narrow widths.
- The optional `Show map` action stays within the information area and opens the existing on-demand Leaflet dialog. External destinations alone carry the external-link cue.


No new Administration customization, Exhibition detail route or duplicate
media source of truth is introduced.

The existing `MediaAsset` copyright modes, file attribution, ALT and publication guards remain authoritative and persisted. [`JournalMediaRenderer`](../app/Domain/Content/JournalMediaRenderer.php) now accepts an optional `showCaption` flag; the public Blog listing/detail covers, Exhibition covers and shared Journal Gallery presentations pass `showCaption: false`. This suppresses **all visible figure captions** on those surfaces, including description/credit/copyright text; it does **not** erase asset metadata, silently waive rights or suppress the accessible `img alt`. `includeInheritedCopyright` / `includeCopyrightNotice` remain distinct controls in that renderer for other consumers. The prior statement that explicit copyright/credit is still printed next to Exhibition works and Blog covers is no longer current. Actual on-screen behavior and rights-visibility decisions beyond these sources require browser/editorial acceptance.


Enabled Exhibition maps remain available for past, current and upcoming
exhibitions. A single **Show map** action opens a native, viewport-bounded
map dialog; inline map iframes, their Expand control, and nested map disclosures
are retired. The dialog presents the exhibition identity, automatically adapts to the
available viewport, and gives access to OpenStreetMap/Directions.
The map dialog has equal inset margins and a bounded inner three-row layout
(header, map, footer) on phone and desktop, with no nested scrollbar. The public
document is scroll-locked only while that modal is open, retaining its scrollbar
geometry so the main content does not jump. The map sizes its canvas from the
real available viewport, with
touch-sized zoom controls and container resizing handled through
`ResizeObserver` (no timers or polling); device rotation must not leave tiles
misaligned or the close/external controls outside the visible dialog.
The map uses a locally vendored, pinned Leaflet 1.9.4 runtime; the default
OpenStreetMap tile layer is requested **only after the visitor opens the
map dialog**, and it shows the required visible linked
**© OpenStreetMap contributors** attribution. OSM iframe extras (donations,
reporting, API terms) are not part of the new map UI; its tile attribution
is never hidden. The source files and BSD-2-Clause license live in
`public/vendor/leaflet/`. The public Content Security Policy permits exactly
the OSM raster-tile image origin, not third-party scripts. There is no new
gallery/map setting, geocoding workflow, map-scroll owner, timer or polling.
The obsolete `map_shape` selector, shape-dependent public classes and shape
validation have been removed. The historic database column stays inert only
until Publication schema compatibility allows its coordinated removal.
The Admin's existing map preview still consumes the canonical Exhibition map
presentation service.

## Public media delivery

Only media valid for the requesting public consumer is exposed.

- private/quarantined/deleted originals are not public substitutes;
- required variants must exist where required;
- public routes do not expose arbitrary storage paths;
- references remain authoritative when content moves/detaches/disables presentation/deletes;
- being accepted into Storage does not imply universal public-consumer support;
- protected preview routes do not weaken ordinary public eligibility.

Structured Journal Cover/Gallery runtime ALT uses canonical MediaAsset ALT. Legacy Journal usage overrides are not runtime public authority.

See [MEDIA.md](MEDIA.md).

## SEO and metadata

- indexable pages have canonical HTTPS URLs;
- Empty Public Gallery, Exhibitions, Blog, Custom and Home surfaces are visually blank: public views do not output editorial placeholders such as “No artwork available” or “No exhibitions published”. Availability and publication filters remain unchanged; configured page headings remain independent content when enabled.
- sitemap follows current public model and excludes admin/preview/drafts/navigation-only nodes;
- `robots.txt` does not advertise private/admin/preview content;
- stable Artwork/content slugs support sharing;
- deliberate path changes may create redirect records;
- titles/favicon/meaningful ALT remain part of acceptance.

## Analytics boundary

Public Matomo tracking is optional runtime config. Analytics failure does not block rendering/navigation/viewer/Contact.

No form content, visitor email/name, admin IDs or secret values are emitted as analytics payload. See [ANALYTICS.md](ANALYTICS.md).

## Responsive/artistic presentation

The public site preserves the currently accepted Lars Möller visual character: artwork-first presentation, typography, spacing and restrained header/navigation/content treatment. Legacy breakpoint values are historical evidence only and are not target layout authorities.

Responsive behavior is content-driven across **Wide**, **Compact**, **Narrow** and **Minimal** states. Exact thresholds are chosen where the current composition would otherwise stop fitting cleanly rather than from named device classes.

- preserve the accepted wide/desktop composition unless current browser feedback requires a presentation change;
- keep navigation usable by pointer, touch and keyboard through narrow phones;
- keep artwork media and authored metadata legible without horizontal page overflow;
- prefer progressive composition changes over shrinking typography or reproducing legacy breakpoint mechanics;
- feature-specific Journal, Custom Page, Contact and Exhibition content follows the same responsive system while preserving its own semantics;
- Public pages use native document scrolling and a `position: sticky` header; the root document owns the single full-viewport scrollbar with no mirrored gutter reservation, so the header and content share the same centered shell axis. The fullscreen Artwork Viewer reserves only the original scrollbar side while root scrolling is locked to avoid layout jumps. There is no independently scrolling `.site-scroll-region`. The same derived `--public-content-inset` defines navigation and content interior widths, while `--public-shell-gap` sets equal, positive margins above and below the content at every viewport width. Artwork Viewer root scroll-lock retains the reserved gutter to prevent horizontal layout shifts. Public presentation CSS alone owns responsive public padding.
- Artwork metadata and its adjacent category/detail navigation remain on one horizontal footer row at Narrow and Minimal widths; text may reflow within a control, but the controls themselves do not stack simply because of a viewport breakpoint.
- The configured Public artwork maximum and shell width jointly determine a single fluid `--public-content-inset`: it never falls below the mobile minimum or exceeds the authored padding, and reduces continuously as the shell approaches the artwork width. Navigation rails and content padding consume that same value. Narrowing the viewport must not make an existing rail or artwork area wider, increase the number of visible pages through a geometry reset, or remove necessary overflow arrows. The 46rem threshold belongs only to feature content whose actual composition changes there; it must not independently resize the shared navigation controls, header title or shell padding.
- The Public shell and its responsive navigation rail continue to derive horizontal geometry from General's `Page width` and `Content padding` settings. This cleanup does not silently change those saved values, add responsive controls, or introduce another breakpoint-specific geometry owner.
- Public content retains a stable, border-box minimum height of `15rem` and normal content-driven height by default (including Home and populated sections). Only a genuinely empty Gallery, Exhibitions, Blog or Custom section **without an enabled page heading** expands its white content surface to the bottom of the viewport, leaving `--public-shell-gap` above and below without an unnecessary scrollbar on adequately tall viewports. This is detected from existing rendered child elements; no controller-side duplicate empty-state flags or separate render path are introduced. Longer content retains natural document scrolling. Its vertical inset (`2rem`) and the equal external page margins (`.75rem`) do not vary with viewport width. Header title area (`4.7rem`) and type (`1.75rem`) retain a stable vertical footprint; only the established left-to-center title composition switch remains.
- Gallery and Artwork spacing is a single `3.55rem` token, without the old Minimal breakpoint collapse. CV-specific presentation rules live exclusively in `custom-pages.css`; generic Rich Text semantics stay in `public-content.css`. Feature-specific responsive composition remains local to its component stylesheet.
- browser acceptance includes continuous resizing through intermediate widths, not only fixed desktop/tablet/mobile presets.

Reliability/accessibility/responsive improvements must not replace the artist-specific composition with generic portfolio/card templates.

## Deliberately not preserved

The target does not preserve insecure HTTP behavior, blanket legacy query dispatch, legacy admin/auth/session/SQL/upload implementation, warning/debug output, broken handlers, accidental DB order, public workshop/development surfaces, unsafe Rich Text or missing-media fallbacks that hide integrity problems.

## Acceptance

Public acceptance requires durable publication/routing/data verification, media integrity, viewer interaction checks, isolated Validation, representative browser comparison and explicit editorial/artist review before Production cutover.
