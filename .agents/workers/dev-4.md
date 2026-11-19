# Dev Worker 4 — Gallery/Artwork

**Lease:** 20
**Branch:** dev
**State:** Corrected Home/detail mismatch by sharing actual viewport-fit sizing, the fitted Page geometry and image/metadata track, including hidden title/navigation. Browser acceptance pending.

## Ownership / scope

- Active Dev Worker 4 on `dev`, lease 20. The artist explicitly extended this chat's Gallery task to Home Hero presentation and its connected options. Dev Worker 6 still owns all other Home Hero candidate selection, source ranking, Stage behavior and Dummy Gallery workflows. Preserve those source, ranking, rotation and multiple-slide navigation rules unchanged.
- Gallery Workspace Stage and modal continue sharing `gallerySettingsFields(bool $stage)`. Never create duplicate layout selectors or separate persistence/renderer layers.
- Before any edit: recheck `dev` head, lease and scopes. No unapproved migration/CI/deployment, broad test editing or live data operations.

## Sole Expanded artwork composition

- Individual Artwork details always use the accepted Expanded view at `/artworks/{slug}`, regardless of originating Gallery or Home. General owns physical Menu/Page width and inset, the fitted image and information are centered as one group, and information follows the visible media edge with the standard small margin. No Standard/Museum options, grid variants or visual outlines.
- Home Hero now uses an **independently owned presentation with the same Expanded visual rules** rather than the old per-orientation `landscape_detail_enabled`/`portrait_detail_enabled` flags and alternate `artwork_page_surface_mode`/`artwork_menu_surface_wide` choices. Public Hero always uses `site-content--home-hero-fitted`; General keeps control of paper, menu and divider.
- All Home Hero slides (Artworks **and** direct Storage media, including video posters where dimensions exist) use the same `artwork-card__composition--home-hero` image/caption grouping. Existing variant dimensions create a nonce-authorized per-slide image ratio; CSS makes each image as large as permitted by **General Content width** and `min(82svh, 100svh - 9rem)` without distortion. Footer/rights align exactly with that media width; Gallery/hero navigation and arrow hit targets span General Content. Small landscapes and their information remain vertically grouped/centered, with no phantom stage below the image.
- `data-home-hero`, `[data-home-hero-slide]`, current/hidden member state, previous/next buttons, rotation and enabled viewer remain canonical. All source profiles and media eligibility unchanged. The Home settings no longer expose landscape/portrait expand, alternate page surface or wide-menu controls in Stage or Settings. The configuration service no longer reads/saves these fields and drops inactive keys on the next authorized Home edit, but **historic committed versions and checkpoint snapshots remain untouched**. No database migration was required.
- Do not confuse independent Home Hero media rendering with individual Artwork detail routes; the common visual language is image sizing and footer alignment, not forcing Home into the detail renderer or sharing its navigation controller.

## Home/Artwork fitted presentation parity — correction

- Artist reported Home was not actually identical to individual Expanded Artwork pages: extra bottom whitespace and captions not always matching the media. Prior change incorrectly added Home-only `min-height: calc(100svh - 9rem)`, `--home-hero-media-height`, separate ratio-token CSS and a dedicated page-flow path that did not account for the actual title/navigation/footer budget. The page was longer than the viewport despite an image height cap.
- Removed those parallel Home-only height/ratio formulas. Home now shares the **same fitted public paper-frame and General shell geometry** as individual Artwork pages, and both call one `fittedMediaWidth()` calculation in `public-gallery-loading.js`: real available Content width, actual folio presence, visible Page heading, viewport bottom, caption size, Page bottom padding and native media dimensions. A single `--public-fitted-image-width` in the existing nonce-authorized layout stylesheet controls the visible Home slide's image *and* metadata container; Home is not a second CSS sizing algorithm.
- The visible Home image and caption are vertically centered as one flex composition, with no art-card viewport minimum. If Title or navigation is absent, that vertical space becomes available to the image automatically. The existing Home slide-switch handler emits `home-hero-change` so the central fitter measures the next portrait/landscape/video with its own footer; fit responds to resize and fonts. Original Gallery index and Home source selection/navigation/video/viewer remain unchanged.
- Source-only; needs real Laravel Preview acceptance at desktop/mobile, with Home title/nav toggled on/off, different portrait/landscape media, long captions, multiple-slide switching, no bottom overflow and image-edge/footer alignment.

