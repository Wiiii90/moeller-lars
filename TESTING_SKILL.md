# TESTING_SKILL.md — current testing contract

Repository: `Wiiii90/moeller-lars`. **`testing` is the canonical, curated test suite.** Active feature development and temporary incident tests occur on `dev`. Periodically merge newer `dev` product changes into the existing `testing` branch, preserving the curated tests and rejecting superseded or presentation-only regressions. Do not merge `testing` back to `dev` until the user explicitly authorizes the final testing promotion (before cutover) and isolated qualification is complete. There is no second test structure or new branch. Suite definitions remain `tests/README.md` and `docs/TESTING.md`.

## Scope and execution

- For each batch, read both current HEADs, compare all intervening production and test changes, then reconcile onto `testing`. The **actual Git tree** is authoritative: preserve deletions, consolidation and intentional renames even when compare reports a file as renamed. Inspect tests supplied by `dev` against the responsible production contract; keep enduring behavior, replace duplicate scenarios, and delete obsolete source/markup assertions. Recheck branch HEADs before writing; no force/reset/rebase, no new branches/worktrees.
- In direct-`dev` mode, **no CI/Actions interaction**, including status/log reads, dispatch, monitoring and retries. Editing workflow source on `dev` is permitted when requested; running CI, releases, Server, Validation or Production is separate user authorization.
- Report `SOURCE-REVIEWED`, `TEST-RUN`, `BROWSER-VERIFIED` and `USER-ACCEPTED` distinctly. Old suite results never certify subsequent production changes.

## Single test structure

- Pest: `Unit`, `Integration`, `Feature` (Admin/Public) and `Migration`, owned by `tests/Pest.php` and `phpunit.xml`.
- Node: `npm run test:js`.
- Chromium: `npm run test:browser:component`, `test:browser:e2e`, `test:browser:profile`, together via `npm run test:browser`.
- Dedicated multi-process PostgreSQL concurrency: `tests/Support/RunPublicationConcurrency.ps1` and `tests/Support/RunPublicSessionConcurrency.ps1`, invoked from an explicitly authorized `testing` or `dev` checkout in a suitable Windows environment. These are *not* part of ordinary Pest/CI suites.
- Static checks: `composer analyse` and `composer lint`; PHP line coverage requires PCOV/Xdebug. There is **no Architecture suite**.

## Mandatory isolation

Database, seeded browser and destructive test commands **only** in a freshly created, independently verified disposable PostgreSQL 17 instance: unique database/user/loopback port, tmpfs storage, no persistent mounts, initially empty schema. Verify the actual SQL/PDO database/user/version/cluster identity, not only Docker names or CI flags.

Before each PHP/HTTP worker enables disposable test flags or resets a database, verify its actual PHPUnit/Laravel environment: `DB_URL` cleared, no inherited config cache, exact APP_ENV/connection, and separate writable private/public media, sessions, caches, compiled views, other framework caches, PHP system/upload temp and logs. `LocalPreviewDatabaseGuard` must remain fail-closed.

The persistent Compose database `moeller_lars_testing` and volume `moeller-lars_postgres_data` are **never** test targets. Do not use `migrate:fresh`/`reset`/`db:wipe` on them. Preserve the user's Preview data, stashes, all unknown files and Production/Validation data. Remove only uniquely owned test resources after containment/reparse validation. An exit code of zero does not prove isolation.

## Regression quality

Protect persisted behavior, visibility in Working vs Committed, authorization after role changes, source media and checksum integrity, atomic audit/Undo/rollback semantics, exactly-one notification per real admin mutation, no-op/error feedback, route/media authorization, real HTTP and correct Session lifecycle. Tests should fail for broken behavior, not changed source spelling or UI repair-era CSS. Do not weaken assertions, silently skip fixtures or create duplicate incident test systems.

Use targeted checks appropriate to the changed domain: media rights and artifacts, Working/Committed publication and historical snapshots, rich-text sanitization, public media/privacy, admin persistence/permissions, regional formatting and real HTTP/SPA interaction. Add cases to existing topical tests and consolidate redundant variants instead of retaining incident-named files or superficial UI layout assertions. Record failures without speculative production-code changes. Avoid an unnecessary full-suite rerun after each small `dev` commit. The normal Pest sessions are in-memory, so **Public Session races require** their separate HTTP/database-session runner. Publication concurrency uses genuine PostgreSQL blocking in its own runner.

## Coverage and release boundary

`release.yml` covers current four PHP suites, build, dependency audit, PHPStan, Pint and JS. `browser-profile.yml` covers Chromium. A combined PHP `app/` line-coverage percentage is produced on an authorized PR to `main` and on `main` pushes; it excludes JS/browser and special concurrency runners. There is no verified percentage yet and no arbitrary threshold. The exact release procedure is `docs/RELEASE.md`; nothing in this file authorizes CI or promotion.

Keep only active workflows and open risks here. Completed incident logs, old testing-branch orchestration, historical commit matrices and retired test suites belong in Git history, not a parallel living system.
