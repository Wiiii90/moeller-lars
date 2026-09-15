# Testing

This document defines the durable test strategy for `moeller-lars`. The test suite protects product, domain, security, persistence, publication and selected browser-interaction behavior; it is not a record of implementation rounds or browser-repair history.

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
| Browser profiling | `tests/browser` | Representative real-browser interaction chains, request fan-out, payload evidence, idle behavior and console/runtime failures | Playwright Chromium + disposable PostgreSQL application |

`Feature` is split into `Admin` and `Public`. `Integration` is split by domain boundary rather than screen. `Migration/Legacy` is intentionally isolated so it can be retired as one deliberate cutover cleanup instead of leaking into permanent application tests.

The browser layer is deliberately small and evidence-oriented. Direct Playwright owns interaction tracing because the performance contract needs request classification, transfer sizes, traces and machine-readable per-flow metrics. Do not add Dusk, Cypress or a second browser framework for the same contract. Browser profiling complements, rather than duplicates, lower-level Pest coverage and manual browser/product acceptance.

## Choosing a layer

Use **Unit** when the subject can be constructed and exercised without Laravel infrastructure. Use **Integration** when the contract belongs to a domain/service but correctness depends on the database, filesystem, mail or an external adapter. Use **Feature/Admin** for Filament/Livewire workflows and **Feature/Public** for public HTTP/application behavior. Use **Architecture** only for a durable structural rule. Use **Migration** only when the behavior exists to reconcile or retire legacy state. Use **Browser profiling** only when the contract depends on a real browser/request chain or on evidence that lower layers cannot provide.

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
├── browser/
└── js/
```

Directories are allowed to grow with the application. Do not create a directory only to mirror a PHP namespace; add one when it makes test ownership clearer.

## Running and discovering tests

The PHP suite is self-describing through Pest rather than through a hand-maintained inventory of filenames:

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
npm run test:browser:profile
composer analyse
composer lint
```

`composer test:coverage` is available when a coverage driver is enabled. Coverage is a diagnostic map for untested code, not a substitute for meaningful contracts; do not add an arbitrary repository-wide percentage gate until the cleaned suite has an intentional baseline.

The directory groups are configured in `tests/Pest.php`, while PHPUnit suite discovery lives in `phpunit.xml`. Keep those two views aligned whenever a PHP test layer is added or removed. Playwright has its own `playwright.config.mjs` and is intentionally not part of PHPUnit discovery.

CI executes Unit, Integration, Feature, Architecture and Migration as separate Pest steps. JavaScript tests run separately. Browser interaction profiling runs in its own workflow with Chromium and its own disposable PostgreSQL database so browser evidence does not touch the persistent local preview database or Validation/Production data.

## Database safety

PHP tests that boot the Laravel application use `Tests\TestCase`, which calls `LocalPreviewDatabaseGuard::assertDisposableTestContext()`. Never bypass that guard or point Feature/Integration/Migration tests at the persistent local browser database. CI supplies an explicit disposable PostgreSQL context.

`RefreshDatabase` is the default for Integration, Feature and Migration tests. A narrower test may opt out only when isolation is still explicit and safe.

Browser profiling likewise creates only its explicit disposable CI database and synthetic admin fixture. Do not repoint it at the persistent local browser database, Validation or Production.

## What belongs in tests

Good permanent tests protect examples such as publication snapshot integrity, append-only audit history, database constraints, guarded media deletion, public visibility, hierarchy rules, authentication, contact delivery semantics, bounded query behavior and canonical media references.

Avoid permanent tests whose main assertion is that a particular CSS class, source filename, method call spelling or repair-era component still exists. If centralization itself is the durable contract, test the smallest architecture rule that proves it and keep that test under `Architecture`.

For UI presentation, source review and browser/product acceptance remain distinct evidence. A unit or source-string test cannot establish visual correctness. A successful Playwright performance flow establishes the tested interaction/request contract; it does not establish visual/product acceptance of the complete admin.

## Regression policy

When fixing a defect:

1. Identify the durable behavior that was violated.
2. Put the regression at the narrowest appropriate layer.
3. Prefer extending the existing domain-focused file over creating a new incident-named file.
4. Do not encode the branch name, issue phase, worker name or repair round in the test name.
5. Remove superseded scaffolding when the durable regression test replaces it.

For performance regressions, prefer deterministic structural budgets such as request fan-out, navigation type, idle behavior, console errors, duplicate-query absence and stable payload bounds. Shared-runner wall-clock timings are diagnostic context unless a deliberately coarse, measured product ceiling has proven stable.

## Migration lifecycle

Tests under `tests/Migration/Legacy` remain only while their source-to-target or compatibility guarantees are needed for cutover. After successful cutover and explicit legacy retirement, review that directory as a dedicated deletion candidate together with the migration docs. Do not silently move legacy assertions into permanent Integration or Feature suites just to keep test counts high.

## Static analysis, warnings and CI

The required state is:

- all required PHP and JavaScript tests green;
- browser profiling structural gates green when that workflow is part of the change;
- zero unexpected test warnings;
- Pint clean;
- PHPStan blocking with zero errors;
- no broad PHPStan baseline or ignore used to hide application debt.

Warnings and static-analysis failures are repaired at their source. CI does not mark PHPStan as optional and does not weaken warning visibility to obtain a green result.

## Browser profiling contract

`tests/browser/admin-performance.spec.mjs` is the initial representative admin interaction suite. It records machine-readable evidence under `artifacts/performance` and Playwright traces on failure. It exercises representative warmed navigation, local Filament/Livewire dialogs, Activity view switching and an idle window.

The initial blocking assertions are structural: a local action must not unexpectedly perform a full document navigation, deliberate interactions must work on the first click, browser console/page errors fail the flow, and an otherwise idle page must not create unexplained application polling. Request duration and transfer/payload sizes are recorded first; only stable measured baselines are promoted to blocking budgets.

The browser workflow may finish an already running profiling job when `dev` advances. This prevents continuous integration activity from cancelling every expensive Chromium setup before it can produce evidence; newer pending runs still represent the more current candidate for final review.

See `ADMIN-PROFILING.md` and `ADMIN-PERFORMANCE.md` for the diagnostic and performance contracts.

References: [Playwright Test](https://playwright.dev/docs/intro), [Pest CLI API](https://pestphp.com/docs/cli-api-reference), [Pest grouping](https://pestphp.com/docs/grouping-tests).
