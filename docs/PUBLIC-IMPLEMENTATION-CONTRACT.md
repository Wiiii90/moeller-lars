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

**SPA asset build consistency (2026-10-17):** `AppServiceProvider` uses Laravel Vite's supported `createAssetPathsUsing` API and `manifestHash()` for the existing built JS/CSS URLs. The same `?id=<manifest hash>` query string is attached to every built Vite entry asset, preserving its hashed filename and the normal `@vite` `data-navigate-track` tags. Livewire only compares tracked query-string revisions, not changes to hashed asset filenames. A preview rebuild therefore causes a full reload on the **next internal SPA navigation**, rather than retaining outdated JS alongside newer CSS/Blade; this also applies to Vite-managed Admin assets without changing Filament's SPA implementation. Hot Vite and missing-manifest contexts are unaffected. No parallel loader, reload listener, polling or custom cache-buster is introduced. Existing public layout CSSOM handoff still needs real browser stress testing for repeated Gallery→Gallery transitions; static code review cannot prove the incident resolved.

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
or widths. The static contour presentation is independent of the SPA transport.

Navigation is generated from the canonical Site Node tree and persisted order/publication status, not hard-coded page arrays, legacy category assumptions or a separate menu-visibility toggle. Published nodes appear automatically; unpublished nodes remain publicly unavailable. Published children of an unpublished parent are promoted to public root navigation entries. The configured Skip Home routing option remains an explicit exception.

Main and child navigation use **one visible row** in the common public shell: entering a parent switches the rail to that parent's child-level navigation rather than expanding an additional row. A contextual parent-name return control with a distinct corner-up-left SVG icon and a fine separator leads back to the main level. Its full parent name is retained for accessibility and truncated visually at limited widths. The separate left/right chevrons are reserved for cyclic whole-item navigation. If the parent has its own public URL, its page is the first item inside the child level; navigation-only parents do not invent a page. The active parent or active child branch is rendered in its correct level by Blade on initial response, with no opening/closing animation or transition timer. The central menu rail retains the configured content/artwork width and its chevrons remain in outer padding. Every level uses one shared cyclic window algorithm: display only complete Site Node items, show no arrows when all fit, otherwise evict opposite-side items and refill remaining space. Items align left at natural widths without overflow; overflowing windows distribute across the rail. Visible windows survive ordinary internal page navigations via the destination-scoped session handoff, including the previous main window while browsing children; only a real width change may refit it. Selecting a page does not move its window. Child browsing uses the same selected, hover, keyboard and focus rules as main navigation; Escape or the explicit parent return action brings back the main level. No global outside-click dismissal, duplicated DOM menus, polling or separate child-selection algorithm is introduced. Overwide single items may wrap their own text. Public icons use explicit inline SVG paths instead of platform-dependent glyphs.

The Public paper outline is **static**, with one explicit presentation
owner rather than two unrelated strokes forced to meet. A single
`public-paper-frame` wraps the sticky Header and normal Content without
introducing another scroll container. Its sole decorative child
`public-paper-decoration` owns the two uninterrupted exterior contour
strokes and optional single backing for **physically connected** Menu/Page
sheets. The frame's connected/compatible status is resolved from rendered
page geometry once in Blade: a Below-Menu title, transparent Page, or
landscape-width artwork is not a common paper plane. For separated sheets,
the existing Menu and Content primitives own their own backing and outlines.
The common shell and paper edge outset are shared tokens.

The static white contour returns inward just before the final visible
content item (the last Artwork, Journal entry, Exhibition, Home/Custom
component, or Artwork detail). A single `public-paper-frame.js` owner
resolves the last rendered content edge within the existing Content flow
and sets `--public-paper-content-end`. A scoped ResizeObserver follows
native content reflow (including later-loaded artwork and opened entries);
a MutationObserver handles visibility changes. Both observers disconnect
through the existing Public SPA lifecycle. The CSS return inset is shared
between left/right strokes, while the gray backing continues to the natural
Content paper bottom independently. The pre-initialization fallback uses
Content block inset only until real layout geometry is available. No
intervals, extra scroll container, per-template geometry offsets, or
animation are introduced.
The canonical folio palette (`--public-folio-light`,
`--public-folio-soft`) and 3px-left/4px-down backing remain authoritative.
Existing six route-deterministic corner endpoints remain in the
nonce-authorized metadata for independent sheets; there is no ambient
animation, polling, duplicate frame engine, content scroll owner or timer.
The prior `public-folio-motion.js` animation remains deleted.
Main and child navigation retain the same cyclic complete-item window,
measured rail, keyboard behavior and selected state.

