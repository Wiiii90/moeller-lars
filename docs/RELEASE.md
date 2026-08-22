# Application release contract

This document defines the `moeller-lars` application artifact/runtime contract. Production/Validation deployment topology and operator runbooks are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

## Canonical CI workflow

The repository has one canonical GitHub Actions workflow:

```text
.github/workflows/release.yml
```

It verifies pull requests and non-PR runs with the application quality gates. For eligible non-PR runs (including `main`), it publishes an immutable GHCR image tagged with the exact Git SHA.

Canonical image tag:

```text
ghcr.io/wiiii90/moeller-lars:<40-character-git-sha>
```

The release image is built only after verification succeeds.

## Release identity

A deployable candidate is identified by all of:

- repository `Wiiii90/moeller-lars`;
- exact source Git SHA;
- GHCR image tag for that SHA;
- immutable OCI digest (`sha256:...`);
- CI run that verified/built it.

The image embeds the Git SHA in `/app-release.json` (and image revision metadata). Mutable labels/tags alone are never sufficient release identity.

A green pull-request CI run does not imply that the PR head image exists, because release-image publication is intentionally skipped for pull-request events.

## Verification gates

The canonical workflow verifies:

- Composer dependency installation;
- Composer security audit;
- frontend dependency installation/build;
- Pest;
- PHPStan;
- Pint formatting check;
- JavaScript tests.

Application-level local equivalents include:

```sh
composer test
composer analyse
vendor/bin/pint --test
npm run test:js
npm run build
```

No warning suppression or fake build artifact is part of the release contract.

## Runtime interface

- application protocol: HTTP;
- internal application container port: `8080`;
- health/readiness endpoint: `GET /up`;
- platform ingress proxies privately to the application container;
- concrete public ports, host paths, container network names and proxy topology are not owned here.

The application image must be bootable with runtime environment injected by the platform. Composer/artisan bootstrap must not depend on a pre-existing Vite manifest merely to discover packages/configuration.

## Runtime/media envelope

The current application image configures the PHP/media runtime to safely admit the supported Media policies.

Durable assumptions include:

- image upload ceiling from `MEDIA_IMAGE_MAX_BYTES` (default 20 MiB);
- video upload ceiling from `MEDIA_VIDEO_MAX_BYTES` (default 100 MiB);
- image decoding bounded by the application media policy;
- expensive ingest is serialized/bounded through the media service path;
- platform container memory/resource limits must remain compatible with the application's documented PHP/media processing envelope.

When a one-shot legacy import requires a deliberately larger CLI memory limit, that is an explicit operator invocation, not a permanent web-runtime setting.

## Database migrations

- database: PostgreSQL;
- forward migration command: `php artisan migrate --force`;
- migration execution is a platform deployment step, not implicit container startup behavior;
- migration failure blocks activation/cutover.

The migration history is **not guaranteed data-reversible**. A rollback across schema/data changes may require restoring the pre-migration recoverable PostgreSQL state rather than running `migrate:rollback`.

Therefore the platform must establish the appropriate recoverable state before a Production migration that could affect rollback compatibility.

## Persistent state

Authoritative non-reproducible application state:

- PostgreSQL application database;
- canonical media originals under the application's private media storage contract.

Generated/rebuildable state:

- media variants/derivatives;
- Laravel caches/views and other disposable runtime caches.

The platform chooses actual mount/host paths. This repository documents logical application persistence only.

## Required runtime configuration

Production always requires appropriate values for the normal Laravel/application boundary, including:

- `APP_ENV=production`
- `APP_KEY`
- canonical HTTPS `APP_URL`
- PostgreSQL connection variables
- secure session/cookie configuration
- `MEDIA_DISK`

Feature-specific configuration includes:

- media quota/type limits;
- mail transport and From identity;
- contact recipient configuration;
- separate Matomo tracking/reporting enablement;
- Matomo base URL/site ID and restricted Reporting API token when reporting is enabled.

Real values and credentials never belong in Git.

`.env.example` is the canonical variable-name/default template for local/reference configuration. Platform-owned production values override it outside the repository.

## Matomo configuration

Tracking and reporting are independent capabilities:

```text
MATOMO_TRACKING_ENABLED
MATOMO_REPORTING_ENABLED
```

Production may enable both. Validation may deliberately keep tracking disabled while using an explicitly restricted read-only reporting identity.

Reporting requires bounded timeouts and must not make public/application correctness depend on Matomo availability.

## Administrator provisioning

No legacy admin credential is migrated and no production admin password is seeded in Git/image content.

Provision the initial admin explicitly in the deployed container:

```sh
php artisan admin:provision
```

Password input remains interactive/hidden and is not accepted as a command-line argument.

## Workers and scheduling

The current application contract does not require a permanent queue worker or application scheduler for core operation.

Contact delivery is synchronous under the current contract. Blog scheduled visibility is evaluated against persisted schedule timestamps at read time rather than requiring a promotion job.

If a future feature introduces a required worker/scheduler, this release contract and `server-platform` integration must be updated together.

## Validation checks

For an exact deployed Validation candidate:

1. verify `/app-release.json` contains the expected Git SHA;
2. confirm `/up` succeeds;
3. run required migrations/migration status checks;
4. run `php artisan media:verify`;
5. run `php artisan legacy:validate <reviewed-manifest>` when validating the frozen migration dataset;
6. run `./scripts/release-smoke.sh http://127.0.0.1:8080` in the appropriate container/operator context;
7. complete required browser/admin acceptance.

`legacy:validate` is migration-specific and is not a normal startup check.

## Restore verification

After platform restore orchestration attaches a consistent database/media recovery point:

1. keep the recovery target out of public service;
2. attach the exact application release being evaluated;
3. inspect migration status before applying any intentional forward migration;
4. run `media:verify`;
5. run the application release smoke contract;
6. regenerate/verify any omitted required derivatives before service activation.

A missing required derivative must never cause the original to be silently substituted as successful public output.

## Rollback

`server-platform` owns the actual rollback command/sequence and retains the previous known-good artifact/digest as appropriate.

Rollback is release/data compatibility dependent:

- if database state remains compatible, the prior image may be reactivated;
- if a migration changed state incompatibly, rollback requires the appropriate pre-migration restore point.

The application container never rewrites the legacy production application or runs a source import automatically during startup/rollback.

## Production authorization

CI success, image publication and Validation success are release evidence only. They do **not** authorize Production mutation.

Production deployment/cutover requires explicit operator/project approval under the platform readiness/backup/rollback gates described in [MIGRATION-PLAN.md](MIGRATION-PLAN.md).
