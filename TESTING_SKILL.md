# Testing workstream — living handoff and working rules

> **Status: active, continuously maintained.** This file is the operational memory for the long-lived **`testing`** branch of `Wiiii90/moeller-lars`. Read it **at the beginning of every new testing chat** and update it when work changes scope, architecture, validation status, or next steps. It is not a frozen status report.

**Last reconciled:** 2026-10-13. **Branch at this update:** `testing`, previous HEAD `75dd26a79e1dff17e010897ac4ee0609ce0104fc` (refresh GitHub HEAD; do not treat this hash as permanently current). **Base:** initially `dev` at `82879135c0ab18dd88b07663c1e214a4ade0e45b`; subsequent `dev` changes were merged into `testing`, most recently in merge `a53d59030a03e198fbba3c631d560bdffdf5e6e3` (from `dev` at `a53d59030a03e198fbba3c631d560bdffdf5e6e3`). More recent `dev` commits may exist.

## 1. Mission and quality bar

Rebuild the test suite so it is **small in maintenance cost, legible by domain/page, and highly effective at finding real bugs**. Neither the pre-existing tests nor tests added during the refactor are assumed to be good merely because they exist.

- Tests must establish **observable user behavior, data consistency, permission boundaries, publication visibility, failure handling, or enduring domain invariants**. Include meaningful negative cases and persisted/reloaded outcomes.
- **No CSS pixel/geometry snapshots, source-string assertions, historical repair tests, arbitrary performance thresholds or tests that merely certify private implementation details.** Do not restore the deleted `tests/Architecture` directory. If a structural rule truly matters, justify it and prefer an executable behavior check.
- Eliminate obsolete and duplicative assertions rather than transferring everything into new files. **Do not make broken production behavior appear correct by weakening a test.**
- Use deterministic fixtures; no sleeps/timers, random fragile ordering, user-dependent data, or silently skipped required coverage. Exercise current signatures and methods in production code before writing tests.
- Name cases in terms of what can break: what operation, what input/condition, what observable result. Split broad tests by meaningful responsibility, not a rigid line limit.
- The target is **high signal**, not a particular number of tests, coverage percentage, or artificially green CI.

## 2. Branch coordination and restrictions

- **Work only on `testing` for this workstream.** Do not push to `dev`, `main`, or `docs`. `docs` is a separate long-lived documentation workstream and owns changes to `docs/TESTING.md`. Do not overwrite it with a stale copy.
- **Do not trigger CI, GitHub Actions, deploy, release, preview deployment or automated polling.** Prepare workflows for an eventual deliberate run. Pushing to `testing` must not implicitly require any workflow run.
- Before **every** write: fetch the live `testing` HEAD, inspect the latest `dev` HEAD, compare affected paths and perform expected-SHA fast-forward updates. Never force-push/reset. Merge `dev` into `testing` when useful and safe; explicitly resolve overlaps, preserving the latest application changes and test-suite cleanup. Never merge `testing` back into `dev` without approval.
- Other project chats modify `dev` concurrently. Keep scope to tests, test fixtures, test commands and test workflows. Production code must only change for a proven defect and with a properly traced cause.
- If a local checkout is unavailable, use the connected GitHub repository tools. **GitHub source inspection and commits are not a substitute for running PHP/Node/Playwright or checking a real browser.** Never claim runtime success based on static inspection.

## 3. Source of truth and test taxonomy

Read `AGENTS.md`, `ui-skills.md` as relevant, `tests/README.md`, `tests/Pest.php`, `tests/TestCase.php`, `composer.json`, `phpunit.xml`, `package.json`, `playwright.config.mjs`, and the relevant production implementation.

