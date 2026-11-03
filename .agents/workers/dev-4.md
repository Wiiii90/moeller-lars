# Dev Worker 4 — Gallery/Artwork

**Lease:** 7
**Branch:** dev
**State:** Active; local schema migration reported successful (Batch 76), data parity and historical restore acceptance outstanding.

## Ownership and hard limits
- Own Gallery/Artwork controls, metadata and sorting; Materials and Artwork public presentation. Do not change Dev Worker 1's Exhibition date/address, Dev Worker 2's Storage/media lifecycle or Dev Worker 3's Rich Text controls.
- No deployment, CI or migration execution without user's permission; Test Worker owns canonical tests. Check fresh head/roster/lease before every write.

## Accepted and implemented source contracts
- **Artwork date, canonical 2026-11-03 cutover:** The *only* persistent Artwork date fields are `year`, nullable `month`, nullable `day`. No combined `work_date`, `work_year`, `date_precision`, synthetic 1st-of-month or parallel date representation. The `artwork_date` picker is a virtual Admin input only. `ArtworkDate` owns parsing and localized display. Gallery, one-time date sort, Home candidate rankings and all Admin/Public readers use the same fields. Historical import/export/validation produces the canonical schema. Source commits `20ddb05`, `ae7c9fe`.
- **Migration:** `2026_11_03_150000_replace_artwork_date_with_components.php` first checks existing data and atomically migrates both Working and Committed under publication lock, deleting old active columns/constraints only inside the successful transaction. **Executed locally by the user** (migration status: Batch 76, Ran); NOT executed by this worker or on Validation. Column/data parity not yet verified. Frozen historical migration files and immutable publication version payloads remain untouched. Pre-change checkpoint restores are subject to existing schema-hash parity gate; review explicitly with Testing/Org rather than silently rewriting history.
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
- `PublicationVersionService::verifyCheckpointReadOnly()` reuses schema checks, original checksum and complete restored-row diff without mutating Working/Committed/media/history; invoke via Tinker for a local checkpoint preflight. No Stage Version action run.
- One guarded read-time projection now maps ONLY immutable pre-change Artwork date payloads into current year/month/day during staged restore and pending-count calculations; shared `history_payloads`, checkpoint hashes and source rows remain unchanged. Verifies precise field sets for all historical rows, permitted date values and original snapshot hash before touching Working; unknown schema differences fail closed.
- Admin Publication History now uses the same VersionService eligibility check for staged restore and Revert, instead of separate schema-hash-only UI logic.
- No runtime or database-copy restore executed here. Use local read-only inventory and *disposable database copy* before any real staging. Validation remains out of scope.

## Local migration read-only follow-up (2026-11-03)
- Read-only checkpoint verification additionally compares the current LIVE checkpoint's projected Artwork rows against Committed Artwork rows field-by-field (excluding framework timestamps), reporting committed_artwork_differences. Zero means the live published Artworks survived the schema cutover; Working edits remain separately unverified without a pre-migration backup.
- User's `docker exec moeller-lars-local-web php artisan migrate:status` reports `2026_11_03_150000_replace_artwork_date_with_components` as **[76] Ran**. This confirms local execution, not semantic parity or historic snapshot restore.
- User asked for local-only qualification; gave two read-only Tinker checks for public/committed columns and date counts. Await outputs. New `PublicationVersionService::verifyCheckpointReadOnly()` can check retained historical payload hash + the projected full row diff without staging/clearing Working.
- Explicitly DO NOT stage a historical restore on normal local preview: it overwrites Working and can queue media cleanup. Test actual restore only in a fully isolated database AND media store, following independent verification and user decision. No Validation deployment or migration.
- Source commits: `909a2c1` guarded historical restore projection; `69c582f` read-only checkpoint preflight. PHP linter/runtime and actual local database preflight unverified by worker.

