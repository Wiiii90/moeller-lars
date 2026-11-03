# Testing Worker 1 — active assignment

- **Branch:** `testing`
- **Lease token:** 2
- **State:** active
- **User assignment:** Integrate current `dev` changes into `testing` and reconcile the canonical test suite; keep `dev` untouched, remove obsolete tests, preserve durable contracts; no CI or final qualification.
- **Starting testing source:** `116f3563884b7a1cff660952ca9108691f123d67`
- **Baseline integrated dev:** `0200407133ce4696817271d2141fceaca82f8aff` (last known common source, to be reconciled against current dev).
- **Current dev at registration:** `d903569fa972fc049a1a52e2e5c43947a9c4c757`.
- **Verified reconciliation:** `dev` source SHA `d903569fa972fc049a1a52e2e5c43947a9c4c757` is an ancestor of `testing` at `a00ef9b22352197f3ededc1a865261aec5584070`; `testing` is zero commits behind `dev`. Exact tree inspection found zero differences outside `tests/`, testing-local `AGENTS.md` and testing-local `.agents/roster.md`/`.agents/workers/`.
- **Curated tests:** Preserved existing deliberate deletions; added a gated, bounded rights queue dispatch case to `MediaRightsWorkingPreparationTest.php`, tested Journal media video-controls rendering and copyright absence in the existing caption integration suite, and deleted the superseded Unit test which constructed `JournalMediaRenderer` with an obsolete signature. No new test file or runner created.
- **Execution evidence:** GitHub source/tree comparisons only. PHP tests, JavaScript/browser suites, database concurrency tests, and Continuous Integration were not run. No local project runtime or isolated disposable PostgreSQL execution is available in this session.
- **Next:** Reconcile any later `dev` change as a new bounded batch. On a user-declared freeze, recheck the source tree at that exact SHA and qualify the curated suite in a positively isolated test environment. Transfer only reviewed tests/support to `dev` after user approval; do not import testing worker files.
- **Gate:** No test run, CI, Dev promotion, release or protected database action without separate authorization.

## Reconciliation batch — current dev

- **Integrated Dev SHA:** `3282cce04e6f8721a854e8a4f4d47fc391533790` (a subsequent Publication history fix after the initial batch was merged as well).
- **Verified Testing source SHA:** `34a8bccb3dca445a0b3ccd2de5f7703cc4453782` before updating this worker record. Direct Git tree comparison: no differences from current Dev outside Testing-owned `tests/`, `AGENTS.md`, `.agents/roster.md` and `.agents/workers/`. No Dev-worker records were imported.
- **Authored/corrected tests:** Existing Gallery presentation Unit and Gallery Settings Feature tests now cover the per-Gallery menu-surface-width setting, validation, no-op, Working/Committed persistence. Current `public-v2` libvips variants replaced stale `public-v1` in relevant shared Gallery, Journal, Public Rights and artifact-revision fixtures. Safe Rich Text Unit setup no longer constructs the production media service with an invalid signature. Existing Media Lifecycle Integration verifies exact file reclamation, original integrity, database preservation and deterministic restoration. Existing JavaScript Artwork image lifecycle fixture now supplies the DOM methods used by the current implementation.
- **Tests deliberately not added:** No extra incident-named test files, CSS-geometry assertions or speculative mock of historical Publication data. The newest `PublicationVersionService` replay of legacy exhibition snapshot payloads requires an actual disposable PostgreSQL history/restore acceptance case before cutover; record as a specific follow-up rather than claiming verification.
- **Execution:** No PHP/Pest, JavaScript/Node, browser, PostgreSQL multi-process, CI/Actions or preview/validation database tests executed. No code coverage measured. These are reviewed source/test edits, not passed runtime results.
- **Next:** On a future change, compare both branch heads and reconcile only new source. On explicit Dev freeze, verify unchanged source, construct a genuinely disposable PostgreSQL/media environment, execute targeted historical Publication and media behavior first, then the authorized cutover test campaign. Transfer only reviewed Testing-owned tests after explicit user approval.

## Reconciliation — current request

- **Dev source reconciled:** `478bccc1c8aa7cf119cae0f8f02647628877247f` (compare against prior integrated `3282cce04e6f8721a854e8a4f4d47fc391533790`: 42 new Dev commits).
- **Changes inspected:** Admin tables and bulk-action states, Gallery/Exhibition presentation, Filament Rich Text popovers, shared controls, public documentation and the small PublicationVersionService adjustment. Dev supplied no new `tests/` diffs in this interval.
- **Testing ownership:** Existing curated tests and removals retained; testing worker roster and own record preserved; dev worker files excluded. Existing Pages and Home Selection feature tests extended for parent-delete constraints and derived bulk-action availability before/after a persisted change, rather than generating incident-specific test files.
- **Verification limitation:** Branch/tree and source review only. No PHP/PostgreSQL/Node/Chromium test run, no Code Coverage and no CI or deployment. These test edits are not claimed as passing.
- **Next:** When development freezes, perform exact source parity check and user-authorized disposable-database and browser qualification, especially Publication historical snapshot behavior and editor interactions; transfer curated tests to Dev only on explicit user authorization.

