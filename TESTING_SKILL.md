# Testing workstream — living handoff / TESTING_SKILL

**Active as of 2026-10-13.** Repository: `Wiiii90/moeller-lars`. Long-lived branch: **`testing`**, current base HEAD before this update `f5efd87d13a4b03be25592fcb994443e6cadc966`; latest observed `dev`: `7294d19ae48a58624fc5f0883e63aee207198edb`. The latest *actual merge commit* already on testing is `9194235980b3772063c8db4aa236e1e1b34120d7` (dev parent `7294d19ae48a58624fc5f0883e63aee207198edb`). **Always inspect live heads and ancestry; these recorded SHAs become stale.** Initially forked dev `82879135c0ab18dd88b07663c1e214a4ade0e45b`.

This is the **single living operational handoff** for a full test-suite refactor. Read it first in each new conversation, then read `tests/README.md`, `tests/Pest.php`, relevant test and production code. Update this file **in the same commit** as each substantive testing change. Replace stale status/next steps; do not just append historical notes.

## Non-negotiable repository rules

- **Only write/commit on `testing`.** `dev` is edited independently by Admin/Public chats; `docs` is the separate long-lived documentation branch. Never edit or push `dev`, `main` or `docs`, and do not alter `docs/TESTING.md` (docs owner).
- **Never trigger CI/Actions, deployment, polling, releases, server operations or preview workflows.** The user intentionally wants eventual CI readiness without spending current CI runs. Branch push does not activate existing workflows configured for `dev`/`main`.
- Before every commit fetch current `testing` and `dev` heads, and for overlapping files inspect their live content. GitHub writes must be compare-and-swap with `update_ref(expected_sha=oldTestingHead)`, never force pushes. Integrate `dev` via genuine merge commits with `additional_parent_shas` after identifying conflicts; intentionally deleted `tests/Architecture` files must stay deleted.
- No unrelated production refactors. Validate current domain API signatures, Filament traits and actual views rather than infer methods from filenames. Preserve `AGENTS.md` and `ui-skills.md` rules; notify docs branch of obsolescence instead of changing its files.
- No destructive tests on the persistent local preview, staging or production DB. `Tests\\TestCase` uses `LocalPreviewDatabaseGuard`, which requires **`GITHUB_ACTIONS=true` and `MOELLER_LARS_DISPOSABLE_TEST_DATABASE=1` with no persistent-preview flag**. Do not bypass safety checks merely to run a suite. Browser seeder has the same guard.

## Quality bar

Every retained test should detect meaningful wrong behavior: **observable admin/public workflow, security/permissions, data consistency, publication Working vs Committed, notifications/audit/no-op, undo/rollback, failure recovery or a durable pure domain invariant**. Retire duplicates and assertions of PHP source strings, arbitrary icons/CSS class/pixel order, private method names, static constant arrays, artificial budgets or historical one-off repairs. Avoid fragile random/time-based fixtures, sleeps/timers, fake browser passes and conditional skips. Split large suites when responsibilities differ, not to game line counts. Actual *run* evidence must be distinguished from source review.

## Current structure, progress and tooling

Last confirmed test tree: **141 files / 545564 bytes** (original fork circa 141 files / 751 KB), including **115 PHP `*Test.php` files**, 78 Feature, 32 Integration, 3 Migration, 10 Unit, 11 browser (including support), 4 Node test files, and **zero `tests/Architecture/`**. Do not optimize these counts.

- `tests/Unit`: pure classes without Laravel app/DB. `tests/Integration`: DB-backed domain boundaries. `tests/Feature/Admin`: Filament/Livewire, HTTP, mutations. `tests/Feature/Public`: visibility, contact, publication, media, security/SEO. `tests/Migration`: historical cutover coverage only.
- `tests/js`: Node test runner, real JS exports. `tests/browser/component/public`: production JS in isolated Chromium DOM. `tests/browser/e2e/admin`: real authenticated web flows with disposable seeded DB. `tests/browser/profiling`: non-blocking diagnostic metrics + runtime errors.
- Commands configured: `composer test:unit`, `test:integration`, `test:feature`, `test:migration`; `npm run test:js`, `test:browser:component`, `test:browser:e2e`, `test:browser:profile`, `test:browser`. **DO NOT run CI.** Read `composer.json`, `phpunit.xml`, `playwright.config.mjs`, `.github/workflows/release.yml`, `browser-profile.yml` for wiring.
- Connected GitHub repository tools under `functions.exec`: `mcp__GitHub__fetch` branch/tree; `fetch_file` or `fetch_blob`; `create_tree` (typically batch <=24 objects); `create_commit` parent SHA; `update_ref` with expected SHA. Full checkout unavailable here; no PHP/Postgres/Playwright runtime proven. Never call GitHub Action dispatch.

## Completed refactor that must not be undone

