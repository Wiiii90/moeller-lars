# Dev Worker 6 — Gallery Hero

**Working branch:** `dev`
**Current lease:** 32 (replacement chat, 2026-11-21).
**Current state:** Scoped Hero source corrections staged for checked publication. Source inspection only; local application/browser verification and user acceptance outstanding.

## Ownership and accepted design

- Gallery → Hero Artworks (`/admin/pages/gallery/hero/1`) configures Home Hero from regular Galleries and named negative-ID Dummy Galleries. Source modes: Automatic (enabled regular), Semi-Automatic (regular plus enabled Dummy), Manual (enabled Dummy only).
- Regular Gallery identity, contents and settings belong to the existing Gallery workspace and editorial service, not to the Hero source editor. Dummy Gallery can add/remove its own references, not mutate an underlying regular Gallery.
- Gallery-first source ordering, source ranking and limits (Total N or Per Gallery N), Display (Top Only/Random/Sequential) persist. Do not restore individual manual candidate sorting.
- Coordinate broader Gallery UI with Dev Worker 4, public Gallery loading with Dev Worker 11, and shared controls with current owners.

## Current correction — regular Edit vs Dummy Edit; read-only Date

- The collapsed regular Gallery's Edit button incorrectly opened the **first Artwork's** `editHeroArtwork` action, not Gallery settings. Collapsed and expanded source-group Edit now route to the existing Gallery workspace (`workspace_url`); Dummy source Edit continues to use the Hero-specific Dummy Gallery dialog. Individual Artwork edits remain available on expanded candidate rows.
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

1. Reopen Hero regular Gallery Edit in both collapsed/expanded source-group presentations: it should reach the correct Gallery workspace, not an arbitrary first Artwork. Verify individual expanded Artwork Edit no longer produces `Unknown named parameter $onInformationChanged`, including save/reopen and primary-media path.
2. Verify Dummy Gallery Edit and Create: no editable Date, an em dash for missing Date, correct date locale from Admin user settings, intact title edit, candidate insert/removal, disable/enable, pagination/search and clean responsive Actions alignment.
3. Verify previously unaccepted Random behavior (1/2/3+ distinct visuals and next probabilities), Filter popup opening/fitting across consumers, and media picker geometry. No browser/runtime or suite was run by this chat.
4. Route accepted lasting Hero and Dummy source semantics to Docs after user approval; do not edit general docs/tests or run CI/deploy without separate authorization.

**Next:** Complete checked source publication, then request the user to verify the affected Hero interactions in the refreshed local browser. Only fix demonstrated regressions and revalidate lease/head before each write.