- `tests/Unit/{Artwork,Content,...}`: pure domain logic, minimal setup. **Do not use `app()`, Laravel test-case state or DB facades in this suite**; inject a mock or use a deterministic constructor.
- `tests/Integration/{Analytics,Artwork,Content,Database,External,Media,Publication}`: real domain boundaries; PostgreSQL + deterministic fakes.
- `tests/Feature/Admin/{Activity,Analytics,Authentication,Dashboard,Dialogs,Gallery,General,Home,Journal,Notifications,Observability,Pages,Search,Storage}`: admin/Livewire user workflows organized by the visible page or responsibility.
- `tests/Feature/Public/{Contact,Media,Publication,Seo,...}`: HTTP and public access, privacy and visibility.
- `tests/Migration/Legacy`: short-lived compatibility/cutover expectations, with a clear retirement decision.
- `tests/js`: production JS behavior using Node's test runner.
- `tests/browser/component/public`: isolated real-browser JS/CSS behavior; **not** full Laravel E2E.
- `tests/browser/e2e/admin`: authenticated, disposable-DB full-browser workflows.
- `tests/browser/profiling`: representative browser interaction traces and diagnostics; no arbitrary pixel or transfer-budget blockers.
- `tests/browser/support`: shared sign-in and browser support. `database/seeders/BrowserProfilingSeeder.php` supplies disposable admin + required Page/Contact fixtures; missing fixture prerequisites must fail.

Only introduce subdirectories as they earn their existence. Fixtures adjacent to their domain. Prefer a shared helper when multiple cases genuinely need it.

## 4. Completed work (verify actual HEAD; do not redo blindly)

