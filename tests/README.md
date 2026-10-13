# Test suite — practical orientation

Tests protect observable behavior and durable invariants. They are not a source-history archive and must not pin CSS numbers, private method spellings, or repair-era markup.

## Placement

- `Unit`: pure, deterministic rules with no application or database boot.
- `Integration`: domain behavior requiring PostgreSQL, filesystem, mail or mocked external adapters.
- `Feature/Admin`: Filament/Livewire user workflows, including persisted state, audit, notification and failure handling.
- `Feature/Public`: public HTTP contracts such as visibility, redirects, contact and media access.
- `Migration`: compatibility only while the corresponding migration is still necessary; every case needs a retirement condition.
- `js`: production JavaScript behavior exercised with Node's test runner.
- `browser`: actual browser behavior; distinguish component fixtures, end-to-end and profiling scenarios.

Follow module and responsibility, not a past incident. Prefer meaningful assertions about persisted outcomes, HTTP responses, events, permissions and browser interaction. A test that only checks a string in source code is not evidence that the feature works.

## Local commands

```sh
composer test:unit
composer test:integration
composer test:feature
composer test:migration
npm run test:js
npm run test:browser:component   # isolated real-browser component behavior
npm run test:browser:e2e         # disposable Laravel admin workflows
npm run test:browser:profile     # browser profiling and evidence
npm run test:browser             # all Playwright projects
```

Only run database suites in an explicitly disposable PostgreSQL testing context. The `Tests\\TestCase` database guard must remain active. Browser tests must use a disposable application with deterministic fixtures, not the persistent local preview or production database.

## Changes and refactors

1. Name the user-visible or domain contract.
2. Exercise the failing condition and meaningful success/negative boundaries.
3. Add the smallest independent test that would detect a violation.
4. Delete superseded assertions and duplicated regression scaffolding.
5. A required browser scenario must fail if fixture prerequisites are missing; never silently skip coverage due to missing records.
6. No CI runs from work on the `testing` branch. Merge only after local test validation and deliberate CI approval.

The overarching policy is `docs/TESTING.md`, maintained separately on the `docs` branch. This file intentionally documents only how to navigate and execute tests.
