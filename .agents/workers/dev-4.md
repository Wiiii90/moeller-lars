# Dev Worker 4 — Gallery/Artwork

**Lease:** 38  
**Branch:** dev  
**State:** Active — correction of public Gallery image/information edge mismatch source-integrated; current user-requested Gallery presentation cleanup and browser acceptance remain open.

## Current task (2026-11-22)

- User supplied two Public Gallery screenshots showing inconsistent artwork-information alignment: one Artwork's caption extends past the visible right image edge; another aligns correctly. The Gallery had previously been accepted, so preserve its appearance/interaction while correcting the regression and consolidating the canonical presentation owner.
- Confirmed current source: `resources/css/public-presentation.css` assigned the same Gallery card two independent media caps: the aspect-derived `.artwork-card__composition` width from `--gallery-artwork-height`, and a separate `max-height` on the contained image/video. With `object-fit: contain`, the latter can shrink visible paint without shrinking the element's width, leaving the caption aligned to the wrong edge.
- Refactor after correction: verified all six imported Public stylesheets and the actual Gallery, Artwork, Hero and Viewer consumers. Removed the unused `data-gallery-image-buffer` marker (its former image-prefetch queue is retired), removed the mobile-only `max-width: 100%` duplicate already declared at the Gallery composition owner, removed the unused `artwork-card__composition--video` modifier after checking its CSS and JavaScript consumers, and clarified that the shared Artwork card must not introduce a second media height cap. The Hero's separate rights-caption selectors are actively used in `pages/gallery/hero-artwork.blade.php`, so preserve them; do not mistake them for dead Gallery code.
- Canonical correction: remove Gallery-specific link/image/video overrides and the redundant media-level max-height. Base image/video rules in `resources/css/public-presentation.css` and `resources/css/app.css` already fill the composition width; `--gallery-artwork-height` now controls the dimensioned Gallery media exactly once through the composition's thumbnail/poster aspect. Keep existing server-rendered media data, viewer links, native video controls, Gallery offsets, batch delivery and information arrangements unchanged.
- Important remaining risk: aspect rules are emitted with request-specific style nonces in `resources/views/components/artwork-card.blade.php` and separate later chunks are restored by `resources/js/public-gallery-batches.js`. Real same-page, appended-batch and Livewire navigation checks must verify that the aspect rule applies; a blocked/missing rule can still lose the intended height ceiling even though the redundant cap no longer causes caption-edge letterboxing. Do not invent a second sizing mechanism or claim Browser acceptance without evidence.
- Verification: current source/cascade reviewed. No running local application or artist browser session is attached to this chat; PHP, end-to-end browser checks, CI, deployment and tests were not run. Reproduce screenshot examples with current Preview, direct loading and navigation before accepting the fix.

## Canonical ownership and protected behavior

- Gallery administration: `app/Filament/Pages/GalleryWorkspace.php` and its `GalleryWorkspace* ` concerns; `app/Filament/Support/ArtworkEditDialog.php` is the shared Artwork form owner. Preserve List/Grid/Dense, selections, order, staging uploads/preview, editable metadata and settings.
- Gallery Public: `resources/views/pages/artworks/index.blade.php`, shared `components/artwork-card.blade.php`, `pages/artworks/partials/batch.blade.php`, `PublicArtworkController.php`, and Gallery-specific selectors in `public-presentation.css`. Keep one actual image/thumbnail, pre-decode width reservation, authentic ALT/error behavior, viewer and pagination.
- Public individual Artwork video change already source-integrated: `pages/artworks/show.blade.php`, `public-gallery-loading.js`, and shared media styles now give fitted video pages native player controls; previous source review only, no artist browser acceptance.
- Do not casually alter Home Gallery Hero (Dev Worker 3/6), General (Dev Worker 7), Pages (Dev Worker 5), Journal (Dev Worker 1) or Custom Pages (Dev Worker 2). Shared Public primitives require explicit consumer comparison and regression review. No Dev modifications to tests or routine `docs/`.

## Other still-open user acceptance / Testing and Docs handoff

- Gallery Stage upload queue and edited values are restored within the session; reused/referenced Storage files survive Discard while truly unreferenced new uploads can be removed by the canonical media service. Review rapid navigation, uploads and queue cleanup in a real browser.
- Gallery/Storage List/Grid/Dense and Gallery Upload/Staged/Settings tabs use established server or central remembered-tab state. Inspect fast navigation, selection, form changes and valid-tab fallbacks. Gallery's Auto Focus and preview-specific behavior must not regress.
- Storage primary Artwork deletion confirmation offers a per-reference option to delete the Artwork; otherwise its Gallery ownership/position is retained as an unpublished draft. Journal Carousel and Blog content side effects are shown in single/bulk deletion confirmation. Migration/runtime/notification acceptance is outstanding.
- Preserve partial Artwork dates, Gallery responsive action rails, row selection axis, audit/notification invariants and Stage form controls. Dedicated Testing must update/qualify integrated suites after user review.
- Pending durable Docs consolidation: accepted Gallery Stage lifecycle, safe Discard, List/Dense date/action semantics, Storage media deletion choices and the Public Gallery's single media/caption track. See `docs/public/site.md` for the existing public Gallery contract; its 2026-11-21 source-only qualification is not browser acceptance.

## Next

1. Obtain real browser measurements for both screenshot Artworks on direct Public/Preview navigation and after appended batches. Verify media painted bounds vs caption bounds in landscape/portrait, initial loading, unavailable dimensions, narrow screens and video. User acceptance remains open.
2. If computed Gallery `--gallery-media-aspect` is absent after Livewire navigation, establish the actual nonce/style delivery fault in the existing public style owner before making further architectural changes; no compensating page-local sizing script.
3. Finish a bounded review of Gallery-only presentation selectors and their direct consumers; remove only proven dead/conflicting styling. Report exact checks and ask Testing for regression qualification. No automatic deployment or Continuous Integration.
