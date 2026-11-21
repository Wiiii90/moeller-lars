# Dev Worker 2 — Custom Pages

- **Working branch:** `dev`
- **Lease:** 35
- **State:** Active — component consolidation and canonical recursive Grid source integrated. Local migration execution, PHP/runtime/browser acceptance outstanding.
- **Scope:** Custom Page content editor, canonical structured layout, Public Custom Page renderer. Coordinate shared Stage/preview with Dev Worker 1; Pages, navigation and protected Home identity with Dev Worker 5.
- **Recent source integration:** through `d0bacb732d33e9e5f3ddc32c02d862ac23aca44b`.

## User decisions
- Ordinary lists and CV data use the shared Rich Text editor, not a separate List component.
- Page-template capabilities should eventually be embedded as **page-local configurations/content** inside Custom Pages, not as implicit references to other Pages or hidden Gallery subpages.
- Layout uses one `CustomPageSetting.blocks` canonical document with responsive row-major Grids/areas, shared content components, central public renderer and Stage preview. No parallel layouts datastore.
- Decorative media and separate Divider should not be separate layout composition mechanisms.
- An optional, understated Public footer supporting Contact and Legal remains a **global Public follow-up**, not an implicit Custom Pages-only component.

## Completed implementation on dev
- `ed78ebd3`, `637c3eec`: Remove obsolete `PublicContentSetting::navigationNestingEnabled()` from Custom Page Settings, save and Navigation parent options; use `SiteSection::canContainSitePages()`.
- `a4ac971b`: Split Image and Video editor/component types while sharing canonical Media storage, validation, public rendering, cleanup, media references, and checkpoint conversion. Migration: `2026_11_21_140000_split_custom_page_image_video_types.php`.
- `637eebf3`: Remove standalone List from canonical runtime, editor, media references and Public renderer, delete the obsolete `ComponentListEditor`, convert unexpected legacy List records into editable Rich Text/content at migration and historical restore boundaries. Migration: `2026_11_21_150000_retire_custom_page_list_components.php`.
- `d78fd526`: Establish Grid structure and area placement in the single content document, with stable Grid/area IDs; one to four columns, one to twelve rows, responsive Public CSS, column/row editing, Stage area/member actions and per-area separators. Maintain the shared Stage and existing Public renderer, not an independent preview or page hierarchy.
- `7b81fd1f`: Convert decorative Image/Video child trees to nested local Grids with their original media and authored content. Retire the old nested Image-specific editor, sorting, serialization, removal and Public float; migrate Divider components into layout border structures. Keep working/committed publication and historical checkpoint restore conversion. Migration: `2026_11_21_160000_canonicalize_custom_page_layouts.php`.
- `3856bb65`: Preserve recursively projected nested Grid content across the shared Blade renderer's recursive includes.
- `d0bacb73`: Remove residual Divider editor/conversion options and fix the now-invalid `editingType` named argument in the canonical Edit action.
- The reusable Grid supports optional equal/65%-35% two-column layouts and row-major stacking in narrow containers; the existing Stage exposes content editing and placement into addressable areas.

## Architecture and data compatibility
- Source owner: `CustomPageSetting` for schema and publication checks, `CustomPageEditorialService` for audited mutation, shared `component-sequence.blade.php` for Public and Stage presentation.
- Grid content stays as individually authored source blocks with `layout_area_id`, never cloned into Grid `areas[]` at persistence. `layoutProjection()` supplies nested render-time children only.
- `HistoricalCustomPageBlockCanonicalizer` is used **only at migration/restore boundaries**, with deterministic historical Grid/area IDs. Retired Image children and Divider records are not accepted as active editable content.
- Old List, decorative media Child and Divider payloads receive conversions, not silent deletion. Both `public` and `committed` schemas have a migration step, and historical checkpoints are translated on restore.
- Grid placement cycles and missing areas are validation errors. The existing positional component action targets remain; stable persisted identifiers currently apply to Grid and areas, not all content components.

## Verification / acceptance
- GitHub commit/ref updates and current file readback verified in source.
- Targeted source bracket-structure scans of 14 PHP files and matching Blade conditional/loop counts completed. These are not `php -l`, a runtime test, or proof of correctness.
- **Not run:** application migrations, local containers, PHP unit/integration tests, automated Browser tests, validation deploy, Continuous Integration or server changes. No permission to perform CI/deploy.
- **Testing acceptance required:** migration against representative Working and Committed content, legacy checkpoint restore, multi-level Grid Public rendering, responsive 1/2/3/4-column variations, Stage add/edit/reorder/selection, Media deletion and publication, Settings dialog, Image/Video MIME validation.

## Future work not silently included in this feature slice
- Embed real Gallery/Hero/Journal/Exhibitions presentation template instances as local content configurations, without creating hidden SiteSection records or referencing unrelated Pages.
- A global optional understated footer with Contact/Legal variants.
- Upgrade content action targeting from positional indexes to stable component identity after dedicated concurrency design and acceptance.
