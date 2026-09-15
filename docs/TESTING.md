# Testing

This document defines the durable test strategy for `moeller-lars`. The test suite protects product, domain, security, persistence and publication behavior; it is not a record of implementation rounds or browser-repair history.

## Principles

- Test durable behavior and invariants, not the shape of the current implementation.
- Prefer the narrowest layer that can prove the behavior.
- A regression test should fail for the bug it protects and stay useful after ordinary refactors.
- Source-string inspection is allowed only for a documented structural rule and belongs in `tests/Architecture`.
- CSS selectors, exact markup structure and visual geometry are not permanent contracts unless a durable accessibility or architecture rule requires them.
- Test files are named after system behavior. Names such as `Repair`, `Polish`, `FinalPass`, `Reconciliation` and broad `Acceptance` are not permanent test taxonomy.
- Migration/legacy tests must have an explicit retirement condition. They do not define current product language.
- Duplicate coverage is not automatically useful coverage. Prefer one focused contract over several historical variants of the same assertion.

## Layers

| Layer | Directory | Purpose | Runtime |
| --- | --- | --- | --- |
| Unit | `tests/Unit` | Pure value objects, algorithms and isolated domain/support behavior | No database by default |
| Integration | `tests/Integration` | Domain services crossing PostgreSQL, filesystem, mail or external-adapter boundaries | Laravel application + disposable test database |
| Feature | `tests/Feature` | User-visible admin and public application workflows, including Livewire/Filament and HTTP behavior | Laravel application + disposable test database |
| Architecture | `tests/Architecture` | Small set of durable dependency/centralization/metadata rules that cannot be expressed more directly | No database by default |
| Migration | `tests/Migration` | Temporary compatibility, reconciliation and cutover guarantees | Laravel application + disposable test database |
| JavaScript | `tests/js` | Isolated frontend interaction/math/state logic | Node built-in test runner |

`Feature` is split into `Admin` and `Public`. `Integration` is split by domain boundary rather than screen. `Migration/Legacy` is intentionally isolated so it can be retired as one deliberate cutover cleanup instead of leaking into permanent application tests.

There is currently no real-browser test suite. When browser coverage is introduced, use Pest v4 Browser Testing with Playwright and keep it deliberately small: authentication, one representative admin edit/publish flow, one public publication flow, and interaction/visual behavior that cannot be proven below the browser. Do not recreate source-string UI tests as browser tests merely to preserve old coverage.

## Choosing a layer

Use **Unit** when the subject can be constructed and exercised without Laravel infrastructure. Use **Integration** when the contract belongs to a domain/service but correctness depends on the database, filesystem, mail or an external adapter. Use **Feature/Admin** for Filament/Livewire workflows and **Feature/Public** for public HTTP/application behavior. Use **Architecture** only for a durable structural rule. Use **Migration** only when the behavior exists to reconcile or retire legacy state.

A test that needs `RefreshDatabase` is not automatically a Feature test. A test that renders HTML is not automatically a browser test. Classification follows the contract being protected.

## Current structure

```text
tests/
├── Unit/
│   ├── Admin/
│   ├── Artwork/
│   └── Content/
├── Integration/
│   ├── Artwork/
│   ├── Content/
│   ├── Database/
│   ├── External/
│   ├── Media/
│   └── Publication/
├── Feature/
│   ├── Admin/
│   │   ├── Activity/
│   │   ├── Analytics/
│   │   ├── Authentication/
│   │   ├── Dashboard/
│   │   ├── Files/
│   │   ├── Gallery/
│   │   ├── General/
│   │   ├── Home/
│   │   ├── Notifications/
│   │   ├── Search/
│   │   ├── SitePages/
│   │   └── Storage/
│   └── Public/
│       ├── Contact/
│       ├── Home/
│       └── Publication/
├── Architecture/
│   └── Admin/
├── Migration/
│   └── Legacy/
└── js/
```

Directories are allowed to grow with the application. Do not create a directory only to mirror a PHP namespace; add one when it makes test ownership clearer.

## Running and discovering tests

The suite is self-describing through Pest rather than through a hand-maintained inventory of filenames:

```bash
composer test              # all PHP tests
composer test:suites       # live suite list
composer test:groups       # live Pest groups
composer test:list         # live test catalog
composer test:unit
composer test:integration
composer test:feature
composer test:architecture
composer test:migration
npm run test:js
composer analyse
composer lint
```

`composer test:coverage` is available when a coverage driver is enabled. Coverage is a diagnostic map for untested code, not a substitute for meaningful contracts; do not add an arbitrary repository-wide percentage gate until the cleaned suite has an intentional baseline.

The directory groups are configured in `tests/Pest.php`, while PHPUnit suite discovery lives in `phpunit.xml`. Keep those two views aligned whenever a test layer is added or removed.

## Database safety

PHP tests that boot the Laravel application use `Tests\TestCase`, which calls `LocalPreviewDatabaseGuard::assertDisposableTestContext()`. Never bypass that guard or point Feature/Integration/Migration tests at the persistent local browser database. CI supplies an explicit disposable PostgreSQL context.

`RefreshDatabase` is the default for Integration, Feature and Migration tests. A narrower test may opt out only when isolation is still explicit and safe.

## What belongs in tests

Good permanent tests protect examples such as publication snapshot integrity, append-only audit history, database constraints, guarded media deletion, public visibility, hierarchy rules, authentication, contact delivery semantics, bounded query behavior and canonical media references.

Avoid permanent tests whose main assertion is that a particular CSS class, source filename, method call spelling or repair-era component still exists. If centralization itself is the durable contract, test the smallest architecture rule that proves it and keep that test under `Architecture`.

For UI presentation, source review and browser/product acceptance remain distinct evidence. A unit or source-string test cannot establish visual correctness.

## Regression policy

When fixing a defect:

1. Identify the durable behavior that was violated.
2. Put the regression at the narrowest appropriate layer.
3. Prefer extending the existing domain-focused file over creating a new incident-named file.
4. Do not encode the branch name, issue phase, worker name or repair round in the test name.
5. Remove superseded scaffolding when the durable regression test replaces it.

## Migration lifecycle

Tests under `tests/Migration/Legacy` remain only while their source-to-target or compatibility guarantees are needed for cutover. After successful cutover and explicit legacy retirement, review that directory as a dedicated deletion candidate together with the migration docs. Do not silently move legacy assertions into permanent Integration or Feature suites just to keep test counts high.

## Static analysis, warnings and CI

The target state is:

- all PHP and JavaScript tests green;
- zero unexpected test warnings;
- Pint clean;
- PHPStan blocking with zero errors;
- no broad PHPStan baseline or ignore used to hide application debt.

PHPStan is temporarily non-blocking while the current debt is removed. Test warnings and static-analysis failures are repaired after structural test work, not hidden by weakening the tools.

## Browser testing direction

Pest 4 has first-class Playwright-based browser testing through `pestphp/pest-plugin-browser`. The plugin is intentionally not installed merely to create an empty layer. Add it when the first real browser contract is implemented, then add a `Browser` suite and CI browser dependencies in the same change. Prefer this single stack over adding Dusk, Cypress or a parallel browser framework.

References: [Pest CLI API](https://pestphp.com/docs/cli-api-reference), [Pest grouping](https://pestphp.com/docs/grouping-tests), [Pest browser testing](https://pestphp.com/docs/browser-testing).
