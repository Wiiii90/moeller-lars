# Testing Worker 1 — active assignment

- **Branch:** testing
- **Lease token:** 3 (explicit takeover by successor chat on 2026-11-03)
- **State:** active; active stabilization on testing under the user-authorized final qualification exception
- **Ownership:** Confirmed application, migration, test and test infrastructure fixes on testing. Dev remains frozen. Integration requires explicit approval.

## Reconciled source

- Last fully reviewed application source: Dev fcd000455f292f03c32f2c2406d8fb291b776e9e, 39 cumulative Dev commits since 570f9f987757022655003bea8fc73393880204e2. All 18 changed non-test application/migration/frontend/route files were imported as identical blobs on Testing in a93b68b7148f9b6326b4334703a73d6ba9354caf.
- Most recent checked Dev HEAD: ca0498c9596287acbd8ac3918dfb86d9c1ad00c2; only a Dev worker-roster/doc change after the source snapshot. A full recursive tree comparison against Testing 251450f4ed32625cd4208d3742bb2b0506ec1806 found **zero differences in non-test application source**.
- Expected Testing-owned exceptions: tests/, playwright.config.mjs, root AGENTS.md, branch-local .agents/ and disposable BrowserProfilingSeeder.php. No Dev worker records or roster transferred. Source parity is **not** a passing-test result and may change before freeze.

## Curated coverage (authored, NOT run)

**Media, uploads and cleanup:** Existing suites cover truthful upload/duplicate/error feedback, integrity of original and required preview/poster bytes, large JPEG and progressive rejection, PNG/WebP, videos/audio, references and protected retained/unassigned media, Free Storage and Publication/Undo. A distinct HTTP resumable upload suite covers ownership, offsets, replay, content mismatch, abort and cleanup; a distinct JS unit suite covers changed middle bytes with unchanged name/size/timestamp. Historic MediaVariantSchemaMigrationTest covers removal of transform_profile with duplicate prevention and immutable source-era checkpoint projection/restore. All private staging tests use owned disposable storage, restoring original Laravel storage path afterward.

**Rich Text:** Canonical Unit, Feature, Integration and browser tests now cover Storage selection exactly once per user pick (not a parallel insertion method), repeated same image, independent editors, captions add/edit/remove and placement, link security, tables, color, spacing, undo/redo, popover visibility/focus/Escape/outside-click, persistence/reload and public rendering across Home, nested Custom Pages, Journal/Exhibitions and Artwork. User-observed native Filament insertion is accepted; earlier double-insert incident was not reproduced. WebKit projects use existing real browser scenarios; only real macOS/iOS Safari can establish native Safari acceptance.

**Exhibition/Artwork dates and Working/Committed:** Pure ExhibitionDate and ArtworkDate Unit owners assert year/month/exact-day precision, regional display, invalid dates/ranges, ambiguous legacy authored dates and temporal certainty. All source-era public Vita Exhibition date rows are checked through the real importer inventory. A dedicated Node unit owner tests the shared Alpine partial-date picker and no-op vs real change notifications. Mounted Journal/Gallery Feature owners cover editing, reopening, validation rollback and timing filter consistency. Migration owners reconstruct the immediately preceding schema only inside isolated PostgreSQL test transactions, test both Working and Committed conversion, invalid values failing atomically, and read-only historical Publication restore. CommittedSiteSectionMetadataMigrationTest confirms schema repair without leaking unpublished Working Gallery metadata. Obsolete current Artwork fields were eliminated from Gallery/Home/Activity/public/Publication test fixtures; legacy field names remain only in deliberately reconstructed historical migration schemas.

**Publication and public:** Existing suites cover commit/revert/reset, Working-vs-Committed content, snapshot/asset rights/retention, preview security and immutable history. PublicationVersionHistoryTest now strictly rejects unknown-schema and checksum-corrupt checkpoints before staging. Existing browser Journal component tests simulate optional Leaflet tile failure without losing editorial content; synthetic public caption fixtures verify desktop wrap/mobile reflow.

