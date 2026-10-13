# Testing workstream — living handoff and working rules

> **Status: active, continuously maintained.** This file is the operational memory for the long-lived **`testing`** branch of `Wiiii90/moeller-lars`. Read it **at the beginning of every new testing chat** and update it when work changes scope, architecture, validation status, or next steps. It is not a frozen status report.

**Last reconciled:** 2026-10-13. **Branch at this update:** `testing`, parent HEAD `def6d2406c7519023dacbe48d8c429f2f4390d0d` (refresh GitHub HEAD; do not treat this hash as permanently current). **Base:** initially `dev` at `82879135c0ab18dd88b07663c1e214a4ade0e45b`; subsequent `dev` changes were merged into `testing`, most recently in merge `4d2b97edb9b29297c7204be62f7582dca00a0e07`. More recent `dev` commits may exist.

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

- `tests/Unit/{Artwork,Content,Publication,...}`: pure domain logic, minimal setup.
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

- Removed all **34** historical PHP Architecture test files and removed the Architecture PHPUnit/Pest/Composer/CI entry points. Moved the actual publication restore-order invariant to `tests/Unit/Publication/PublicationRestoreOrderTest.php`.
- Deleted large suites of source/CSS/string/pixel checks, legacy patch simulation, fragile browser geometry checks, and private performance probes. Trimmed implementation-locked assertions embedded in Feature suites.
- Decomposed oversized suites for Admin Pages, Custom Page, Gallery, General, Home Hero, Dashboard, Storage, Activity Undo and Publication into responsibility-specific tests and adjacent fixtures.
- Moved Admin `SitePages` tests to `tests/Feature/Admin/Pages`; `Files` tests to `Storage`; `JournalOrderingTest.php` into `Journal`; `PulseReportTest.php` into `Observability`.
- Replaced obsolete four-argument `SiteSectionEditorialService::updatePlacement` calls with the current three-argument method; removed obsolete `toggleSectionNavigation` expectations. Public menu visibility is driven by publication; remove historical `show_in_navigation` fixtures except where **legacy migration semantics themselves** are under test.
- Browser tests are split into `component-chromium`, `e2e-chromium`, `profiling-chromium`. New E2E cases cover page creation, selection, filters, notification dismissal and dialog lifecycle. Disposable browser seeder creates actual Page configuration alongside Pages and Contacts. Browser CI workflow is prepared for all projects but **has not run**.
- Added public behavior checks for sitemap committed/draft visibility (`tests/Feature/Public/Seo/SeoRoutesTest.php`) and private unreferenced media access (`tests/Feature/Public/Media/OriginalAccessTest.php`).
- Last known snapshot after refactor: **140 files under `tests/`, approx. 542 KB**, vs **141 files / 751 KB** originally. Counts will change as quality work continues; never optimize just to reduce the number.
- Latest completed domain split before creating this handoff: commit `def6d2406c7519023dacbe48d8c429f2f4390d0d`, splitting Publication workflows by commit, media retention and Preview security.

## 5. Explicitly unverified / risks

**No CI has been triggered. No full PHP, PostgreSQL, Playwright/Chromium, or Node test run has been demonstrated for this refactor.** The suite is not certified green. File movement and static checks alone cannot prove correctness.

- **First priority when a safe local disposable runtime is available:** validate PHP syntax, Composer/Pest test discovery, imports/helpers, Unit, Integration, Feature, Migration, Node tests and Playwright separately. Fix genuine failures without disguising them. CI must remain off until explicitly authorized.
- Database test setup must retain `Tests\\TestCase` → `LocalPreviewDatabaseGuard::assertDisposableTestContext()`. Never run `migrate:fresh` on an ambiguous, persistent local preview or production database. Require explicit disposable PostgreSQL context.
- Watch for helper redeclarations after splitting Pest files, misplaced `require_once` paths, unused imports, source/history-based assertions, and test cases tied to stale production APIs.
- Browser testing depends on the disposable admin seeder, built Vite assets, current Filament markup and actual interactions; neither static fixture review nor HTML-only component tests can establish full-stack behavior.
- **Documentation drift to reconcile with the `docs` owner:** `AGENTS.md` and `followup-skill.md` may still say “`dev` only,” or reference deleted tests; `docs/TESTING.md` on `testing` still describes an obsolete Architecture layer and old browser paths. Do not unilaterally edit `docs/TESTING.md`; flag specific changes for the docs branch / later integration.
- New public tests and the split Publication/Activity tests deserve runtime verification, not exemption because the refactor authored them.

## 6. Next work, in recommended order

1. Audit remaining existing test assertions **against live production code**, not filenames alone. Remove empty signal, stale user interactions, duplicated checks, arbitrary constants and hidden dependency on test order.
2. Review the split test files as standalone PHP: import correctness, setup/teardown scope, global helper collisions, nondeterminism, and correctness of failure expectations. Prefer simplifying rather than mechanically splitting.
3. Strengthen only the important gaps: permissions, audit/persistence correctness, Livewire reopen/no-op/error behavior, publication Working vs LIVE, rollback, public privacy and media authorization. Use **few, strong** tests.
4. Validate test discovery, syntax, and runtime using a verified disposable environment **without CI**, if one becomes available; otherwise mark each unexecuted layer as `NOT RUN`.
5. Keep browser scenarios representative and stable; consolidate setup and ensure E2E fixtures reflect actual requirements. Report measured profiles separately from blocking correctness assertions.
6. Coordinate revisions to test policy and obsolete path references with the `docs` branch, and keep eventual CI wiring consistent with the actual test tree.

## 7. Maintenance ritual — mandatory

At **every substantive testing commit / merge**:
1. Re-read this file and check current `testing`/`dev` HEADs.
2. Update **Completed work**, **Unverified / risks**, and **Next work** to reflect reality. Remove already-resolved TODOs; don't accumulate a historical changelog.
3. Update the **Last reconciled** date and relevant recent merge/commit note. Exact SHA is context only; live GitHub HEAD always wins.
4. Keep this operational file **on `testing`**, preferably in the same commit as related changes. Avoid redundant updates for formatting-only edits.
5. Before handing work to a new chat, direct it to read **this file first**, then `tests/README.md` and relevant production/test code, and distinguish *inspected*, *authored*, *executed* and *browser-verified* evidence.

**No CI without the user's explicit authorization.**
