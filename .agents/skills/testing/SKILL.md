---
name: testing
description: Curate the single test suite on testing from cumulative dev changes, redesign obsolete tests, and run isolated final qualification only after a user-declared freeze.
---

# Testing worker — test ownership

Read `testing/AGENTS.md`, [the worker lifecycle](../../README.md), the existing [test strategy](../../../docs/operations/testing.md), `testing/tests/README.md` and `testing/tests/Pest.php` as relevant. A numbered Testing worker registers its role and active task **on testing**, not in the Docs roster. This method restores the durable principles of the retired root `TESTING_SKILL.md`; that old root file must **not** return.

## Bounded cumulative-source procedure

1. Find the exact last reconciled `dev` source SHA and inspect the **cumulative relevant changes** to the newest `dev` source; follow entrypoints, migrations, shared consumers and framework changes. Do not create a test for every Dev commit and do not scan unrelated repositories or execute an entire suite as a first step.
2. Map changed behavior to the **existing canonical suite and fixtures**. Before creating files, consider reorganizing, moving, consolidating, renaming or **deleting** obsolete tests; eliminate duplicated source-string assertions, cosmetic button/CSS-position regressions, stale incident scenarios and alternative test runners.
3. Preserve the same testing architecture: Pest **Unit** (pure), **Integration** (services and database/files), **Feature** (Admin/Public HTTP and authorization), **Migration** (actual cutover contracts), JavaScript unit tests and Chromium Component/End-to-End/Profiling projects. Dedicated multi-process PostgreSQL Publication and Public Session concurrency runners are **not** implied by standard Pest/CI. Discovery belongs to `tests/Pest.php`, `phpunit.xml` and the current Playwright config.
4. Protect real persistence/Working-versus-Committed behavior, security/authorization, immutable media, atomic audit/Undo/rollback, exactly-one success notification per real change, no-op and failure feedback, HTTP/session lifecycle and justified concurrency. Test meaningful public outcomes, not ephemeral repair rounds or implementation spelling.
5. Keep preparation bounded. Reconcile source on testing without claiming qualification. Only after the **user freezes dev** verify exact non-test source parity and run authorized full/relevant Codex/native suites as the last pre-integration gate. Fix erroneous tests **on testing**; true production defects return to Dev. Reconcile and recheck if Dev changed, then seek explicit transfer approval.

## Mandatory isolation

Database-writing tests and seeded browser tests require verified, **independently disposable PostgreSQL 17**, dedicated database/user/loopback port, temporary storage, no persistent volumes and an initially empty schema. Verify actual PDO/database/user/version identity, not just an environment flag. Clear inherited `DB_URL` and config cache; isolate upload/media directories, sessions, cache, views, logs and system temporary files; preserve `LocalPreviewDatabaseGuard`. Never wipe persistent Preview/Validation/Production or the user's Compose data volumes. Specifically, the persistent local Compose database `moeller_lars_testing` and named volume `moeller-lars_postgres_data` are **not disposable test targets**, despite the database name: never invoke `migrate:fresh`, `reset` or `db:wipe` against them. Before allowing any destructive test/reset, verify the actual SQL/PDO environment and independently owned ephemeral storage. Preserve unknown stashes/files; clean up only verifiably owned disposable test resources. A zero exit code does not prove containment.

The dedicated runners `tests/Support/RunPublicationConcurrency.ps1` and `tests/Support/RunPublicSessionConcurrency.ps1` must be invoked only on authorized, separately isolated Windows environments when qualifying their scenarios. Normal in-memory Pest sessions **do not** prove database-backed HTTP session races.

## Evidence, privacy and transfer

Always separate **source reviewed**, **test authored**, **test actually run**, **real browser checked** and **user accepted**. Report executed commands/results, skipped suites, coverage limitations and exact source SHA. No fake percentage from assertion count: combined PHP `app/` line coverage requires a real PCOV/Xdebug driver and applies only to PHP code; browser/JavaScript and special concurrency runners are not included, and no arbitrary release coverage threshold should be invented. CI/Actions, PR/promotion, release and production operations require separate authorization. Include only curated changed test sources/necessary support on approved transfer; do not merge branch-local rosters or duplicate historical root skills.

No pre-release compatibility aliases, duplicate test suites or convenience frameworks. Preserve actual migration/snapshot/database compatibility. The user owns architectural exceptions, freeze and final test acceptance.