General → Appearance reuses the established **admin-stage-tabs** and
**admin-action** grammar from responsive Dashboard/Storage via the canonical
`x-admin.stage-switcher`, never Filament Tabs/chips. Its toolbar and
icon-only More/Less space action are a **fixed first row** above the
**only scrolling field pane**; scrolling Appearance fields never moves the
tabs. The General controls pane owns three explicitly sized rows: Appearance
heading, fixed tabbar and one bounded scrolling field pane. This prevents
the tabbar from absorbing the scrolling grid row.

All four Appearance panels remain mounted as single Filament Groups and
are selected locally via Alpine (no Livewire tab writes). The **Appearance
scroll container itself** is the one CSS Grid owner. Because Filament inserts
nested Group wrappers, only the wrapper ancestors of these panels are flattened
with a scoped `:has(.general-appearance-stage__panel)` rule. The real panels
therefore occupy the **same grid-area: 1 / 1**, instead of four sequential
rows. In normal mode inactive panels are out of flow; in expanded mode all
four contribute to that **single track's maximum height**, while remaining
visually hidden, inert and aria-hidden. This gives precisely the height of
the tallest tab plus the single fixed header/toolbar/insets, not the sum.
The responsive General rule preserves this max-content sizing in narrow mode.
No duplicated measurement DOM, ResizeObserver, JavaScript pixel height,
animation, or timer is involved.

The existing Stage divider follows the expanded Stage. Live Preview may
stretch within that new space with a viewport-relative upper bound and its
existing frame-fit/aspect-ratio logic; it is never remounted or given a new
iframe URL for this purely local More/Less space action. The iframe refresh
remains owned only by persisted Appearance-change events. The arrow is
icon-only, with More/Less space in its tooltip and accessible name.

**Global** owns Site icon/Background. **Title** uses paired rows for
Title/visible Text, Position/Lettering, Alignment/Responsive alignment,
Color/Size and Responsive size/Minimum size. **Menu** places Menu to page and
Divider color in one row; its background selector reads **Menu background**.
**Page** owns Content width, Content padding and **Page background**.
The heading of Live Preview occupies its own Stage heading row; below it,
the `x-admin.stage-tool-row` shared primitive places all controls flush
right with the same underlining and full-control-height separator strokes as
the Appearance tabs and the inline unit fields. Device icons (Desktop/Phone),
the existing special Zoom Out/percentage/Zoom In group, Pan and Open preview
retain their original behavior, disabled/selected states, tooltips and
accessible labels. The redundant device-dimensions text is removed from
the toolbar, not from the preview's internal device width/height dataset.
The old General-specific icon and separator styling has been deleted;
`primitives.css` and `stage.css` own the common look. Preview content
begins after the same toolbar-to-content spacing as the Appearance pane.

No duplicate forms, special responsive tabs, or independent CSS buttons are
introduced. All actual Appearance changes still use the existing autosave
methods and explicit `general-appearance-updated` iframe refresh event.
Local tab switches and Stage expansion do not reload the preview.

Title Position offers **Beside menu**, **Inside menu**,
**Above menu**, and **Below menu**. Beside is a true outer title column
on the Global Background, while the Menu paper stays centered and aligned
to the Content paper. Inside has a real reserved title width, so Left/Right
have different positions; its Alignment dropdown intentionally omits Center.
Above owns a permanent top title row **inside** the Menu paper, matching the
responsive stacked composition. Both Inside and Beside move that same
title into the Menu paper on their content-fit fallback and apply
Responsive alignment (Left/Center/Right). Above and Below support the three
desktop alignments; the 40rem narrow mode applies the configured responsive
alignment. Below is still its own content-sized row outside Menu.
The single public-header ResizeObserver observes actual visible Menu rail
geometry for Beside rather than the now viewport-wide title-layout wrapper.

