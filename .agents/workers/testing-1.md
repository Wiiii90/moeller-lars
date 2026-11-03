# Testing Worker 1 — active assignment

- **Branch:** `testing`
- **Lease token:** `2` (user-authorized chat replacement of token 1)
- **State:** active
- **Assignment:** Consolidate new Dev worker handoffs into the canonical existing suite first, then inspect remaining cumulative Dev behavior. Testing owns tests only; no application changes on Dev, no CI/deploy or final qualification without user instruction.

## Source parity and ownership

- **Last exact source comparison:** `dev` `570f9f987757022655003bea8fc73393880204e2` versus `testing` `544566d265948ff5d65f36a669de2ad725c3fb10`: **zero differing tracked paths outside Testing-owned `tests/`, `playwright.config.mjs`, root `AGENTS.md` and branch-local worker files**. This is source parity, *not* a passing test result.
- Integrated current non-test Dev sources in bounded reviewed batches, excluding Dev worker files. Preserve Testing-owned deletions and edits and check both branch heads/lease before every write. Recheck any later Dev commits.
- Past curated work is retained in Git: Gallery settings and Working/Committed selection tests, Journal/video rights, Home/Pages selection, migration and Publication preservation, media lifecycle and original bytes.

## Current handoff — Dev Worker 2 media/uploads (prepared, not run)

- Existing media lifecycle tests now check persistent originals, thumbnails and video posters, WebM/VP9, baseline vs large progressive JPEG. Retired `MediaVariantRegenerationService` and `transform_profile` usages removed across feature/integration/support fixtures. `tests/Migration/MediaVariantSchemaMigrationTest.php` intentionally reconstructs the historic field.
- Reused existing Free Storage, Publication and media usage suites for orphan reclamation, protection of required previews and unassigned registered media, historical restoration/release, and Home plus nested Custom Page Rich Text ownership. Private incoming-media staging is redirected into owned temporary storage and original application storage root restored at teardown.
- New distinct HTTP owner `tests/Feature/Admin/Storage/ResumableMediaUploadTest.php`: authorization, true ingest and SHA-256/preview, resumable offsets, chunk rollback, finish replay, deduplication, session ownership/cancel and incomplete preview failure.
- New distinct JavaScript owner `tests/js/admin-media-upload.test.mjs`: changed middle file bytes despite equal filename, size, timestamp and first/last sample cannot reuse a staged upload receipt. Existing notification suite covers truthful saved/reused/failed/partial/no-op feedback.
- New distinct Migration owner `tests/Migration/MediaVariantSchemaMigrationTest.php`: populated public/committed cutover, safe duplicate-preview abort, independent media migration despite site_sections drift, and full Publication parity rejection.
- Existing browser projects broadened to WebKit desktop/mobile Gallery, public media, navigation and touch cases. Playwright WebKit **does not** prove native macOS/iOS Safari acceptance; separate real-browser evidence needed.

## Pending

- Further Dev worker handoffs expected from user, followed by a bounded whole-change test-architecture consolidation. The latest Gallery/Artwork `year/month/day` source change has initial caption, Home and Gallery fixture updates, but remaining date-model/migration acceptance is not yet curated.
- Review lost-final-response and true concurrency under isolated execution; confirm all new or changed assertions and fixtures through actual test runs. Complete historical Publication version payload restore coverage before cutover.
- **Final gate only upon user-declared Dev freeze:** check exact source parity at frozen SHA, positively verify independent disposable PostgreSQL 17, media/tmpfs and effective Laravel test connection, then authorize targeted/full Codex/Pest/Node/Playwright runs. Fix test defects here; report real product defects back to Dev. Transfer only curated tests/support after explicit approval. No CI, production/preview/validation database writes or protected storage access authorized.

**Execution:** Only GitHub source/tree reviews and authored commits. No PHP/Pest, PostgreSQL, Node, Chromium/WebKit or native Safari tests executed. No coverage measured.

## Current handoff — Rich Text / Dev Worker 3 (2026-11-03)

