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

### Media-processing runtime envelope

The application image pins the web/CLI PHP `memory_limit` to `128M`, `upload_max_filesize` to `20M`, and `post_max_size` to `24M`. `MediaIngestService` permits at most 20 MiB per file and 16,000,000 decoded pixels, rejects larger dimensions before GD decoding, streams canonical originals from the upload file instead of retaining a second in-memory copy, and serializes expensive media ingests through the configured cache lock.

The Apache prefork pool is capped at four request workers. The platform must allocate at least 384 MiB to the application container for this runtime envelope. The platform owns the concrete container memory setting; the application owns the PHP/media/worker assumptions that make that setting meaningful.

The frozen legacy corpus includes a 4499×3551 image (15,975,949 pixels), which remains inside the application limit. A bulk legacy import is an isolated one-shot operation, not concurrent web serving, and may be invoked as `php -d memory_limit=256M artisan ...` when required by the migration runbook. Changing the release candidate alone does not require repeating an already validated import.

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

### Restore validation order

Backup automation, off-server storage, restore orchestration and the isolated recovery environment are owned by `server-platform`. Application recovery evidence must nevertheless follow this order so database records and media are never validated from different recovery points:

1. Keep public traffic away from the recovery target and restore PostgreSQL from the selected recoverable state.
2. Restore the canonical `storage/app/private/originals` set from the matching recovery state before application smoke checks. Restoring the complete `storage/app/private` tree, including generated variants, is allowed and is the current conservative recovery baseline.
3. Attach the restored database and private media to the exact application release being evaluated. Do not run a legacy import as part of backup recovery.
4. Run `php artisan migrate:status --no-interaction` first. A same-release restore is expected to have no unexpected pending migration. When intentionally recovering and then moving forward to a newer compatible release, establish the recoverable restore point before running that release's `php artisan migrate --force`.
5. Run `php artisan media:verify`. It checks every media asset and recorded variant against the restored files, including existence/deletion state, byte size, SHA-256, MIME type and public-thumbnail constraints, and returns a non-zero exit status on any integrity failure.
6. Run `./scripts/release-smoke.sh http://127.0.0.1:8080` and representative controlled media requests before any traffic switch. The restored application is not recovery-ready if either media integrity or application smoke fails.

Variants remain non-authoritative because they derive from canonical originals. Their absence must never cause the original to be silently served as a thumbnail. Until a recovery run has regenerated any omitted derivative and `media:verify` is green, the recovered target must remain out of service. The platform may avoid that regeneration dependency by restoring the backed-up complete private media tree.

For the frozen legacy migration snapshot specifically, `legacy:validate {manifest}` remains an additional migration reconciliation check. It is not a replacement for the general backup/recovery `media:verify` check and a normal backup restore must not rerun the legacy import merely to obtain validation data.

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

## Administration provisioning

No production administrator is seeded and no legacy credential is imported. Create the initial administration account explicitly from the deployed application container:

```sh
php artisan admin:provision
```

`--name` and `--email` may be supplied for operator convenience; the password is always entered through a hidden interactive prompt and is never accepted as a command-line option. Provisioning refuses duplicate email addresses and creates the account with `is_admin=true`, which is required by the production Filament panel-access policy.

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