The old `page_surface_full_width` option is retired: a width-independent
paper surface made short and long layouts visually inconsistent. Content width
still sets the Menu/Page shell geometry and Content padding still sets the
reading/art measure. **Transparent** is now a Page-only background mode alongside
Solid/Linear/Radial/Conic. It reveals the artist-configurable Global Background
without painting a white sheet or static contour/shadow over it. It does not
manufacture full-viewport height or alter scroll ownership. Transparent is
not available for Global or Menu backgrounds. Previously selected page
gradients and colors remain in persistence to use again if the artist switches
back.

The artist title is globally configurable and can be entirely hidden
without leaving an empty menu column or a blank title row. Its public anchor,
home link and accessible label use the same edited plain-text title; the
default is `Lars Möller`. The title is always HTML-escaped on output.
The positions `inside`, `outside`, `above` and `below` are
distinct Menu compositions; their existing stored values are preserved.
Outside is a true non-paper title column, Above always has its own Menu
row, Inside reserves space for meaningful Left/Right alignment, and Below
remains its own content-sized row. On narrow Inside/Beside layouts the
title stacks above the navigation **inside** the Menu paper. The base
Title size is
16–64px, with optional responsive reduction and an independently editable
minimum of 12–64px (default 18px). The reduction uses CSS
`clamp(min(minimum, base), 5.4vw, base)`; disabling it keeps the chosen base
size and allows text wrapping. The single public-header ResizeObserver
continues to measure fit for stacked versus horizontal composition; it never
creates a second scroll area, animation or timer. A blank artist title color
continues to use contextual ink.

**Menu to page** supports five validated storage keys and the
existing independent Divider color:
- `fine`: **No divider** — no drawn line; when geometrically compatible,
  Menu/Content use a single structural paper frame.
- `menu`: **Menu divider** — one line across the Menu's white paper
  width (never the whole viewport), followed by a sticky-owned gap.
- `full`: **Full-width divider** — one line across the viewport
  followed by a sticky-owned gap.
- `separate`: **Separate surfaces** — individual Menu/Page backing
  and a sticky-owned gap, without additional divider.
- `full_gap`: **Full-width divider and separate surfaces** — a
  viewport-width line centered between independent surfaces.

Global **Frame style** controls exterior presentation independently:
`layered` (Layered paper: white strokes plus gray backing),
`continuous` (Layered paper · continuous contours: one shared line
across even separated equal-width sheets; two gray backings remain),
`contours` (Contours only), `backing` (Backing only), and `plain`
(Plain). No second decoration implementation per option; these
selectors enable or disable the components of the same shared owner.
The stored `frame_style` column remains unchanged (12 characters)
and the existing `layered` default is preserved. SPA layout metadata
synchronizes exactly one server-defined frame style without page reload.

These variants remain subject to real-browser visual acceptance across
regular, landscape, narrow and long-page routes.

### Navigation-only groups and hover

A Page of type Group Node has no public content route. It is the existing
semantic solution for a navigation-only parent such as `Prints`, with Gallery
children `Etchings & Lithography` and `Cyanotypes`. Ordinary Gallery parents
retain their own content routes: no implicit redirect to the first child.
The unnecessary per-Gallery child landing setting is retired.

Pages → Settings still contains the optional desktop-pointer hover presentation
(currently off by default, pending design approval). It uses the existing
child-menu DOM. The shared click/keyboard/touch child navigation remains the
canonical interaction; no second routing or child selection implementation is
introduced.

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

Gallery index markup retains the **complete canonical Artwork and Viewer
sequence**; images are not paginated out of the document or removed on scroll.
The first two Artwork media images load eagerly (only the first is high
priority). Later Gallery images preserve their original thumbnail URL in
`data-gallery-src`, with no initial `src` request. The shared
`public-gallery-loading.js` uses `IntersectionObserver` with a 960px lookahead
and at most two speculative image requests. Visible images always bypass the
prefetch queue and receive high request priority. Once loaded, their canonical
URLs use the normal immutable browser cache; scrolling backward never reloads
a new image object. Intrinsic thumbnail dimensions prevent layout jumps.

A `<noscript>` image preserves native `loading=lazy` when JavaScript is
disabled. Without IntersectionObserver, the runtime restores real `src`
attributes and lets the browser handle native lazy loading. Gallery ownership
and observers are disposed with the existing Livewire SPA page lifecycle.
No timers, detached preloader images, duplicated requests, dynamic viewer
sequence or new media URL endpoint are introduced. Hero and Artwork detail
retain their independent media loading/priority contracts. Artwork detail now
uses the same revision-aware `PublicMedia::variantUrl()` as the Gallery, so
public and preview render the selected immutable rights revision where present
rather than an unrevisioned URL the controller must reject.

