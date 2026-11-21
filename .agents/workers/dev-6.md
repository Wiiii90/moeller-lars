# Dev Worker 6 — Gallery Hero

**Working branch:** `dev`
**Current lease:** 32 (replacement chat, 2026-11-21).
**Current state:** User rejected navigation substitution for regular source Edit. Source-only corrective rollback restores the pre-existing mounted Edit dialog instead of navigation; the obsolete `onInformationChanged` named parameter fix remains applied. Browser/user acceptance outstanding.

## Ownership and accepted design

- Gallery → Hero Artworks (`/admin/pages/gallery/hero/1`) configures Home Hero from regular Galleries and named negative-ID Dummy Galleries. Source modes: Automatic (enabled regular), Semi-Automatic (regular plus enabled Dummy), Manual (enabled Dummy only).
- Regular Gallery identity, contents and settings belong to the existing Gallery workspace and editorial service, not to the Hero source editor. Dummy Gallery can add/remove its own references, not mutate an underlying regular Gallery.
- Gallery-first source ordering, source ranking and limits (Total N or Per Gallery N), Display (Top Only/Random/Sequential) persist. Do not restore individual manual candidate sorting.
- Coordinate broader Gallery UI with Dev Worker 4, public Gallery loading with Dev Worker 11, and shared controls with current owners.

## Current correction — regular Edit vs Dummy Edit; read-only Date

- The user's existing Edit button was wrongly changed into a Gallery navigation link. That change is reverted **without designing a new editor**. Collapsed regular Gallery/Edit and first expanded source-group Edit again mount the original `editHeroArtwork` dialog for the corresponding first candidate. The separate Gallery title link remains the only navigation to the Gallery workspace; Dummy-source Edit still opens `editDummyGallery`. This restores prior behavior, and does not assert that the historical naming/target of the source-level Edit matches the user's desired whole-Gallery semantics.
- The individual Artwork action passed unsupported `onInformationChanged` to `ArtworkEditDialog::schema`. Remove the stale callback; rely on the current canonical dialog's schema and existing save/primary-media mutation owner.
- Dummy Gallery membership's Date is informational for both Artwork and Storage references, formatted through `ArtworkDate::displayParts(..., RegionalFormats::adminRegion())`, with an em dash for missing values. Title editing, enabling/disabling and removing source references remain. No date picker or Hero member date mutation is exposed.
- Remove the now-unnecessary shared partial-date wrapper introduced solely for the Hero Date editor, restoring the existing Artwork editor's canonical Blade picker. Retain the admin-region preference fix and data-format display helper. Persisted year/month/day values are preserved; no database migration or data cleanup.
- Previous member table geometry (single identity, Date, fixed Actions slots, responsive compaction and static-row hover) remains as committed.

## Previously committed source

- `c27664e1`: Random excludes the last visual Media Asset when multiple positive-weight visuals exist, preserves stored weights, and shows next-draw probability; public/Preview histories separated.
- `dcb97e21`, `e2fd3a76`: common visual MediaAssetPicker for Dummy Gallery and bounded pagination past 30 Artwork/Storage options.
- `9365f8de`: shared Filters popup opens after placement measurement; first-frame flash corrected at source level.
- `60465aa9`: fixed missing `disabled` key in Hero Stage.
- `c6c60d2`: visual MediaAssetPicker options use a symmetric compact inset rather than inheriting ordinary Select option margins.
- `611461b4`, `9e9edd9e`: Dummy Gallery three-column responsive table, compact Actions rail and regional Date display. The inline date-edit control from that first implementation has now been rejected and is removed by the current change.

## Outstanding verification and handoff

1. Reopen regular Hero source Edit in collapsed/expanded states: the original mounted Artwork Edit dialog must open **in place**, never navigate. Verify the `Unknown named parameter $onInformationChanged` crash is gone and save/reopen works. The difference between the historical source Edit target (first candidate Artwork) and a potential future full regular-Gallery editor is an unresolved product requirement; do not invent a new editor in a bugfix.
2. Verify Dummy Gallery Edit and Create: no editable Date, an em dash for missing Date, correct date locale from Admin user settings, intact title edit, candidate insert/removal, disable/enable, pagination/search and clean responsive Actions alignment.
3. Verify previously unaccepted Random behavior (1/2/3+ distinct visuals and next probabilities), Filter popup opening/fitting across consumers, and media picker geometry. No browser/runtime or suite was run by this chat.
4. Route accepted lasting Hero and Dummy source semantics to Docs after user approval; do not edit general docs/tests or run CI/deploy without separate authorization.

**Next:** Publish the narrow rollback of the erroneous navigation and verify the two UI entry points in source. Ask for runtime/browser verification of the original mounted Edit behavior. Do not redesign or replace the feature while fixing this crash.
