# Development

## Local environment

The supported repository-local environment is the Docker Compose development/test shell in `compose.yaml`:

- PHP 8.3 CLI with Composer and required extensions
- Node.js 22 / npm
- PostgreSQL 17
- repository mounted into `/var/www/html`

It is deliberately a development/test environment. Production Compose, ingress, host paths, secrets and service placement belong to `Wiiii90/server-platform`.

## Bootstrap

```sh
docker compose up -d --build
docker compose exec app composer install
docker compose exec app npm ci --ignore-scripts
```

The Compose service supplies isolated test database settings and a non-production application key. Do not reuse these values for production.

Stop the local services with:

```sh
docker compose down
```

## Verification

Run the checks relevant to the changed subsystem while developing. Before an integration checkpoint or release candidate, run the full suite:

```sh
docker compose exec app composer test
docker compose exec app composer analyse
docker compose exec app vendor/bin/pint --test
docker compose exec app npm run test:js
docker compose exec app npm run build
```

Useful direct commands include:

```sh
docker compose exec app php artisan test --filter=SomeFeature
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app vendor/bin/pint --test
```

GitHub Actions performs the integration verification and builds the immutable release image described in [RELEASE.md](RELEASE.md).

## Configuration

Copy or inspect `.env.example` for supported variable names. Real credentials and production values must remain outside Git.

Important configuration groups include:

- Laravel application/session/database settings
- mail/contact delivery settings
- media storage settings
- Matomo tracking and reporting settings

Matomo browser tracking and admin reporting are independent capabilities:

- `MATOMO_TRACKING_ENABLED`
- `MATOMO_REPORTING_ENABLED`

Both use the configured Matomo base URL/site ID as applicable; reporting additionally requires the restricted read-only API token. See [ANALYTICS.md](ANALYTICS.md).

## Database and migrations

Local/test development uses PostgreSQL. Schema changes belong in Laravel migrations and must preserve the data/recovery assumptions in [DATA-MODEL.md](DATA-MODEL.md) and [RELEASE.md](RELEASE.md).

Do not use production data for development or CI. Legacy imports operate only from explicitly supplied external snapshots and must follow [MIGRATION-PLAN.md](MIGRATION-PLAN.md) and [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md).

## Repository boundaries

Do not add any of the following to this repository:

- production Compose manifests or Caddy configuration
- `/srv/...` deployment topology
- production secrets or database credentials
- database dumps or private media archives
- recurring backup automation or off-server backup destinations
- host-level monitoring/runtime configuration

Those responsibilities belong to `Wiiii90/server-platform`.

## Change discipline

- Keep public behaviour data-driven; do not reintroduce hard-coded artwork category identities.
- Preserve server-side authorization and audit boundaries for admin mutations.
- Route all uploaded media through the canonical media validation/ingest path.
- Keep raw HTML and unsafe links outside public rich-text output.
- Treat Matomo as non-critical: analytics failure must not break normal public or admin behaviour.
- Update the relevant canonical document when a contract or environment variable changes.
