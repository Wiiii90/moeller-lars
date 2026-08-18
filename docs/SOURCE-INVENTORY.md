# Source inventory

This file describes the source repositories used by the rebuild and their authority. It intentionally avoids credentials, private data and production-only paths.

## `larsmoeller` — legacy public/content reference

Role:

- evidence for the legacy public presentation, content, artwork ordering and intended interactions;
- migration-source schema/content evidence;
- reference for legacy admin behaviour only where it helps identify intended editorial semantics.

Important boundary:

- legacy PHP/MySQL code is not reused as the target runtime;
- legacy authentication, direct-SQL admin patterns, credentials and configuration are not migrated;
- old web-server/application configuration found in the source tree is historical evidence, not the authority for current production networking/TLS posture;
- the currently running legacy production dataset remains authoritative for the final pre-cutover migration snapshot if it contains changes newer than previously captured development snapshots.

Current host containment and operational facts are documented in [SERVER-OPERATIONS-BASELINE.md](SERVER-OPERATIONS-BASELINE.md) and, authoritatively for target operations, in `Wiiii90/server-platform`.

## `glassygallery` — architectural/design reference only

Useful reference areas include structured content/media concepts, role vocabulary, database migrations and CI/container awareness.

It is not the application base. Its general site-customizer direction, unfinished admin/analytics surfaces and authorization inconsistencies do not match the Lars Möller target product or security boundary.

No `glassygallery` API/admin implementation is treated as trusted production code merely because a similar concept exists in `moeller-lars`.

## `Wiiii90/moeller-lars` — target application

This repository is the active application implementation and owns:

- Laravel application source and public rendering;
- Filament artist administration;
- PostgreSQL target schema and migrations;
- artwork/category/CV/Exhibition/Blog/Media domain logic;
- secure media processing and application-owned storage contract;
- legacy importer, mapping and reconciliation logic;
- Matomo tracking/reporting integration and local operational aggregates;
- tests/static analysis/build verification;
- Dockerfile and immutable application release image;
- application health/readiness, persistence, migration and rollback contracts;
- configuration variable names/templates, but not production values.

It deliberately contains no production database dump, private production media archive, deployment secret or copy of the legacy application as a runtime dependency.

## `Wiiii90/server-platform` — target production platform

This is the authoritative repository for production and temporary-validation operations, including:

- Caddy ingress and canonical HTTPS routing;
- production/validation Compose and service placement;
- private networks and host ports;
- production secret placement;
- Matomo runtime/database/persistence;
- resource limits and health integration;
- recurring backup/restore automation;
- monitoring;
- deployment, release activation and rollback orchestration.

`moeller-lars` consumes this platform contract. Production topology must not be duplicated into the application repository.

## Authority order

For conflicting information, use this order:

1. current `moeller-lars` target contracts and implementation for application behaviour;
2. open GitHub Issues/milestones for unfinished acceptance requirements;
3. `server-platform` for current target production operations;
4. legacy source/contracts for migration and visual/content evidence.

Legacy evidence may constrain migration or visual acceptance, but it does not override explicit target architecture decisions such as canonical modern routing, separated CV/Exhibitions, secure media handling or application/platform ownership.