Public thumbnails remain the established WebP `public-v1` MediaVariant
resources (maximum edge 960px) served by `EnsureMediaVariantFile` and
`PublicMediaController`. Deleted-but-rebuildable variants can be regenerated
synchronously during first request. **Slow server responses or initial
regeneration are not fixed by scroll buffering**; before changing publication
access, variant integrity, sendfile, storage or cache behavior, inspect real
response timings and variant availability in the running preview.

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

Artwork cards, Home Hero Artworks and Artwork detail pages share one **unboxed, right-aligned artwork caption** from `artwork-caption.blade.php`, directly below the media and flush with its right edge. The first text line is `Title, Material` when material exists, using a quietly italic editorial-serif title and restrained regular material. The second line is `Year, Size` when authored. Structured dimensions keep their saved unit (cm/mm/in). Strictly numeric legacy H × W [× D] strings without an explicit unit adopt the existing admin editorial default cm; zero-valued legacy depth is omitted, while positive third dimensions remain. Non-numeric freeform artist-authored dimensions are preserved without inventing a unit. Commas appear only between present values. The previous printed prompts, ruled paper slip, handwriting styling, border, card background and NOTE are removed. Long content wraps within the image width without clipping; missing fields introduce no empty lines. Artwork descriptions stay separate on the detail page. On Gallery/Home Artwork cards the caption remains a detail link regardless of media viewer activation.

Individual Artwork detail pages have one Gallery-specific **Expand landscape Artwork pages** toggle, OFF by default. Image orientation is derived server-side from the stored public thumbnail dimensions, before first paint. The original approved envelope is restored: at most a 1000px white sheet and a 960px image area, with the size also derived from General's `--public-art-max` and always bounded by the viewport. Crucially, the actual thumbnail is shown at its **intrinsic pixel width or smaller**; it is never enlarged to fill empty space. Image and two-line caption occupy the same naturally sized and centered media block, so their right edges coincide for images of every width. Portrait/square Artworks, Gallery indexes, Home Hero and other public pages retain the unchanged normal General geometry. The user-facing multiplier/width selection and its CSS scale classes were removed: a single toggle now enables the coherent, fixed maximum design. The same opt-in layout also allows the description divider, narrative and artwork credits to occupy the complete available Artwork detail text track; the former shared 36rem maximum continues to apply to standard portrait/square detail pages, other content types and disabled Galleries. The description remains outside the intrinsic-width image-and-caption group, avoiding reintroducing its former collapsed-image regression.

The shared `artwork-navigation.blade.php` keeps the Gallery name centered and both Artwork arrow targets on General's normal artwork-width track, even when a larger landscape image is displayed. Per-Gallery sequence-end choices remain **Disabled** (default), **Hidden**, or **Cyclic**; the latter wraps between first/last only when two or more Artworks exist. The original Artwork Viewer navigation is a separate interaction and remains unchanged. The responsive **main menu and submenu** arrows are distinct from Artwork arrows: on overflow their CSS grid reserves at least 44px for each arrow hit rail, preventing the buttons from being squeezed beyond the clickable area on smartphones. Whole-item cyclic stack behavior and no-overflow alignment remain owned by existing `public-navigation.js`.

Gallery Settings also control the shared Artwork caption/footer display: Artwork information (default on), Media File credits (default off), and effective copyright notice (default off) are independent visibility decisions. If rights are displayed, they appear on the opposite side from Artwork information; the Gallery can swap the sides. Values come exclusively from the canonical MediaAsset metadata and its configured copyright inheritance, never duplicated on Artwork; the existing public Artwork detail credit policy is not changed. Persist these Gallery settings in Working/Committed, with explicit public publication. Browser and migration acceptance pending.

Gallery Settings provide an Artwork ordering mode persisted in both Working and Committed: Manual (default, newly created Artworks placed first through the canonical audited Gallery reorder service) or newest Work date (year/date descending with deterministic tie breakers and missing dates last). The Admin and Public galleries use the same `GalleryPresentation::orderArtworks` query; drag/move actions require Manual. Existing manual order is retained when switching modes. Browser and migration acceptance remain pending.

