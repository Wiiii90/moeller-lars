# Test suites — canonical testing branch

**Temporary final qualification exception:** The Testing Worker 1 holding the active branch-local roster lease owns stabilization on `testing` and may correct confirmed application, migration, test, and test infrastructure defects there. The frozen `dev` tree is the baseline. Review each intentional divergence and qualify the resulting exact tree. Transfer the reviewed application and test changes selectively only after explicit approval, exclude branch-local records, and reverify the integrated tree. Existing isolation and architecture requirements still apply.

Tests protect observable domain/product behavior and durable invariants, not repair history or implementation spellings. **`testing` is the authoritative curated suite**; `dev` carries ongoing application features but Dev workers no longer create or maintain tests. The current production changes are periodically merged into `testing` while preserving canonical test deletions and refactors. Only an explicitly authorized, isolated qualification and user-directed promotion puts the curated suite back on `dev` before cutover; `main` is a separate release step.

## Source reconciliation and final gate

Review the cumulative `dev` source changes since the last accepted integration, including framework/library changes, shared components, migrations and test configuration. Reconsider and consolidate the whole affected suite rather than adding branch-specific regressions. Preparatory reconciliation may run in parallel with Dev.

Final Codex/native test execution and corrections require **a user-declared Dev freeze**, a verified frozen baseline with reviewed stabilization changes, independent isolated database/filesystem checks, and final approval before selective transfer. During the temporary exception, repair confirmed application defects on testing; otherwise return them to Dev. a new Dev commit requires a fresh source-parity check and affected test rerun. Do not infer browser/user acceptance from authored test code.

## Layers

| Layer | Location | Purpose |
| --- | --- | --- |
| Unit | `tests/Unit` | Pure deterministic behavior |
| Integration | `tests/Integration` | Domain services, PostgreSQL, files/media, adapters and concurrency |
| Feature | `tests/Feature/Admin`, `tests/Feature/Public` | Livewire/Filament and HTTP behavior, persisted outcomes |
| Migration | `tests/Migration` | Compatibility/cutover guarantees with retirement conditions |
| JavaScript | `tests/js` | Node-based frontend logic |
| Browser | `tests/browser/component`, `e2e`, `profiling` | Chromium components, real HTTP/browser flows, WebKit desktop/mobile smoke tests and Chromium profiling |

PHP discovery is defined by `phpunit.xml` and `tests/Pest.php`. Playwright uses `playwright.config.mjs`. There is **no separate Architecture suite**.

## Commands

```sh
composer test:unit
composer test:integration
composer test:feature
composer test:migration
npm run test:js
npm run test:browser:component
npm run test:browser:e2e
npm run test:browser:profile
npm run test:browser
npx playwright test --config=playwright.config.mjs --project=e2e-webkit-desktop --project=e2e-webkit-mobile
composer analyse
composer lint
```

**Database safety:** Only run Integration/Feature/Migration or seeded browser tests in a positively verified disposable PostgreSQL environment with isolated application storage, cache, sessions, media and PHP temporary directories. `Tests\\TestCase` and `LocalPreviewDatabaseGuard` must not be bypassed. The persistent local preview database is never a test target; CI environment flags alone are not proof of isolation.

The WebKit desktop/mobile projects reuse the existing Gallery ordering, media delivery, mobile menu and desktop public navigation tests. Playwright WebKit is **not** proof that native Safari passed on macOS or iOS. That requires a separate real-device acceptance check with versions and observed results recorded.

Two special PostgreSQL-17/tmpfs concurrency suites need their dedicated Windows PowerShell isolation runners, from an authorized `dev` or `testing` checkout:

```powershell
& .\tests\Support\RunPublicationConcurrency.ps1 --compact
& .\tests\Support\RunPublicSessionConcurrency.ps1 --compact
```

These runners are **not** executed by ordinary `composer test:integration` or the standard CI PHP suites; their prerequisites are explicit and fail closed. The Public Session runner exercises two real HTTP processes, a shared database session and Livewire navigation.

## Coverage and CI

The number of passing tests or PHPUnit assertions is **not a code-coverage percentage**. For a PHP `app/` line-coverage measurement use Pest with an enabled PCOV/Xdebug coverage driver and an isolated database. The canonical `release.yml` workflow measures and reports a combined PHP line-coverage baseline on pull requests to `main` and on `main`; there is currently **no percentage threshold**. JavaScript and browser tests are not included in that PHP number.

During preparatory Testing work, source and test code can be reconciled without triggering GitHub Actions or a final qualification run. The user separately authorizes an isolated final test campaign after freezing `dev`; the curated tests, permitted support, and approved stabilization changes are later transferred into `dev` with explicit approval. `main` promotion is independent.

## Regression policy

1. Identify the violated visible/domain invariant.
2. Test the narrowest meaningful behavior, including failure/no-op boundaries.
3. Verify database/HTTP/browser outcomes rather than source-string matches.
4. Compare actual source changes and both Git trees; delete superseded assertions, orphaned fixtures, repair-era scaffolding and files accidentally resurrected by rename detection.
5. Do not silently skip required fixtures or weaken assertions to obtain green results.
6. Distinguish source review, actual test runs, browser review and user acceptance.

Canonical strategy: [docs/operations/testing.md](../docs/operations/testing.md).

For local Windows qualification, use the existing PostgreSQL runner's explicit suite mode:

```powershell
& .\tests\Support\RunPublicationConcurrency.ps1 -QualificationSuite Migration --compact
& .\tests\Support\RunPublicationConcurrency.ps1 -QualificationSuite Integration --compact
& .\tests\Support\RunPublicationConcurrency.ps1 -QualificationSuite Feature --compact
```

Each invocation owns a fresh PostgreSQL cluster and runtime directory. Relative Laravel cache
paths resolve inside that runtime on Windows. The runner checks the effective Laravel connection,
cluster identifier, empty schema and runtime paths before enabling the existing disposable
database guard flags and launching Pest.


For media-dependent suites, append `-Linux` after the suite name or use it
as the first argument for the dedicated Publication runner. The runner uses
the locally built runtime image with libvips, ExifTool and FFmpeg, mounts the
checkout read-only, and verifies the new database through the application's
effective connection before starting a regular Pest suite.

Run seeded browser projects with `npm run build` followed by
`.\tests\Support\RunPublicationConcurrency.ps1 -QualificationSuite Browser`.
Browser mode starts Apache on a loopback port and seeds media as Apache's
worker user. Install Chromium and WebKit for the locked Playwright version.