- Resumed active lease 2 on `testing` after user-requested continuation; expressly **do not import later `dev` revisions** until the user requests the next reconciliation. Source baseline remains `570f9f987757022655003bea8fc73393880204e2`.
- User-observed acceptance: native Storage insertion once, editor caption beneath image, popovers, tables, colors and spacing. Previously alleged duplicate insertion was **not reproducible**; abandoned alternate insertion implementation is not a contract.
- Added pure output tests in canonical Rich Text Unit suites: repeated-image/caption association with all placements and escaping, intentional caption removal, image count mismatch, public table/spacing/color/link rendering and unsafe-link rejection. Source edits only, never executed.

- **Feature persistence:** Existing Custom Page autosave and nested Rich Text tests now assert saved/reopened visual HTML containing line spacing, color, links and table. Existing Journal Blog edit checks formatted body and reload; Home content modes have a shared reopen scenario. All are authored only, not executed.

- **Browser E2E authored:** New `tests/browser/e2e/admin/rich-text-editor.spec.mjs` exercises real disposable UI page creation, native Rich Text editor, a single panel across Link/Color/Spacing/Storage, focus/Escape/outside click, unsafe-link rejection and link removal, repeated same Storage image insertion (exactly one per selection), caption persistence/reopen/removal. All test pages are unique draft UI creations; no artist data. Browser suite NOT executed; selectors and timing must be qualified in final isolated WebKit/Chromium runs.

- **Browser toolbar test:** The same canonical E2E Rich Text spec now also exercises real color/spacing selection, table insertion/cell editing, Undo/Redo and reload persistence. Added it to the existing desktop WebKit project; real native macOS Safari and all browser runtime results remain pending.

- **Remaining context tests:** Added Artwork Edit description round-trip/no-op, Exhibition mounted description/reopen and a real Public Rich Text render with two occurrences of one image, divergent placement and one escaped caption. Media disk is fake, synthetic, and isolated. Source-only; still no browser/PHP execution.

- **Source check corrected synthetic fixture assumptions:** BrowserProfilingSeeder names its available image `browser-viewer-1.jpg` (not `browser-gallery-1.jpg`), so the Storage E2E option was corrected. Two BrowserProfilingSeeder test-only Artwork fixtures still used retired `work_year`; updated them to canonical `year`, without importing later Dev code. This Seeder is independently guarded to run only on disposable test profiles; it is an explicit test-support source exception in the next Dev-parity gate. Added browser link URL edit and caption edit/reopen to existing scenarios. None executed.

- **Exhibition Gallery conditional control:** Added one Livewire/Filament Feature case in existing `ExhibitionOrganizerEditingTest.php` using the framework's reversible `Repeater::fake()` so it asserts hidden/visible/hidden video playback controls as the actual selected asset changes image→video→image in the mounted Add Exhibition action. No production source changed; not executed yet.

- **Multi-editor isolation:** Existing browser Rich Text test owner extended with an end-to-end scenario that creates two independently saved Text components, selects the same Storage image once in each editor, verifies the other editor is untouched before inserting, and checks both separately after a full reload. Browser run and actual native Safari acceptance remain pending.

- **Public browser layout:** Reused the existing published synthetic SPA page 2 as a Rich Text caption/float/spacing/color fixture (all owned by its disposable seeder, no new public pages or media). Extended canonical `public-media.spec.mjs` to compare image/caption rectangles and float on desktop vs mobile, plus computed line spacing and color marker. No execution; fixture may only be seeded after independent disposable DB qualification.

- **Popover sequence consolidated:** The same image/caption browser test now explicitly switches Caption→Color→Storage and asserts exactly one visible panel, Escape closure and retention of the parent editor, complementing Link→Color→Spacing→Storage. Not executed.

## Cumulative `dev` reconciliation — 2026-11-03

- Recorded source baseline `570f9f987757022655003bea8fc73393880204e2`; reviewed 39 `dev` commits and exact 18 changed application/migration/frontend/route paths through **`fcd000455f292f03c32f2c2406d8fb291b776e9e`**. Synced those exact blobs to Testing in one guarded source commit, excluding all `dev` rosters/records, Testing's curated files and its special synthetic browser seeder.
- Main contract changes: canonical Exhibition partial dates (`YYYY`, `YYYY-MM`, `YYYY-MM-DD`), strict legacy conversion, dual Working/Committed migration and immutable snapshot validation; Publication historical projection, read-only preflight/restore, Gallery metadata schema parity; Exhibition maps fail silently for missing dependencies; shared Admin controls and UI layout. Need curate direct tests in existing Unit, Migration, Publication, Feature, JS and browser owners.
- This is source preparation only, not executed qualification or a user-declared freeze. Remain on lease 2; no `dev`, main, CI, validation or production mutations.

