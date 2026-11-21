# Dev Worker 2 — Custom Pages

- **Branch:** `dev`
- **Lease:** 35
- **State:** Active — source review complete for proposed consolidation and canonical composition model; product architecture draft pending user review, no application changes.
- **Owned scope:** Custom Page composition, component editor and Public Custom Page content. Coordinate shared Stage/preview with Dev Worker 1 and page types/navigation/Home conversion with Dev Worker 5.
- **Latest source baseline inspected:** `7b7d5d2d930762051c54bd483e73f62ae92a68fc`.

## User direction (2026-11-21)
- Custom Page should compose local instances of existing page-template capabilities (Gallery, Hero Artworks, Blog, Exhibitions), not automatically reference Gallery or other Page records. No subordinate SiteSection/Navigation Node per embedded instance.
- Design a genuinely responsive, visually understandable row/grid/column/area layout, including a two-column CV + portrait / legal + contact example. Reuse the shared Stage when Dev Worker 1 completes it; do not create a parallel preview owner.
- Consolidate overlapping components first while preserving all existing editorial content, publication states and media links; migration may be required.
- Keep an optional, understated Public footer with Contact and Legal accessible as a separately tracked follow-up, not a Custom Page-only feature.

## Verified source findings
- Canonical persisted Custom Page content is the ordered `CustomPageSetting.blocks` JSON, owned by `CustomPageSetting` normalization/validation and `CustomPageEditorialService` transactional mutations/audit; Admin `CustomPageWorkspace` presents a positional hierarchy, and Public uses `pages/partials/component-sequence.blade.php`.
- `list` currently contains separately publishable titled/contextual Rich Text entries and optional media. Automatic replacement by one Rich Text body would lose editable semantics. `image.children` stores nested components (including Contact/Legal), and `divider` is a content block today.
- `HomePresentationSetting` is retired in the current source; protected Home can be Custom Page via `SiteSection`. Avoid resurrecting Home-only copies of component pipelines.
- Both `public` and `committed` schemas contain `custom_page_settings`; retained historical publication snapshots and restores require deliberate schema canonicalization. The existing `HistoricalCustomPageBlockCanonicalizer` is a boundary-only precedent.
- The current editor targets positions/types rather than persistent component identity. A new composition model should make each node and area stable and concurrency-safe before implementing drag targets.

## Proposed architectural target — NOT YET IMPLEMENTED OR USER-ACCEPTED
- Keep one authoritative persisted composition inside `CustomPageSetting.blocks`. Do not add a second `layout` column, a mirror of `blocks`, or a separate Page subclass.
- Two node families only: **layout** nodes (stack/grid with responsive tracks, gaps, borders, and nested addressable areas) and **content** nodes (Rich Text, Media, Contact, central Legal, future local Template instance). Rows are ordered grid/stack layouts; columns and areas are layout properties/slots, not standalone page records.
- Stable persisted node/area IDs, single ordered document, no simultaneous index-based and ID-based edit protocols in current runtime. Author source order for accessible narrow stacking; container-based responsive behavior and the same Public renderer/preview projection.
- Normal Rich Text handles headings and ordinary lists. The present structured List must first be losslessly migrated to composed text/area records retaining per-entry visibility/context. Old dividers become layout borders; image wrapping needs a reviewed layout/content conversion preserving authored content and public behavior. Image and Video retain one Media owner, with type-specific controls.
- A Template instance is page-local configuration and content, rendered by the corresponding reusable template/presentation owner; never a hidden gallery Page or implicit reference to unrelated pages. No new template renderers merely for embedded use.
- One complete cutover should convert editable Working and Committed records and reconcile immutable historical snapshot restore through the established boundary. Do not introduce new schema alongside active legacy schema merely as a scaffold.

## Verification and blocker
- Source review only; no migration, build, database tests, browser check, validation deployment or application diff.
- **Architectural gate:** Lossless List -> Rich Text/area migration, decorative Image-child wrapping behavior, and Divider-to-layout semantic choices require the final normalized layout schema and review before deleting stored component types. An isolated early removal would damage existing CV content and violate the no-parallel-model rule.
- Stage owned by Dev Worker 1 is not yet ready for this worker's implementation.

## Next
Present the minimal canonical layout document/schema and migration mapping to the user. After agreement, implement a bounded end-to-end migration and consolidation against current `dev`: same content owner, editor actions, Public renderer, retained publication semantics and relevant direct consumers, removing superseded paths within the same cutover. Do not implement a speculative second component runtime.
