# Dev Worker 4 — branch-local progress

**Lease:** 7
**Branch:** dev
**State:** Active — Gallery/Artwork corrections; browser acceptance pending.

## Active assignment and owner boundaries
- Gallery/Artwork workflow, Material presets, filtered selection, Artwork image presentation. Avoid Dev Worker 2 media deletion/ingest, Dev Worker 3 Rich Text and Dev Worker 1 Exhibitions. No CI/deploy without user authorization.
- Read dev AGENTS.md, Dev skill and shared worker protocol. Use fresh dev roster/lease/head before each write. Current deletion source change committed as 77d5fd3; browser acceptance not executed.

## Accepted product semantics
- Add artwork creates a draft slot even with no image; an available existing Media file may be selected from Storage. No upload in Add/Edit: uploads only through Gallery dropbox or Storage. ALT text override is Artwork-specific; slug generated from title when omitted.
- Clearing Edit Artwork → Media file removes only the association; slot and Storage file remain.
- Delete artwork replaces both previous Delete and redundant Remove controls. Default removes the draft Artwork slot and association, leaves original file in Storage. Optional checkbox requests deletion of original file and all its references through the media-domain deletion owner with impact review. No normal Restore removed artwork choice or Remove button.
- Selected Delete removes draft Artwork slots, preserving source media; there is no optional mass media deletion. Publish/Unpublish selected operate without redundant confirmation. Selection pruned when filters hide previously selected items.
- Material presets use canonical Admin table with direct Add/Rename/Move/Delete persistence and stable positions. No extra redundant heading.
- Additional Artwork images were never an intended feature. Active Gallery Images relation, editors, extra media commands removed; historical database references and audit records preserved until inventory. Generic audio uploads are retained.

## Current urgent assignment — full Artwork date-model redesign
- User rejected `work_year`, `work_date`, `date_precision` and the first-day-of-month storage hack. Target a single canonical Artwork data model `year` / nullable `month` / nullable `day` with no precision flag, no old live schema columns or code aliases. The existing UI picker may remain as a *presentation* control, not a second persisted field.
- Cutover must include Working and Committed tables, backwards-safe historical data migration, domain validation, Gallery and Home queries, public/admin rendering, import/export/legacy verification and distinct rollback compatibility. Avoid Dev Worker 1's Exhibition/CV date fields and Dev Worker 2's media code. Never drop artist data without proof.
- Existing restored snapshot payloads retain immutable historical schema/hashes and may require a separate authorized compatibility review if the app refuses restoration after schema evolution. No silent rewrite of historical snapshots.
- Skill corrected now: fundamental redesign names flow end-to-end; migration-only legacy identifiers acceptable, active aliases prohibited.

## Source implementation (NOT runtime/browser acceptance)
- Removed obsolete work_date fillForm state from Add Artwork action; canonical single artwork_date field owns picker input from creation through normalization.
- Same precision-aware DATE_ORDER_SQL now reused in Gallery and Home candidate selection/ranking. No separate Home algorithm that treats a month-only value like an exact first-day Artwork. Browser/tests pending.
- Source review: force dimensions Grid 4 columns at default breakpoint (avoids Filament integer-columns breakpoint collapse); partial-date popover style now uses CSS pixel lengths and bounds selectable years. Browser unverified.
- Artwork date now has one centrally owned ViewField popover with Year, Month / year and Exact date modes, canonical ISO partial-value state and General RegionalFormats display. ArtworkDate normalizes into existing work_year/work_date/date_precision (month represented internally by first day only; precision prevents rendering an invented day). Existing Draft service preserves chosen precision, Gallery date ordering compares months first then exact days ahead of month-only. No migration; browser/runtime acceptance outstanding.
- Dimensions: implemented a central AdminDimensionsControl group with Height × Width × Depth then Unit in one compact four-column grid; multiplication signs belong to gaps, never field suffixes; root CSS in shared controls.css. Browser responsive geometry unverified.
- Dimensions editor shows a separator after Width only when Depth is entered; avoid an extra × before Unit for flat artworks. Uses same numeric fields, no new CSS.
- Exact date picker now immediately synchronizes displayed Year value when user selects a complete date, matching Artwork model's canonical save invariant. Clearing exact date leaves Year available as year-only fallback. Source unverified.
- Global border regression 389b078: removed invalid .admin-table + .admin-add-row border suppression in 2271aad; Exhibitions and other Admin tables were affected. Dev skill now requires checking both seam-owning borders and other consumers before global CSS changes.
- Media unlink InvalidArgumentException root cause: artwork.primary_media_detached absent in AdminActionCatalog; corrected d83e920. Requires browser verification.
- Artwork form restructured 6510dc1: Year/date, Material/Position, grouped dimensions, Unit optional, Home eligibility default, no Custom field; preserved existing unstructured dimensions until inventory.
- Gallery Settings b54fcbf: clarified mode labels and help, one-time sort of Manual positions from date ordering, full-width Gallery title and removed inline helper.
- Gallery Settings: Gallery title full row; illegal Menu helper text removed; Artwork order options now Manual / Newest artwork date with lightbulb help. One-time date-sort action writes Manual positions using the identical comparator and canonical audited reorder owner, only when already saved in Manual. Browser unverified.
- Root cause of Edit → clearing Media file InvalidArgumentException: ArtworkPrimaryMediaService::detachPrimary wrote artwork.primary_media_detached audit event absent from AdminActionCatalog; registered missing canonical action. The database transaction previously rolled back when audit validation threw. Source fix committed; retest needed.
- Artwork form reorganized: Title/slug; Media file; ALT override; Year/Exact date; Material/Position; four-column Height × Width × Depth / Unit; Home eligibility with no blank choice; custom freeform field retired from UI, existing raw dimensions preserved unless numeric replacements supplied. Year and Exact date remain optional. No migration/data audit claimed.
- Reinstated Delete in Edit Artwork header, opening exact same canonical delete-and-impact confirmation. Reverted global .admin-table + .admin-add-row border-top suppression accidentally introduced from Materials, restoring divider in Exhibitions and all tables; no component workaround.
- Gallery Remove/Restore entrypoints and actions retired, including Gallery card, Editor header, Selected menu, and Add artwork form. Hidden standalone Artwork editor cannot create new unassigned records. Source commit 77d5fd3. The existing single Delete dialog and media-domain optional deletion mechanism remain.
- Previous source changes: new image-less Artwork slot creation and Media file selection, Material table and position migration, selection guards, Working/Committed link presentation, public image fitting and suppression of empty-description divider, additional-image UI retirement, scoped browser test definitions. No test execution, CI, deploy or migrations performed by this worker.