- **Canonical Exhibition partial-date Unit contract:** Added one distinct pure Unit test owner for date normalization, impossible dates, exact/partial formatting, legacy authored conversion, ambiguity/contradiction failure, temporal uncertainty and overlapping ranges. No database touched, no runtime execution.

- **Removed obsolete date fixture fields:** Artwork/publication fixtures now use canonical `year/month/day` nullable columns rather than retired `date_precision`. The old Journal ordering migration is tested against its authentic source-era schema by temporarily restoring the retired `date_text` column inside the isolated PostgreSQL Migration test, not by changing production code. Not executed.

- **Exhibition partial-date feature:** Extended the existing Journal/Exhibition mounted editor suite with actual year-month and exact-day save/reopen/no-op, historical title/field preservation, and invalid/backwards/end-without-start rejection without false Activity/feedback. Untested until isolated runtime.

- **Exhibition migration tests:** New dedicated Migration owner recreates the immediately preceding `DATE` plus `date_text` schema ONLY inside disposable RefreshDatabase transactions. Covers lossless dual Working/Committed conversion, immutable original publication checkpoint hash/payload, read-only historical preflight and true restore, transaction abort on ambiguous/contradictory/undated published Committed rows, plus rejection of corrupt historic checkpoint rows. Not run.

- **Public Exhibition map failure:** Extended existing Journal browser component tests with actual map tile-error/unavailability lifecycle: removes broken optional modal/trigger, retains exhibition content and external link, no unhandled app JavaScript error. Uses synthetic DOM and deterministic Leaflet tile events, not external requests. Not run.

- **Publication metadata schema repair:** Added a scoped Migration owner for Committed `site_sections.meta_description` alignment. Confirms published Gallery description backfills only from Committed while newer unpublished Working remains untouched, parity is restored and idempotent, and missing Working owner fails closed. Not executed.

- **Source-level Publication defect candidate (must go to Dev):** `PublicationVersionService::isRestorable()` returns `true` whenever stored schema hash matches current schema, bypassing `originalSnapshotHashValid()`; `stageVersion()` calls `assertRestorable()` and likewise accepts that path, while `verifyCheckpointReadOnly()` checks the immutable checksum. Added a deliberately strict negative test for corrupted `snapshot_hash` in existing PublicationVersionHistoryTest, plus unrelated historical-schema-hash rejection. This test may fail until product fixes canonical restore eligibility; do NOT weaken test. Not executed.

- **Timing semantics:** Added existing Exhibition workspace Feature coverage for fixed-clock exact current vs uncertain year/month, past/upcoming month, absent date and filter projections; verifies the model and Admin filters share one temporal certainty. Not executed.

- **Fixture reconciliation sweep:** Read 58 targeted Gallery/Journal/Artwork/Publication/Migration PHP test owners and corrected remaining stale current-schema Artwork fields in shared GalleryFixtures, GalleryOrdering, GalleryUploads and public rights delivery. Historical `date_text`/`transform_profile` in explicit old-schema Migration tests are intentional. Canonical `artwork_date` is used for Admin forms; persisted ordering uses `year/month/day`. Not executed.

- **Full PHP test fixture scan:** Reviewed all 153 PHP test/support files for retired Artwork (`work_year`, `work_date`, `date_precision`), Exhibition (`date_text`) and Media (`transform_profile`) fields, including files whose names did not mention the changed domains. Fixed remaining Activity deletion and Home source eligibility fixtures. Expected old fields are retained **only** in explicitly source-era Migration tests. Source/text inspection only, not test execution.

- **Artwork date Unit owner:** Added the canonical pure `tests/Unit/Artwork/ArtworkDateTest.php` to validate exact `year/month/day` normalization, malformed or impossible values, missing-ancestor enforcement, reversible persisted string projection and locale formatting. No second abstraction; not executed.

- **Artwork date migration contract:** Added dedicated source-era Migration suite using the existing migration's genuine `down()` to reconstruct historical `work_year/work_date/date_precision` in isolated transactional schemas. Tests conversion of year/month/day/unknown in Working and Committed and fail-atomic behavior on a conflicting Committed row. Production migration unchanged; no execution.
