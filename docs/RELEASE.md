# Application release contract

This document defines the `moeller-lars` application artifact/runtime contract. Production/Validation topology and operator runbooks are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

## Canonical CI workflow

The repository has one canonical GitHub Actions workflow:

```text
.github/workflows/release.yml
```

It verifies pull requests and eligible non-PR runs. Release-image publication is intentionally skipped for normal PR events; an immutable candidate image is produced only by an eligible verified non-PR run.

Canonical SHA tag:

```text
ghcr.io/wiiii90/moeller-lars:<40-character-git-sha>
```

A deployable candidate is identified by exact source SHA, GHCR SHA tag, immutable OCI digest and the CI run that built it. A green PR CI run alone does not mean an image exists.

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