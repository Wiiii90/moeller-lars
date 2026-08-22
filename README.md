# Lars Möller — artist website

[![Verify and build release image](https://github.com/Wiiii90/moeller-lars/actions/workflows/release.yml/badge.svg)](https://github.com/Wiiii90/moeller-lars/actions/workflows/release.yml)

Laravel application for the rebuilt Lars Möller artist website and its artist-facing administration.

The public site keeps the artist's established visual language and content presentation while replacing the legacy PHP/MySQL application, administration, security model, analytics integration, migration tooling, and release process.

## Stack

- PHP 8.3+ and Laravel 13
- Filament 5 for `/admin`
- Blade, Vite, custom CSS and targeted JavaScript for the public site
- PostgreSQL
- Pest, PHPStan, Pint and Node's test runner
- self-hosted Matomo Community/Core for human analytics
- OCI/Docker release images published to GHCR

## Repository scope

This repository owns the application: source code, database migrations, tests, public/admin UI, media rules, migration/reconciliation tooling, Docker image contract and CI.

Production and Validation infrastructure are intentionally separate. Host configuration, ingress, runtime placement, secrets, backups, monitoring, deployment and rollback are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

No production credentials, database dumps or authoritative production media belong in this repository.

## Local development

The development Compose stack provides the application container and PostgreSQL 17.

```sh
docker compose up -d --build
docker compose exec app composer install --no-interaction
docker compose exec app npm ci --ignore-scripts
```

Run the same core verification used by CI:

```sh
docker compose exec app composer test
docker compose exec app composer analyse
docker compose exec app vendor/bin/pint --test
docker compose exec app npm run test:js
docker compose exec app npm run build
```

Stop the stack with:

```sh
docker compose down
```

## Site structure

The editable public site is modeled as typed site nodes:

- **Home** — singleton root presentation
- **Gallery** — artwork collection, optionally nested
- **Journal** — Blog or Exhibitions
- **Custom Page** — structured content/components
- **Navigation Node** — navigation-only grouping

The domain types, public routing, admin destinations and navigation projection have separate owners; persistence details do not define application behavior.

## Releases

`.github/workflows/release.yml` is the canonical GitHub Actions workflow. It verifies pull requests and, for non-PR runs such as `main`, publishes an immutable image tagged with the exact Git SHA:

```text
ghcr.io/wiiii90/moeller-lars:<git-sha>
```

A green CI run or published image does not itself authorize a Production deployment.

## Documentation

Start with [docs/README.md](docs/README.md). It separates current application contracts from migration evidence and historical architecture decisions.

## Security

Never commit secrets or private production data. Use environment/platform secret storage for credentials and tokens. Security-sensitive findings should not be posted with exploitable details or secret material in public issues.
