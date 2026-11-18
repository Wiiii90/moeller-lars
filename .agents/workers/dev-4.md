# Dev Worker 4 — Gallery/Artwork

**Lease:** 20
**Branch:** dev
**State:** Previous Public Gallery width-expansion and artificial image-upscaling rejected and reverted. Replacement source limits only upper whitespace and image height inside General's unchanged width; real browser acceptance pending.

## Ownership and current scope

- Active Dev Worker 4 on `dev`; Gallery/Artwork Public presentation, shared Gallery Stage/Settings schema, Information arrangement and Gallery Preview. Resume Stage/Settings after the Public Gallery corrections.
- Other owners remain unchanged: Dev Worker 6 owns Home Hero, Dev Worker 7 General Appearance, Dev Worker 2 Media/Storage, Dev Worker 8 shared dialog/scrollbar lifecycle, Dev Worker 1 Exhibitions. Earlier requested bounded Home Hero fixes were source-only, not a transfer of ownership.
- All writes require a fresh checked `dev` HEAD and lease. Do not run CI, deployment, create tests or operate on protected databases without explicit authorization.

## Public Gallery — current replacement candidate

- The user rejected the wide Content paper, broken Menu/Page alignment, oversized landscape art and stretched-looking proportions shown after `50140ff` and `ab7eacf`. The affected CSS and Blade are restored **exactly** to pre-expansion source `9c0200c`, apart from the next three targeted Gallery CSS adjustments.
- General remains sole owner of Page/Menu width, Content padding and responsive bounds. No per-Artwork aspect token, forced image-upscaling or expanded sheet. The Gallery keeps stable lateral drift, natural image proportions, and caption/credit/copyright width equal to its actual image.
- Gallery-only upper Content padding reduces from 2rem to 1rem; the first Artwork loses its artificial viewport minimum and vertical centering; the image ceiling reduces from `min(94svh, calc(100svh - 6rem))` to `min(82svh, calc(100svh - 9rem))`. Subsequent slots and the accepted tighter Artwork gaps stay unchanged. The media's image width is still limited by natural aspect and General Content width, so some landscapes may stay below the height ceiling.
- Primary owner: `resources/css/public-presentation.css`. Canonical Gallery Blade and Artwork card remain pre-expansion. Shared Home Hero, Artwork detail and General controls are untouched.

## Gallery Stage/Settings — retained acceptance target

- Gallery Stage is the fixed-height upload / Settings-or-Staged-Editor / interactive Preview triptych. Stage and large Settings modal use the **same** `gallerySettingsFields(bool $stage)` schema and Gallery editorial mutation owner. Actual mutations refresh Preview; invalid/no-op must not emit success.
- Gallery Title uses its own row; Artwork order and Between Artworks share the next row. Flat, square-cornered Artwork Information, Media Viewer and Individual Artwork Pages panels have paired child controls. Video-sound checkbox remains standalone, controls only the speaker button; looping videos initially muted.
- Information Arrangement is a real compact table with Field/Row/Align, native sortable drag and equivalent Move up/down. No nested rounded cards, Arrangement duplicate heading, persistent helper paragraphs or required stars. Gallery Auto-publish was removed: all new Artworks enter draft until expressly published. ArtworkInformation controls sparse per-Artwork overrides, persisted arrangement and media rights. Historic schema/working/committed snapshots must be preserved.

## Verification and next steps

- Source comparison, Gallery-only selector checks, restored markup checks and stylesheet brace checks executed. Reconciled Dev Worker 1's simultaneous Artwork-detail narrative/full-width edits from `0e2357b` so the Gallery rollback does not revert independent Artwork-detail functionality. No complete application run, CSS visual acceptance or runtime tests performed for this candidate.
- Next: inspect local Preview with the user's configured Page width/padding and Menu, both portrait and landscape at full desktop/mobile/zoom; verify page/header alignment, first item proximity, unchanged image aspect and footer width. Check galleries with headings/dividers/videos and long rights.
- Once Public Gallery is accepted, resume Stage and Settings browser qualification, persistence, feedback, manual Draft/Publish and interactive Preview. The published public-site documentation still contains the older height value; hand off the accepted replacement to Docs for later consolidation.