## Pending user browser checklist — MUST NOT LOSE
1. Add artwork from Admin → Pages → Gallery: check title-generated slug and Create draft checkmark, image-less slot survives reload, attach and clear Media file independently of slot and Storage original. Artist previously clicked create and NOTHING happened; not yet reproduced/fixed with browser evidence.
2. Single Delete: absent Remove/Restore controls; default deletes only a disposable draft slot while Storage image survives. Optional file deletion requires separate disposable non-shared asset and impact dialog review, no live destructive tests.
3. Filtered selection: user ALREADY confirmed selecting two, filtering down to one, then only one remains selected and targeted Publish/Unpublish works. Do not make user retest without regression.
4. Material dialog: previous screenshot FAILED visually (columns, labeled actions, top whitespace, Add row and divider, drag cursor); new code pending visual acceptance. Test Add/Rename/Move Up/Down/drag/Delete persist after reopen, correct display of etching & aquatinta, responsive layout and new migration.
5. Artwork ordering: use existing Artworks, Manual drag and absolute Edit Position even with filters; precision-aware Newest and Sort once compare exact day, month/year and year-only, with date-only first within same month and year-only last within year. New slots first only under Manual.
6. Public Artwork detail: user still sees distorted/oversized portrait and landscape images, wrong upper whitespace and mixed Menu/Paper/Divider behaviors; empty-description divider root cause corrected only. Obtain example existing Artwork titles, settings and screenshots before any further formula changes.

## Testing Worker backlog — requested later, NOT NOW
- One partial-date picker: Year, Month/year, Exact day; valid/leap-day handling and Clear; General Germany 03.11.2026/11.2026/2026 format versus British/US; edit/save/reopen, legacy year/date state, Preview/Committed publishing, Home and Gallery exact-day > month-only > year-only ordering and one-time manual sort; inspect modal narrow/wide and Alpine change/autosave event semantics.
- Central dimensions control: actual single-row four controls, × characters BETWEEN Height/Width and Width/Depth rather than suffixes; responsive widths, numeric input, optional unit and canonical public dimensions rendering.
- Shared table Add-row border checks across Exhibitions, Gallery and Materials after rollback of bad global selector, and media detach audit success/notifier count; plus one-time date sort and current edit form behavior.
- Source reconciliation: previous GalleryAssignmentTest tests expect retired Remove/Restore workflow and need replacement; earlier temporary Dev-edited tests for Material/Upload/secondary images must be reconciled to current source.
- Real Safari/macOS and automated Playwright WebKit desktop/mobile sorting and drag-handle behavior; test configuration was added but never executed. Chromium mobile cross-check. Include disabled-with-filters behavior.
- Verify Working/Preview and Committed public links/visibility isolation and retained historical media; slot-only and optional-file deletion with reference cascades, refresh and no-op/validation diagnostics; Add artwork slug and image-less drafts; Material ordering migration; portrait/landscape image geometry across layout combinations.
- User explicitly wants Testing Worker prompt only upon later request. Do not issue one automatically.

## Cross-worker and later global work — MUST NOT LOSE
- Dev Worker 1 currently owns Exhibition date precision/editing concurrently. The new shared AdminPartialDateControl may inform its work, but Dev Worker 4 did not touch Exhibition editors or migration. Any reuse needs an explicit coordinated handoff, not simultaneous edits.
- **Dev Worker 2 owns media deletion:** MediaAssetEditorialService::removeCanonicalReferences() currently detaches other Artwork records from Galleries when their primary original is deleted. Check whether these other slots should instead remain as image-less draft Artworks under accepted product semantics. Do not change their currently owned media service in parallel.
- **Dev Worker 2 owns prior public-v1/public-v2 media profile removal.** User requested that topic remain with Upload worker, not this Gallery chat.
- **Global multi-admin refactor:** simultaneous editors/admins may overwrite more recent field changes via stale complete form saves. Design an application-wide concurrency/version/conflict contract after Gallery work; no local patch.
- **Legacy orphan data:** old Remove may have left Artwork records with artwork_category_id null, retained slugs and media. Inventory safely before any intentional cleanup; do not silently delete saved artist data.
- **Legacy extra-image references:** active feature removed; persisted role=additional associations/audit could remain. Do not delete assets, history or generic audio upload without inventory.
- **Docs/skill:** user repeatedly rejects invented action families and one-off UI rules. Dev method updated with explicit entity/action map. After material visual acceptance, publish tested Admin table/dialog style expectations through Docs worker; no false claim of accepted design.
- **Testing handoff:** pending as above; not authorized yet.

## Next action
- User will resume remaining browser checks after this Delete correction. Make only bounded root-cause changes on current dev with checked lease/head. Report source versus actually executed browser evidence separately.