## Local publication schema drift (2026-11-03)
- User's `verifyCheckpointReadOnly` failed first at `PublicationSchemaGuard::assertParity()` with `Publication snapshot schema drift detected for site_sections`. Source root cause: existing `2026_10_31_120000_move_gallery_meta_description_to_site_sections.php` adds `public.site_sections.meta_description` but never adds the corresponding `committed` column.
- New forward-only local correction `database/migrations/2026_11_03_160000_align_committed_site_section_meta_description.php`: independently add Committed field under publication advisory transaction, seed only from Committed Gallery description where empty; never promote Working changes or rewrite historical checkpoint hashes. **Not executed**; user must update local-preview to apply. Before declaring parity, rerun the guard for all 15 tracked tables.
- User supplied read-only evidence: public.artworks 137 (136 have full year/month/day), committed.artworks 138 (137 complete); both have exactly year, month, day and no retired fields. Local `pg_dump -Fc` saved at `C:\\Users\\Wiiii\\moeller-lars-checkpoint.dump`, 680686 bytes, 2026-11-03 16:25:58 (dump restore not tested). `local-preview.ps1` built Git 6a432c9 successfully at 16:27:21; PHP lint for VersionService and AdminPublicationHistory succeeded. Current schema hash `15643fe528b2d770d833af420c2e6feebf0a4e9042ee4843aa784caac2c10d3b`; old available checkpoint ids 10/9/8/7 have different hashes. Snapshot preflight blocked before their compatibility check. Validation untouched.
- Real historical snapshots may differ for more than Artwork dates (e.g. site_sections meta_description); do not weaken schema guard or mark all old checkpoints restorable. Diagnose exact payload column sets and validate immutable checksum before staged restore. **No destructive restore** in persistent local preview.

## Current local verification evidence — 03 Nov 2026
- User confirmed `2026_11_03_160000_align_committed_site_section_meta_description` as **[77] Ran** in persistent local preview, and `PublicationSchemaGuard::assertParity()` outputs **SCHEMA PARITY OK** across all 15 tracked publication tables. This resolves the previously reported structural `site_sections` parity defect locally.
- `verifyCheckpointReadOnly(10)` now reaches the historical checkpoint eligibility guard and throws `Historical snapshot validation failed; the original version remains preserved but cannot be restored safely.` `verifyCheckpointReadOnly(9)` output was truncated in user message (`Illumin...`), so no definitive result yet. This does **not** prove any payload loss; older Oct 20 checkpoint schema hashes differ from the current schema, including later migrations beyond Artwork dates.
- The historical compatibility code deliberately accepts only exact old/current column-set equality apart from the three Artwork date columns. Other post-checkpoint schema changes require inventory of actual missing/extra keys, not hash rewrites, blind defaulting or disabling the guard. Await a **read-only** PostgreSQL diff of archived row keys versus current live column sets for checkpoint 9/10; use a single PowerShell psql command and no rebuild before diagnosis.
- Previously established: local Working=137 Artworks (136 fully dated), Committed=138 Artworks (137 fully dated); exactly year/month/day columns each; user backup `C:\\Users\\Wiiii\\moeller-lars-checkpoint.dump` 680686 bytes created 16:25:58. No Validation mutation; old restore in persistent local preview remains prohibited. Testing/Browser tasks remain pending.
- Git incident: Assistant unnecessarily suggested standalone `git pull --ff-only` before `local-preview.ps1` which already auto-fetches/fast-forwards, causing partial Windows index update. User subsequently used Stash to protect partial files and reported preview working again. Do not suggest extra `git pull`, `git reset` or manual stash replay. Local checkout exact state after repair not independently verified.

## Historical snapshot schema inventory and guarded projection (2026-11-03)
- User read-only local archive inventory for checkpoints **9 and 10**: both missing `artwork_categories.artwork_menu_surface_wide` (added 2026-10-29, database default false) and `site_sections.meta_description` (added 2026-10-31; originally backfilled from Gallery description); `media_variants` includes retired `transform_profile` (removed 2026-11-03). No other shape differences beyond the already accounted Artwork date fields in this inventory.
- VersionService now projects these **specific immutable source-era values** at snapshot-read time: Gallery menu wide defaults false, old Gallery description supplies older Gallery SiteSection meta description, retired transform_profile is removed only from restored projection, Artwork year/month/day projection and Dev-1 Exhibition date handling retained. Projection used by stage and read-only row differences; exact projected-key validation still blocks unexpected schema changes; original stored SHA-256 checked, historical media variant uniqueness validated. Admin UI retains the shared eligibility rule.
- No historical records or checkpoint hashes rewritten, no validation deployment, no restore executed. New source code requires local preview refresh and read-only check for checkpoints 9 and 10; a disposable database+media copy is still mandatory for a full destructive stage verification.

## Next
- Migration not executed. Review exact source and have user run safe local migration, then browser acceptance of Artwork/date/forms and outstanding image sizing. No tests authored, no CI or deployment.
