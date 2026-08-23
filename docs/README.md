# Documentation

The documents in this directory are split by purpose so current application contracts are not mixed with legacy investigation notes or unfinished-work diaries.

## Current application contracts

These describe the application architecture/behavior as it exists on current `main` and should be updated when those durable contracts change:

- [PROJECT-CHARTER.md](PROJECT-CHARTER.md) — product scope, current artist-admin/public principles and non-goals
- [ARCHITECTURE.md](ARCHITECTURE.md) — current application boundaries, typed site structure and ownership
- [DATA-MODEL.md](DATA-MODEL.md) — durable persistence/domain relationships
- [PUBLIC-IMPLEMENTATION-CONTRACT.md](PUBLIC-IMPLEMENTATION-CONTRACT.md) — public routing, publication, Contact and Artwork/viewer behavior
- [MEDIA.md](MEDIA.md) — image/video/audio ingest, storage, reuse and reference rules
- [ANALYTICS.md](ANALYTICS.md) — Matomo/reporting and operational-metrics boundary
- [ADMIN-PERFORMANCE.md](ADMIN-PERFORMANCE.md) — admin performance budget and measurement rules
- [RELEASE.md](RELEASE.md) — immutable image, runtime, persistence and release contract
- [SERVER-OPERATIONS-BASELINE.md](SERVER-OPERATIONS-BASELINE.md) — application/platform ownership boundary; mutable operational implementation lives in `server-platform`

These documents should state the current contract, not repeat the chronological history of every PR/Validation iteration.

## Live work and acceptance status

GitHub Issues are the source of truth for **unfinished work, browser acceptance and current blockers**. A merged PR may establish part of a contract without making the associated product issue complete.

In particular, keep current implementation contracts here and keep changing UI/product acceptance findings in the relevant issue until they become durable architecture.

Do not encode temporary worker branches, release-candidate SHAs or obsolete issue/PR sequencing as permanent documentation dependencies.

## Migration and cutover evidence

These remain relevant until the legacy site is explicitly retired:

- [MIGRATION-PLAN.md](MIGRATION-PLAN.md) — current remaining reconciliation, Validation and cutover sequence
- [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md) — source-to-target reconciliation guarantees
- [SOURCE-INVENTORY.md](SOURCE-INVENTORY.md) — reviewed source systems/migration inputs
- [LEGACY-PUBLIC-CONTRACT.md](LEGACY-PUBLIC-CONTRACT.md) — detailed legacy behavior evidence for browser/cutover comparison

After successful cutover and explicit legacy retirement, legacy-only evidence can be archived/removed in a dedicated cleanup rather than kept indefinitely as active architecture.

## Architecture decisions

Accepted ADRs are historical decisions and are intentionally not rewritten to mirror every later implementation detail:

- [ADR-0001: Application stack](adr/ADR-0001-APPLICATION-STACK.md)
- [ADR-0002: Hosting cost baseline](adr/ADR-0002-HOSTING-COST-BASELINE.md)

## Repository policies

- [Security policy](../SECURITY.md) — private vulnerability-reporting guidance and supported security scope
- [Contribution policy](../CONTRIBUTING.md) — current external-contribution policy
- [Pull request template](../.github/pull_request_template.md) — verification, migration impact and release/Validation claims

## Documentation rules

- prefer present-tense contracts over implementation diaries;
- do not duplicate `server-platform` topology, host paths, credentials or mutable runbooks here;
- do not turn closed issue/PR numbers into permanent architecture dependencies;
- migration evidence may describe legacy names, but runtime docs use current domain language: **Gallery**, **Site Node**, **Journal**, **Custom Page**, **Navigation Node**, **Files** and reusable **Contact component**;
- database/model names may retain historical persistence terminology where renaming adds migration risk; document that boundary explicitly rather than exposing the old name as product language;
- distinguish reusable Files media support from narrower consumer support (for example Gallery primary visual media);
- never include secret values, production dumps, private media or access tokens.