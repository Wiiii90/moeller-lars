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

The normal development Compose stack provides the application container and PostgreSQL 17.

```sh
docker compose up -d --build
docker compose exec app composer install --no-interaction
docker compose exec app npm ci --ignore-scripts
```

Run the core verification used by CI when that level of verification is actually required:

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

Browser-polish/reconciliation work may deliberately use the existing lightweight local preview loop instead of recreating the development stack. That workflow is governed by [AGENTS.md](AGENTS.md) and the current continuation/orchestration prompt; it is not the canonical Production release path.

## Site structure

The editable public site is modeled as typed site nodes:

- **Home** — singleton root presentation with Artwork, Under Construction, Skip Home or Custom presentation modes
- **Gallery** — artwork collection, optionally nested
- **Journal** — Blog or Exhibitions; switching the active Journal template does not destructively convert/delete the inactive template's entries
- **Custom Page** — structured content/components, including CV and reusable Contact composition
- **Navigation Node** — navigation-only grouping

The domain types, public routing, admin destinations and navigation projection have separate owners; persistence details do not define application behavior.

## Admin development and review

The artist admin is browser-reviewed as an editorial product, not accepted merely because a page boots or CI is green.

Start with:

- [AGENTS.md](AGENTS.md) — branch/reconciliation/worker workflow and central technology rules
- [ui-skills.md](ui-skills.md) — shared admin UI grammar for headings, metrics, control rows, tables, grids, selection, ordering and dialogs
- [worker-prompt-skill.md](worker-prompt-skill.md) — compact execution-only worker-prompt contract
- [followup-skill.md](followup-skill.md) — how to hand a long orchestration session to a new chat without losing exact Git/runtime/review state

## Releases

`.github/workflows/release.yml` is the canonical GitHub Actions workflow. It verifies pull requests targeting `main` and, for eligible non-PR runs, publishes an immutable image tagged with the exact Git SHA:

```text
ghcr.io/wiiii90/moeller-lars:<git-sha>
```

A green CI run, a local browser candidate or a published preview image does not itself authorize a Production deployment.

## Documentation

Start with [docs/README.md](docs/README.md). It separates current application contracts from migration evidence and historical architecture decisions.

## Security

Never commit secrets or private production data. Use environment/platform secret storage for credentials and tokens. Report security-sensitive findings according to [SECURITY.md](SECURITY.md), not through public issues containing exploit details or secret material.

## License and contributions

The repository is publicly readable but is currently **proprietary / source-visible**, not open source. No open-source license is granted at this time.

External code contributions and pull requests are not currently accepted. See [CONTRIBUTING.md](CONTRIBUTING.md). This policy can be changed later before external contributions are accepted.