- All 153 previously existing PHP test/support files and 35 non-PHP test/support files were inspected for retired field references; no unintended remaining current-schema uses were identified. Counts reflect the baseline *before* this reconciliation added further cases.
- No extra runner or compatibility alias was introduced. Tests were grouped with existing canonical owners except when a distinct date or migration contract warranted its own file.

## Known product concern — return to Dev

- **Checksum bypass, source-level finding:** PublicationVersionService::isRestorable and assertRestorable return early when schema_hash matches current schema without checking original snapshot_hash. verifyCheckpointReadOnly checks the checksum explicitly. A corrupted current-schema checkpoint can therefore appear restorable and enter Stage Version. The strict new negative test in tests/Integration/Publication/PublicationVersionHistoryTest.php is expected to fail until the canonical Dev owner repairs eligibility; do not weaken the test or modify production source on Testing. Not yet runtime-confirmed.

## External executor's first partial run — follow-up required

User-provided execution report, **not independently rerun by this chat**, at Testing `a0debcc0733afc10032f1784c19a10651b9f3aa3` and Dev `33f598f94afd6717cc3bb234b0d85c14e758491f`:
- JavaScript suite: initially 42/43; 43/43 after repairing a Gallery test double. **The repair remains local and uncommitted** and must be reviewed/committed on Testing.
- Pest Unit: 98 cases, 10 failing assertions, 6 errors. Inspect and fix test bootstrap, then triage the remaining real assertions; do not defer all Unit tests because a migration is blocked.
- Pint and PHPStan: failed, including application-source `RegionalFormats` findings. Separate test-owned corrections from product-owner findings; do not modify application behavior on Testing.
- Migration/Integration/Feature Pest suites had 21/154/540 bootstrap errors respectively due to an unresolved absolute Windows Laravel package-cache path; no meaningful behavior assertions ran. The delegated isolation preflight incorrectly continued after effective Laravel connection verification failed. Fix the fail-closed preflight and package-cache setup **before any retry**.
- Publication concurrency: 10 failures, setup/fixtures not yet diagnosed; Public Session concurrency: stopped by clean-database migration. Dedicated PostgreSQL runners reported independently disposable PostgreSQL 17.11 environments, but do not infer the separate Pest environment's effective Laravel connection was proven.
- Chromium Component, Chromium E2E, WebKit desktop/mobile and profiling were not run. Classify independent component checks for safe immediate execution, and retain DB-backed/browser E2E until a verified clean migration.
- Production defect reported: empty-source Exhibition partial-date migration indexes missing `$planned[$table]` for `public.exhibitions`; requires canonical Dev Worker 1 fix including empty Working/Committed cases, without a Testing-only workaround. Previously documented Publication current-schema `snapshot_hash` validation bypass remains a Dev concern not yet runtime-verified.
- No GitHub Actions, deployment, Dev changes or curated test transfer authorized. Final gate not passed.

**Immediate continuation:** (1) review and commit the local JS-test correction; (2) complete unaffected JS/Unit/static/browser-component checks and triage failures; (3) repair local fail-closed isolation guard and Windows package-cache path, validate effective PostgreSQL/Laravel connection, only then rerun eligible DB suites; (4) route canonical migration and checksum defects to Dev without modifying source on Testing; (5) when Dev fixes arrive, recheck exact source parity and finish Migration, Integration, Feature, concurrency, Chromium E2E, WebKit and profiling as applicable. Keep a precise list of what still depends on the Dev migration and do not terminate the whole assignment just because one family is blocked.

## Final qualification authorized by user — execute now

