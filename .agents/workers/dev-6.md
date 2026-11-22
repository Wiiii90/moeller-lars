# Dev Worker 6 — Gallery Hero

**Branch:** `dev`
**Current lease:** 32
**State:** Hero Artwork Edit dialog simplified to one canonical Activate Override panel; source-only verification pending publication and browser review.

## Current accepted product behavior

- Hero Artworks consumes Gallery Artworks and Dummy source references. The original Artwork, Gallery membership and Media Asset are never mutated by Hero editing.
- Source Gallery title is already a navigation link; group Edit opens Hero-only source settings. Expanded Artwork **Edit** opens **Edit Hero Artwork**. The Hero source identifier is not an editable form field.
- **Activate Override** is the only metadata editor panel switch. When off, the current Hero renders inherited Title/Date/Material/Dimensions. Previously entered differences stay stored in the Hero configuration for a future reactivation. When on, only sparse changed values are effective.
- The form contains Title, canonical partial Date, Material and canonical structured Height/Width/Depth/Unit. It does **not** contain an Original Source field, Custom dimensions, Hero Media File, ALT or an individual metadata visibility checkbox collection.
- Hero media visuals and ALT always come from their source Artwork/Media Asset; existing direct Storage references in Dummy Galleries remain separate source members and are not affected. The newly introduced but rejected Hero Media/ALT override pipeline was removed from resolver, candidate, public card, Stage and media reference authorization.
- Information visibility (including Copyright, which is **visibility**, not content editing) remains in the existing Hero settings area: `GalleryHeroSettingsDialog` show_details/show_media_credits/show_media_copyright. The duplicate individual checkbox panel is removed from Edit Hero Artwork.
- The previous **Restore original** header action is replaced by the central `AdminPanelControl::make` switch; no standalone Restore button remains in this specific dialog. This decision is Hero-specific; it does not change other dialogs' Restore semantics.

## Canonical owners and persistence

- `GalleryHeroArtworkDialog` composes the existing `AdminPanelControl`, `AdminPartialDateControl`, `ArtworkMaterialSelect`, `AdminDimensionsControl` and `AdminControl` inputs without a parallel picker. It reads only the necessary immutable original fields; it never calls original Artwork editing/persistence methods.
- `GalleryHeroArtworkOverrides` is the sole source-bound overlay mutation trait of `GalleryHeroConfigurationService`; overlays live in `gallery_hero_settings.configuration.hero_artwork_overrides` by `sourceId|artwork:artworkId`. The `enabled` boolean gates rendering while preserving previously authored deltas. Older entries lacking `enabled` remain active when nonempty. Previously created unsupported media/ALT/visibility overlay entries are no longer editable or projected.
- The resolver applies enabled metadata after source ranking. `GalleryHeroWorkspaceProjection`, the public Hero `artwork-card` and `ArtworkInformation::rows` consume the same effective values. All original Gallery screens continue to consume canonical Artwork data.
- Legacy authored freeform dimensions are still preserved for read/display; no Hero UI creates or edits `dimension_custom`. Do not delete historical original dimensions. `ArtworkDimensions` continues to own structured parsing/display.
- Deliberate null overrides for Material, Date and Dimensions remain distinct from an omitted autosave field: clearing a value survives an off/on panel cycle instead of restoring inherited data inadvertently.
- Existing `MediaReferenceQuery` still authorizes directly sourced Dummy Storage files; it does not authorize rejected Hero Media replacements.
- Existing Stage fade, source table action rails and regular/Dummy source editor contracts remain separate.

## Review and remaining work

1. Hero Artwork Edit opens without navigation; panel initially off for a new Artwork, on for active stored overrides, off for disabled stored overrides; uses the canonical central panel visual and responsive modal.
2. Activate, edit Title, Date (year/month/day/clear), Material and structured dimensions, confirm immediate persistence and matching public Hero/Preview/Stage data. Check that an original Gallery Artwork retains its original metadata and Media reference.
3. Deactivate the override and check inherited values in Stage/Public; reopen and reactivate to restore the previous Hero-specific metadata without retyping. Test repeating toggles and saved/reopened state.
4. Hero editor has no Source input, Custom dimensions, Media File, ALT or the confusing Title/Date/Material/Copyright visibility checkbox collection. The separate Hero settings visibility controls remain operational.
5. Verify old stored freeform dimensions survive unrelated edits; no data loss. Verify Dummy/regular source identities remain independent and removed source/member overrides are pruned correctly.
6. Original Artwork Editing/Media changes remain under Gallery workers. Coordinate with Dev Worker 3 for Stage/public Hero and Dev Worker 4 for source Gallery; do not modify their code without source/head reconciliation.
7. Prior pending browser acceptance: Random visual anti-repeat and percentages, Filter popup first-frame geometry, MediaAssetPicker inset, Stage fade/Actions and Dummy Gallery responsive table. All still require real browser verification.

**Verification:** Repository source checks only; no PHP runtime, browser, test-suite, Continuous Integration, server deployment or release promotion was executed.
**Next:** Publish this scoped cleanup with a checked lease/head; request user browser review and fix confirmed regressions. Handoff durable accepted semantics to Docs after approval.
