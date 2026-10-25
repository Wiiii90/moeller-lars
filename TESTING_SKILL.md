# TESTING_SKILL.md — current testing contract

Repository: `Wiiii90/moeller-lars`. **All ordinary source work is directly on `dev`.** The former dedicated `testing` worktree, testing-to-dev merges, historical HEAD handoffs and duplicate test-suite documentation are retired. Current usage is defined by `tests/README.md` and `docs/TESTING.md`.

## Scope and execution

- Read the actual `dev` HEAD, `AGENTS.md`, responsible source and relevant tests. Recheck HEAD before writes, reconcile other chats' changes, use normal fast-forward updates, never force/reset/rebase shared history. No new branches/worktrees.
- In direct-`dev` mode, **no CI/Actions interaction**, including status/log reads, dispatch, monitoring and retries. Editing workflow source on `dev` is permitted when requested; running CI, releases, Server, Validation or Production is separate user authorization.
- Report `SOURCE-REVIEWED`, `TEST-RUN`, `BROWSER-VERIFIED` and `USER-ACCEPTED` distinctly. Old suite results never certify subsequent production changes.

## Single test structure

- Pest: `Unit`, `Integration`, `Feature` (Admin/Public) and `Migration`, owned by `tests/Pest.php` and `phpunit.xml`.
- Node: `npm run test:js`.
- Chromium: `npm run test:browser:component`, `test:browser:e2e`, `test:browser:profile`, together via `npm run test:browser`.
- Dedicated multi-process PostgreSQL concurrency: `tests/Support/RunPublicationConcurrency.ps1` and `tests/Support/RunPublicSessionConcurrency.ps1`, invoked from the `dev` checkout in a suitable Windows environment. These are *not* part of ordinary Pest/CI suites.
- Static checks: `composer analyse` and `composer lint`; PHP line coverage requires PCOV/Xdebug. There is **no Architecture suite**.

## Mandatory isolation

Database, seeded browser and destructive test commands **only** in a freshly created, independently verified disposable PostgreSQL 17 instance: unique database/user/loopback port, tmpfs storage, no persistent mounts, initially empty schema. Verify the actual SQL/PDO database/user/version/cluster identity, not only Docker names or CI flags.

Before each PHP/HTTP worker enables disposable test flags or resets a database, verify its actual PHPUnit/Laravel environment: `DB_URL` cleared, no inherited config cache, exact APP_ENV/connection, and separate writable private/public media, sessions, caches, compiled views, other framework caches, PHP system/upload temp and logs. `LocalPreviewDatabaseGuard` must remain fail-closed.

The persistent Compose database `moeller_lars_testing` and volume `moeller-lars_postgres_data` are **never** test targets. Do not use `migrate:fresh`/`reset`/`db:wipe` on them. Preserve the user's Preview data, stashes, all unknown files and Production/Validation data. Remove only uniquely owned test resources after containment/reparse validation. An exit code of zero does not prove isolation.

## Regression quality

Protect persisted behavior, visibility in Working vs Committed, authorization after role changes, source media and checksum integrity, atomic audit/Undo/rollback semantics, exactly-one notification per real admin mutation, no-op/error feedback, route/media authorization, real HTTP and correct Session lifecycle. Tests should fail for broken behavior, not changed source spelling or UI repair-era CSS. Do not weaken assertions, silently skip fixtures or create duplicate incident test systems.

Use only targeted tests relevant to changes; avoid repeating a 700+-test suite without a reason. The normal Pest sessions are in-memory, so **Public Session races require** their separate HTTP/database-session runner. Publication concurrency uses genuine PostgreSQL blocking in its own runner.

## Coverage and release boundary

`release.yml` covers current four PHP suites, build, dependency audit, PHPStan, Pint and JS. `browser-profile.yml` covers Chromium. A combined PHP `app/` line-coverage percentage is produced on an authorized PR to `main` and on `main` pushes; it excludes JS/browser and special concurrency runners. There is no verified percentage yet and no arbitrary threshold. The exact release procedure is `docs/RELEASE.md`; nothing in this file authorizes CI or promotion.

Keep only active workflows and open risks here. Completed incident logs, old testing-branch orchestration, historical commit matrices and retired test suites belong in Git history, not a parallel living system.