- On 2026-11-03 the user explicitly confirmed that Dev had already been frozen and requested that the execution assistant run the full prepared test qualification now. **Do not ask for the freeze or test-execution authorization again.**
- Last reviewed application-source commit: `fcd000455f292f03c32f2c2406d8fb291b776e9e`. GitHub comparison against Dev HEAD `33f598f94afd6717cc3bb234b0d85c14e758491f` found only four changed `.agents/` process/worker files since that baseline, and **no application-source changes**. This establishes application parity through that checked HEAD but is not itself executed qualification; recheck exact current Git source when starting.
- A separate local execution assistant may run the authorized suites without taking or replacing Testing Worker 1's branch lease 3. This worker retains ownership of curation and integration; the executor must report exact commands, database/file isolation evidence, pass/fail/skipped results and real product defects.
- The user did not specify a new full frozen commit SHA in this chat; treat the last reviewed application-source tree as the candidate where byte-equivalent, checking latest Git references. Do not invent a separate freeze SHA or use an outdated local branch as the source of truth.


1. Record frozen Dev SHA, inspect any intervening source delta and reverify exact non-test source parity, preserving the intentional synthetic browser seeder exception.
2. Positively verify independent disposable PostgreSQL 17 database/cluster/user/port, effective PDO and Laravel test connection, empty schema, temp media, sessions/cache and guards. Persistent local Compose moeller_lars_testing and moeller-lars_postgres_data are NOT disposable targets, nor Preview, Validation or Production.
3. Authorize isolated Codex/native Node/Unit/Pest Migration/Integration/Feature qualification, separately isolated Publication and Public Session concurrency runners, Chromium component/E2E, WebKit desktop/mobile and diagnostics as needed. Actual native macOS Safari remains a distinct acceptance step.
4. Fix erroneous tests only here. Return confirmed product defects to Dev, reconcile fixes and rerun; record actual pass/fail/skipped and evidence before seeking explicit selective test transfer to Dev. CI and deployment remain separately gated.

**Evidence:** Earlier authoring/parity established through GitHub; delegated local executor subsequently reported the partial JavaScript, Pest, PHPStan, Pint and concurrency results above. These were not rerun in this chat. No coverage measured, no browser acceptance, no final gate passed.

## Local qualification continuation

- Frozen application candidate: Dev 33f598f94afd6717cc3bb234b0d85c14e758491f. Remote Testing base: 70e83fbbada5fce7869f8902c8592e6f68a59788. Local commits 3c6f0d68, e8b43a18, b75f60ca, 0a1e63a8 and 5cd0bec0 contain Testing-only changes.
- JavaScript: 43/43. Chromium components: 7/7 without an application server. Unit: 96/97, 358 assertions and no bootstrap errors. The database-backed vernissage case moved to Integration. The remaining strict renderer assertion shows authored 2.0 line spacing missing from public output; this is a runtime observation to investigate on Dev with the locked dependencies.
- Pint passes for tests. Repository-wide Pint findings remain in application source. The default PHPStan configuration analyzes app only and reported production-source diagnostics, including unhandled matches in RegionalFormats and a collection return type in public navigation.
- The local Windows qualification mode reuses the disposable PostgreSQL runner and relative Laravel cache paths. A targeted Migration run verified the effective Laravel connection and PostgreSQL 17.11 cluster identity, empty public schema, private and public file roots, temporary and upload paths, array cache and array sessions. The test then failed before its first behavior assertion during the clean Exhibition migration.
- Dev Worker 1 owns the migration defect. Reproduce on a new empty PostgreSQL cluster with the documented Migration command or either dedicated concurrency runner. Both concurrency setups reached the same missing public.exhibitions entry. Initialize the migration's per-table plan for empty Working and Committed sources in the canonical migration. The Publication checksum bypass remains source-confirmed and awaits isolated runtime execution of PublicationVersionHistoryTest.
- After Dev corrections, check current remote references and reconcile exact source parity. Run the three documented Windows Pest suites, both dedicated concurrency runners, Chromium end-to-end, desktop and mobile WebKit, and Chromium profiling using an isolated seeded server. Rerun the strict renderer assertion after the renderer is corrected. No browser projects requiring an application server have been run.
