# Testing

**Ownership:** Dev workers implement application changes and report required scenarios; they **do not author, modify, remove or reorganize tests on `dev`**. All new tests, fixtures, browser suites and test-suite maintenance belong to the separate testing workstream on its test branch. Importing curated, reviewed tests back into `dev` is a distinct authorized integration step. Source inspection or running existing safely isolated checks does not transfer test authorship to Dev. This replaces older descriptions of test editing as ordinary direct-development work.

This is the current durable test strategy for `moeller-lars`. The suite protects observable product/domain/security behavior, persistence, publication and selected browser interaction. It must not preserve obsolete test taxonomies, source spellings or repair-round history.

## Layers and ownership

| Layer | Location | Purpose |
| --- | --- | --- |
| Unit | `tests/Unit` | Pure deterministic rules, no database by default |
| Integration | `tests/Integration` | Domain services across PostgreSQL, files/media, adapters and explicit concurrency |
| Feature | `tests/Feature/Admin` and `tests/Feature/Public` | Filament/Livewire actions, HTTP, authorization, state, audit and feedback |
| Migration | `tests/Migration` | Compatibility and cutover guarantees, each with a retirement condition |
| JavaScript | `tests/js` | Node-based production frontend logic |
| Browser | `tests/browser/component`, `e2e`, `profiling` | Chromium components, seeded Laravel E2E and representative performance evidence |

The former separate Architecture suite is retired. Structural contracts belong in meaningful behavior tests; source-string matching is not proof of working software. `tests/Pest.php` and `phpunit.xml` own PHP discovery; `playwright.config.mjs` owns browser projects. **The suite currently integrated into `dev` is the documentation baseline.** The separately curated `testing` branch returns improvements to `dev` through an authorized integration; update this document and the [evidence matrix](architecture/completion-criteria.md) after that integration, not by routinely treating unmerged Testing changes as accepted. A source/branch comparison is not a passing-test result.

## Running and discovering tests

```sh
composer test:unit
composer test:integration
composer test:feature
composer test:migration
composer test                  # standard PHP suites; disposable DB only
composer test:suites
composer test:groups
composer test:list
npm run test:js
npm run test:browser:component
npm run test:browser:e2e
npm run test:browser:profile
npm run test:browser
composer analyse
composer lint
```

Special real-process PostgreSQL concurrency cases do not execute in an ordinary Pest or Continuous Integration (CI) run; they require the verified Windows PowerShell runners in the authorized checkout:

```powershell
& .\tests\Support\RunPublicationConcurrency.ps1 --compact
& .\tests\Support\RunPublicSessionConcurrency.ps1 --compact
```

These provision isolated PostgreSQL-17/tmpfs instances and verified paths. The public-session tests use independent HTTP workers with the same database session and Livewire navigation. Their actual separate result must be reported rather than claimed as ordinary CI coverage.

## Database and file safety

Never use the persistent local browser database or protected Validation/Production state for test execution. `Tests\TestCase` invokes `LocalPreviewDatabaseGuard`. Database-backed tests require positive verification of an independent disposable database, empty initial schema, PostgreSQL instance/version/identity, and isolated session/cache/storage/media/view and PHP temp paths. A CI env flag or process exit code is not isolation proof. Never weaken the guard to get a green result.

Standard Pest suites typically use in-memory sessions; that does not exercise races in a real database-backed session. The dedicated Public Session runner does. Browser E2E uses a seeded disposable application and PostgreSQL database, separate from persistent Preview.

## Durable regression rules

Choose the narrowest layer that demonstrates the actual failure and success/no-op boundaries. Prioritize persisted outcomes, HTTP requests, Filament/Livewire actions, authentication and permission revocation, publication consistency, append-only audit, media integrity, concurrency and error behavior. Remove duplicate incident scaffolding and tests that only pin filenames, CSS selectors or implementation details.

Authenticated admin mutations need meaningful verification from the domain result through persistence, append-only Activity/audit, one durable notification and one immediate header event per successful change, and no false success on no-op or failure. Close/reopen and browser-visible field state are separate acceptance evidence.

For performance, use request fan-out, idle behavior, payload budgets and console errors as stable structural contracts. Stopwatch timings on shared runners are diagnostic until repeated measured limits have been accepted. Browser profiling uses `artifacts/performance` and Playwright traces. Neither it nor a passing PHP test suite is a substitute for real browser/product acceptance.

## PHP line coverage

**Passing test count and assertion count are not a percentage of source covered.** With PCOV or Xdebug enabled, Pest measures the PHP `app/` source declared in `phpunit.xml`:

```sh
vendor/bin/pest --coverage --only-summary-for-coverage-text --coverage-clover=coverage.xml
```

The existing `release.yml` workflow will report the combined PHP app/ line-coverage baseline for user-authorized pull requests to `main` and pushes to `main`. It prints covered/coverable statements and a percentage. No arbitrary coverage threshold is imposed before that first actual run. The calculation excludes JavaScript, Chromium and dedicated concurrent HTTP/database runners; it cannot be interpreted as end-to-end product coverage.

## CI, gates and retirement

`release.yml` verifies dependencies, Vite build/manifest, the four current Pest suites, PHPStan, Pint and JS. `browser-profile.yml` verifies Chromium component, E2E and profiling paths. `runtime-image-smoke.yml` verifies its scoped container boot contract. Special multi-process concurrency is intentionally a separate isolated test boundary until a safe CI runner exists.

During direct-`dev` work, development agents do not run, monitor, inspect or troubleshoot GitHub Actions. Updating CI source on `dev` does not authorize CI execution. Release qualification and `main` promotion are separate, explicitly authorized tasks described by `docs/RELEASE.md`.

Migration/legacy tests are retained only until their documented cutover retirement condition. Remove obsolete Architecture/SitePages suite descriptions, obsolete runbooks, redundant regressions and replaced assertions. Keep the active `testing` curation aligned to the common architecture rather than establishing two incompatible permanent systems.
