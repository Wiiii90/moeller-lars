# Application release contract

This document defines the `moeller-lars` application artifact/runtime contract. Production/Validation topology and operator runbooks are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

## CI workflows

Canonical verification/release workflow:

```text
.github/workflows/release.yml
```

It runs for pull requests targeting `main`, pushes to `main` and explicit `workflow_dispatch` runs. Release-image publication is skipped for normal PR events; eligible verified non-PR runs build the release image.

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

Admin/browser polish may use a temporary local combined branch such as `reconcile/admin-v0.3-browser` and a lightweight local preview image.

That loop is deliberately separate from release qualification:

1. reconcile accepted worker diffs on one combined source branch;
2. run only the migrations required by that candidate against the isolated local preview database;
3. build/recreate the local preview once per coherent browser cycle;
4. collect browser/editorial acceptance;
5. repeat only when the accepted fix set changes.

A local container being healthy means only that the candidate boots. It is not browser acceptance, Validation acceptance or release qualification.

Do not trigger the canonical full release suite merely to inspect a CSS/Blade/editorial-workspace iteration unless a concrete risk warrants it.

## Fast protected Validation preview loop

When protected Validation is required:

1. work on the intended source branch and run risk-appropriate targeted checks;
2. push the exact branch/SHA;
3. run `scripts/validation-preview.ps1 <branch-or-sha>`;
4. the helper resolves exact SHA, dispatches/waits for `preview.yml`;
5. after success use the existing platform helper printed by that script;
6. perform browser acceptance against protected Validation.

The preview workflow is not release qualification. Do not invent host commands/topology outside the existing platform contract.

When several dependent workers form one product tranche, use one deliberate integration/reconciliation line. Parallel browser-fix workers may use side branches from one exact shared base, then be statically reviewed and reconciled before a combined preview. Do not make every worker independently build/deploy the same tranche.

## Verification gates

The canonical final workflow covers:

- Composer dependency installation/security audit;
- frontend dependency installation/build;
- Pest;
- PHPStan;
- Pint;
- JavaScript tests.

Local equivalents when full verification is appropriate:

```sh
composer test
composer analyse
vendor/bin/pint --test
npm run test:js
npm run build
```

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
- local preview Dockerfile `storage/local-validation-snapshot/Dockerfile.local-preview`.

Local container names/mount source paths are iteration details, not Production topology. The current follow-up prompt carries their exact transient values when needed.

## Media/runtime envelope

Current application media ceilings:

- `MEDIA_IMAGE_MAX_BYTES` — default 20 MiB;
- `MEDIA_VIDEO_MAX_BYTES` — default 100 MiB;
- `MEDIA_AUDIO_MAX_BYTES` — default 100 MiB;
- `MEDIA_STORAGE_QUOTA_BYTES` — operator/platform-injected allowance when configured.

The canonical media policy supports validated image/video/audio content. Consumer support remains narrower where appropriate.

## Database migrations

- database: PostgreSQL;
- forward migration command: `php artisan migrate --force`;
- migration execution is a deliberate deployment/preview step, not an implicit app-container startup side effect;
- migration failure blocks activation of that candidate.

Data migrations may be intentionally forward-only. Rollback can require restoring the matching recoverable database/media state rather than `migrate:rollback`.

Current pre-cutover reconciliation includes forward canonicalization of Journal Rich Text media and Exhibition presentation/restore state; see `MIGRATION-INVARIANTS.md`.

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

Real secrets never belong in Git. `.env.example` is the variable-name/default reference only.

## Matomo

Tracking and Reporting are independent:

```text
MATOMO_TRACKING_ENABLED
MATOMO_REPORTING_ENABLED
```

Validation may keep tracking disabled while using a restricted read-only Reporting identity. Reporting failure must not become a dependency for public rendering or ordinary admin editing.

## Administrator provisioning

No legacy admin credential is migrated/seeded.

```sh
php artisan admin:provision
```

Password input remains interactive/hidden and is not accepted as a command-line argument.

## Workers and scheduling

Core application operation currently requires no permanent queue worker/application scheduler. Contact delivery is synchronous; scheduled Blog visibility derives from persisted timestamps.

If a future feature requires workers/scheduler, update this document and `server-platform` integration together.

## Validation checks

For an exact deployed candidate:

1. verify `/app-release.json` expected Git SHA;
2. confirm `/up`;
3. inspect/apply required forward migrations;
4. run `php artisan media:verify`;
5. run `legacy:validate` only when frozen migration data is part of the gate;
6. run application smoke contract;
7. perform required public/admin browser acceptance.

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
