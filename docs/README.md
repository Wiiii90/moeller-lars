# Documentation

The documents in this directory are split by purpose so current application contracts are not mixed with legacy investigation notes.

## Current application contracts

These documents describe the application as it exists now and should be kept in sync with code changes:

- [PROJECT-CHARTER.md](PROJECT-CHARTER.md) — product scope and non-negotiable behavior
- [ARCHITECTURE.md](ARCHITECTURE.md) — current application boundaries and ownership
- [DATA-MODEL.md](DATA-MODEL.md) — durable persistence/domain relationships
- [PUBLIC-IMPLEMENTATION-CONTRACT.md](PUBLIC-IMPLEMENTATION-CONTRACT.md) — public routing, publication and viewer behavior
- [MEDIA.md](MEDIA.md) — media ingest, storage and reference rules
- [ANALYTICS.md](ANALYTICS.md) — Matomo/reporting and operational-metrics boundary
- [ADMIN-PERFORMANCE.md](ADMIN-PERFORMANCE.md) — admin performance budget and measurement rules
- [RELEASE.md](RELEASE.md) — immutable image, runtime and release contract
- [SERVER-OPERATIONS-BASELINE.md](SERVER-OPERATIONS-BASELINE.md) — application/platform ownership boundary; operational implementation lives in `server-platform`

## Migration and cutover evidence

These files remain relevant until the legacy site has been retired. They are not general application architecture:

- [MIGRATION-PLAN.md](MIGRATION-PLAN.md) — remaining migration, Validation and cutover sequence
- [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md) — source-to-target reconciliation guarantees
- [SOURCE-INVENTORY.md](SOURCE-INVENTORY.md) — reviewed source systems and migration inputs
- [LEGACY-PUBLIC-CONTRACT.md](LEGACY-PUBLIC-CONTRACT.md) — detailed legacy behavior evidence used for browser/cutover comparison

After successful production cutover and explicit legacy retirement, the legacy evidence can be archived or removed in a dedicated cleanup rather than kept indefinitely as active documentation.

## Architecture decisions

Accepted ADRs are historical decision records and are intentionally not rewritten to mirror every later implementation detail:

- [ADR-0001: Application stack](adr/ADR-0001-APPLICATION-STACK.md)
- [ADR-0002: Hosting cost baseline](adr/ADR-0002-HOSTING-COST-BASELINE.md)

## Repository policies

- [Security policy](../SECURITY.md) — private vulnerability-reporting guidance and supported security scope.
- Pull requests use [`.github/pull_request_template.md`](../.github/pull_request_template.md) to keep verification, migration impact and release/Validation claims explicit.

## Documentation rules

- Prefer present-tense contracts over implementation diaries.
- Do not duplicate `server-platform` topology, host paths, credentials or runbooks here.
- Do not turn closed issue/PR numbers into permanent architecture dependencies.
- Migration evidence may describe legacy names, but runtime/application docs should use current domain language: **Gallery**, **Site Node**, **Journal**, **Custom Page** and **Navigation Node**.
- Database table/model names may retain historical persistence terminology when renaming them would add migration risk; document that boundary explicitly rather than creating compatibility aliases.
- Never include secret values, production dumps, private media or access tokens.
