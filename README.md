# moeller-lars

Secure rebuild of the Lars Möller artist website and its editorial backend.

The project preserves the public site's artistic identity and meaningful content structure while replacing the legacy application, administration, data model, deployment model, and security boundary with a maintainable Laravel application.

## Current state

As of 2026-08-18, the repository contains the active target implementation rather than an early scaffold. The application includes the core artwork/media domain, secure artist administration, Blog and Contact workflows, CV/Exhibition domain support, migration/reconciliation tooling, Matomo tracking/reporting integration, and an immutable release-image pipeline.

The remaining work is primarily acceptance and release work: public visual parity and viewer refinement, completion/validation of the CV and Exhibitions split, final admin UX acceptance, production Matomo integration, migration validation, and the release/cutover gates tracked in GitHub Issues.

See [Project status](docs/PROJECT-STATUS.md) for the current implementation snapshot. GitHub Issues and milestones remain the authoritative source for unfinished work.

## Stack

- PHP 8.3+
- Laravel 13
- Filament 5 for `/admin`
- Blade + targeted vanilla JavaScript for the public site
- PostgreSQL 17 for local/test development
- Pest, PHPStan and Laravel Pint
- Vite 8 / Node.js 22 toolchain
- self-hosted Matomo Community/Core integration
- Docker/OCI release image consumed by `Wiiii90/server-platform`

## Repository boundaries

| Repository | Responsibility |
| --- | --- |
| `Wiiii90/moeller-lars` | application source, schema/migrations, importer and reconciliation logic, tests, application runtime contract, release image and app-specific configuration templates |
| `Wiiii90/server-platform` | production/validation orchestration, Caddy ingress, networks, host placement, secrets, resource limits, Matomo runtime, backups, monitoring, deployment and rollback |
| `larsmoeller` | legacy public/content/behaviour reference and migration source evidence only |
| `glassygallery` | design/architecture reference only; not a production-code base |

Production credentials, database dumps, private media archives and server-local paths do not belong in this repository.

## Development

The repository includes a Docker Compose development/test shell with PHP 8.3, Node.js and PostgreSQL 17.

```sh
docker compose up -d --build
docker compose exec app composer install
docker compose exec app npm ci --ignore-scripts
```

Run the main verification suite with:

```sh
docker compose exec app composer test
docker compose exec app composer analyse
docker compose exec app vendor/bin/pint --test
docker compose exec app npm run test:js
docker compose exec app npm run build
```

See [Development](docs/DEVELOPMENT.md) for environment rules and targeted verification guidance.

## Documentation

Start with the [documentation index](docs/README.md).

Core current contracts:

- [Project charter](docs/PROJECT-CHARTER.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Data model](docs/DATA-MODEL.md)
- [Public implementation contract](docs/PUBLIC-IMPLEMENTATION-CONTRACT.md)
- [Analytics](docs/ANALYTICS.md)
- [Application release contract](docs/RELEASE.md)
- [Migration plan](docs/MIGRATION-PLAN.md)

Legacy/source evidence is intentionally kept separate from current target behaviour and is indexed in `docs/README.md`.

## Release model

GitHub Actions verifies PHP/JavaScript tests, static analysis, formatting and frontend build before producing an immutable GHCR image identified by the exact Git SHA and OCI digest. Production placement and rollout are owned by `server-platform`; this repository does not own production Compose or ingress.

## Security

Never commit secrets or production data. This includes credentials, API tokens, database dumps, production media archives, private server paths, recovery hashes, or screenshots containing sensitive values.

Security-sensitive changes must preserve the existing server-side authorization, audit, media validation, safe-rich-text, CSRF/session and least-privilege boundaries described in the architecture and implementation contracts.