- Removed all **34** historical PHP Architecture test files and removed the Architecture PHPUnit/Pest/Composer/CI entry points. Kept the publication restore-order invariant in the actual database-backed `tests/Integration/Publication/PublicationRestoreTest.php`; the transient constant-only Unit test was later deleted.
- Deleted large suites of source/CSS/string/pixel checks, legacy patch simulation, fragile browser geometry checks, and private performance probes. Trimmed implementation-locked assertions embedded in Feature suites.
- Decomposed oversized suites for Admin Pages, Custom Page, Gallery, General, Home Hero, Dashboard, Storage, Activity Undo and Publication into responsibility-specific tests and adjacent fixtures.
- Moved Admin `SitePages` tests to `tests/Feature/Admin/Pages`; `Files` tests to `Storage`; `JournalOrderingTest.php` into `Journal`; `PulseReportTest.php` into `Observability`.
- Replaced obsolete four-argument `SiteSectionEditorialService::updatePlacement` calls with the current three-argument method; removed obsolete `toggleSectionNavigation` expectations. Public menu visibility is driven by publication; remove historical `show_in_navigation` fixtures except where **legacy migration semantics themselves** are under test.
- Browser tests are split into `component-chromium`, `e2e-chromium`, `profiling-chromium`. New E2E cases cover page creation, selection, filters, notification dismissal and dialog lifecycle. Disposable browser seeder creates actual Page configuration alongside Pages and Contacts. Browser CI workflow is prepared for all projects but **has not run**.
- Added public behavior checks for sitemap committed/draft visibility (`tests/Feature/Public/Seo/SeoRoutesTest.php`) and private unreferenced media access (`tests/Feature/Public/Media/OriginalAccessTest.php`).
- Last known snapshot after refactor: **140 files under `tests/`, approx. 542 KB**, vs **141 files / 751 KB** originally. Counts will change as quality work continues; never optimize just to reduce the number.
- Publication/Activity split reviewed again: removed duplicated imports from the extracted suites and eliminated redundant `PublicationSnapshot::TABLES`/restore-order constant tests. An actual publication media-dependency restore scenario already covers correct ordering.
- Strengthened the actual `PublicationStateControl` Livewire Commit test: a successful Commit must emit the immediate header notification and add one durable admin notification; a repeated no-op Commit must add none. This is **test-authored only, NOT RUN**.
- Replaced the Dashboard feed hook-only test with an authenticated Livewire feed search/selection workflow. Removed the Pulse recorder-configuration snapshot while retaining its admin access contract. Publication history deletion test now creates its **own real checkpoint** instead of relying on seeded history.
- Updated `tests/README.md` with the component / E2E / profiling / full browser commands. These were **authored but not executed**.
- Removed unnecessary preview-route registration from unrelated Publication commit/media tests; Media-retention tests no longer load unrelated Publication workflow helpers. Preview access tests keep their own isolated preview route.
- Added a security regression to `MediaVariantReclamationTest`: requesting an unreferenced Committed variant as a guest does not regenerate a missing generated file; access remains 404. This crosses public middleware and disk behavior, runtime NOT RUN.
- Replaced `uniqid()` checksums/storage keys and `random_int()` site-section position in Media Lifecycle fixtures with deterministic, collision-free derived values. This preserves the same file/database behavior without time or randomness dependency.
- Removed redundant General request-cache object-identity test: canonical General autosave tests already verify fresh persisted values from the same request. Replaced an exact CSS gradient-string assertion in Committed/Public/Preview test with committed-versus-working content checks (still verifies the public HTTP response and preview headers).
- Strengthened admin Storage route security tests: guest and non-admin access denied for original, generated thumbnail, single download and selected archive; bulk selection rejects missing/malformed IDs without altering originals. These cases have been **authored, not executed**.
- Added a public-media publication transition contract: draft/Working original and variant not public before Commit; protected admin Preview available; public original and variant available after Commit; Working unpublish does not leak; unavailable after next Commit. Admin-original denies guests and non-admins. **Authored, runtime NOT RUN**.
- Integrated dev `a53d590` Blog gallery presentation: new `blog_posts.gallery_presentation` migration/model/editor/public renderers, plus new Integration test. The incoming test's duplicate RefreshDatabase registration was removed during merge; runtime NOT RUN.
- Reintegrated later `dev` Journal/Exhibition changes and their **new or updated Feature/Unit tests**, preserving the intentional deletion of `tests/Architecture/Admin/AdminExhibitionMapAutosaveTest.php`.
- Reintegrated October 13 Blog gallery presentation (sequence/grid/mosaic/slideshow), the canonical Blog model/editor and aligned migration in both schemas, plus Integration behavior tests from `dev`.
- Added Integration publication lifecycle for the new Blog gallery field: first Commit, staged uncommitted change, reset staged state, second Commit. Checks both schemas and pending-state transitions, not internal table lists. **Not executed.**
- Extended Blog gallery Integration coverage to all four presentations and to verify invalid input preserves the previous choice. Added isolated Chromium component scenarios for real Slideshow JS: initial visibility, reverse/forward wrapping, keyboard activation/focus, aria-hidden/status and single-image stability. **Not yet executed.** Review publication/restore behavior of the new field and public rendering next.
- Corrected the newly merged Journal copyright Unit test to construct `JournalMediaRenderer` with a mock `PublicMedia` dependency rather than calling `app()` without bootstrapping Laravel. All current `tests/Unit` files were scanned for `app()`; no other such calls were found.
- Deleted the dialog-submit implementation introspection suite (icon/name/modal property assertions); actual Edit dialog lifecycle and Add page/create workflows retain behavioral coverage. Removed two brittle markup assertions from Custom Page type-conversion coverage; the test still performs the real conversion and verifies persisted component types. Deleted a separate one-row test consisting entirely of incidental drag-handle markup and copy assertions.
- Source-inventoried all PHP test files by case name and inspected obvious weak-assertion patterns. Further full code-to-contract review remains required.
- Removed a constant-only Undo retention case and a hard-coded 256 MiB expectation; the live retention behavior test remains. Removed Home component options equality snapshot, leaving real component conversion/validation; removed Storage CSS class order and enum icon assertions while keeping actual page actions.
- Removed wall-clock-dependent page names from Playwright creation E2E; deterministic retry/repeat-index suffix preserves a fresh slug on retries. A new browser run still requires recreating the disposable seeded database.
- Strengthened Exhibition map Livewire autosave scenarios with immediate header event on a real change and an audit/mailbox no-op boundary after repeat save; source-reviewed `JournalEntryEditorSchema` and existing mailbox assertions, runtime NOT RUN.
- Reworked Playwright Admin filter E2E: no fixed field counts per page, verifies a single semantic Filters group and adds the real Pages status-filter behavior across compact-to-inline resize. These browser tests have **not been run**.
- Added the `LocalPreviewDatabaseGuard` to `BrowserProfilingSeeder`: besides explicit `APP_ENV=testing` and Browser flag, the seeded browser run requires the existing CI/disposable DB guard. Never bypass or relax that check to get a local run. Removed historical dialog-copy assertions from Journal template autosave.

