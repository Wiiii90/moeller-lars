# Testing

**Ownership:** Dev workers implement application changes and report required scenarios; they **do not routinely author, modify, remove or reorganize tests on `dev`**. The `testing` branch owns **canonical test architecture, fixtures, regression cleanup and final suite curation**. A `refactor` worker may revise the existing tests **on its own branch** when necessary to keep a behavior-preserving structural correction accurate, and may identify/consolidate redundant tests in the touched area; Testing subsequently reviews these changes for integration. Refactor does not establish a parallel permanent test suite. Importing curated, reviewed tests into `dev` is a distinct authorized transfer. Source inspection or running existing safely isolated checks does not transfer test authorship to Dev.

This is the current durable test strategy for `moeller-lars`. The suite protects observable product/domain/security behavior, persistence, publication and selected browser interaction. It must not preserve obsolete test taxonomies, source spellings or repair-round history.

## Development batches and final Testing gate

The user may run many implementation workers concurrently. A Test worker periodically checks the **cumulative diff** from the exact last integrated `dev` baseline to the newest `dev` revision and inspects the **current resulting code**, not only commit messages or the latest change. This is sufficient without Dev-authored tests, provided the source, interface changes, migrations, shared consumers and untested scenarios are all understood.

Before editing test cases, map the affected behavior to the **existing test owners and layers**. Where a framework or shared domain architecture changed, rethink the suite's organization and fixtures *before* adding tests. Consolidate duplicates, remove obsolete regression-only or purely cosmetic markup tests, maintain meaningful scenarios, and do not introduce a second test runner or parallel test taxonomy.

Testing workers may keep their test branch aligned in advance while `dev` remains active. **Do not claim final coverage or acceptance during preparatory sync.** At a **user-declared Dev freeze**, verify complete source parity with the precise frozen Dev commit (apart from testing-owned paths), check that Dev did not advance again, and then perform authorized, isolated final Codex/native test execution. Fix/restructure failed **test code** on Testing; if application behavior is actually broken, report the required product fix to Dev and repeat source parity and relevant checks after that change. Report exact suites executed, failures, skipped checks, source commit and the integration-ready state. Only then may reviewed Test results be selectively transferred; all branch-local worker records and unapproved scaffolding stay behind.

## Layers and ownership

| Layer | Location | Purpose |
| --- | --- | --- |
| Unit | `tests/Unit` | Pure deterministic rules, no database by default |
| Integration | `tests/Integration` | Domain services across PostgreSQL, files/media, adapters and explicit concurrency |
| Feature | `tests/Feature/Admin` and `tests/Feature/Public` | Filament/Livewire actions, HTTP, authorization, state, audit and feedback |
| Migration | `tests/Migration` | Compatibility and cutover guarantees, each with a retirement condition |
| JavaScript | `tests/js` | Node-based production frontend logic |
| Browser | `tests/browser/component`, `e2e`, `profiling` | Chromium components, seeded Laravel E2E and representative performance evidence |

The former separate Architecture suite is retired. Structural contracts belong in meaningful behavior tests; source-string matching is not proof of working software. `tests/Pest.php` and `phpunit.xml` own PHP discovery; `playwright.config.mjs` owns browser projects. **The suite currently integrated into `dev` is the documentation baseline.** The separately curated `testing` branch returns improvements to `dev` through an authorized integration; update this document and the [evidence matrix](../architecture/completion-criteria.md) after that integration, not by routinely treating unmerged Testing changes as accepted. A source/branch comparison is not a passing-test result.

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

During ordinary `dev` work, development agents do not automatically trigger, inspect or troubleshoot GitHub Actions. **Continuous Integration (CI) is not forbidden**: it may be used when the user explicitly requests that activity, including a pre-merge or release gate. Avoid indefinite CI polling and large multi-minute log retrieval that exhausts the chat; check bounded run summaries and specific failed steps instead. Costly Codex/native broad test execution is reserved for authorized need, especially final isolated qualification after a declared freeze. Updating CI source does not itself authorize CI execution. Release qualification and `main` promotion remain separate, explicitly authorized tasks described by `docs/operations/release.md`.

Migration/legacy tests are retained only until their documented cutover retirement condition. Remove obsolete Architecture/SitePages suite descriptions, obsolete runbooks, redundant regressions and replaced assertions. Keep the active `testing` curation aligned to the common architecture rather than establishing two incompatible permanent systems.