- The shared fit reserves a minimum practical image height (30% of the viewport, capped at 240px) when exceptionally long rights would otherwise create a self-amplifying caption-wrap feedback loop. Longer descriptions scroll instead of collapsing the image to zero width. A single `ResizeObserver` on the Home metadata/controls remeasures after wrapping and is disconnected on SPA dispose.

## Gallery index spacing

- Stable lateral variation retains `GalleryPresentation::indexCardOffsets()` and never changes Artwork order. New `indexCardGapSteps()` uses Gallery+Artwork identity and a separate hash salt to yield stable steps 0–4 for **vertical spacing**. This is deliberate small visual pseudo-randomness, not request-time randomness or persisted ordering state.
- Gallery desktop base gap is `clamp(8rem, 17svh, 10rem)` plus zero to one rem jitter in quarter-rem steps; total roughly two to two-and-a-half times the former `clamp(3.5rem, 8svh, 5rem)` at ordinary desktop heights. With a divider, the same full gap is divided evenly above/below the separator. The last Artwork adds no trailing gap. On phones (<=40rem), the preexisting `clamp(1rem, 3svh, 2rem)` remains **without** jitter.
- General owns Gallery paper width, media height remains `min(82svh, calc(100svh - 9rem))`, top inset follows the General side inset on larger screens and is 1rem on phones, captions match visible image width. Images preserve aspect ratio, no decorative edges, no artificial viewport-height Gallery card slots.

## Artwork caption punctuation — current correction

- User spotted commas separated from the preceding title/dimensions and isolated at the start of wrapped lines. The old CSS generated a `, ` pseudo-element **before** each next Artwork information field, leaving Blade-rendered inter-span whitespace before punctuation and allowing an orphan comma at line start.
- Moved comma generation onto the **preceding field's** `::after`, for all field groups including titles and dimensions; the normal whitespace between inline field spans supplies a single following separation. Last field emits no punctuation. No change to layout geometry, wrapping rights, information arrangement, source data or UI controls.
- The correction is source-reviewed, not yet accepted in the running Preview. Verify narrow captions with `Title, Date` and `Dimensions, Material`, with/without media credits and phone wrap.

## Gallery Workspace — 500 on load repaired

- Removed the stale `galleryContext['artwork_page_layout']` projection in `GalleryWorkspaceSelectionSupport::loadGallery()`. The stored layout selector and `GalleryPresentation::artworkPageLayout()` were retired with the sole-Expanded change, but this admin context still invoked the deleted reader, causing a GET /admin/pages/gallery/{id} HTTP 500 before Stage and Settings rendered.
- The Gallery admin view and inspected GalleryWorkspace traits consume no `galleryContext['artwork_page_layout']`; the obsolete context entry can be removed rather than reinstating a compatibility method or misleading choice. Gallery settings, navigation and publication are unchanged.
- Source review confirms the deleted call is absent. No full local Laravel request was executed; artist should reload the failing Gallery admin route.

## Gallery Stage/Settings retained

- Artwork Information arrangement is the canonical Field/Row/Align table, with existing row movement and Gallery-specific sparse overrides. Media Viewer, Artwork Page availability/end navigation and looping video sound keep their independent meanings. Stage/modal controls use flat grouping without persistent helper paragraphs, excessive rounding or mandatory stars.
- Gallery Artworks are drafts until explicitly published. Publication/checkpoint integrity is a real compatibility boundary and must not be rewritten as a layout cleanup.

## Verification and next acceptance

- Source-level checks for Home option removal, media grouping, CSS bounds, stable Gallery jitter, mobile override, and unchanged Hero navigation; real local Laravel Preview and complete browser review are **not** performed in this worker chat.
- User should inspect Home with single landscape, portrait, direct Storage image/video, two or more navigable Hero slides, viewer on/off, hidden/unavailable media and long rights. Check General width and padding, visible image-to-caption alignment, no viewport overflow and no menu/page width changes. Inspect Gallery with and without dividers, landscape/portrait and phone gaps.
- Remaining runtime qualification and any full test maintenance should be routed through the existing Testing branch/Docs publication gate. Other Home Hero ranking and Stage acceptance remains Dev Worker 6's scope.
