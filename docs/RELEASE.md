# Application release contract

This document defines the `moeller-lars` application artifact/runtime contract. Production/Validation topology and operator runbooks are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

## CI workflows

Canonical verification/release workflow:

```text
.github/workflows/release.yml
```

It runs for direct pushes to `dev` and `main`, pull requests targeting `dev` or `main`, and explicit `workflow_dispatch` runs. Those events run the verification job. Release-image publication remains restricted to verified non-PR runs on `main`; pushes to `dev` and pull-request checks never publish release images. The disposable PostgreSQL service is marked with `MOELLER_LARS_DISPOSABLE_TEST_DATABASE=1` inside GitHub Actions.

Rapid protected-Validation browser workflow:

```text
.github/workflows/preview.yml
```

`preview.yml` builds/publishes an exact-SHA preview image without the full release suite. It does not publish release qualification and does not authorize Production use.

Canonical SHA tag:

```text
ghcr.io/wiiii90/moeller-lars:<40-character-git-sha>
```

Tag existence alone is not release evidence. A deployable release candidate requires exact source SHA, immutable OCI digest and successful canonical release verification for that source.

## Browser reconciliation versus release

The branch/review workflow is defined in [AGENTS.md](../AGENTS.md). Browser review uses an exact dev candidate and the existing lightweight local preview image.

That loop is deliberately separate from release qualification:

1. source-review and integrate worker diffs into dev under the AGENTS contract;
2. run only the migrations required by that candidate against the persistent local preview database;
3. build/recreate the local preview once per coherent browser cycle;
4. collect browser/editorial acceptance;
5. repeat only when the accepted fix set changes.

A local container being healthy means only that the candidate boots. It is not browser acceptance, Validation acceptance or release qualification.

Do not trigger extra/manual canonical verification runs merely to inspect a CSS/Blade/editorial-workspace iteration unless a concrete risk warrants it. Normal pushes to `dev` already invoke the configured verification workflow.

## Accepted candidate promotion to `main`

When an exact `dev` candidate has browser/product acceptance and is intended to become the release candidate:

1. finish durable documentation and acceptance-issue bookkeeping on that exact integration line;
2. create a temporary promotion branch `chore/promote-<short-dev-sha>-to-main` from the exact accepted `dev` SHA;
3. open the protected-main PR from that temporary branch to `main`; never use permanent `dev` itself as the PR head;
4. merge only after the required PR gates pass, then allow/delete only the temporary promotion branch;
5. verify after merge that permanent `dev` still exists and that the intended accepted `dev` commits are reachable from the resulting `main`; reconcile concurrent branch advances without force/reset or history loss;
6. let the canonical `release.yml` verification on `main` qualify and publish the image for that exact `main` SHA;
7. deploy isolated Validation from that exact `main` SHA/immutable image digest through the `server-platform` contract;
8. verify `/app-release.json`, health, migrations/media and the remaining Validation/RC checks against the deployed candidate.

The temporary promotion branch exists specifically because merged PR head branches may be automatically deleted by repository settings. Permanent `dev` must never be exposed to that cleanup path.

Do not rebuild the same release candidate from a different branch after promotion. A branch/SHA preview can be useful before `main`, but it is not the release-qualified artifact used for final candidate Validation.

## Fast protected Validation preview loop

When protected Validation is required:

1. work on the intended source branch and run risk-appropriate targeted checks;
2. push the exact branch/SHA;
3. run `scripts/validation-preview.ps1 <branch-or-sha>`;
4. the helper resolves exact SHA, dispatches/waits for `preview.yml`;
5. after success use the existing platform helper printed by that script;
6. perform browser acceptance against protected Validation.

The preview workflow is not release qualification. Once a candidate has been accepted and promoted to `main`, use the canonical verified `main` release image for release-candidate Validation instead of rebuilding it through `preview.yml`. Do not invent host commands/topology outside the existing platform contract.

Worker integration and branch deletion follow `AGENTS.md`. A protected Validation preview is a separate explicitly authorized operation.

## Verification gates

The canonical verification job covers, in order:

