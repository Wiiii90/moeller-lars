# Test suites — current dev workflow

Tests protect observable domain/product behavior and durable invariants, not repair history or implementation spellings. All source changes are made on `dev`; the former `testing` branch/worktree is not part of the current workflow.

## Layers

| Layer | Location | Purpose |
| --- | --- | --- |
| Unit | `tests/Unit` | Pure deterministic behavior |
| Integration | `tests/Integration` | Domain services, PostgreSQL, files/media, adapters and concurrency |
| Feature | `tests/Feature/Admin`, `tests/Feature/Public` | Livewire/Filament and HTTP behavior, persisted outcomes |
| Migration | `tests/Migration` | Compatibility/cutover guarantees with retirement conditions |
| JavaScript | `tests/js` | Node-based frontend logic |
| Browser | `tests/browser/component`, `e2e`, `profiling` | Chromium component behavior, actual flows and performance evidence |

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
composer analyse
composer lint
```

**Database safety:** Only run Integration/Feature/Migration or seeded browser tests in a positively verified disposable PostgreSQL environment with isolated application storage, cache, sessions, media and PHP temporary directories. `Tests\\TestCase` and `LocalPreviewDatabaseGuard` must not be bypassed. The persistent local preview database is never a test target; CI environment flags alone are not proof of isolation.

Two special PostgreSQL-17/tmpfs concurrency suites need their dedicated Windows PowerShell isolation runners, from the `dev` checkout:

```powershell
& .\tests\Support\RunPublicationConcurrency.ps1 --compact
& .\tests\Support\RunPublicSessionConcurrency.ps1 --compact
```

These runners are **not** executed by ordinary `composer test:integration` or the standard CI PHP suites; their prerequisites are explicit and fail closed. The Public Session runner exercises two real HTTP processes, a shared database session and Livewire navigation.

## Coverage and CI

The number of passing tests or PHPUnit assertions is **not a code-coverage percentage**. For a PHP `app/` line-coverage measurement use Pest with an enabled PCOV/Xdebug coverage driver and an isolated database. The canonical `release.yml` workflow measures and reports a combined PHP line-coverage baseline on pull requests to `main` and on `main`; there is currently **no percentage threshold**. JavaScript and browser tests are not included in that PHP number.

In the current direct-`dev` mode, CI/Actions must not be run, inspected or monitored by development agents. Preparing workflow source on `dev` is separate from authorizing release qualification. Future promotion to `main` is a separately authorized operation.

## Regression policy

1. Identify the violated visible/domain invariant.
2. Test the narrowest meaningful behavior, including failure/no-op boundaries.
3. Verify database/HTTP/browser outcomes rather than source-string matches.
4. Delete superseded assertions, orphaned fixtures and repair-era scaffolding.
5. Do not silently skip required fixtures or weaken assertions to obtain green results.
6. Distinguish source review, actual test runs, browser review and user acceptance.

Canonical strategy: [Testing](../docs/operations/testing.md).
