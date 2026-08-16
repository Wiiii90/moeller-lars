# Application release contract

This file is the `moeller-lars` specialization of `Wiiii90/server-platform/docs/APPLICATION-DEPLOYMENT-CONTRACT.md`. Production topology remains owned by `server-platform`.

## Immutable release identity

The production unit is the OCI image built from an exact Git commit. A release record must contain:

- repository: `Wiiii90/moeller-lars`;
- exact source Git SHA;
- OCI image reference;
- immutable image digest (`sha256:...`);
- CI run that built and verified that digest.

The image contains OCI revision metadata and `/app-release.json` with the build SHA. Mutable tags are convenience pointers only and are never sufficient release identity.

## Runtime interface

- protocol: HTTP
- internal container port: `8080`
- platform-owned ingress proxies privately to this interface
- readiness: `GET /up` must return success
- no production Caddy, host path, network name or public port mapping is owned here

## Database and migration

- database: PostgreSQL
- migration command: `php artisan migrate --force`
- migration execution is separate from container startup and owned by platform deployment sequencing
- the current migration history is **not classified as data-reversible** for rollback: migrations can contain destructive target-schema transformations whose `down()` method cannot reconstruct removed production data
- therefore a recoverable PostgreSQL state is required before production migration; rollback after a migrated release may require database restore rather than `migrate:rollback`

A migration failure fails deployment. Traffic must not switch to a release whose required migrations failed.

## Persistent data

Authoritative, non-reproducible data:

- PostgreSQL application database
- `/var/www/html/storage/app/private/originals` — canonical uploaded originals

Generated/rebuildable data:

- `/var/www/html/storage/app/private/variants` — generated public derivatives; these may be regenerated from canonical originals by application media processing
- Laravel framework caches/views/sessions are runtime state, not release data

`server-platform` chooses concrete host volumes/paths and backup placement. It is acceptable to persist/backup the complete `storage/app/private` tree even though variants are rebuildable.

## Required production configuration

Always required by the production image:

- `APP_ENV=production`
- `APP_KEY`
- `APP_URL` using HTTPS
- `DB_CONNECTION=pgsql`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `SESSION_SECURE_COOKIE=true`
- `MEDIA_DISK=local`

Feature/runtime configuration names include:

- mail: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `CONTACT_TO_ADDRESS`
- Matomo: `MATOMO_ENABLED`, `MATOMO_BASE_URL`, `MATOMO_SITE_ID`, `MATOMO_API_TOKEN`, `MATOMO_REPORT_TIMEOUT_SECONDS`

No real values belong in Git. When Matomo browser tracking is enabled the image additionally requires a valid HTTPS `MATOMO_BASE_URL` and positive `MATOMO_SITE_ID`. The admin Reporting API requires `MATOMO_API_TOKEN`; an unavailable Reporting API produces an explicit dashboard error state and does not break public requests.

## Workers and scheduling

No background queue worker or application scheduler is required for the current release contract. Contact delivery is synchronous. Scheduled blog visibility is evaluated against `scheduled_at` at read time; no promotion job is required.

## Build and verification

Local image build:

```sh
docker build --build-arg APP_GIT_SHA="$(git rev-parse HEAD)" -t moeller-lars:"$(git rev-parse HEAD)" .
```

Application verification before designating a release candidate:

```sh
composer test
composer analyse
vendor/bin/pint --test
npm run test:js
npm run build
docker build --build-arg APP_GIT_SHA="$(git rev-parse HEAD)" -t moeller-lars:"$(git rev-parse HEAD)" .
```

Validation environment smoke contract after platform-provided PostgreSQL, secrets and persistent media are attached:

```sh
php artisan migrate:status --no-interaction
./scripts/release-smoke.sh http://127.0.0.1:8080
```

## Rollback

The initial rebuilt-site deployment has no previous rebuilt-site production image. Legacy production remains the rollback/cutover safety boundary until explicit cutover. For subsequent releases, `server-platform` pins the previous known-good image digest and determines whether its PostgreSQL state remains compatible. If not, rollback requires restore from the pre-migration recoverable state.

The application container never edits the legacy production application or imports/finalizes legacy data automatically at startup.
