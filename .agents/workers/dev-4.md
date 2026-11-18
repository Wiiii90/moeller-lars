# Dev Worker 4 — Gallery/Artwork

**Lease:** 20
**Branch:** dev
**State:** Gallery images now maximize their displayed size within General's unchanged Content width and the existing viewport-height ceiling; mobile Artwork separation reduced. Source-only change; real application browser acceptance pending.

## Ownership and current scope

- Active Dev Worker 4 on `dev`; Gallery/Artwork Public presentation, shared Gallery Stage/Settings schema, Information arrangement and Gallery Preview. Resume Stage/Settings after the Public Gallery corrections.
- Other owners remain unchanged: Dev Worker 6 owns Home Hero, Dev Worker 7 General Appearance, Dev Worker 2 Media/Storage, Dev Worker 8 shared dialog/scrollbar lifecycle, Dev Worker 1 Exhibitions. Earlier requested bounded Home Hero fixes were source-only, not a transfer of ownership.
- All writes require a fresh checked `dev` HEAD and lease. Do not run CI, deployment, create tests or operate on protected databases without explicit authorization.

## Public Gallery — current replacement candidate

- The user rejected the wide Content paper, broken Menu/Page alignment, oversized landscape art and stretched-looking proportions shown after `50140ff` and `ab7eacf`. The affected CSS and Blade are restored **exactly** to pre-expansion source `9c0200c`, apart from the next three targeted Gallery CSS adjustments.
- General remains sole owner of Page/Menu width, Content padding and responsive bounds. The Gallery keeps stable lateral drift, natural proportions, and captions/credits/copyright at the actual rendered image width. A Gallery-only, nonce-authorized numeric image aspect token permits sizing each still to the smaller of the existing Content width and the image-height ceiling; unlike the rejected earlier implementation, the Gallery paper and Menu never expand. Smaller source thumbnails can scale to that bound. No new script, persistence field or alternate rendering path.
- Gallery-only upper Content padding stays at 1rem; the first Artwork has no artificial viewport minimum or vertical centering. The image height ceiling remains `min(82svh, calc(100svh - 9rem))`, but a wide landscape cannot reach this ceiling when the fixed General Content width limits it first (e.g. 16:9 at 650px width is about 366px high). Other desktop slots/gaps stay unchanged. At 40rem and below, gaps reduce to `clamp(1rem, 3svh, 2rem)` and every card loses the viewport-minimum slot, including Gallery divider layouts.
- The only affected presentation owner is `resources/css/public-presentation.css`, with a conditional Gallery-only sizing token in the existing `resources/views/components/artwork-card.blade.php`. `resources/views/pages/artworks/index.blade.php` and General layout/width owners are unchanged; Home Hero, individual Artwork detail and gallery videos retain their existing presentation.

## Gallery Stage/Settings — retained acceptance target

- Gallery Stage is the fixed-height upload / Settings-or-Staged-Editor / interactive Preview triptych. Stage and large Settings modal use the **same** `gallerySettingsFields(bool $stage)` schema and Gallery editorial mutation owner. Actual mutations refresh Preview; invalid/no-op must not emit success.
- Gallery Title uses its own row; Artwork order and Between Artworks share the next row. Flat, square-cornered Artwork Information, Media Viewer and Individual Artwork Pages panels have paired child controls. Video-sound checkbox remains standalone, controls only the speaker button; looping videos initially muted.
- Information Arrangement is a real compact table with Field/Row/Align, native sortable drag and equivalent Move up/down. No nested rounded cards, Arrangement duplicate heading, persistent helper paragraphs or required stars. Gallery Auto-publish was removed: all new Artworks enter draft until expressly published. ArtworkInformation controls sparse per-Artwork overrides, persisted arrangement and media rights. Historic schema/working/committed snapshots must be preserved.

## Verification and next steps

- Inspected the current Gallery CSS, single shared Artwork component and General width owner. Ran isolated headless Chromium layout fixtures at desktop (1600×900) and mobile (390×844) with landscape, portrait and smaller 500×281 image variants; measured content/image/footer widths and no horizontal overflow. The smaller variant now displays at the full allowed 650px desktop Content width without distortion; a 16:9 landscape necessarily remains below the height ceiling if the width cap is reached first. Additional mobile viewport fixtures (390×844, 420×600 and 375×350) confirmed compact computed gaps, removed minimum slots and preserved image/footer widths. **These are isolated geometry fixtures, not local Laravel application or artist-accepted browser reviews.**
- Next: inspect local Preview with the user's configured Page width/padding and Menu, both portrait and landscape at full desktop/mobile/zoom; verify unchanged Page/Menu alignment, maximum feasible Gallery image sizes, first item proximity, responsive image/footer width, reduced phone gaps and no phantom viewport slots. Check optional Gallery headings/dividers/videos, long rights and deferred images.
- Once Public Gallery is accepted, resume Stage and Settings browser qualification, persistence, feedback, manual Draft/Publish and interactive Preview. The published public-site documentation still contains the older height value; hand off the accepted replacement to Docs for later consolidation.