1. Removed the **34 Architecture PHP tests** and stale Pest/Composer/CI references. Actual FK restore-order behavior is exercised by `tests/Integration/Publication/PublicationRestoreTest.php`, rather than a constant-only Unit snapshot.
2. Reorganized Feature/Admin by actual page (`Pages`, `Storage`, `Journal`, `Observability`), split Activity Undo and Publication commit/media/preview/history/restore tests with adjacent helpers; simplified outdated menu flags, old method argument counts, and duplicate imports. `tests/Pest.php` centrally owns `RefreshDatabase` for Feature/Integration/Migration; 33 duplicate file-local registrations already removed. Do not reintroduce them.
3. Rebuilt Playwright project split; admin E2E covers page creation, selection, compact/inline filters, notifications and modal lifecycle. Browser seeder creates synthetic admin, two draft Pages and contact messages and requires disposable CI context. Removed timestamp-based page-name entropy. Tests are authored only.
4. Preserved rich behavior coverage while removing implementation-only checks: CSS markup/geometry and icon snapshots, raw source scans, Pulse recorder exact config, internal Publication table order, Undo constant quota/retention values, Dashboard SQL hydration/probe thresholds and filter-label snapshot, and redundant General cache-object identity test.
5. Added substantive public privacy checks: sitemap committed vs Working; unpublished/unreferenced originals and generated variants inaccessible, admin/preview guarded; file availability before and after explicit Commit; missing generated guest variant cannot be regenerated. Added admin original/variant/single/bulk download denial to guests/non-admins and malformed/missing bulk selection outcomes.
6. Publication Commit Livewire tests now assert immediate/durable notification for real commit and silence for repeat no-op. Journal Exhibition Map autosave tests verify hydrated coordinates, notifications, incomplete address, geocoder errors and redundant saves. Fixed stale `toggleSectionNavigation` calls to real publication behavior and `updatePlacement` 3-arg calls.
7. Reintegrated several real `dev` merges: latest `9194235` brings Blog gallery modes and copyright presentation. Current tests cover `BlogGalleryPresentationTest.php` sequence/grid/mosaic/slideshow persistence, invalid choice with no mutation, two schema Commit/Stage Reset/Commit; `tests/browser/component/public/journal-slideshow.spec.mjs` cycles real JS, focus/keyboard/aria and single image; `JournalMediaCaptionTest.php` verifies rendered authored credit independent of suppressed inherited or overridden copyright. `MediaLifecycleTest.php` fixtures no longer use `uniqid()` or random section positions.
8. Source-inventoried all PHP cases and reviewed all existing browser specs and global helper names/relative `require_once` paths. **This was an inventory, not proof that every case is correct.**

## Current blocking risks and evidence

**All tests and test layers remain NOT RUN under this refactor. CI not triggered.** No full PHP syntax/Pest discovery, Postgres Unit/Integration/Feature/Migration, Node, Vite build or Chromium test suite has run. Static reading and GitHub commits do not establish green status. A safe verified disposable environment is a prerequisite before any DB tests; preserve the current CI-only safety guard.

- Specifically runtime-verify newly written `StorageRouteTest.php` guest/nonadmin tests, `OriginalAccessTest.php` Committed/Working original+variant, `BlogGalleryPresentationTest.php` staged reset, `JournalMediaCaptionTest.php` unsaved usage with loaded asset relation, `journal-slideshow.spec.mjs` script loading, `DashboardFeedActionsTest.php` Livewire mount, and split Activity/Publication suite imports/actions. Fix actual failures, never weaken permissions or bypass DB guard.
- Production `dev` moves quickly. Check latest migration/schema additions and potential changed signatures each time. Latest tests may require valid PostgreSQL schema parity in `public` and `committed`.
- `AGENTS.md`, `followup-skill.md`, and `docs/TESTING.md` on `testing` may still refer to obsolete architecture paths/branches. **Documentation synchronization belongs to `docs`**, not this branch.
- The currently configured release/browser workflows run on `dev`/`main`, not on testing; an eventual authorized test run and integration into dev are outstanding.

## Remaining work — priority order for the next chat

1. Continue **file-by-file production-to-test behavioral audit** of the remaining Feature/Integration/Unit/Migration cases. The inventory is complete but only part has been reviewed deeply. Particularly check stale Filament modal APIs, absent assertions after mutations, no-op audit/notification behavior, cross-user access, and real public visibility. Keep useful tests; remove duplicates.
2. Audit Admin `Gallery`, `Storage`, `Activity`, `General`, `Home`, `Pages`, `Journal`, public contact/security, and Publication domain in that order or in parallel without conflicting with `dev`; make small focused commits with test + this file.
3. Verify the new Blog gallery **end-to-end public rendering** and gallery/copyright components (the present tests cover isolated runtime helpers and domain persistence, not a complete public page). Review map address fields and real user notification feedback whenever `dev` changes.
4. Validate Jest/Node/Playwright imports and PHP syntax/discovery under a strictly disposable setup. If none is accessible, keep status `NOT RUN`; do not simulate success.
5. Reconcile docs on the separate docs workstream; once tests actually pass in a permitted environment, prepare integration to `dev` only with the user's approval.

### Migration-specific retirement decisions

- `tests/Migration/JournalEntryOrderingMigrationTest.php`: keep until historical journal resequencing migration is no longer supported during cutover.
- `tests/Migration/Legacy/HomeHeroCompatibilityTest.php`: keep while old Hero fixed/newest/random inputs are accepted.
- `tests/Migration/Legacy/SiteSectionMigrationValidatorTest.php`: keep until historical Gallery→SiteSection validation/cutover path is removed.
- `tests/Unit/Content/HistoricalCustomPageBlockCanonicalizerTest.php`: migration-only compatibility, keep until old payload normalization is deliberately retired.
- Historical `show_in_navigation` DB column is retained for old publication snapshots; migration fixtures may rightly contain it even though current public navigation does not use it.

**MANDATORY handoff procedure:** Read `TESTING_SKILL.md` first; inspect current `testing` and `dev` HEADs; inspect `AGENTS.md`, `ui-skills.md`, `tests/README.md` and production code for next target; implement actual changes on `testing`; update this file in the same commit; explicitly distinguish source-inspected, test-authored, test-executed and browser-confirmed. **Never trigger CI without explicit permission.**
