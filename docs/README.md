# Documentation

This directory separates **current target contracts** from **migration/legacy evidence** and **operations/release contracts**. Existing contract paths are intentionally stable because GitHub Issues and the platform repository already reference them.

## Start here

| Document | Purpose |
| --- | --- |
| [PROJECT-CHARTER.md](PROJECT-CHARTER.md) | product scope, non-negotiable behaviour and definition of done |
| [PROJECT-STATUS.md](PROJECT-STATUS.md) | dated implementation snapshot and remaining acceptance tracks |
| [ARCHITECTURE.md](ARCHITECTURE.md) | target application boundaries, stack, trust boundaries and ownership |
| [DATA-MODEL.md](DATA-MODEL.md) | canonical application data model and invariants |
| [PUBLIC-IMPLEMENTATION-CONTRACT.md](PUBLIC-IMPLEMENTATION-CONTRACT.md) | target public routing/rendering/interaction contract |
| [ANALYTICS.md](ANALYTICS.md) | Matomo and local operational-metrics contract |
| [DEVELOPMENT.md](DEVELOPMENT.md) | local development and verification workflow |
| [RELEASE.md](RELEASE.md) | immutable application artifact, runtime, persistence and rollback contract |

## Migration and source reconciliation

These documents define how the legacy site is treated as migration evidence. They do **not** make the legacy PHP application a runtime dependency.

- [SOURCE-INVENTORY.md](SOURCE-INVENTORY.md) — source repositories and ownership boundaries
- [MIGRATION-PLAN.md](MIGRATION-PLAN.md) — migration sequence and responsibilities
- [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md) — losslessness and reconciliation requirements

## Legacy/reference evidence

These files describe observed legacy behaviour or historic security conditions. When they conflict with the current target contracts, the current target contracts win unless an explicit project decision says otherwise.

- [LEGACY-PUBLIC-CONTRACT.md](LEGACY-PUBLIC-CONTRACT.md)
- [LEGACY-SECURITY-BASELINE.md](LEGACY-SECURITY-BASELINE.md)

## Architecture decisions

Accepted decisions live under [`adr/`](adr/). ADRs record why a decision was made; they are not a substitute for the current architecture contract when implementation details later evolve within that decision.

## Authority and maintenance rules

1. GitHub Issues and milestones are authoritative for unfinished work and acceptance state.
2. `PROJECT-CHARTER.md` defines product intent and non-negotiable scope.
3. `ARCHITECTURE.md`, `DATA-MODEL.md`, `PUBLIC-IMPLEMENTATION-CONTRACT.md`, `ANALYTICS.md` and `RELEASE.md` describe current target behaviour.
4. Migration/legacy documents preserve evidence and reconciliation rules; they must not reintroduce obsolete runtime behaviour into the target application.
5. Production topology, secrets, ingress, host paths, backups, monitoring and deployment orchestration are authoritative in `Wiiii90/server-platform`.
6. Documentation changes should update the canonical document instead of appending contradictory addenda.
7. Do not rename established contract files merely for tidiness; update this index when classification changes so external references remain valid.

A dated status snapshot is useful for orientation but should not duplicate the entire issue tracker. If implementation changes materially, update `PROJECT-STATUS.md` together with the relevant canonical contract.