Gallery Settings also expose an optional **Between Artworks** divider, defaulting to None, with the existing `DividerVariant` choices Thin / Subtle / Strong / Dotted. Its public markup reuses the exact `custom-page__divider` stroke styling already used in Home, Custom Pages and public headings, without a second set of gradients, colors or weights. The persisted option lives on `artwork_categories` in Working and Committed; public changes require explicit publication while preview uses Working. The Gallery's existing ordered visible Artwork collection produces precisely one divider between adjacent Artworks, never before the first or after the last; empty and one-artwork Galleries display none. Its gallery-specific spacing splits `--public-section-gap` equally above and below each divider, cancelling the usual card bottom margin only in divided galleries. Both the legacy undivided layout and the Gallery image buffering/viewer sequence remain unchanged.

Gallery Settings expose the single landscape expansion toggle and sequence-end mode without extra visible helper paragraphs. Both are persisted and audited in the existing Gallery editorial workflow and included in the Working/Committed publication lifecycle. The previous `landscape_detail_scale` column is retained solely as inert database history for compatibility with existing publication version schemas; no public or admin code reads/writes it, and no width form field exists. The existing `public-folio-motion.js` remains the one owner of content-paper width and height animation during SPA transitions. No second animation system, timers or image upscaling are introduced.

The image viewer supports close/Escape, visible zoom, wheel/trackpad zoom, touch pinch/pan where supported, pointer pan when enlarged, previous/next with boundaries, viewport recalculation, loading/error state and correct accessibility/focus behavior. The top-right **Open artwork page** action uses the existing inline-SVG `arrow-right` from the shared public icon component, consistent across desktop and mobile. The distinct `external-link` icon remains only for links to external destinations such as exhibition sites and OpenStreetMap. Unused legacy double-chevron icon variants are removed rather than kept as alternative glyph implementations.

## Rich Text public rendering

Canonical Rich Text public rendering uses Markdown through `SafeRichTextRenderer`.

Embedded Storage media images use canonical `media:<id>` references resolved by central media rendering. Arbitrary external-image URLs and legacy Journal inline-token runtime syntax are not equivalent public formats.

## Custom Pages, CV and Contact

Custom Pages are ordered structured components validated by `CustomPageSetting`. CV/Vita uses ordinary Image, List, Divider, Contact and Legal Disclaimer components, not a separate runtime widget. Root components and Image children remain independently publishable and reorderable through the same Admin row structure.

An Image marked `image_decorative` can own one ordered level of children: Text, List (without another image), Divider, Contact details (Email and Social links, not Form), and Legal Disclaimer. The Image editor controls media, size, independent Left/Right alignment and the Decorative flag; it never edits child content. Every child has the same complete Position, Type, Content, five Actions and Selection as a root component. Storage uses `image.children` solely for the parent relationship. Validation forbids child images, nested Contact Forms, image-bearing Lists and second-level nested content; the Legal Disclaimer remains unique across the page. Divider children are supported by the same persistence, Parent selector and native group-sorting contract.

The public image and its children share ONE native float formatting context. The selected Left/Right alignment controls the float side, independently of the Decorative flag; for a standalone Image it controls the image position without enabling text wrap. Children flow alongside the floated image while width permits and continue underneath. Following root components start after the Image group. The Custom Page renderer owns ONE `--custom-page-component-gap` rule between adjacent components at both root and child levels: no type-specific trailing padding, no extra Divider margins and no special last-child spacing. Floated Images reserve horizontal clearance but **no bottom float margin**; the Image group's height and the standard following sibling gap supply the vertical clearance. This keeps top/bottom Divider clearance and Contact/Legal headings consistent without inserting blank spacer components.

The generic List presents each entry as a local two-column description-left/context-right row, allowing each column to wrap within its own width. The heading-to-first-entry separation is stronger than inter-entry spacing, and Contact details use compact shared label/value rows. Contact's platform selection reuses the existing Admin Toggle controls for each configured General profile; the old chip-based multi-select is removed. Visibility is still explicitly controlled by each Contact component, so adding a new General platform does not silently publish it. Deleting a General social profile cascades in the same transaction to all Custom Page and Home Contact selections, including nested Image children; the deleted platform is removed from stored selections and is not automatically restored when re-added. Hiding a configured General profile does not erase its existing Contact selection. Newly selected platforms must be available in General. An undated entry spans the available row. List Details and publication filters remain generic. Contact is one reusable Custom Page/Home component with full-width, vertically stacked Name, Email and Message inputs; form security and delivery behavior remain unchanged. The Legal Disclaimer remains General-owned legal text with shared editorial heading typography; the component never invents legal copy.