- Composer dependency installation and locked dependency security audit;
- frontend dependency installation, generated analytics map and Vite production build;
- Vite manifest contract verification;
- Pest Unit tests;
- Pest Integration tests;
- Pest Feature tests;
- Pest Architecture tests;
- Pest Migration tests;
- PHPStan/Larastan static analysis;
- Pint formatting verification;
- JavaScript tests through Node's built-in test runner.

All verification steps are blocking. PHPStan has no `continue-on-error` escape hatch, and the project does not use a broad PHPStan baseline or ignore set to hide application debt. Test warnings and static-analysis failures are repaired at their source rather than suppressed to obtain a green workflow.

The Pest layers are intentionally separate CI steps so a failure is attributed to its owning layer. The job is fail-fast: if an earlier blocking step fails, later verification steps are skipped for that run and must be observed on the next green-through-that-point run.

Commands used in disposable CI (not a recipe for the persistent local browser database):

```sh
composer test:unit
composer test:integration
composer test:feature
composer test:architecture
composer test:migration
composer analyse
vendor/bin/pint --test
npm run test:js
npm run build
```

Any agent or worker that changes PHP must treat Pint as a pre-push gate, not as a CI-only check. Before committing/pushing the final PHP diff, run `vendor/bin/pint --test`. If it fails, run Pint to fix the affected PHP formatting, inspect that formatting diff, and rerun `vendor/bin/pint --test` before pushing. Do not use the remote CI run as the first place where PHP formatting is discovered.

`composer test` remains available for running all PHP tests together when a disposable test database context is already established. The durable layer ownership, placement rules and browser-testing direction are defined in [TESTING.md](TESTING.md).

Browser/product acceptance remains separate evidence.

## Runtime interface

- protocol: HTTP;
- internal application container port: `8080`;
- health endpoint: `GET /up`;
- platform ingress proxies privately to the application container;
- concrete Production host ports/network names/persistent paths are platform details.

The image boots from runtime environment injection. Application bootstrap must not depend on a pre-existing Vite manifest merely to discover packages/configuration.

## Local preview interface

The current project workflow may reuse the lightweight local browser preview documented in `AGENTS.md` and the current continuation prompt. The durable known interface is:

- browser URL `http://127.0.0.1:8001`;
- application image internal port `8080`;
- local preview build target `local-preview` in the root `Dockerfile`;
- opt-in Compose service `preview` and Windows helper `scripts/local-preview.ps1`.

Local container names/mount source paths are iteration details, not Production topology. The current follow-up prompt carries their exact transient values when needed.

The local browser database is persistent and is not a PHPUnit target. Feature/Pest
tests run only in the explicit disposable CI database context; local preview work
uses narrow static checks and browser review instead.

## Media/runtime envelope

Current application media ceilings:

- `MEDIA_IMAGE_MAX_BYTES` — default 20 MiB;
- `MEDIA_VIDEO_MAX_BYTES` — default 100 MiB;
- `MEDIA_AUDIO_MAX_BYTES` — default 100 MiB;
- `MEDIA_STORAGE_QUOTA_BYTES` — operator/platform-injected whole-site logical allowance when configured; admission accounts for canonical originals, generated variants and logical persistent application/database data rather than raw PostgreSQL physical-file size.

The canonical media policy supports validated image/video/audio content. Consumer support remains narrower where appropriate.

## Database migrations

- database: PostgreSQL;
- forward migration command: `php artisan migrate --force`;
- migration execution is a deliberate deployment/preview step, not an implicit app-container startup side effect;
- migration failure blocks activation of that candidate.

Data migrations may be intentionally forward-only. Rollback can require restoring the matching recoverable database/media state rather than `migrate:rollback`.

Current pre-cutover reconciliation includes forward canonicalization of Journal Rich Text media and Exhibition presentation/restore state. Admin authentication also requires the forward user-table migration that provides Filament app-authentication secret/recovery-code storage. See [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md) for migration/reconciliation rules and [ADMIN-AUTHENTICATION.md](ADMIN-AUTHENTICATION.md) for the auth contract.

## Persistent state