## Chat replacement — 2026-10-31

- **Takeover:** User deliberately replaced Testing Worker 1 because the prior chat reached its context limit. New active lease token: `2`; previous chat must not write under token `1`.
- **Checked head:** `testing` `aafafd295d3d00da8919e6dc6b55fa183aa97ed5`, latest previously reconciled `dev` source `478bccc1c8aa7cf119cae0f8f02647628877247f`.
- **Current Dev observed at takeover:** `8351bea9f97952abf295cc3c718311178b2085d5`; branch comparison indicates 15 newer Dev commits not yet reconciled into Testing. No new Dev source transfer or test execution was performed during this registration.
- **Resumption gate:** Retain the curated Testing-owned suite and previous deletions; wait for an explicit continuation/task instruction before reconciling newer Dev source. No continuous integration, destructive tests, production operations or integration are authorized by takeover alone.

## Current assignment — Media and upload handoff (2026-11-03)

- **User scope:** First consolidate media/upload regression requirements against canonical existing tests, then later handoffs and broad Dev changes. Native Safari/macOS validation is an eventual browser matrix requirement, not something Linux WebKit alone establishes.
- **Source reconciliation:** `dev` baseline `f13ef2bc98942ccf3adb4a2a35a7fb30760c7368`; 137 differing non-test, non-worker paths synchronized on Testing while retaining Testing's curated tests, roster and root AGENTS. No Dev test edits copied blindly.
- **Immediate test work:** Retire obsolete `MediaVariantRegenerationService` expectations; Free Storage must retain required variants/registered unassigned assets; test resumable transfer protocol and migration guard behavior, media references, dedup, error feedback. No database execution or CI authorized; authored tests do not imply passed tests.

- **Media test curation (source-only):** Removed obsolete preview-regeneration assertions/fixture column; transferred mandatory preview retention and genuine orphan reclamation to the existing Free Storage suite; deleted the obsolete dedicated regeneration test. This is authored test source, not runtime validation.

- **Resumable transport:** Added one dedicated HTTP feature test suite covering authenticated staging, real image ingest, final receipt replay, deduplication, incomplete chunks and offset recovery, owner-bound cancellation and required-preview fail-closed behavior. The suite redirects `storage_path()` to a uniquely owned temporary directory and deletes only that directory on teardown. Execution is pending.

- **Migration coverage authored:** A dedicated PostgreSQL migration-contract file reconstructs the previous `media_variants` shape within disposable test transactions and verifies populated Working/Committed success, duplicate-preview rollback and unrelated `site_sections` drift isolation. Not executed; only eligible after separately verified disposable PostgreSQL qualification.

- **JavaScript behavior:** Added a transport-level Node.js unit test that exercises the real upload module with changed interior file bytes but identical name, size, timestamp and first/last 64 KiB. It verifies the acknowledged staged chunk is rejected, canceled and replaced before a successful fresh transfer. Not run.

- **Format matrix:** Extended the existing media lifecycle integration owner with real FFmpeg WebM/VP9 + poster verification and a high-resolution progressive JPEG rejection versus baseline JPEG acceptance test at identical pixel dimensions. These are runtime-dependent and unexecuted.

- **Reference coverage:** Extended existing Storage usage tests with saved nested Custom Page and Home visual Rich Text references, verified canonical in-use vs unassigned categorization without introducing another fixture or runner. Not executed.

- **User feedback:** Extended canonical notification feature suite with status, title and truthful saved/reused/failed summaries for Storage/Gallery and a no-op batch; no new notification implementation or runner. Not executed.

- **Safari/macOS browser coverage:** Broadened existing WebKit desktop/iPhone projects to exercise real media delivery, Gallery reordering, touch navigation and desktop public SPA flows; updated Testing commands and caveat. Native Safari on macOS remains a separate real-device gate; none executed yet.

- **Isolation hardening:** Redirected Free Storage's private incoming-upload staging to a uniquely owned disposable directory and restored the original Laravel storage root in both resumable and Free Storage suites after teardown. No user/Preview files may be pruned by these tests. Not executed.

- **Dev delta:** Synchronized the four application sources from subsequent `dev` `570f9f987757022655003bea8fc73393880204e2` (ArtworkDate formatting, ordering declaration, Home and Artwork cleanups), excluding Dev Worker files. Additional Gallery/Artwork date test review remains pending later handoffs.

- **Artwork date source follow-up:** Updated Gallery create-form, public caption and shared Home hero fixtures to current `artwork_date` UI and persisted `year`/optional `month`/`day` instead of defunct fields. The broader Gallery/Artwork date and migration tests still need separate full curation; none executed.
