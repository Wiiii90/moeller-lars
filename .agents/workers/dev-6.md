# Dev Worker 6 — Gallery Hero

**Working branch:** `dev`
**Current lease:** 32 (replacement chat, 2026-11-21).
**Current state:** Hero-only individual Artwork overrides implemented across edit persistence, candidate resolution, Stage, public Hero card and media authorization for source review. Runtime/browser verification and user acceptance outstanding.

## Ownership and product contract

- Gallery → Hero Artworks (`/admin/pages/gallery/hero/1`) configures Home Hero Artworks from regular and Dummy Galleries. Regular Gallery/Artwork data belongs to Gallery; Hero is strictly a **consumer**. No Hero Edit writes original Artwork, Gallery or Media Asset.
- Collapsed source-group Edit opens a Hero source Edit dialog, not an Artwork editor or Gallery navigation. Each expanded Artwork row's button **Edit** opens a dedicated **Edit Hero Artwork** dialog. The existing Gallery title remains the separate navigation link. Dummy group Edit controls explicit source members; regular group Edit does not add/remove Gallery members.
- The dialog presents inherited title, partial Date, Material, Dimensions and Media (including ALT), and the canonical individual Artwork information panel. Changes save only the delta for the corresponding Hero source and Artwork. Changing a value back to its inherited original removes that field's override; **Restore original** in the dialog header removes the entire Hero delta and remounts the unchanged original. The Source label has canonical Help explaining isolation.
- Reused owner controls: `ArtworkEditDialog::fill` (read-only source snapshot), `ArtworkEditDialog::individualInformationPanel`, `AdminPartialDateControl`, `AdminDimensionsControl`, `ArtworkMaterialSelect`, `MediaAssetSelect`, `AdminDialog` and its header action. `GalleryHeroArtworkDialog` defines only Hero-specific form composition; original `ArtworkEditDialog::save`, `changePrimaryMedia` and Gallery/Media draft writers are never invoked.

## Persistence and consumers — current work

- `GalleryHeroConfigurationService` remains the **only** Hero configuration mutation/audit owner; its composed `GalleryHeroArtworkOverrides` trait validates source membership, metadata precision/length and replacement image/video eligibility. It stores sparse `hero_artwork_overrides` keyed by **`sourceId|artwork:artworkId`** in the existing `gallery_hero_settings.configuration` JSON. This map is shared across source modes and remains separate per Hero page. A deleted Dummy source/member drops its stale overrides. No new table/migration/parallel settings entity was introduced.
- `GalleryHeroResolver` applies overlays **after** original Gallery ranking and candidate selection. It loads selected valid replacement Media in one batch and keeps canonical Artwork identities, source order/weights and original Gallery ranking. `GalleryHeroCandidate` exposes Hero values and changed visual key without mutating its source record.
- `GalleryHeroWorkspaceProjection` and Dummy source member row projection show the same effective title/date/thumbnail as the Hero selection; excluded candidates use source identity and the same overlay application.
- `resources/views/pages/gallery/hero-artwork.blade.php` passes the Hero overlay into the existing `artwork-card` component. That shared card uses the Hero-specific visual for image/video, Viewer item, title/ALT/metadata and information visibility **only when `heroFitted`**. All ordinary Gallery/Artwork consumers retain their canonical behavior. Original Artwork detail links remain original Gallery links.
- `MediaReferenceQuery` includes actively selected Hero replacement media in public authorization and all stored Hero replacement media in reference retention. Disabled or out-of-group media do not become public through this path. No Media Asset record/primary link gets replaced.
- Previous Stage candidate fade and fixed Enable/Disable vs Remove axes remain. Shared source-group Edit retains canonical top-right state action, and individual Hero Artwork Restore uses the same header action grammar.
- **No runtime, PHP/unit/browser test, CI or deployment executed in this chat.** Source-only review required before user acceptance.

## Browser review checklist

1. Regular and Dummy source candidate **Edit** both open **Edit Hero Artwork**, no navigation, preserving the original Gallery. Check content values and Help; date in Admin regional format; original meta and primary asset unchanged after edits.
2. Change Title, Material, partial Date (year/month/day/clear), Dimensions (structured/custom), Media and Hero ALT. Preview and public Hero must show effective Hero values; original Gallery, Artwork Edit, Artwork page and original Media ALT remain identical.
3. Override the **same Artwork** in two different Hero sources and two different Hero pages: values must stay scoped. Switching Automatic/Semi-Automatic/Manual and source order must not wipe overrides or change Gallery rankings.
4. Change fields back to their inherited originals, then test **Restore original** (including repeated no-op): Hero value returns to inherited value, source data stays intact, no duplicate success audit.
5. Individual information panel toggles should only affect Hero caption visibility, not the original. Test Media with different visual, same visual selected twice in Random and Viewer/video behavior; source-only media authorization must be limited to eligible active group.
6. Test missing/unpublished/removed Media File fallback, Dummy member removal, deleted source, disabled/excluded candidates, responsive Edit dialog and Stage action/scroll fade. Public-rights delivery remains the canonical Media policy.

## Remaining coordination

- Dev Worker 3 also owns Hero source-list/Stage/Public Preview presentation; reconcile their concurrent changes before any future overwrite. Dev Worker 4 owns original Gallery/Artwork; no mutation to their editorial services was introduced. Dev Worker 11's public Gallery work remains separate.
- Documentation, Tests, Continuous Integration, deployment and release promotion require their own assigned workers/authorization. After user browser acceptance, hand off durable Hero override semantics to Docs.

**Next:** Source verification of the checked commit, then user browser review. Fix concrete regressions only after fresh lease/head reconciliation.
