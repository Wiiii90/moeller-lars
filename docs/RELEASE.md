# Application release contract

This document defines the `moeller-lars` application artifact/runtime contract. Production/Validation topology and operator runbooks are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

## CI workflows

The canonical verification/release workflow is:

```text
.github/workflows/release.yml
```

It runs for pull requests targeting `main`, pushes to `main` and explicit `workflow_dispatch` runs. Pull requests targeting an integration branch do not trigger this full release workflow. Release-image publication is skipped for normal PR events; eligible verified non-PR runs build the release image.

A second workflow exists only for rapid protected-Validation browser iteration:

```text
.github/workflows/preview.yml
```

`preview.yml` is manually dispatched from the trusted `main` workflow definition and checks out an explicitly requested source ref/SHA. It builds and publishes an exact-SHA image without running the full release verification suite. It does not publish the `release/image` status, does not authorize Production use and is not release qualification.

Canonical SHA tag used by both the release and Validation helper contracts:

```text
ghcr.io/wiiii90/moeller-lars:<40-character-git-sha>
```

Tag existence alone is therefore not release evidence. A deployable release candidate is identified by exact source SHA, immutable OCI digest and a successful canonical `release.yml` run for that source. A green PR run or a successful preview build alone is not release qualification.

## Fast Validation preview loop

For normal implementation work:

1. work on a feature branch and run risk-appropriate targeted checks locally;
2. push the branch; do not open a PR merely to obtain a browser preview;
3. run `scripts/validation-preview.ps1 <branch-or-sha>`;
4. the script resolves the requested ref to an exact SHA, dispatches `preview.yml`, finds the exact new workflow run and waits for it with `gh run watch --exit-status`;
5. after success, use the existing platform command printed by the script: `sudo server-platform-moeller-lars-validation update <SHA>`;
6. perform browser acceptance against the existing protected Validation environment.

The preview workflow rejects a source commit that is already reachable from `main`; such commits belong to the canonical release path. Superseded preview runs for the same source are cancelable. Preview images omit release-only SBOM/provenance work; the final release workflow retains it.

When several dependent workers form one product tranche, use an `integration/<tranche>` branch. Worker PRs may target that integration branch without triggering `release.yml`; reconcile there, browser-review the combined exact SHA, then open one final integration PR to `main`. Do not introduce an integration branch for unrelated or single-slice work.

The final PR targeting `main` still receives the complete verification gate below. Pushes to `main` then verify the exact merged commit and build its release image. `main`/release runs are not canceled merely because a newer release run starts.

## Verification gates

The canonical workflow covers:
- Composer dependency installation;
- Composer security audit;
- frontend dependency installation/build;
- Pest;
- PHPStan;
- Pint;
- JavaScript tests.

Local equivalents:

```sh
composer test
composer analyse
vendor/bin/pint --test
npm run test:js
npm run build
```

## Runtime interface

- protocol: HTTP;
- internal application container port: `8080`;
- health endpoint: `GET /up`;
- platform ingress proxies privately to the application container;
- concrete host ports, Caddy/network names and persistent host paths are platform details.

The image must boot from runtime environment injection. Application/bootstrap commands must not require a pre-existing Vite manifest simply to discover packages/configuration.

## Media/runtime envelope

Current application media ceilings are configured in bytes:

- `MEDIA_IMAGE_MAX_BYTES` — default 20 MiB;
- `MEDIA_VIDEO_MAX_BYTES` — default 100 MiB;
- `MEDIA_AUDIO_MAX_BYTES` — default 100 MiB;
- `MEDIA_STORAGE_QUOTA_BYTES` — operator/platform-injected site allowance when configured.

The canonical media policy supports explicitly validated image, video and audio content. Image decoding remains bounded by application safety policy. Video/audio support does not imply server-side transcoding.

Platform container memory/CPU/PID limits must remain compatible with the documented PHP/media processing envelope.

## Database migrations

- database: PostgreSQL;
- forward migration command: `php artisan migrate --force`;
- migration execution is a platform deployment step, not an implicit application-container startup side effect;
- migration failure blocks activation/cutover.

Migration history is not guaranteed data-reversible. If a schema/data migration breaks compatibility with the previous image, rollback may require restoring the matching pre-migration recoverable PostgreSQL/media state rather than `migrate:rollback`.

## Persistent state

Authoritative non-reproducible application state:
- PostgreSQL application data;
- canonical private MediaAsset originals.

Generated/rebuildable state:
- media variants/derivatives;
- Laravel caches/views and other disposable runtime caches.

The platform chooses actual mount/host paths.

## Required runtime configuration

Production requires normal Laravel/application runtime values including:
- `APP_ENV=production`;
- `APP_KEY`;
- canonical HTTPS `APP_URL`;
- PostgreSQL connection;
- secure session/cookie configuration;
- `MEDIA_DISK`;
- media quota/type limits where configured;
- mail transport + sender identity;
- Contact recipient/runtime fallback where applicable;
- Matomo tracking/reporting configuration where enabled.

Real secrets/credentials never belong in Git. `.env.example` is the variable-name/default reference only.

## Matomo

Tracking and Reporting are independent:

```text
MATOMO_TRACKING_ENABLED
MATOMO_REPORTING_ENABLED
```

Validation may keep tracking disabled while using an explicitly restricted read-only Reporting API identity. Reporting uses bounded failure behavior and must not become a dependency for public rendering or ordinary admin editing.

## Administrator provisioning

No legacy admin credential is migrated or seeded into the image.

Initial provisioning is explicit:

```sh
php artisan admin:provision
```

Password input remains interactive/hidden and is not accepted as a command-line argument.

## Workers and scheduling

Core application operation currently requires no permanent queue worker/application scheduler.

Contact delivery is synchronous under the current contract. Scheduled Blog visibility is derived from persisted timestamps.

If a future feature introduces a required worker/scheduler, this document and `server-platform` integration must be updated together.

## Validation checks

For an exact deployed candidate:

1. verify `/app-release.json` reports the expected Git SHA;
2. confirm `/up` succeeds;
3. run/inspect required forward migrations;
4. run `php artisan media:verify`;
5. run `php artisan legacy:validate <reviewed-manifest>` only when validating the frozen migration dataset;
6. run the application release smoke contract;
7. perform the required public/admin browser acceptance for the candidate.

A green CI run, migration validator or health endpoint is evidence, not complete product acceptance.

## Restore verification

After platform restore orchestration attaches a consistent recoverable database/media point:

1. keep the recovery target out of public service;
2. attach the exact application release being evaluated;
3. inspect migration state before any intentional forward migration;
4. run `media:verify`;
5. run the application smoke contract;
6. regenerate/verify omitted required derivatives before activation.

Missing required derivatives must not be silently replaced by originals when a consumer contract requires the derivative.

## Rollback

`server-platform` owns the actual rollback sequence and prior known-good artifact/digest.

- if current data remains compatible, the prior image may be reactivated;
- if data/schema changed incompatibly, rollback requires the corresponding recoverable state.

The application never rewrites the legacy Production application or automatically reruns a source import during startup/rollback.

## Production authorization

CI success, image publication and Validation success do **not** authorize Production mutation.

Production deployment/cutover remains an explicit operator/project action under the gates in [MIGRATION-PLAN.md](MIGRATION-PLAN.md).