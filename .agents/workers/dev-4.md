# Dev Worker 4 — Gallery/Artwork

**Lease:** 20
**Branch:** dev
**State:** Gallery image sizing remains within General's Content width and the existing height ceiling; desktop viewport-minimum Artwork slots removed, so portrait/landscape gaps use only the configured Gallery gap. Browser acceptance pending.

## Ownership and current scope

- Active Dev Worker 4 on `dev`; Gallery/Artwork Public presentation, shared Gallery Stage/Settings schema, Information arrangement and Gallery Preview. Resume Stage/Settings after the Public Gallery corrections.
- Other owners remain unchanged: Dev Worker 6 owns Home Hero, Dev Worker 7 General Appearance, Dev Worker 2 Media/Storage, Dev Worker 8 shared dialog/scrollbar lifecycle, Dev Worker 1 Exhibitions. Earlier requested bounded Home Hero fixes were source-only, not a transfer of ownership.
- All writes require a fresh checked `dev` HEAD and lease. Do not run CI, deployment, create tests or operate on protected databases without explicit authorization.

## Public Gallery — current replacement candidate

- The user rejected the widened Content paper, broken Menu/Page alignment, oversized landscape art and stretched-looking proportions shown after `50140ff` and `ab7eacf`. Keep their expanded Gallery paper, forced image width, and changed Gallery page class fully retired. The current source adds only a measured, Gallery-contained image-width calculation and compact mobile rhythm to the restored General-width composition.
- General remains sole owner of Page/Menu width, Content padding and responsive bounds. The Gallery keeps stable lateral drift, natural proportions, and captions/credits/copyright at the actual rendered image width. A Gallery-only, nonce-authorized numeric image aspect token permits sizing each still to the smaller of the existing Content width and the image-height ceiling; unlike the rejected earlier implementation, the Gallery paper and Menu never expand. Smaller source thumbnails can scale to that bound. No new script, persistence field or alternate rendering path.
- Gallery-only upper Content padding stays at 1rem; **every** Gallery Artwork card now takes natural media-and-caption height, with no viewport-minimum slot and no vertical centering. The previous first-card and phone exceptions were consolidated into the Gallery base rule. The desktop gap remains `clamp(2.5rem, 6.5svh, 4.5rem)`, and at 40rem and below it remains `clamp(1rem, 3svh, 2rem)`. With dividers, the same gap is distributed equally around the divider. The image-height ceiling remains `min(82svh, calc(100svh - 9rem))`; General Content width can limit landscapes before the ceiling is reached. This change does not alter height/width calculations, responsive drift, media aspect ratios, or captions.
- The only affected presentation owner is `resources/css/public-presentation.css`, with a conditional Gallery-only sizing token in the existing `resources/views/components/artwork-card.blade.php`. `resources/views/pages/artworks/index.blade.php` and General layout/width owners are unchanged; Home Hero, individual Artwork detail and gallery videos retain their existing presentation.

## Gallery Stage/Settings — retained acceptance target

- Gallery Stage is the fixed-height upload / Settings-or-Staged-Editor / interactive Preview triptych. Stage and large Settings modal use the **same** `gallerySettingsFields(bool $stage)` schema and Gallery editorial mutation owner. Actual mutations refresh Preview; invalid/no-op must not emit success.
- Gallery Title uses its own row; Artwork order and Between Artworks share the next row. Flat, square-cornered Artwork Information, Media Viewer and Individual Artwork Pages panels have paired child controls. Video-sound checkbox remains standalone, controls only the speaker button; looping videos initially muted.
- Information Arrangement is a real compact table with Field/Row/Align, native sortable drag and equivalent Move up/down. No nested rounded cards, Arrangement duplicate heading, persistent helper paragraphs or required stars. Gallery Auto-publish was removed: all new Artworks enter draft until expressly published. ArtworkInformation controls sparse per-Artwork overrides, persisted arrangement and media rights. Historic schema/working/committed snapshots must be preserved.

## Verification and next steps

- Inspected the current Gallery CSS, single shared Artwork component and General width owner. Ran isolated headless Chromium layout fixtures at desktop (1600×900) and mobile (390×844) with landscape, portrait and smaller 500×281 image variants; measured content/image/footer widths and no horizontal overflow. The smaller variant now displays at the full allowed 650px desktop Content width without distortion; a 16:9 landscape necessarily remains below the height ceiling if the width cap is reached first. Additional mobile viewport fixtures (390×844, 420×600 and 375×350) confirmed compact computed gaps, removed minimum slots and preserved image/footer widths. **These are isolated geometry fixtures, not local Laravel application or artist-accepted browser reviews.**
- Next: inspect local Preview with the user's configured Page width/padding and Menu, both portrait and landscape at desktop/mobile/zoom; confirm the actual visible gap from each caption/image bottom to the next Artwork is consistent regardless of orientation, including with optional dividers. Check Page/Menu alignment, image/footer widths, long rights, video and deferred images. Only source-level review, not an application browser run, has been performed for this gap correction.
- Once Public Gallery is accepted, resume Stage and Settings browser qualification, persistence, feedback, manual Draft/Publish and interactive Preview. The published public-site documentation still contains the older height value; hand off the accepted replacement to Docs for later consolidation.
