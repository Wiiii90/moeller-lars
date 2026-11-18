# Dev Worker 4 — Gallery/Artwork

**Lease:** 20
**Branch:** dev
**State:** Public Gallery top spacing and height-driven image width updated in source; full Laravel browser acceptance pending. Gallery Stage/Settings to resume after the current Public Gallery excursion.

## Assignment and ownership

- Active Dev Worker 4 replacement, lease 20, on `dev`. Own Gallery/Artwork Public presentation, Gallery Stage, the shared Gallery Settings schema, Artwork information/arrangement and related Preview consumers.
- Preserve adjacent ownership: Dev Worker 6 owns Home Hero, Dev Worker 7 owns General Appearance, Dev Worker 2 owns Media/Storage integrity, Dev Worker 8 owns shared dialog/scrollbar behavior, and Dev Worker 1 owns Exhibitions. Earlier narrowly authorized Home Hero picker/action corrections were source-committed; further Home Hero work belongs to Dev Worker 6.
- Work directly on the latest `dev` with checked head and lease. No deployment, continuous integration runs, test-file editing, protected data migration or production operations without explicit authorization.

## Current Public Gallery decisions

- Artwork order is preserved; `GalleryPresentation::indexCardOffsets()` creates small deterministic side offsets, without changing sorting or persistence. The Gallery-only composition keeps the media, Artwork information, Credits and Copyright at the same actual rendered width and preserves user-configured field visibility and left/center/right arrangement. Home Hero and Artwork detail use separate presentation compositions.
- The last accepted sizing request raised the image ceiling to `min(94svh, calc(100svh - 6rem))`, reduced Gallery gaps to `clamp(2.5rem, 6.5svh, 4.5rem)` and the subsequent card minimum slot to `100svh - 12rem` (commit `7c9391f`).
- New feedback: Menu-to-first-image space was excessive, and landscape images were held to the default 650px General reading width, hence did not reach the Gallery image-height ceiling. The Gallery index now owns a page-specific, intrinsic-width paper capped by actual viewport width and bounded below by the normal shell; normal General Menu, ordinary pages and Artwork detail remain unchanged. The first card has no artificial viewport minimum slot and starts at the top. Later cards retain their rhythm.
- The Artwork image's published thumbnail width/height supplies a numeric image aspect token via the existing nonce-protected component-style stack, so even smaller thumbnail variants can be *displayed* at the height ceiling when enough screen width exists. Width shrinkage at a narrower physical viewport reduces height proportionally; no crop, image distortion or forced empty object-fit letterboxing. Missing width/height metadata gracefully retains intrinsic auto sizing. Gallery video composition remains bounded by General's normal artwork width.
- Single presentation owner: `resources/css/public-presentation.css`; markup owners: `resources/views/pages/artworks/index.blade.php`, `resources/views/components/artwork-card.blade.php`. No new state store, controller, media derivative, scripts, route or configuration option.

## Retained Gallery Stage/Settings decisions

- Gallery Stage shares its fixed-height triptych: queue/upload, Gallery Settings or Staged Artwork editor, interactive responsive Live Preview. Stage and Settings modal use the same `gallerySettingsFields(bool $stage)` schema and Gallery editorial mutation owner; a real save refreshes Preview. Invalid/no-op changes must not announce success.
- Layout: full-width Gallery Title; Artwork order and Between Artworks in a paired row; optional video-sound checkbox below flat, square-cornered Artwork information / Media viewer / Individual Artwork Pages panels. Panels expose two-column children across Stage and Settings without persistent helper paragraphs or required asterisks.
- Information arrangement is a dense real table with Field / Row / Align, native drag and Move up/down persistence; no nested rounded Repeater cards or redundant Arrangement caption. Gallery Auto-publish preference is entirely retired: new Artworks from all Gallery create flows are drafts until explicit publication. Gallery loop videos start muted, and the Gallery sound preference controls only the visitor speaker button.
- `ArtworkInformation` owns ordered fields and sparse per-Artwork overrides; Gallery independent sort options include Manual, Artwork date and primary Media Added. Original rights remain from MediaAsset. Historical published snapshots and schema compatibility must be preserved. The integrated presentation migration was previously source-only, not confirmed run locally.

## Verification and remaining work

- Source owner inspected: the former Gallery reading-width cap and vertically centered first card are confirmed root causes. Isolated headless Chromium geometry fixtures cover landscape and portrait (including thumbnails smaller than the displayed size), wide/compact/mobile widths, image/footer width parity, a short first-item top gap and no horizontal overflow. A separate video-only fixture identified and corrected an intrinsic-width shrink to 300px; the ordinary General video width (650px at its default desktop size, available width on mobile) is preserved. These fixtures are **not** full-app browser acceptance.
- Full Laravel Preview still needs user-visible verification for current Gallery, especially configured responsive General dimensions/padding, optional headings/dividers, long rights, videos, zoom, lazy-loading and backing sheets. Test both direct Public Preview and embedded Gallery Preview. Existing Stage/Settings narrow table, persistence, notifications, manual Draft/Publish and migration gates also remain pending.
- Permanent `docs/public/site.md` still describes the older fixed General-width Gallery; route the updated geometry to Docs at the documentation consolidation gate rather than editing general documentation in Dev.

## Next

1. Review the height-fitted Gallery source candidate in the actual local Preview and adjust based on user screenshots or reproducible defects, preserving both the page-backed wider media composition and the first-card spacing.
2. Resume Gallery Stage/Settings visual and functional acceptance when the user ends the Public Gallery excursion, including its shared controls, real Arrangement table, and interactive Preview.
3. Keep direct consumers and other workers' established boundaries intact. Record any executed verification accurately and reconcile fresh `dev` HEAD/lease before every write.
