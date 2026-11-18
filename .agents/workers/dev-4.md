# Dev Worker 4 — Gallery/Artwork

**Lease:** 20
**Branch:** dev
**State:** Expanded is the sole individual Artwork detail composition. Removed Default/Museum presentation branches and Gallery Stage/Settings layout selector; acceptance in real Preview pending.

## Scope and ownership

- Active Dev Worker 4 on `dev` owns Gallery/Artwork public index/detail and shared Gallery Stage/Settings. Dev Worker 6 owns the separate Home Hero; Dev Worker 7 General, Dev Worker 2 Media/Storage and Dev Worker 8 shared dialogs.
- Keep the branch-local lease/head write gate. No migration execution, deployment, all-suite tests or protected data operations without authorization.
- The Home Hero is rendered by `resources/views/pages/gallery/hero-artwork.blade.php` and remains unchanged. An individual Artwork opened from Home uses the same `/artworks/{slug}` detail route and now displays Expanded like Paintings.

## Individual Artwork — accepted sole layout

- The Paintings screenshot shows the artist-approved Expanded composition: Gallery back/previous/next at the General Content edges, image fitted inside General width and available viewport height, Artwork information aligned immediately underneath the actual image, and optional longer description on the full General content track.
- `show.blade.php` always outputs `site-content--fitted-artwork`; images additionally receive `site-content--fitted-image` for the existing height fit and vertically centered image+footer grouping. Videos keep their natural dimensions. No Standard/Museum conditional path and no second detail renderer.
- The Museum grid styles and unused Standard/Museum/Expanded option constants and Gallery-specific layout selector were removed from source. `gallerySettingsFields(bool $stage)` continues as the single Stage and modal schema, now with only the Individual Artwork Pages enable toggle and end-navigation setting in that panel. The legacy field is removed from active validation, editable stage whitelist and model mass-assignment.
- **Publication boundary:** Historical `artwork_page_layout` columns in Working/Committed, earlier migrations and `PublicationVersionService` checkpoint projection remain unchanged; they are no longer consulted by live rendering or editing. Do not drop or rewrite those columns/versions before an approved cutover.
- No Gallery index, Home Hero, General shell/padding or media viewer behavior was changed by this task.

## Existing accepted Gallery composition

- Gallery index uses natural-size ratio with max height `min(82svh, calc(100svh - 9rem))`, bounded by General content width and with deterministic five-stop lateral drift. Information matches displayed image width, without decorative 1px image/video outlines.
- Desktop Gallery vertical gap: `clamp(3.5rem, 8svh, 5rem)`; phone (<=40rem): `clamp(1rem, 3svh, 2rem)`. First Artwork top inset is `max(1rem, var(--public-content-inset))` on larger viewports; phone 1rem. No artificial viewport-height slots.
- Fitted detail image and footer are vertically centered as one group, with existing .7rem media-to-caption spacing. General owns shared Menu/Page paper and Content padding.

## Gallery Stage/Settings — remaining ownership

- Stage and modal share `gallerySettingsFields(bool $stage)`. Keep existing Gallery Title, Artwork order, Between Artworks, Artwork Information, Media Viewer, Individual Artwork Pages enabled/edge navigation, video sound controls. No persistent help prose or excessive rounding; new Artworks remain draft until expressly published.
- Artwork Information arrangement is one editable Field/Row/Align schema with reorder and move controls, sparse per-Artwork overrides and independent rights/Viewer visibility. Preserve publication and Undo/checkpoint behavior.

## Verification outstanding

- Source-level checks only; no real Laravel browser or user acceptance pass in this chat. Check Paintings plus Galleries previously set to Standard or Museum, portrait and landscape media, video, long captions/descriptions, on/off Viewer, responsive widths/zoom and Home-linked detail navigation.
- Docs on the dedicated docs workstream should update public documentation after visual acceptance. Historical database cleanup is a separate authorized cutover decision.