- Audited named global test helpers and relative `require_once` fixtures across the complete PHP test tree: no duplicate declarations or missing relative fixture paths detected by source inspection. 
- Removed redundant file-scoped `RefreshDatabase` imports/`uses` in Activity, Dashboard, Dialog, Journal and Notification test files (10 files in first batch, then 10 Pages/Custom Page files, then 10 further Pages/Storage/Public/Integration files, plus 3 Integration/Migration files (33 targeted tests total). File-local duplicate DB trait registrations from the inventory are now eliminated; Pest.php still centrally owns RefreshDatabase for every DB layer.); central Pest registration remains authoritative; these layers are already centrally configured in `tests/Pest.php`.

## 5. Explicitly unverified / risks

**No CI has been triggered. No full PHP, PostgreSQL, Playwright/Chromium, or Node test run has been demonstrated for this refactor.** The suite is not certified green. File movement and static checks alone cannot prove correctness.

- **First priority when a safe local disposable runtime is available:** validate PHP syntax, Composer/Pest test discovery, imports/helpers, Unit, Integration, Feature, Migration, Node tests and Playwright separately. Fix genuine failures without disguising them. CI must remain off until explicitly authorized.
- Database test setup must retain `Tests\\TestCase` → `LocalPreviewDatabaseGuard::assertDisposableTestContext()`. Never run `migrate:fresh` on an ambiguous, persistent local preview or production database. Require explicit disposable PostgreSQL context.
- Watch for helper redeclarations after splitting Pest files, misplaced `require_once` paths, remaining unused imports, source/history-based assertions, and test cases tied to stale production APIs. The newly rewritten Dashboard Livewire test specifically requires runtime confirmation. **Avoid unverified automated import pruning**: the initial mechanical pass removed required PHP imports; follow-up commits `94cd8e6` and `e63ef36` restored them. Independently inspect every resulting file before claiming syntax or runtime correctness.
- Browser testing depends on the disposable admin seeder, built Vite assets, current Filament markup and actual interactions; neither static fixture review nor HTML-only component tests can establish full-stack behavior.
- **Documentation drift to reconcile with the `docs` owner:** `AGENTS.md` and `followup-skill.md` may still say “`dev` only,” or reference deleted tests; `docs/TESTING.md` on `testing` still describes an obsolete Architecture layer and old browser paths. Do not unilaterally edit `docs/TESTING.md`; flag specific changes for the docs branch / later integration.
- New public tests (including the media transition through two Commits) and the split Publication/Activity tests deserve runtime verification, not exemption because the refactor authored them.

## 6. Next work, in recommended order

1. Continue auditing every inventoried test **against live production code**, not filenames alone. Name-and-pattern inventory is complete but is **not** a full implementation review. Remove empty signal, stale user interactions, duplicated checks, arbitrary constants and hidden dependency on test order.
2. Review remaining split test files as standalone PHP: unused global fixtures, setup/teardown scope, function redeclarations, nondeterminism, and correctness of failure expectations. Publication media/commit route setup was simplified; review other domains rather than redoing that work.
3. Strengthen only the important gaps: permissions, audit/persistence correctness, Livewire reopen/no-op/error behavior, publication Working vs LIVE, rollback, public privacy and media authorization. Use **few, strong** tests.
4. Validate test discovery, syntax, and runtime using a verified disposable environment **without CI**, if one becomes available; otherwise mark each unexecuted layer as `NOT RUN`.
5. Keep browser scenarios representative and stable; consolidate setup and ensure E2E fixtures reflect actual requirements. Report measured profiles separately from blocking correctness assertions.
6. Coordinate revisions to test policy and obsolete path references with the `docs` branch, and keep eventual CI wiring consistent with the actual test tree.