Public Contact workflow uses English fixed UI labels and plain-text notification labels, CSRF middleware, a single named limiter with 5/minute/IP, 6/hour per HMAC-hashed sender address and 60/hour across the site (shared cache required), server-side validation (name 160, email RFC/320, message 5000 characters), and a hidden company honeypot. It accepts submissions only while an eligible published Contact Form is enabled, stores each received message, attempts delivery to the private recipient from the configured sender with the visitor's email as Reply-To, and returns a safe acknowledgement even if delivery fails. **180-day retention for database ContactMessage rows is implemented in source** using `ContactMessageRetentionService`, `contact:purge` (preview unless `--apply`) and a daily Laravel Scheduler registration. It deletes by submission creation time in bounded batches, including read, unread and mail-failed records. Mailbox copies, backup archives and their separately defined retention remain untouched. **Running scheduler/production execution and local tests are not verified**; activation requires checking the standard `schedule:run` operator, without deploying/altering server configuration in this workstream. The distributed-source quota limits total throughput, not bot identity, and can temporarily lock out legitimate messages; proxy trust, shared cache and production mail protection still require deployment review.

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
original image/media credits, sanitized body, ordered Gallery and a preview-safe
return link. The Blog index is a reading sequence with fixed serif typography,
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
All extra Exhibition entry separators have been removed after browser rejection;
the open chronological catalogue is intentionally undecorated between entries.
Neither surface gets framed records, date-axis timelines, decorative stickers,
repeated corner ornaments or new entry rules.
No artwork is cropped or covered.
The existing artist name and navigation retain their independent layout owners.

The layered outer-sheet silhouette and the Blog's small angled corner strokes
on individual entries are the only accepted new interior accents. These Blog
strokes may enter the configured content padding but never displace text into
it; they alternate to the left or right with the cover composition. The
translucent headline washes, Exhibition date marks, heading backgrounds,
open-body corner strokes and tinted fact panels were browser-rejected and
removed. Do not restore them by accumulating overrides. Exhibition details
retain their existing artwork/visit/prose composition pending a separate
design pass. Do not revive numbering, date-axis nodes, graphical stickers,
clipped/castle corners, rounded corners, chips, repeated bordered entries,
fake old-paper textures, decorative bars, duplicated underlines or extra
Admin controls. Neither Journal view renders a timeline. The presentation is
reversible without changing Journal storage, publication or media contracts.

The Blog index and full Blog article temporarily suppress all visual
copyright **notices** on structured Cover/Gallery images (both inherited
General notices and explicit notice overrides). Separately authored media
credits remain attached to their figures via `JournalMediaRenderer`. This
only changes display on Blog; persisted copyright modes, authored overrides,
the General setting and Publication history remain intact. Exhibition index
continues to suppress only inherited General notices, retaining explicit
copyright overrides and credits beside its works.
The previously removed Exhibition index footer stays absent; Exhibition gallery
presentation is otherwise unchanged. Rich Text keeps its existing authored
content and embedded media rules. The long-term site-wide copyright/footer
policy remains open for a separate explicit design pass.

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

Optional material is exposed through independent native `details` disclosures
with the plain **Open / Close** control. Entries without additional material
render non-interactive summaries, and multiple exhibitions may stay open at
once. Opened bodies retain the existing cover/description/visit composition,
gallery modes, map dialog, media credits and external links. Their layout may
stack at content-fit thresholds, but fonts do not shrink.

Expanded content reuses the canonical cover, rich description, ordered
Gallery presentations (grid, mosaic or slideshow), vernissage, address and
external link contracts. Opened Exhibition folios compose their available
content rather than alternate images by row parity:

- A cover paired with vernissage, address or map/external actions uses a
  responsive, two-column image-and-visit composition if the actual content
  width supports it. At narrower widths those items stack naturally.
- An unaccompanied cover stands alone without a fabricated empty partner
  column. Text-only entries align their information with the title column.
