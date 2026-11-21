# Dev Worker 2 — Custom Pages

- **Branch:** `dev`
- **Lease:** 35
- **State:** Active — canonical layout design and component consolidation planning; no application code changes yet.
- **Owned scope:** Custom Page composition, component editor and Public Custom Page content. Coordinate shared Stage/preview with Dev Worker 1 and page types/navigation/Home conversion with Dev Worker 5.
- **Latest source baseline inspected:** `7b7d5d2d930762051c54bd483e73f62ae92a68fc`.

## User direction (2026-11-21)
- Custom Page should compose local instances of existing page-template capabilities (Gallery, Hero Artworks, Blog, Exhibitions), not automatically reference Gallery or other Page records. No subordinate SiteSection/Navigation Node per embedded instance.
- Design a genuinely responsive, visually understandable row/grid/column/area layout, including a two-column CV + portrait / legal + contact example. Reuse the shared Stage when Dev Worker 1 completes it; do not create a parallel preview owner.
- Consolidate redundant components first. User confirmed all real authored lists use Rich Text, not the standalone List component, so retire standalone List instead of inventing a structured-list replacement. Preserve actual content, publication records and media references; migration requires safety checks.
- Keep an optional, understated Public footer with Contact and Legal accessible as a separately tracked follow-up, not a Custom Page-only feature.

## Verified source findings
- Canonical persisted Custom Page content is the ordered `CustomPageSetting.blocks` JSON, owned by `CustomPageSetting` normalization/validation and `CustomPageEditorialService` transactional mutations/audit; Admin `CustomPageWorkspace` presents a positional hierarchy, and Public uses `pages/partials/component-sequence.blade.php`.
- Source supports an independent `list` type, but the user confirms real authored lists use Rich Text. Do not preserve the unused type for speculative List workflows. `image.children` contains nested content; `divider` is a separate content block today.
- `HomePresentationSetting` is retired in the current source; protected Home can be Custom Page via `SiteSection`. Avoid resurrecting Home-only copies of component pipelines.
- Both `public` and `committed` schemas contain `custom_page_settings`; retained historical publication snapshots and restores require deliberate schema canonicalization. The existing `HistoricalCustomPageBlockCanonicalizer` is a boundary-only precedent.
- The current editor targets positions/types rather than persistent component identity. A new composition model should make each node and area stable and concurrency-safe before implementing drag targets.

## Proposed architectural target — NOT YET IMPLEMENTED OR USER-ACCEPTED
- Keep one authoritative persisted composition inside `CustomPageSetting.blocks`. Do not add a second `layout` column, a mirror of `blocks`, or a separate Page subclass.
- Two node families only: **layout** nodes (stack/grid with responsive tracks, gaps, borders, and nested addressable areas) and **content** nodes (Rich Text, Media, Contact, central Legal, future local Template instance). Rows are ordered grid/stack layouts; columns and areas are layout properties/slots, not standalone page records.
- Stable persisted node/area IDs, single ordered document, no simultaneous index-based and ID-based edit protocols in current runtime. Author source order for accessible narrow stacking; container-based responsive behavior and the same Public renderer/preview projection.
- Rich Text handles headings and lists. Retire standalone List without adding another list type; check Working, Committed and historical snapshot restore for unexpected persisted List payloads and fail safely rather than silently discarding data. Dividers transition to layout borders; image wrapping and nested content need deliberate migration. Image and Video share the Media owner.
- A Template instance is page-local configuration and content, rendered by the corresponding reusable template/presentation owner; never a hidden gallery Page or implicit reference to unrelated pages. No new template renderers merely for embedded use.
- One complete cutover should convert editable Working and Committed records and reconcile immutable historical snapshot restore through the established boundary. Do not introduce new schema alongside active legacy schema merely as a scaffold.

## Implemented source on dev (2026-11-21)
- `ed78ebd3`: Removed the retired `PublicContentSetting::navigationNestingEnabled()` guard in Custom Page Settings field display and save. Parent choice is always available to ordinary pages.
- `637c3eec`: Removed the same retired guard from Custom Page parent options. Options now follow `SiteSection::canContainSitePages()`, excluding the protected Home page.
- `a4ac971b`: Separated the previously combined Image/Video Custom Page type into `image` and `video` using the same canonical Media owner. Updated type-specific editor selection, validation, state persistence, public renderer, media references/deletion, row projection and Stage focus. Forward migration retypes legacy video-bearing Image blocks in Working and Committed; historical checkpoint restores retype at the canonicalization boundary.
- Source readback confirms all three commits in `dev`; no local execution, database migration or browser acceptance is claimed. No tests/Continuous Integration/deploy requested or run.
- **Pending:** User-confirmed redundant List retirement, canonical rows/grids/columns/areas layout migration and editor, future local page-template component compositions. Shared Stage belongs to Dev Worker 1. Optional Public footer with Contact/Legal remains a separate follow-up.

## Verification and blocker
- Source-reviewed implementation commits exist; no executed migration, build, database tests, browser check, Validation deployment or production change.
- **Architectural gate:** User confirmation eliminates an assumed standalone List-data dependency; do not delay consolidation for hypothetical List records. Actual Rich Text, image wrapping and Divider appearance still need a safe conversion to the new layout structure. Handle unexpected legacy payloads explicitly.
- Stage owned by Dev Worker 1 is not yet ready for this worker's implementation.

## Next
Finalize the canonical layout design and start clean standalone List retirement after current persisted-data safety review. After agreement, implement a bounded end-to-end migration and consolidation against current `dev`: same content owner, editor actions, Public renderer, retained publication semantics and relevant direct consumers, removing superseded paths within the same cutover. Do not implement a speculative second component runtime.