## 7. Full-suite review ledger (as of 2026-10-13)

Track each review by **verified contract**, not by number of files. 'Source-reviewed' never means 'runtime passed'.

| Domain / test layer | Status | Still to investigate |
| --- | --- | --- |
| Activity / audit / undo | Cases inventoried; partial behavior source-review | Validate split suites, permission/no-op/notification semantics end-to-end |
| Dashboard / notifications | Partially source-reviewed | Run Livewire interaction tests, verify mutations and notifications |
| General / Home / Pages | Cases inventoried; partial behavior source-review | Review every workflow against traits, actions and current dialog behavior |
| Journal / Exhibitions | Partial; Blog gallery presentation + map autosave tests synchronized | Map autosave + address validation, public visibility, Journal state changes |
| Gallery / Storage / media | Cases inventoried; public variant access/regeneration security strengthened | Cross-check deletion/variants/usage, public authorization and preview |
| Publication / Restore | Partially source-reviewed; Commit notification/no-op strengthened | Execute realistic Commit/Stage/Revert and media retention cases |
| Public / security / contact / SEO | Partial; published-vs-Working test simplified | Reverify current routes, middleware, side effects and auth |
| Unit / Integration / Migration | All cases inventoried; migration sources inspected | Validate actual migration compatibility, historical retirement and runtime |

| JS / Playwright | All browser specs inspected; filter workflow strengthened, NOT RUN | Runtime executions and fixture realism; avoid DOM/copy locks |
| Test infrastructure / CI | Source-reviewed; browser-seeder disposable guard added | Disposable Postgres, test discovery, npm imports and safe validation |
| Runtime suites | **NOT RUN** | No CI; local disposable execution not established |

Important: `dev` is moving independently; its new features and tests must be brought over thoughtfully. A deleted Architecture test on `dev` is not evidence of missing coverage until its public behavior is inspected.

### Migration retirement decisions (do not delete solely to hit a file count)

- `tests/Migration/JournalEntryOrderingMigrationTest.php`: run against the `2026_09_17_000001_resequence_journal_entries_newest_first` historical data migration. Retire only when the migration itself is no longer supported/executed in the planned cutover; normal new Journal ordering is already tested separately.
- `tests/Migration/Legacy/HomeHeroCompatibilityTest.php`: `hero_mode`/old fixed/newest/random configurations are still accepted by the compatibility resolver. Retire with the explicit migration/normalization that removes legacy input handling, not before.
- `tests/Migration/Legacy/SiteSectionMigrationValidatorTest.php`: guards historical Gallery → SiteSection parity/one-level migration. Retire when the validator and original migration pathway are intentionally removed at cutover; permanent DB invariants remain independently covered.
- `tests/Unit/Content/HistoricalCustomPageBlockCanonicalizerTest.php`: has migration-only semantic ownership although currently placed under Unit; preserve until the legacy custom-page canonicalization migration is no longer supported, then retire with that adapter.
- The historical `show_in_navigation` database column persists for restorable snapshot compatibility (see `2026_10_12_000001_make_public_navigation_follow_publication.php`). Migration fixture fields are **not** automatically stale current runtime APIs.

## 8. Maintenance ritual — mandatory

At **every substantive testing commit / merge**:
1. Re-read **`TESTING_SKILL.md`** and check current `testing`/`dev` HEADs.
2. Update **Completed work**, **Unverified / risks**, **Next work** and the **full-suite review ledger** to reflect reality. Remove already-resolved TODOs; don't accumulate a historical changelog.
3. Update the **Last reconciled** date and relevant recent merge/commit note. Exact SHA is context only; live GitHub HEAD always wins.
4. Keep this operational file **on `testing`**, preferably in the same commit as related changes. Avoid redundant updates for formatting-only edits.
5. Before handing work to a new chat, direct it to read **`TESTING_SKILL.md` first**, then `tests/README.md` and relevant production/test code, and distinguish *inspected*, *authored*, *executed* and *browser-verified* evidence.

**No CI without the user's explicit authorization.**
