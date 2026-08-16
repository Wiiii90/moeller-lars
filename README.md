# moeller-lars

Secure rebuild of the Lars Möller artist website.

## Product rule

Visitors should experience the existing public website: its visual language,
meaningful information architecture, artwork order, and core interactions are
preserved. The rebuild uses clean modern canonical URLs and changes the
operational layer, not the artistic presentation; legacy PHP/query URL syntax
is not itself a compatibility requirement.

The artist receives a purpose-built editorial backend for artworks, exhibitions, CV, blog posts, media, and privacy-conscious visitor statistics.

## Repository boundaries

This repository is the future production codebase. It deliberately contains no copied credentials, database dumps, production media, or source files from the earlier projects.

| Repository | Role in the rebuild | Not reused as production code |
| --- | --- | --- |
| `larsmoeller` | visual/behavioural reference, content inventory, deployment evidence | authentication, configuration, direct SQL/PHP admin code |
| `glassygallery` | ideas for media handling, structured data, role concepts, and CI/CD | visual site builder, unfinished admin shell, current authorization/API implementation |
| `moeller-lars` | application source, tests, Dockerfile/runtime build, migrations, application configuration templates, CI, immutable application artifact/image, health/readiness and persistence/migration contracts | — |
| `server-platform` | production runtime/deployment manifests, Compose placement, shared networking, Caddy ingress, host ports, resource limits, platform monitoring, backup/restore automation, and production deployment/rollback orchestration | — |

## Start here

- [Project charter](docs/PROJECT-CHARTER.md)
- [Target architecture](docs/ARCHITECTURE.md)
- [Migration plan](docs/MIGRATION-PLAN.md)
- [Source inventory](docs/SOURCE-INVENTORY.md)

Development-only Compose remains allowed in `moeller-lars`; production placement and orchestration are owned by `server-platform`.

## Security rule

Do not place secrets in this repository, including in issues, screenshots, sample data, commits, or CI files. Real deployment configuration belongs in the hosting platform's secret store or a server-local `.env` file.

## Local Docker development

The minimal local environment uses one PHP 8.3 application container and one
PostgreSQL 17 container. Its credentials are local development defaults only.

Start:

~~~sh
docker compose up -d --build
docker compose exec app composer install
docker compose exec app npm install --ignore-scripts
~~~

Test:

~~~sh
docker compose exec app composer test
docker compose exec app composer format
docker compose exec app composer analyse
docker compose exec app npm run build
~~~

Stop:

~~~sh
docker compose down
~~~
