# Dev Worker 4 — Gallery/Artwork

**Lease:** 22
**Branch:** dev
**State:** Active — Gallery Stage reduction source-prepared on 2026-11-19; browser acceptance pending. Next proposed: combine Upload/Editor and adapt Gallery preview width.

## Gallery Stage reduction — 2026-11-19

- Scope: Stage keeps Artwork order first, Artwork information visibility, Media viewer and Individual Artwork Pages; Title, Between Artworks and video sound remain available only in Gallery Settings.
- Viewer checkboxes: Zoom and Navigation. Individual Artwork Pages: Navigation and dependent Cyclic checkboxes on the same line, backed by existing edge mode. Public detail hides arrows entirely when disabled, retains disabled end arrows when enabled without cycling, and wraps when Cyclic is on.
- Retire the editable Artwork information arrangement from Gallery Stage, Settings, and individual Artwork editor. Remove its code owner, edit callback, and stylesheet. Fix public information placement to the former default (rights left, title/date/dimensions/material right) in all displays. Historical saved arrangement data remains in persisted records, but does not drive the new UI; no database rewrite.
- Preserve the current public Gallery and Artwork visual composition otherwise. Keep full video-sound feature until user decides whether to retire or replace it. Defer Stage Upload/Editor merge and adaptive desktop/phone preview columns.
- Evidence: production-source changes only; no local application runtime, browser preview, test execution, Content Security Policy changes, Continuous Integration, deployment or migration. Browser acceptance and actual persisted Artist data inspection remain outstanding.
- Out of scope: Home Hero, General, shared media picker/dialog redesign, production/validation deploy and test modifications.

## Current scope and inherited handoff

- The user explicitly assigned this new chat as Dev Worker 4 and asked it to read into the project, not yet to implement a specific change. The previously accepted public Gallery, Artwork and Home Hero presentation remains satisfactory for now. **Next anticipated task:** substantial redesign of Gallery Admin Stage and checkbox/settings controls, pending concrete user instructions; do not preempt that design.
- Worker takeover uses the next lease token on `dev`. Project contract, same-branch Dev skill and shared protocol were read before this checked registration. No application source, test, deployment or issue has been changed as part of onboarding.
- Dev Worker 4 owns Public Gallery/Artwork and Gallery Stage/Settings. Dev Worker 6 owns broader Home Hero selection/ranking/rotation/Stage; Dev Worker 7 owns General; Dev Worker 8 owns shared dialog/picker styling. Coordinate rather than introducing parallel components.

## Current Public presentation — preserve

- All individual Artwork detail pages use the accepted **Expanded** layout, regardless of Gallery/Home origin; Standard and Museum choices and Gallery Admin layout selector were retired. Gallery Settings still control whether Artwork pages are enabled and the edge-navigation policy. Historical database/snapshot fields must not be dropped opportunistically.
- The Home Hero uses the same shared fitted media-width calculation and image/caption alignment but has its own multiple-slide/navigation/selection owner. Orientation-dependent Home expand toggles were retired. All visible detail/Home Artworks retain natural proportions and a **centered image-plus-information group**, including short landscapes.
- The **outer** background strip below the white Page is owned once by `#content` bottom `margin: var(--public-shell-gap)` (currently `.75rem`); distinguish it from the acceptable white space *inside* the Page. Shared fitted Page sizing no longer subtracts this gap twice. The fitted individual Artwork Viewer link is only as wide as the actual visible image, so white areas alongside portraits are not clickable.
- Gallery index uses aspect-preserving images, deterministic five-stop lateral drift and stable, pseudo-random *vertical* gap steps. Desktop gap `clamp(8rem, 17svh, 10rem)` plus 0–1rem of stable jitter; mobile up to 40rem retains `clamp(1rem, 3svh, 2rem)` without jitter. General owns Menu/Page width and padding. The prior 1px decorative media contours and orphan-leading caption commas were removed.
- Shared public owners: `resources/css/public-presentation.css`, `resources/js/public-gallery-loading.js`, `resources/views/pages/artworks/show.blade.php`, `resources/views/pages/gallery/hero-artwork.blade.php`, `resources/views/components/artwork-card.blade.php` and `app/Domain/Artwork/GalleryPresentation.php`. Keep the Page and Home fitting logic centralized; do not bring back multiple independent height or width calculations.

## Gallery Admin controls — next work

- Canonical Stage + Settings form schema: `app/Filament/Pages/Concerns/GalleryWorkspaceUploadSettings.php::gallerySettingsFields(bool $stage)`. Existing groups include Gallery title, Artwork order, Between Artworks, Artwork information (enable/field checkboxes plus Field/Row/Align arrangement), Media viewer (enable, zoom, navigation), Individual Artwork Pages (enable, end navigation) and video sound. Both Stage and modal reuse the same schema and existing mutation pathways; Stage uses immediate audited updates, while the modal uses its established Edit lifecycle.
- Also examine `app/Filament/Pages/GalleryWorkspace.php`, related `GalleryWorkspace*Support` traits, `AdminPanelControl`, `AdminArrangementControl` and existing canonical Admin dialog/stage rules in `docs/admin/ui.md`, `docs/admin/dialogs.md`, and `docs/admin/responsive.md`. There must not be separate controls or layout implementations for Stage vs Settings. Do not recreate the removed Artwork-page layout choice.
- Keep all Stage settings discoverable through the existing Search/Toolbar/Settings structure where applicable. No persistent helper paragraphs, needless rounded dialog chrome, extra mandatory stars, unsupported checkbox side effects or new duplicate autosave systems. Request the new user design before changing the layout.
- Existing `GalleryWorkspaceSelectionSupport::loadGallery()` no longer calls the removed `GalleryPresentation::artworkPageLayout()`; this previously caused an Admin HTTP 500. Preserve this source-level correction.

## Verification and boundaries

- Public image sizing, the bottom Page strip, Gallery gaps and Viewer click target were source reviewed and checked in **isolated Chromium geometry fixtures**; no full running Laravel Preview browser acceptance or complete regression suite was executed by this worker. The artist's latest visual response is positive but provisional; do not claim final browser qualification.
- Future checks if relevant: desktop/phone, portrait/landscape, title/navigation on/off, long metadata, Gallery pagination/dividers and multiple Home Hero slides. For Admin redesign verify Stage/Settings parity, checkbox persistence, nested field enable/disable, Field/Row/Align reorder, Preview update, save/error/no-op notifications and narrow-width layout. Testing, Docs, deployment and release remain separate authorization/ownership gates.
- This handoff changes coordination state only; no application files, tests, migrations, issues or deployment are part of this sign-off. Reread the latest `dev` source when replacing Worker 4 rather than relying on this handoff's historical commits.
