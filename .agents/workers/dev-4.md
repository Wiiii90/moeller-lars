# Dev Worker 4 — Gallery/Artwork

**Lease:** 7
**Branch:** dev
**State:** Active; browser and database migration acceptance outstanding.

## Ownership and hard limits
- Own Gallery/Artwork controls, metadata and sorting; Materials and Artwork public presentation. Do not change Dev Worker 1's Exhibition date/address, Dev Worker 2's Storage/media lifecycle or Dev Worker 3's Rich Text controls.
- No deployment, CI or migration execution without user's permission; Test Worker owns canonical tests. Check fresh head/roster/lease before every write.

## Accepted and implemented source contracts
- **Artwork date, canonical 2026-11-03 cutover:** The *only* persistent Artwork date fields are `year`, nullable `month`, nullable `day`. No combined `work_date`, `work_year`, `date_precision`, synthetic 1st-of-month or parallel date representation. The `artwork_date` picker is a virtual Admin input only. `ArtworkDate` owns parsing and localized display. Gallery, one-time date sort, Home candidate rankings and all Admin/Public readers use the same fields. Historical import/export/validation produces the canonical schema. Source commits `20ddb05`, `ae7c9fe`.
- **Migration:** `2026_11_03_150000_replace_artwork_date_with_components.php` first checks existing data and atomically migrates both Working and Committed under publication lock, deleting old active columns/constraints only inside the successful transaction. **NOT EXECUTED**. Frozen historical migration files and immutable publication version payloads remain untouched. Pre-change checkpoint restores are subject to existing schema-hash parity gate; review explicitly with Testing/Org rather than silently rewriting history.
- **Admin Artwork:** Add draft slot with or without an existing Storage media file. No direct file upload in Add/Edit (Gallery dropzone/Storage only). Edit's Media file selection may be cleared to unlink while slot/file survive; delete slot with file retained by default or opt-in file cleanup through central media owner. Removed redundant Remove/Restore actions; Delete in Edit header and Gallery cards. ALT override per Artwork. Material/Position and responsive compact Height × Width × Depth/Unit central controls. Material presets: canonical Admin table, immediate persistence/order.
- **General date locale:** One shared picker supports Year, Month/Year, Day/Month/Year and General Regional Formats for display. Do not make month-only dates appear to have a day.
- **Gallery Settings:** Manual positions or live newest-artwork-date mode; one-time Sort once by Artwork date commits manual positions. Title full width, no forbidden helper text; help icon.
- **Shared Admin border:** Erroneous global suppression of Table/AddRow divider removed. Dev skill now requires checking *both* boundary owners and other consumers before common CSS changes.
- **Additional artwork images:** active editing/relation removed as unintended; generic audio Storage upload remains. Historical metadata untouched until inventory.
- **Preview/Committed public links:** Admin shows only links grounded in Committed visibility; no Working leakage. Relevant Safari and browser tests not executed.

## User browser acceptance checklist — STILL PENDING
1. New Artwork draft, generated slug, no Media file required. Edit and clear assigned Media file; slot and original survive. Confirm ALT override, Home eligibility, single Date picker and correct regional display.
2. Date picker Year (2026), Month (11.2026), exact Day (03.11.2026), Clear, reopen/save, invalid date. Gallery chronological order exact-day > month-only > year-only. Manual sort and Sort once. **Run migration in a safe backed-up environment first.**
3. Delete in Gallery card/Edit, confirmation default slot-only and optional media impact on *disposable* assets; ensure no redundant Remove/Restore.
4. Material Presets responsive columns and actions, special HTML names, add/rename/move/delete persistence and Add Row divider. Check Exhibition Add Row divider too.
5. Public portrait/landscape image sizing across menu width and artwork surface modes remains visually broken per artist. Empty description divider only fixed; do not claim image fit accepted.
6. Filtered selection was already successfully manually tested by user: no need to repeat unless changed.

## Testing Worker backlog — prompt only on user request
- Reconcile old Gallery tests after Remove/Restore and additional-image retirement; update old date fixture names/precision cases and import/export tests to canonical year/month/day.
- Validate migration on a disposable copy of real Working/Committed data: year/month/day parity, null semantics, range/leap days, rollback, migration failure is atomic, no invented dates; Home/Gallery/public chronological behavior and year-only fallback. Immutable checkpoint restore policy must be reviewed, not silently rewritten.
- Test the shared date picker visually/interactively in desktop, narrow dialogs and Safari/WebKit; regional display; autosave and publication. Dimension field geometry and × placement.
- Safari/macOS sorting, mobile Chromium, touch Drag and filters. Working/Committed isolation; deletion media cascade; Admin table borders; selected actions, notifications and controls.
- No automatic test run, deployment or Testing Worker prompt authorized.

## Deferred / cross-worker
- **Global multi-admin concurrency refactor:** concurrent admins may overwrite new data via stale form state; assign separate worker after current Gallery tasks.
- **Dev Worker 2:** Media deletion may detach other Artwork slots; agree whether they must remain as image-less drafts. Dev Worker 2 also owns retiring old media transform versions.
- **Historical orphan Artworks:** previous Remove left unassigned rows with retained Slugs; inventory before cleanup. Historical additional-media links/audit also must not be deleted without inventory.
- **Dev Worker 1:** Owns separate Exhibition date precision work; potential shared picker reuse only by coordination.
- **Docs:** Publish accepted Artwork date and UI contracts after runtime/browser qualification; no UI acceptance claimed from source inspection.

## Historical snapshot compatibility
- One guarded read-time projection now maps ONLY immutable pre-change Artwork date payloads into current year/month/day during staged restore and pending-count calculations; shared `history_payloads`, checkpoint hashes and source rows remain unchanged. Verifies precise field sets for all historical rows, permitted date values and original snapshot hash before touching Working; unknown schema differences fail closed.
- Admin Publication History now uses the same VersionService eligibility check for staged restore and Revert, instead of separate schema-hash-only UI logic.
- No runtime or database-copy restore executed here. Use local read-only inventory and *disposable database copy* before any real staging. Validation remains out of scope.

## Next
- Migration not executed. Review exact source and have user run safe local migration, then browser acceptance of Artwork/date/forms and outstanding image sizing. No tests authored, no CI or deployment.