Authoritative non-reproducible state:

- PostgreSQL application data;
- canonical private MediaAsset originals.

Generated/rebuildable state:

- media variants;
- Laravel caches/views and other disposable runtime caches.

The platform chooses actual Production/Validation mount paths.

## Required runtime configuration

Production requires normal Laravel/application values including APP_ENV/APP_KEY/APP_URL, PostgreSQL, secure session/cookies, media disk/quota/type limits, mail transport/sender, Contact recipient fallback and Matomo configuration where enabled.

Admin password recovery uses the normal Laravel mail interface. Production/Validation must inject the actual SMTP/runtime values (`MAIL_MAILER`, scheme/host/port, credentials where required, EHLO domain where required, and sender identity) through platform configuration. `.env.example` is the variable-name/default reference only and local development intentionally defaults to the log mailer. Concrete mail-server topology/credentials belong to `server-platform`, not this repository.

Real secrets never belong in Git. See [ADMIN-AUTHENTICATION.md](ADMIN-AUTHENTICATION.md).

## Matomo

Tracking and Reporting are independent:

```text
MATOMO_TRACKING_ENABLED
MATOMO_REPORTING_ENABLED
```

Validation may keep tracking disabled while using a restricted read-only Reporting identity. Reporting failure must not become a dependency for public rendering or ordinary admin editing.

## Administrator provisioning

No legacy admin credential is migrated/seeded and public admin registration is not enabled.

```sh
php artisan admin:provision
```

Password input remains interactive/hidden and is not accepted as a command-line argument. Newly provisioned administrators can use the panel without MFA enrollment and may enable TOTP/recovery codes later from the Account dialog. Provisioning, account password changes and password reset share the canonical policy in [ADMIN-PASSWORD-POLICY.md](ADMIN-PASSWORD-POLICY.md). Password recovery is email-based and sends a reset link, never an existing password.

## Workers and scheduling

Core application operation currently requires no permanent queue worker/application scheduler. Contact delivery and administrator password-reset mail are synchronous; scheduled Blog visibility derives from persisted timestamps.

If a future feature requires workers/scheduler, update this document and `server-platform` integration together.

## Validation checks

For an exact deployed candidate:

1. verify `/app-release.json` expected Git SHA;
2. confirm `/up`;
3. inspect/apply required forward migrations;
4. run `php artisan media:verify`;
5. run `legacy:validate` only when frozen migration data is part of the gate;
6. run application smoke contract;
7. perform required public/admin browser acceptance;
8. when authentication/mail configuration changed, exercise administrator login, Account dialog, strict password policy/generator, optional MFA enable/challenge/disable and password-reset delivery in the target environment without logging secrets or reset URLs.

CI, migrations and health are evidence; none alone is complete product acceptance.

## Restore verification

After platform restore orchestration attaches a consistent recoverable DB/media point:

1. keep target out of public service;
2. attach exact application release;
3. inspect migration state;
4. run media verification;
5. run application smoke checks;
6. regenerate/verify required derivatives before activation.

## Rollback

`server-platform` owns rollback and prior known-good artifacts.

- if data remains compatible, prior image may be reactivated;
- if schema/data changed incompatibly, rollback requires corresponding recoverable state.

The application never rewrites the legacy application or automatically reruns source import during startup/rollback.

## Production authorization

CI success, local preview success, image publication and Validation success do **not** authorize Production mutation.

Production deployment/cutover remains an explicit operator/project action under [MIGRATION-PLAN.md](MIGRATION-PLAN.md).

## Agent CI ownership

The CI workflow remains the technical verification authority; chat/worker ownership of a run is governed by [`AGENTS.md`](../AGENTS.md), section **CI ownership and log handoff**.

On shared `dev`, an agent either ignores CI for its scope or owns only the exact verification run caused by its own push. Shared-`dev` agents must not ingest Actions job logs themselves; red-run diagnosis uses the compact report supplied by the user for that exact run. A worker on an exclusively owned feature/fix/chore branch may operate its own branch CI end to end. `dev` becomes exclusive only when the user says so explicitly.
