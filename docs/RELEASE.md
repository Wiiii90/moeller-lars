# Application release contract

This document defines the `moeller-lars` application-side release interface consumed by `Wiiii90/server-platform`. Production topology, ingress, service placement, secrets, backups and rollout orchestration remain platform-owned.

## Release identity

The production unit is an immutable OCI image built from an exact Git commit. An accepted release record contains:

- repository `Wiiii90/moeller-lars`;
- exact source Git SHA;
- GHCR/OCI image reference;
- immutable image digest (`sha256:...`);
- CI run that verified and built the image.

Mutable tags are convenience pointers only and are not sufficient release identity.

## CI verification

`.github/workflows/release.yml` verifies the application before building the image. The verification gate currently covers:

- Composer dependency installation and locked dependency audit;
- frontend dependency installation and Vite build;
- Pest test suite;
- PHPStan analysis;
- Pint formatting check;
- JavaScript tests.

The release-image job pushes `ghcr.io/wiiii90/moeller-lars:<git-sha>` and records the immutable digest. A failed verification gate must not produce an accepted release candidate.

## Runtime interface

- protocol: HTTP
- internal container port: `8080`
- readiness: `GET /up`
- platform ingress proxies privately to the application
- no production Caddy configuration, public port mapping, host path or network name is owned here

The application image records its build SHA in OCI metadata and `/app-release.json`.

## Media-processing runtime envelope

The production image configures a bounded PHP/media runtime. The application currently permits at most 20 MiB per uploaded media file and 16,000,000 decoded pixels, validates content-derived MIME/type constraints, re-encodes accepted media and serializes expensive ingest operations through the configured cache lock.

Canonical originals are authoritative. Public derivatives are generated assets and may not silently fall back to originals when a required derivative is missing.

A bulk legacy import is an isolated migration operation and may use an explicitly larger CLI memory limit when required by the migration runbook; it is not normal concurrent web serving.

## Database and migrations

- database: PostgreSQL
- production migration command: `php artisan migrate --force`
- migrations are run separately from application startup in the platform deployment sequence
- migration failure blocks traffic switch
- migration history is not assumed to be data-reversible

A recoverable PostgreSQL state is therefore required before a production migration that could prevent the previous application release from using the resulting schema/data safely. Rollback may require restore rather than `migrate:rollback`.

## Persistent application data

Authoritative/non-reproducible:

- PostgreSQL application database;
- canonical originals under `storage/app/private/originals`.

Generated/rebuildable:

- generated variants under `storage/app/private/variants`;
- framework caches/views and other ephemeral runtime state.

`server-platform` chooses concrete persistent volumes/host paths and backup locations.

## Restore validation

Application-side recovery validation follows this order:

1. Restore the selected PostgreSQL recovery point with public traffic kept away from the target.
2. Restore matching canonical originals (or conservatively the full private-media tree).
3. Attach the exact application release being evaluated.
4. Run `php artisan migrate:status --no-interaction` before any intentional forward migration.
5. Run `php artisan media:verify` and require a successful result.
6. Run `./scripts/release-smoke.sh http://127.0.0.1:8080` and representative controlled-media requests.
7. Keep the target out of service until required generated derivatives exist and media verification is green.

`legacy:validate {manifest}` is an additional legacy-migration reconciliation check, not a general backup-restore procedure.

## Production configuration contract

Production requires the normal Laravel application/database/session configuration, including:

- `APP_ENV=production`
- `APP_KEY`
- HTTPS `APP_URL`
- PostgreSQL `DB_*` settings
- `SESSION_SECURE_COOKIE=true`
- application media/storage configuration

Mail/contact configuration includes `MAIL_*` values and `CONTACT_TO_ADDRESS` where an environment-level default is used. Artist-managed contact delivery settings remain application data where supported by the current admin workflow.

### Matomo configuration

Tracking and Reporting API access are intentionally independent capabilities:

- `MATOMO_TRACKING_ENABLED`
- `MATOMO_REPORTING_ENABLED`
- `MATOMO_BASE_URL`
- `MATOMO_SITE_ID`
- `MATOMO_API_TOKEN` (reporting only; restricted read-only identity)
- `MATOMO_REPORT_TIMEOUT_SECONDS`
- `MATOMO_REPORT_CACHE_SECONDS`
- `MATOMO_REPORT_STALE_SECONDS`

Browser tracking requires tracking to be enabled plus a valid HTTPS Matomo base URL and positive site ID. Admin reporting requires reporting to be enabled plus the reporting configuration/token. Validation may enable reporting while keeping browser tracking disabled so release-review traffic is not recorded as production visitor traffic.

Matomo/API failure produces an analytics error/stale state and must not break public requests or ordinary admin editing.

No real production value belongs in Git. `.env.example` is the canonical repository template for supported variable names.

## Initial administrator

No production administrator is seeded and no legacy credential is imported. Provision the first account explicitly from the deployed application container:

```sh
php artisan admin:provision
```

The password is entered interactively and is never accepted as a command-line option.

## Workers and scheduling

The current application contract does not require a permanent queue worker or Laravel scheduler for correctness. Scheduled Blog visibility is evaluated against publication time at read time. If future features introduce mandatory workers/scheduling, this contract and `server-platform` must be updated together before release acceptance.

## Local release verification

```sh
composer test
composer analyse
vendor/bin/pint --test
npm run test:js
npm run build
docker build --build-arg APP_GIT_SHA="$(git rev-parse HEAD)" -t moeller-lars:"$(git rev-parse HEAD)" .
```

Production-like validation additionally exercises migrations/readiness/media through the platform-provided temporary HTTPS environment.

## Rollback

Until first rebuilt-site cutover is accepted, the legacy production site remains the cutover safety boundary. For subsequent releases, `server-platform` retains the previous known-good image digest and evaluates database compatibility. If the previous release cannot safely use the migrated state, rollback requires restoration of the pre-migration recoverable state.

The application container never edits the legacy production application and never runs a legacy import automatically at startup.