- When a cover and long-form text coexist, prose starts beside the image
  on a sufficiently wide content surface and continues underneath it. The
  float is limited to a readable 41%-wide image rather than forcing text
  into a very narrow parallel column. At narrow content widths the complete
  feature stacks. In other cases description text retains a full-width
  reading measure. The optional Map action stays with visit information
  and opens the established on-demand dialog; only external destinations
  carry the external-link icon.
- Grid preserves the authored row-wise order. Mosaic uses stored source
  width/height to distribute images across two balanced vertical lanes
  before rendering. A modest offset and unequal column widths distinguish
  Mosaic as a deliberate editorial arrangement. Images and captions remain
  uncropped and are not moved by client-side JavaScript. Within each lane
  authored order is retained, but the visual composition is not a strict
  left-to-right reading of the global image sequence.
- Slideshow retains the authored sequence, has compact arrow navigation and
  the position centered above the image, consistent with the site's Artwork
  folio controls. Displayed images have a bounded, uncropped height.
  No large browser-default buttons, navigation row below the work, or
  new editorial presentation settings are introduced.
  The authored `position` values and editorial workflows remain unchanged.

No new Administration customization, Exhibition detail route or duplicate
media source of truth is introduced.

The existing MediaAsset copyright modes remain authoritative. Exhibition
index images retain authored credits and explicit per-asset copyright overrides
beside the corresponding work, but repeated *inherited* General copyright
notices are suppressed. There is no generated Exhibitions-page copyright footer.
The Blog index follows this same presentation policy. Full Blog articles and
other unaffected surfaces keep their current credit behavior; an explicit
No notice remains silent. No stored General or per-asset copyright data is
removed, and source attribution is never inferred from an image filename.

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



The admin hierarchy uses ONE native Livewire Sortable group per editor for both
root and child levels. Its receiving `wire:sort:group-id` determines the parent:
`root` or the persisted Image/Page index. Pages restores the original
`site-pages` group shared between both levels. Custom Pages uses one
`custom-components` group with a single transactional
`CustomPageEditorialService::placeComponent` owner. Do not reintroduce a
parallel JavaScript drag engine, hover overlay, floating "Move out" button,
nested content repeater in Image Edit, redundant child counter or special
child-row design. Existing Image children remain independently editable
and selected, and are shown with the canonical full six-column row.

## Global public appearance surfaces

General is the authoritative global editor for three distinct page paint
surfaces: **Background** (outer viewport and header), **Menu** (both navigation
levels) and **Page** (content backing). Each uses the same validated paint contract: Solid, Linear, Radial and
Conic gradients, each optionally repeating, with 2–12 stops (color and
position). Linear uses the shared numeric/rotary popover control; Conic has a start angle and
center; Radial has a center and circle/ellipse shape. Repeating uses an
explicit span (pixels for Linear/Radial, degrees for Conic) so repetition is
visible. Missing JSON stop lists preserve the legacy two-color appearance.
The existing Page width and Content padding remain separate layout parameters.
Frame style is a separate validated presentation option; gradients remain
independent of that exterior edge treatment.

The `PublicAppearance` domain validates and projects all three values.
`PublicSiteContext` uses the existing committed-versus-preview content read
context; the public layout injects only normalized CSS custom properties into
the nonce-protected style block. Absent Menu/Page settings keep the original
white presentation. These surfaces must not introduce a second CSS-injection
pipeline, new UI color picker, uncommitted public state or browser timers.

The shared global CSS custom property `--public-content-background` provides
the default for every page. A future Pages editor may supply an optional
per-page override at the page content boundary, falling back to the General
value whenever unset; do not add those controls or per-page persistence as
part of the global General editor. Menu remains globally owned by General.

## Appearance editor control presentation

The artist-facing General Stage retains its concise two-column appearance
grammar: Background/Menu/Page selection beside Primary color, and Linear/Conic
angle beside Secondary color. The shared AdminAngleControl exposes one inline
numeric degree value and opens a rotary dial only on focus/click, analogous to
the shared ColorPicker. Both input methods commit through the same validated
General appearance field. The dial is teleported outside the scrollable Stage
to avoid clipping.

Extra gradient stops are never edited with an embedded Filament Repeater,
decorative cards, chip-style buttons or red deletion icons. The existing
primary/secondary endpoint colors remain inline, while additional interior
stops are listed concisely and created/edited/removed via the canonical
AdminDialog contract and common Add row/Action controls. Old two-color settings
are never materialized into JSON merely by opening the editor.
