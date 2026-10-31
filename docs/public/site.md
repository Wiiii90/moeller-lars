# Public implementation contract

**Source reconciliation:** public presentation and media owners source-reviewed through `dev@2ad31502ea4eb352da2c9da4f8334a0d614f1b71` (2026-10-20). This is a source contract, not new browser/artist acceptance. A complete native generator rerun requires a coherent local source checkout.

This document defines durable public behavior for the current application. Detailed legacy observations used for cutover comparison live separately in [LEGACY-PUBLIC-CONTRACT.md](../migration/legacy-public.md).


## Reading map

| Concern | Where to read |
| --- | --- |
| Content eligibility, routes and privacy | [Route model](#canonical-route-model), [publication](#publication-and-discoverability), [rights](#rights-and-privacy) |
| SPA, navigation and global composition | [SPA transport](#public-spa-transport-and-asset-freshness), [Site navigation](#site-navigation), [paper/title/surfaces](#public-paper-title-and-surface-composition), [appearance](#global-public-appearance-surfaces), [responsive composition](#responsiveartistic-presentation) |
| Pages, artwork and editorial composition | [Home](#home-presentation), [Gallery](#gallery-delivery-and-loading), [Journal](#journals), [Custom Pages](#custom-pages-cv-and-contact) |
| Rendering and delivery policies | [Rich Text](#rich-text-public-rendering), [public media](#public-media-delivery), [acceptance](#acceptance) |

These are **one public behavior contract** with cross-cutting owners linked in context. The [system architecture](../ARCHITECTURE.md) explains why these boundaries exist, while this document records actual accepted page/navigation/presentation behavior.

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
editable content. See `docs/public/rights-privacy.md`.

## SEO and metadata

- indexable pages have canonical HTTPS URLs;
- Empty Public Gallery, Exhibitions, Blog, Custom and Home surfaces are visually blank: public views do not output editorial placeholders such as “No artwork available” or “No exhibitions published”. Availability and publication filters remain unchanged; configured page headings remain independent content when enabled.
- sitemap follows current public model and excludes admin/preview/drafts/navigation-only nodes;
- `robots.txt` does not advertise private/admin/preview content;
- stable Artwork/content slugs support sharing;
- deliberate path changes may create redirect records;
- titles/favicon/meaningful ALT remain part of acceptance.

## Regional formatting and editorial language

`RegionalFormats` separates three independent choices: administrator UI language (English or German per authenticated user), administrator date/number region (per user), and public date/number formats (General's separate region settings). Public prose remains artist-authored and the public language remains English; an administrator switching UI language does not translate published content. Exhibition dates and Public numbers use `RegionalFormats`, while legacy authored `date_text` and `opening_text` are preserved verbatim. See [Admin Localization](../admin/ui.md) and [Exhibition Editorial](../content/exhibitions.md).

## Analytics boundary

Public Matomo tracking is optional runtime config. Analytics failure does not block rendering/navigation/viewer/Contact.

No form content, visitor email/name, admin IDs or secret values are emitted as analytics payload. See [ANALYTICS.md](../operations/analytics.md).

## Public SPA transport and asset freshness

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

## Site navigation

The persisted `menu_type` is validated by `PublicAppearance::menuType()`. Its sole supported value at this source boundary is `cyclic_inline` (artist-facing **Cyclic inline**); this is an explicit configuration axis, **not** permission to add a second navigation renderer. Existing one-row root/child cyclic-window behaviors below remain authoritative.


Navigation is generated from the canonical Site Node tree and persisted order/publication status, not hard-coded page arrays, legacy category assumptions or a separate menu-visibility toggle. Published nodes appear automatically; unpublished nodes remain publicly unavailable. Published children of an unpublished parent are promoted to public root navigation entries. The configured Skip Home routing option remains an explicit exception.

Main and child navigation use **one visible row** in the common public shell: entering a parent switches the rail to that parent's child-level navigation rather than expanding an additional row. A contextual parent-name return control with a distinct corner-up-left SVG icon and a fine separator leads back to the main level. Its full parent name is retained for accessibility and truncated visually at limited widths. The separate left/right chevrons are reserved for cyclic whole-item navigation. If the parent has its own public URL, its page is the first item inside the child level; navigation-only parents do not invent a page. The active parent or active child branch is rendered in its correct level by Blade on initial response, with no opening/closing animation or transition timer. The central menu rail retains the configured content/artwork width and its chevrons remain in outer padding. Every level uses one shared cyclic window algorithm: display only complete Site Node items, show no arrows when all fit, otherwise evict opposite-side items and refill remaining space. Items align left at natural widths without overflow; overflowing windows distribute across the rail. Visible windows survive ordinary internal page navigations via the destination-scoped session handoff, including the previous main window while browsing children; only a real width change may refit it. Selecting a page does not move its window. Child browsing uses the same selected, hover, keyboard and focus rules as main navigation; Escape or the explicit parent return action brings back the main level. No global outside-click dismissal, duplicated DOM menus, polling or separate child-selection algorithm is introduced. Overwide single items may wrap their own text. Public icons use explicit inline SVG paths instead of platform-dependent glyphs.

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

## Public paper, title and surface composition

The Public paper outline is **static**. The server-rendered `public-paper-frame` encloses Header and Content without introducing a second scroll owner. Physically compatible, connected Menu/Page surfaces share the single decorative `public-paper-decoration` contour; separate or transparent surfaces use their existing individual sheet edges. Blade decides compatibility from the concrete header and page presentation; CSS owns the final shape. Both horizontal folio-edge outsets use the same Public design token, never page-specific artificial line matching. Header fit observes window/font changes and actual visible Menu geometry, not its own layout writes.

The frame is an **intrinsically content-sized flex item** (`flex: 0 0 auto` in `resources/css/app.css`); the body, not the frame, fills the viewport's remaining height. Contour return uses the frame's actual content-paper height, trailing content padding and one shared return inset. Populated pages do not receive an artificial viewport-driven stretch. Empty/min-height page cases retain their own layout rules and need browser acceptance. There is **no** `public-paper-frame.js`, observer-driven measurement/writes, animation, timeout or duplicated frame engine. The six route-deterministic values retained in nonce-authorized layout metadata belong to the legacy separate-sheet geometry, not an active animation.


The artist title uses one shared, HTML-escaped plain-text value (default `Lars Möller`) for its visible label, home anchor and accessibility text. Hiding it removes its reserved Menu column/row. An unset title color inherits contextual ink.

**Title Position** presents **Beside menu** (`outside`), **Inside menu** (`inside`), **Above menu** (`above`) and **Below menu** (`below`), preserving their stored keys. Beside is a separate column on the Global Background while the Menu paper remains centered at Content width. Inside reserves genuine title space so Left and Right differ; its Alignment control intentionally omits Center. Above is a permanent row inside the Menu paper; Below remains a content-sized row outside it. Above/Below support all three desktop alignments. Inside and Beside stack into the Menu paper when content fit requires it; at the 40rem narrow boundary the configured responsive Left/Center/Right alignment applies. One public-header ResizeObserver measures the actual visible Menu rail, not the outer title-layout wrapper, and introduces no second scroll owner, animation or polling.

The base Title size is 16–64px. Optional responsive reduction uses an independently editable 12–64px minimum (default 18px) and CSS `clamp(min(minimum, base), 5.4vw, base)`; without reduction the base size is retained and long text may wrap.

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


## Global public appearance surfaces

General is the authoritative global editor for three public background
surfaces — **Background** (outer viewport), **Menu** (both navigation
levels) and **Page** (content backing) — plus a fourth, independent
**Title** text paint. All four share PublicAppearance's validated
Solid/Linear/Radial/Conic configuration, each optionally repeating with
2–12 positioned color stops. Linear uses the shared numeric/rotary popover control; Conic has a start angle and
center; Radial has a center and circle/ellipse shape. Repeating uses an
explicit span (pixels for Linear/Radial, degrees for Conic) so repetition is
visible. Missing JSON stop lists preserve the legacy two-color appearance.
The existing Page width and Content padding remain separate layout parameters.
Frame style is a separate validated presentation option; gradients remain
independent of that exterior edge treatment.

The `PublicAppearance` domain validates and projects all four paint definitions.
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

General's editor controls follow the [shared Admin control and table grammar](../admin/controls.md#public-appearance-controls-source-to-dev2ad31502ea4e); the public rendering and committed/Preview boundaries remain specified here.

## Public artist title gradient

Title is a **text paint**, not another panel background. Its previously
supported `header_name_color` remains authoritative for Solid. Null mode
continues to use the existing CSS `--public-title-default` inherited color
in each title-placement context. Only an explicitly selected gradient emits
`--public-title-paint` into the same nonce-protected public layout style and
activates `site-header--gradient-title`; the artist-name anchor clips that
validated CSS gradient to glyphs. No gradient-specific layout, polling, or
separate editor/normalizer is permitted. Every public title placement uses the
same shared rule, and forced-colors accessibility falls back to plain text.
The new title settings exist in both Working and Committed schemas.

## Menu-to-page divider paint

The existing `header_divider_mode` selects **whether** and **where** the
separator is drawn (none, Menu-width, full-width, separate backing, etc.).
In General → Appearance → Menu, **Divider color** is listed below Menu
background and uses the **same** Solid/Linear/Radial/Conic + Repeating editor
and canonical color-stop table as the other global appearance paints.

The existing `header_divider_color` column is the **sole Solid color**.
The independent `header_divider_paint_mode` and validated
`header_divider_paint_gradient_*` fields describe optional gradient
painting without introducing a second solid-color record.
Unconfigured gradient mode retains the previous public divider color.
For gradients, `PublicSiteContext` emits only validated CSS variables
within the existing nonce-protected public style. Menu-width dividers
paint the existing navigation border; full-width dividers use their
existing single pseudo-element line. No additional DOM separator or
alternate public styling pipeline may be introduced.

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
- Populated Public sheets size intrinsically to actual content rather than forcing a viewport-dependent or `15rem` minimum on every route. Only a genuinely empty Gallery, Exhibitions, Blog or Custom section **without an enabled page heading** expands its white content surface to the bottom of the viewport, leaving `--public-shell-gap` above and below without an unnecessary scrollbar on adequately tall viewports. This is detected from existing rendered child elements; no controller-side duplicate empty-state flags or separate render path are introduced. Longer content retains natural document scrolling. Its vertical inset (`2rem`) and the equal external page margins (`.75rem`) do not vary with viewport width. Header title area (`4.7rem`) and type (`1.75rem`) retain a stable vertical footprint; only the established left-to-center title composition switch remains.
- Gallery and Artwork spacing is a single `3.55rem` token, without the old Minimal breakpoint collapse. CV-specific presentation rules live exclusively in `custom-pages.css`; generic Rich Text semantics stay in `public-content.css`. Feature-specific responsive composition remains local to its component stylesheet.
- browser acceptance includes continuous resizing through intermediate widths, not only fixed desktop/tablet/mobile presets.

Reliability/accessibility/responsive improvements must not replace the artist-specific composition with generic portfolio/card templates.

## Home presentation

Home content uses one of three templates:

- **Hero Artwork** — deterministic hero Artwork from eligible public Gallery/Artwork sources;
- **Under Construction** — explicit gated structured component presentation;
- **Custom** — ordered structured Home components.

**Skip Home is routing state, not a content template.** It can be enabled independently of the selected Home content template and may point at an explicit eligible published top-level page. When no explicit valid target is stored, the target is the next eligible published top-level public page after Home in canonical Site Node order. If no eligible target exists, `/` renders the selected Home template rather than redirecting into an invalid route or loop.

Changing the Home content template does not implicitly enable, disable or retarget Skip Home. Existing legacy `skip_home` template rows are normalized to Hero Artwork plus enabled Skip Home routing. **Show in public navigation** is a separate Home-only setting in Pages → Home Edit, using the retained `site_sections.show_in_navigation` field. It defaults to off: `/` remains accessible through the artist-title link without a Home menu item. Turning it on displays Home in the public menu only while Skip Home is disabled. Skip Home always suppresses the entry, independently of this stored switch. Other page types continue to use publication state alone for menu inclusion. This control never affects admin navigation, publication or the root URL.

Artwork selection uses canonical Gallery eligibility, Artwork date and explicit tie-break semantics. An Artwork-level `exclude_from_home` flag (false by default) additionally hides that Artwork from Home candidates without unpublishing it or changing its Gallery eligibility. A Gallery must still opt in with `show_on_home`. Existing explicitly selected Home IDs are retained in saved settings even while an Artwork is temporarily excluded; they are not pruned by the eligibility filter. The retired `featured_on_home` marker is historical migration data, not a current artist-facing selection override. Ambiguous invalid state is surfaced rather than resolved by incidental DB order.

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

**Inline video controls are consumer-specific in current source:** Home Custom/Under Construction and Custom Page Image/video components share the stored `video_controls_enabled` setting (**Show video playback controls**, default on), edited in their existing component dialogs; `CustomPageSetting::videoControls()` reads historical missing values as enabled. The shared `component-sequence.blade.php` renders native `controls` only for a non-decorative video when that setting is enabled. The central page-scoped autoplay/visibility owner still applies; hiding native controls does not introduce a second playback engine or suppress the Artwork Viewer's separate controls. **Journal Cover/Gallery videos now use an independently saved per-usage `video_controls_enabled` flag** (source `dev@97973406`). The existing `JournalEntryEditorSchema` exposes **Show video playback controls** on Exhibition Cover and each Blog/Exhibition Gallery media row; Blog Cover remains image-only. `JournalEntryMediaService` saves/retains the choice in the canonical usage row and `JournalMediaRenderer` emits native `controls` only when enabled. Existing or absent values default to **on**; `image_viewer_enabled` remains a separate image-only enlargement choice. These controls were source-reviewed, not browser accepted. The Gallery/Home Artwork renderer has its own optional sound toggle, which is distinct from native video controls. This is a documented difference in **existing** consumers, not a claim that a Journal controls option has been implemented or browser-accepted.

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

Public thumbnails are immutable WebP `public-v1` or large-image `public-v2` MediaVariant resources (maximum preview edge 960px) served by `EnsureMediaVariantFile` and
`PublicMediaController`. Deleted-but-rebuildable variants can be regenerated
synchronously during first request. **Slow server responses or initial
regeneration are not fixed by scroll buffering**; before changing publication
access, variant integrity, sendfile, storage or cache behavior, inspect real
response timings and variant availability in the running preview.

## Gallery and Artwork ordering

The persisted Gallery sort mode is interpreted by `GalleryPresentation`: Manual retains explicit positions (new drafts are placed first by the audited assignment owner), while Newest orders work chronology with deterministic ties. Reordering interactions are available only for Manual. Deleting an Artwork normalizes remaining Gallery positions within the canonical assignment service; removing an Artwork is not permission to remove a still-referenced MediaAsset.

Gallery presentation uses persisted Artwork `position` as authoritative order.

- deliberate reorder persists;
- IDs/timestamps/database order are not runtime fallbacks;
- moving Artwork preserves canonical media relations;
- unassigned Artwork is not part of public Gallery sequence;
- published Artwork cannot remain public through invalid/missing/unpublished Gallery relation.

Viewer previous/next follows the same public Gallery order.

## First-paint Artwork image readiness and shared viewer (source `dev@671d8214e060`)

The canonical Artwork image is hidden until the real thumbnail has successfully decoded; only then is its media frame revealed. This covers eager Gallery images, deferred Gallery images, Artwork detail and Home/other Artwork cards consistently rather than relying on a late scroll listener or briefly rendering broken intrinsic-width ALT text. `public-gallery-loading.js` owns page-scoped `data-gallery-image-state` and Artwork-detail decode/error transitions and is disposed at Livewire navigation. Deliberate image load errors retain visible failure/ALT feedback; image hiding is **not** permission to mask missing-media integrity failures. `public-spa.js` reconciles the saved Appearance presentation before showing initialized navigation; no default-style flash or second observer/polling engine should be added.

`PublicImageViewer` is the **single public image-to-viewer adapter** for permitted Home and Journal image occurrences. It safely renders Rich Text through the canonical `SafeRichTextRenderer` *before* decorating images with scoped viewer attributes. Journal structured Cover/Gallery and Rich Text use the same content viewer scope; image-only navigation is limited to that content context. Videos stay inline and a Journal image viewer must not expose Gallery Artwork detail-action links. The main Gallery Artwork Viewer retains its existing distinct Artwork/navigation context. Each content consumer has an explicit opt-in for enlargement; never globally make all images clickable merely because a viewer exists.

## Artwork presentation/media/viewer

Public Artwork exposes approved authored metadata and valid media.

Canonical originals remain authoritative; missing/corrupt/unavailable required media is readiness/integrity failure, not permission to choose unrelated fallback.

Informative public images require canonical ALT; explicit Artwork usage override takes precedence only where that consumer supports it.

Gallery Artwork primary media may be a canonical image or supported video. Inline Gallery/Home/Artwork videos use a single page-scoped playback owner (`public-media-playback.js`): muted looping video plays only while intersecting, the document and slide are visible, and reduced-motion policy permits it. Browser autoplay rejection is handled without a retry timer. An artist-enabled sound toggle is scoped by the Gallery/Home sound policy; the separate Artwork Viewer owns its own full-size player and navigation. Video uses the canonical immutable `video-poster-v1` WebP poster and its recorded intrinsic dimensions rather than an inline sizing workaround.

Artwork cards, Home Hero Artworks and Artwork detail pages use the shared unboxed `artwork-caption.blade.php`. The first line contains **Title, Year** (if present); the second contains **Dimensions, Material** (whichever is authored). Dimensions preserve saved cm/mm/in, while strict numeric legacy dimensions without unit use the existing editorial cm default and free-form text remains unmodified. Long lines wrap within the available artwork width without clipping or inventing missing fields. Optional MediaAsset file-rights attribution remains **separate from** authored Artwork information; the Gallery may align these as left/right regions under the media. Artwork descriptions stay separate on the detail page. On Gallery/Home Artwork cards the caption remains a detail link regardless of media viewer activation.

Individual Artwork detail pages preserve two **independent Gallery opt-ins**: **Expand landscape Artwork pages** and `portrait_detail_enabled` (portrait screen-height fitting), both OFF by default. Image orientation comes server-side from stored public thumbnail dimensions; video and nonmatching/square Artworks do not use either fitted image class. Gallery indexes, Home Hero and disabled Galleries retain General's ordinary paper/media sizing.

**Current source layout (`dev@74176c70`, browser behavior not yet accepted):** An enabled landscape or portrait image now adds the **same** `site-content--fitted-artwork` wrapper, with its own orientation class, so the page can distribute remaining viewport height between header/navigation, the image and naturally sized caption, narrative, divider and credits. On wider screens the fitted Artwork page uses **one bounded viewport-derived media extent** rather than making an image, paper and caption measure each other. Its actual image is contained within the measured stage without stretching the surrounding box; the caption, narrative and credits remain normal-flow content with natural overflow when longer than the viewport. On narrow screens the regular Gallery/Artwork layout remains authoritative (reconciled through `dev@478bccc1`). The fitted Artwork detail uses a vertical flex layout and scales the image with `object-fit: contain` within the available width and height, without changing aspect ratio. Long authored text retains normal document scrolling rather than clipping itself or the credits. The image/caption footer stays in one centered media group; no separate detail renderer or new runtime image-measurement script is introduced.

For **landscape**, the available page/content width follows the viewport's public gutters instead of the older fixed **1000px paper / 960px image** envelope or General's usual artwork reading width. A separate **Gallery opt-in** `artwork_menu_surface_wide` (off by default, persisted in Working and Committed) may expand **the Menu paper/surface only** to that Artwork sheet width. The actual artist title, navigation items, arrow rails and typography stay on General's established menu track; it does not stretch or re-space the menu itself, create a second title, or affect Gallery index/other pages. The existing joined/separate surface override is independent. For **portrait**, the existing normal width is retained while flexible viewport height can enlarge the image within the remaining vertical space. Accordingly, do **not** describe the fitted image as always limited to its original pixel dimensions, or the old `calc(100dvh - 14rem)` maximum as active. Gallery-wide surface divider overrides remain separate from media fitting; the viewer stays a distinct presentation. **Verification remains open** for wide/tall images, short/long captions, optional narrative/credits, chosen surface gaps and mobile viewport behavior before declaring visual acceptance.

The shared `artwork-navigation.blade.php` keeps the Gallery name centered and both Artwork arrow targets on General's normal artwork-width track, even when a larger landscape image is displayed. Per-Gallery sequence-end choices remain **Disabled** (default), **Hidden**, or **Cyclic**; the latter wraps between first/last only when two or more Artworks exist. The original Artwork Viewer navigation is a separate interaction and remains unchanged. The responsive **main menu and submenu** arrows are distinct from Artwork arrows: on overflow their CSS grid reserves at least 44px for each arrow hit rail, preventing the buttons from being squeezed beyond the clickable area on smartphones. Whole-item cyclic stack behavior and no-overflow alignment remain owned by existing `public-navigation.js`.

Gallery Settings also control the shared Artwork caption/footer display: Artwork information (default on), Media File credits (default off), and effective copyright notice (default off) are independent visibility decisions. If rights are displayed, they appear on the opposite side from Artwork information; the Gallery can swap the sides. Values come exclusively from the canonical MediaAsset metadata and its configured copyright inheritance, never duplicated on Artwork; the existing public Artwork detail credit policy is not changed. Persist these Gallery settings in Working/Committed, with explicit public publication. Browser and migration acceptance pending.

Gallery Settings provide an Artwork ordering mode persisted in both Working and Committed: Manual (default, newly created Artworks placed first through the canonical audited Gallery reorder service) or newest Work date (year/date descending with deterministic tie breakers and missing dates last). The Admin and Public galleries use the same `GalleryPresentation::orderArtworks` query; drag/move actions require Manual. Existing manual order is retained when switching modes. Browser and migration acceptance remain pending.

Gallery Settings also expose an optional **Between Artworks** divider, defaulting to None, with the existing `DividerVariant` choices Thin / Subtle / Strong / Dotted. Its public markup reuses the exact `custom-page__divider` stroke styling already used in Home, Custom Pages and public headings, without a second set of gradients, colors or weights. The persisted option lives on `artwork_categories` in Working and Committed; public changes require explicit publication while preview uses Working. The Gallery's existing ordered visible Artwork collection produces precisely one divider between adjacent Artworks, never before the first or after the last; empty and one-artwork Galleries display none. Its gallery-specific spacing splits `--public-section-gap` equally above and below each divider, cancelling the usual card bottom margin only in divided galleries. Both the legacy undivided layout and the Gallery image buffering/viewer sequence remain unchanged.

Gallery Settings expose independent landscape expansion, portrait screen-height fit, Artwork page surface override and sequence-end navigation, without extra visible helper paragraphs. **Artwork page surface** is a Gallery-specific tri-state setting, stored as `artwork_page_surface_mode` on both Working and Committed Gallery records: **Follow General** (`inherit`, default) leaves the global header/divider presentation unchanged; **No gap** (`joined`) selects the existing General joined/fine mode; **Separate Surface** (`separate`) selects the existing separated Menu/Page surfaces. This override is applied **only** to the Artwork detail view using `GalleryPresentation::artworkPageDividerOverride()` and the shared `layouts.app` effective divider; it does not change the Gallery index, other Galleries, other routes or General's saved global preference. Preview uses Working state, while ordinary Public requires the Gallery change to be published. It is a layout choice, **not** a new divider-paint system, separate layout template or permission to add a gallery-wide gap.

Gallery Settings still expose sequence-end navigation without extra helper paragraphs. Both are persisted and audited in the existing Gallery editorial workflow and included in the Working/Committed publication lifecycle. The previous `landscape_detail_scale` column is retained solely as inert database history for compatibility with existing publication version schemas; no public or admin code reads/writes it, and no width form field exists. The former `public-folio-motion.js` is removed. The static `public-paper-frame.js`/shared frame markup own the unified public paper contours across header and content; no separate contour animation, timer, doubled stroke or image upscaling is introduced.

The image viewer supports close/Escape, visible zoom, wheel/trackpad zoom, touch pinch/pan where supported, pointer pan when enlarged, previous/next with boundaries, viewport recalculation, loading/error state and correct accessibility/focus behavior. The top-right **Open artwork page** action uses the existing inline-SVG `arrow-right` from the shared public icon component, consistent across desktop and mobile. The distinct `external-link` icon remains only for links to external destinations such as exhibition sites and OpenStreetMap. Unused legacy double-chevron icon variants are removed rather than kept as alternative glyph implementations.

## Journals

### Template switching

Blog and Exhibitions are separate content products sharing Journal placement/settings mechanics.

Switching the active Journal template does not convert/delete retained inactive rows. Public rendering exposes only the active template's eligible content; retained inactive content remains canonical editorial/reference data and can reappear when switched back.

### Blog

A Blog Journal lists only public Posts belonging to it and satisfying lifecycle/schedule. Body uses canonical safe Rich Text visual HTML or historical Markdown/media references. Structured Cover/Gallery media use canonical Journal media policy.

### Exhibitions

An Exhibitions Journal lists public Exhibitions belonging to it.

Exhibitions use one [canonical editorial contract](../content/exhibitions.md): title, independent `organizer` and physical `venue`, address (street/postal code/city/country), structured start/end dates, optional structured or preserved text vernissage, explicit `show_vernissage`, rich description, external URL, Gallery and Map. Structured opening display stops after its timestamp; hiding an opening never deletes its editorial details. The date-only chronology, organizer byline and geocoding responsibilities must not be inferred from one legacy `venue` string.

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
Collapsed Exhibition entries remain undecorated. Expanded entries use at most
one subdued rule at a shared boundary, with no rule above the first or below
the last item. No duplicated separator when adjacent entries are expanded.
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
and no fabricated image substitutes, vertical date spine or connection nodes.
Only opened entries may expose the restrained shared-boundary separator. The artist's manual exhibition order and
the fixed typography remain authoritative.

If an Exhibition has a canonical **image** Cover, its closed catalogue entry may display one **small decorative image teaser** aligned at the far side of the summary. The teaser is a restrained rendition of the same eligible image variant, is `aria-hidden`, does not intercept pointer interaction, carries no duplicate caption/credits/viewer trigger, and disappears when the entry expands. A **video** Cover does not appear as a still teaser; it retains the expanded inline-player experience. An absent Cover creates no placeholder. This teaser is not a second media store or an extra public eligibility owner. Check real mobile alignment, summary hit area and expansion behavior separately; source presence alone is not browser acceptance.

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

## Rich Text public rendering

Canonical Rich Text public rendering accepts validated **visual HTML** and historical Markdown through the one `SafeRichTextRenderer`. Visual input is sanitized on the server before rendering, with `RichTextDocument` handling legacy conversion only when editing; no second public renderer or unconditional backfill is introduced.

Embedded Storage images resolve through canonical `MediaAsset` IDs in both historical `media:<id>` Markdown and current visual HTML. `ArtistImageLayout` owns the six inline/float placements and optional caption without changing ALT, permissions or shared file identity. `SafeRichTextRenderer` resolves the current media variant and canonical public ALT, never an editor-only image URL. The [Editorial Rich Text contract](../content/rich-text.md) is the canonical owner of editor/layout formats; Public owns only permitted presentation and publication eligibility. Do not reintroduce a Markdown-only public rendering assumption.

## Custom Pages, CV and Contact

Custom Pages are ordered structured components validated by `CustomPageSetting`. CV/Vita uses ordinary Image, List, Divider, Contact and Legal Disclaimer components, not a separate runtime widget. Root components and Image children remain independently publishable and reorderable through the same Admin row structure.

An Image marked `image_decorative` can own one ordered level of children: Text, List (without another image), Divider, Contact details (Email and Social links, not Form), and Legal Disclaimer. The Image editor controls media, size, independent Left/Right alignment and the Decorative flag; it never edits child content. Every child has the same complete Position, Type, Content, five Actions and Selection as a root component. Storage uses `image.children` solely for the parent relationship. Validation forbids child images, nested Contact Forms, image-bearing Lists and second-level nested content; the Legal Disclaimer remains unique across the page. Divider children are supported by the same persistence, Parent selector and native group-sorting contract.

The public image and its children share ONE native float formatting context. The selected Left/Right alignment controls the float side, independently of the Decorative flag; for a standalone Image it controls the image position without enabling text wrap. Children flow alongside the floated image while width permits and continue underneath. Following root components start after the Image group. The Custom Page renderer owns ONE `--custom-page-component-gap` rule between adjacent components at both root and child levels: no type-specific trailing padding, no extra Divider margins and no special last-child spacing. Floated Images reserve horizontal clearance but **no bottom float margin**; the Image group's height and the standard following sibling gap supply the vertical clearance. This keeps top/bottom Divider clearance and Contact/Legal headings consistent without inserting blank spacer components.

The generic List presents each entry as a local two-column description-left/context-right row, allowing each column to wrap within its own width. The heading-to-first-entry separation is stronger than inter-entry spacing, and Contact details use compact shared label/value rows. Contact's platform selection reuses the existing Admin Toggle controls for each configured General profile; the old chip-based multi-select is removed. Visibility is still explicitly controlled by each Contact component, so adding a new General platform does not silently publish it. Deleting a General social profile cascades in the same transaction to all Custom Page and Home Contact selections, including nested Image children; the deleted platform is removed from stored selections and is not automatically restored when re-added. Hiding a configured General profile does not erase its existing Contact selection. Newly selected platforms must be available in General. An undated entry spans the available row. List Details and publication filters remain generic. Contact is one reusable Custom Page/Home component with full-width, vertically stacked Name, Email and Message inputs; form security and delivery behavior remain unchanged. The Legal Disclaimer remains General-owned legal text with shared editorial heading typography; the component never invents legal copy.

Public Contact workflow uses English fixed UI labels and plain-text notification labels, CSRF middleware, a single named limiter with 5/minute/IP, 6/hour per HMAC-hashed sender address and 60/hour across the site (shared cache required), server-side validation (name 160, email RFC/320, message 5000 characters), and a hidden company honeypot. It accepts submissions only while an eligible published Contact Form is enabled, stores each received message, attempts delivery to the private recipient from the configured sender with the visitor's email as Reply-To, and returns a safe acknowledgement even if delivery fails. **180-day retention for database ContactMessage rows is implemented in source** using `ContactMessageRetentionService`, `contact:purge` (preview unless `--apply`) and a daily Laravel Scheduler registration. It deletes by submission creation time in bounded batches, including read, unread and mail-failed records. Mailbox copies, backup archives and their separately defined retention remain untouched. **Running scheduler/production execution and local tests are not verified**; activation requires checking the standard `schedule:run` operator, without deploying/altering server configuration in this workstream. The distributed-source quota limits total throughput, not bot identity, and can temporarily lock out legitimate messages; proxy trust, shared cache and production mail protection still require deployment review.

General owns public email/visibility, social links, favicon, private Contact recipient and truly global legal/public text. SMTP/DKIM/TLS remain runtime/platform secrets.

## Public media delivery

At the source-reviewed `dev@434975e1bd09` boundary, `MediaPreviewVariant` is the single selector for immutable image thumbnails (`public-v1`/`public-v2`) and video poster previews (`video-poster-v1`). New image previews use bounded libvips; new video posters are created with FFmpeg. Historical GD recovery remains separate. Publication/readiness may reject missing or corrupt required previews; it must not silently fabricate eligibility. These facts are source-only: no Validation/Production media, browser or migration was touched by this documentation pass.

Only media valid for the requesting public consumer is exposed.

- private/quarantined/deleted originals are not public substitutes;
- required variants must exist where required;
- public routes do not expose arbitrary storage paths;
- references remain authoritative when content moves/detaches/disables presentation/deletes;
- being accepted into Storage does not imply universal public-consumer support;
- protected preview routes do not weaken ordinary public eligibility.

Structured Journal Cover/Gallery runtime ALT uses canonical MediaAsset ALT. Legacy Journal usage overrides are not runtime public authority.

See [MEDIA.md](../media.md).

## Deliberately not preserved

The target does not preserve insecure HTTP behavior, blanket legacy query dispatch, legacy admin/auth/session/SQL/upload implementation, warning/debug output, broken handlers, accidental DB order, public workshop/development surfaces, unsafe Rich Text or missing-media fallbacks that hide integrity problems.

## Acceptance

Public acceptance requires durable publication/routing/data verification, media integrity, viewer interaction checks, isolated Validation, representative browser comparison and explicit editorial/artist review before Production cutover.
