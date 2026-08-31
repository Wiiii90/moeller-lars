# Documentation

The documents in this directory are split by purpose so current application contracts are not mixed with legacy investigation notes or unfinished-work diaries.

## Current application contracts

These describe the durable application architecture/behavior that current accepted work is converging on and must be reconciled before release:

- [PROJECT-CHARTER.md](PROJECT-CHARTER.md) — product scope and public/admin principles
- [ARCHITECTURE.md](ARCHITECTURE.md) — application boundaries, typed site structure and ownership
- [DATA-MODEL.md](DATA-MODEL.md) — durable persistence/domain relationships
- [PUBLIC-IMPLEMENTATION-CONTRACT.md](PUBLIC-IMPLEMENTATION-CONTRACT.md) — public routing/publication/Home/Journal/media behavior
- [MEDIA.md](MEDIA.md) — image/video/audio ingest, Rich Text references, public/preview policy and guarded deletion
- [ANALYTICS.md](ANALYTICS.md) — Matomo/reporting and operational-metrics boundary
- [ADMIN-PERFORMANCE.md](ADMIN-PERFORMANCE.md) — admin performance budget and investigation rules
- [RELEASE.md](RELEASE.md) — immutable image, preview, runtime, persistence and release contract
- [SERVER-OPERATIONS-BASELINE.md](SERVER-OPERATIONS-BASELINE.md) — application/platform ownership boundary; mutable operational implementation lives in `server-platform`

These documents state current durable contracts. They are not chronological PR/worker diaries and should not encode every temporary reconciliation SHA.

## Admin workflow skills

Repository-root workflow documents are intentionally kept close to `AGENTS.md` because they govern coding/review behavior rather than public architecture:

- [AGENTS.md](../AGENTS.md) — branch/orchestration/worker/reconciliation contract and central technology rules
- [ui-skills.md](../ui-skills.md) — admin-only UI grammar for heading/action rows, metrics, filters, selection, tables, grids, DnD, dialogs and browser acceptance
- [followup-skill.md](../followup-skill.md) — lossless continuation-prompt contract for handing a long orchestration chat to a new chat

## Live work and acceptance status

GitHub Issues and the current browser/orchestration review are the source of truth for **unfinished work, browser acceptance and current blockers**.

A source-reviewed or technically running reconciliation candidate is not automatically product accepted. A durable contract may be implemented on a temporary browser branch before it reaches `main`; documentation should describe the intended/current contract without pretending that transient browser acceptance is complete.

Browser feedback from the exact current candidate overrides stale acceptance wording. Temporary worker branches, candidate SHAs and local container state belong in the current continuation handoff, not in timeless architecture docs.

## Migration and cutover evidence

These remain relevant until the legacy site is explicitly retired:

- [MIGRATION-PLAN.md](MIGRATION-PLAN.md) — remaining reconciliation, browser/editorial acceptance, Validation and cutover sequence
- [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md) — source-to-target reconciliation guarantees and forward canonicalization rules
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
- [Pull request template](../.github/pull_request_template.md) — verification, browser acceptance, migration impact and release/Validation claims

## Documentation rules

- prefer present-tense contracts over implementation diaries;
- do not duplicate `server-platform` topology, host paths, credentials or mutable runbooks here;
- exact transient browser-candidate SHAs/ports belong in continuation prompts, not architecture docs, except where a temporary evidence record is explicitly required;
- do not turn closed issue/PR numbers into permanent architecture dependencies;
- migration evidence may describe legacy names, but runtime docs use current domain language: **Gallery**, **Site Node**, **Journal**, **Custom Page**, **Navigation Node**, **Files** and reusable **Contact component**;
- database/model names may retain historical persistence terminology where renaming adds migration risk; document that boundary explicitly rather than exposing the old name as product language;
- distinguish `MediaAsset` being referenced from it being publicly deliverable;
- keep the central Rich Text/media stack singular rather than documenting editor-specific parallel implementations;
- never include secret values, production dumps, private media or access tokens.
