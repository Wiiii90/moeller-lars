# Documentation

This repository's documentation distinguishes the architectural design, authoritative feature contracts, operational procedures, versioned evidence and temporary agent state. Each behavior has **one normative owner**; obsolete and redundant descriptions must be consolidated. Start with [agent entry](../.agents/README.md) for a fresh worker; Dev, Docs, Testing and planned Refactor each have one [shared coordination file](../.agents/areas/dev.md), and each numbered worker has its own temporary living record. The **current user message controls the conversation**: a question is not an instruction to execute unfinished work, and a worker may discuss plans before implementing anything.

## Current source and architecture

**Source review (25 October 2026):** the latest selective batch covers 25 new `dev` commits through `dev@35163591`. Common rich-text colors, paragraph spacing, tables/links, Journal/Home shared viewer, Exhibition media table, Admin Stage and Livewire native session preferences, native Edit selection/save events and public first-paint image readiness are reconciled in the topic contracts. This is **source review**, not a new application/browser/native-generator test. The native documentation baseline remains `docs@317d255e` and is historically versioned.

- [Application architecture](ARCHITECTURE.md) — existing architectural material pending coherent whole-document design review and consolidation; do not treat old generated component graphs or the current accumulation as complete system design.
- [Architecture evidence index](architecture/README.md) — deeper lifecycles and versioned (sometimes historical) source inventories, not a second design authority.
- [Cross-feature compositions](architecture/cross-feature-compositions.md) — artist Edit → Working → Commit → Public, Journal/Exhibition and shared public runtime owners.
- [Normative application contracts](#current-application-contracts) — precise Admin, Public, Media, Rights, schema and product requirements.
- [Current Admin UI](ADMIN-UI.md) — accepted shared presentation/interaction grammar, with topic owners for Controls, Dialogs, Tables and Responsive.
- [Agent protocol](../.agents/README.md), [area coordination](../.agents/areas/docs.md), [Documentation method](../.agents/procedures/documentation.md) and [Tests and isolation](../.agents/procedures/testing.md). Numbered, bounded worker records replace separate chat handoffs.

## Verification boundary

The previous full source-only run on `docs@317d255e` checked 77 Markdown files, 1,221 local links, 199 heading references, five parser self-tests and eight generator checks using PHP 8.3.33; [technical evidence](architecture/completion-criteria.md#technischer-source-only-abschluss-19102026) owns exact provenance. Subsequent GitHub-only edits are not native-run proof. **Do not request a local Docs command for every change**: `P:\moeller-lars-docs` is reserved for rare, expressly authorized Codex batches. The artist-curated Validation data is not a test fixture and Continuous Integration must not be accessed.

## Branch ownership for documentation

The **`docs`** branch remains the explicitly authorized documentation-only exception to ordinary direct-`dev` work; `main` remains the release branch. An earlier `testing` suite was **integrated into `dev`** (`dev@25bc40144`), but active curated test work subsequently resumed on `testing` (verified `testing@b7eee549`, 25 October 2026). The intended test architecture is singular, while `dev` carries interim tests and `testing` curates changes for a controlled return to `dev` before cutover. Read current `dev` source/tests, write **only `docs`**, and import coherent dev batches only with conflict-reviewed **genuine two-parent merges**. The user authorized `P:\moeller-lars-docs` only for rare, explicit Codex bulk checks. Routine source reconciliation uses the remote repository and requires no local command. No new branches/worktrees, automatic `dev` import or Continuous Integration (CI) interaction are authorized without a separate decision; the already-active `testing` branch remains independently maintained. See [AGENTS.md](../AGENTS.md).

## Normative ownership and conflict resolution

Each behavior has **one normative owner**. `ARCHITECTURE.md` is the overview; `architecture/` contains versioned source evidence and test gaps, **not** replacement rules. An inspected `dev` implementation, authored `dev` test assertion or old generator is not an accepted product change; historically pinned `testing@...` links refer only to former source snapshots.

| Responsibility | Contract owner | Scope boundary |
| --- | --- | --- |
| Admin controls, dialogs, table roles, responsiveness | [Control](ADMIN-CONTROL-CONTRACT.md), [Dialog](ADMIN-DIALOG-CONTRACT.md), [Table](ADMIN-TABLE-CONTRACT.md), [Responsive](ADMIN-RESPONSIVE-CONTRACT.md), [admin UI grammar](ADMIN-UI.md) | [Editorial Rich Text](EDITORIAL-RICH-TEXT.md) owns the visual editor/picker and [Admin Localization](ADMIN-LOCALIZATION-CONTRACT.md) owns user language/regions; features do not restate shared behavior |
| Admin messages and recipient-specific inbox | [Notification](ADMIN-NOTIFICATION-CONTRACT.md) | [Activity/Publication](ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md) owns audit, Undo and Commit state—not message delivery |
| Working, LIVE, Commit, Restore and Revert | [Activity/Publication](ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md) | Publication protects history recovery roots; [Media](MEDIA.md) owns reference eligibility and physical deletion |
| Media ingest, reference discovery and physical cleanup | [Media](MEDIA.md) | [Public](PUBLIC-IMPLEMENTATION-CONTRACT.md) defines user-facing eligibility/presentation; [Rights](PUBLIC-RIGHTS-AND-PRIVACY.md) owns incomplete rights/privacy requirements |
| Public navigation, Home, Gallery, Journal, SEO and viewer | [Public](PUBLIC-IMPLEMENTATION-CONTRACT.md) | [Exhibition Editorial](EXHIBITION-EDITORIAL-CONTRACT.md) alone owns organizer, venue/visibility/opening; [Rich Text](EDITORIAL-RICH-TEXT.md) owns the edit/format pipeline |
| Matomo tracking/reporting, event data and failure isolation | [Analytics](ANALYTICS.md) | [Public integration](PUBLIC-IMPLEMENTATION-CONTRACT.md#analytics-boundary) links here; [Rights](PUBLIC-RIGHTS-AND-PRIVACY.md#d-matomo-consent-and-real-privacy-data) owns deferred consent |
| Static Dashboard announcements/changelog and release gates | [Release](RELEASE.md) | Code-owned Markdown is not a per-user Notification record |
| Test layers/discovery/safe isolated DB and cutover | [Testing](TESTING.md), [Migration invariants](MIGRATION-INVARIANTS.md), [Migration plan](MIGRATION-PLAN.md), [Release](RELEASE.md), [platform boundary](SERVER-OPERATIONS-BASELINE.md) | [Completion matrix](architecture/completion-criteria.md) records evidence, not a second test/release contract |

**Historical norm/source differences:** The former Gallery 1-vs-2-eager conflict is superseded by the current [Gallery contract](PUBLIC-IMPLEMENTATION-CONTRACT.md#gallery-delivery-and-loading): two images are eager, only the first high priority. That supersedes the older `dev@11f7e101` source trace. Editor-valid external HTTPS images may violate strict [Public CSP `img-src`](architecture/public-security-privacy-boundaries.md#4-rich-text-url-validierung-vs-csp-ladefreigabe). Invalid *enabled* Matomo URL is distinct from an analytics network outage and has a [source-identified Contact-response risk](architecture/public-security-privacy-boundaries.md#7-fachlicher-source-abschluss-public-read-csp-und-auslieferungsgrenzen). These require product/test decisions, **not** silent documentation redefinition.

[Normative consolidation and derived-artifact classification](architecture/completion-criteria.md#normative-konsolidierung-konfliktstand-und-artefaktentscheidung) records the reconciliation, not a second normative contract.

## Current application contracts

These describe the durable current application architecture and behavior. Browser/product milestone acceptance, release-candidate promotion and environment-specific evidence are tracked separately through the current review/release workflow:

- [PROJECT-CHARTER.md](PROJECT-CHARTER.md) — product scope and public/admin principles
- [ARCHITECTURE.md](ARCHITECTURE.md) — application boundaries, typed site structure and ownership
- [DATA-MODEL.md](DATA-MODEL.md) — durable persistence/domain relationships
- [TESTING.md](TESTING.md) — test layers, placement, discovery, naming, database safety and browser-testing direction
- [PUBLIC-IMPLEMENTATION-CONTRACT.md](PUBLIC-IMPLEMENTATION-CONTRACT.md) — public routing/publication/Home/Journal/media behavior
- [EXHIBITION-EDITORIAL-CONTRACT.md](EXHIBITION-EDITORIAL-CONTRACT.md) — organizer versus venue/address, field visibility, vernissage and forward compatibility
- [EDITORIAL-RICH-TEXT.md](EDITORIAL-RICH-TEXT.md) — canonical visual editor/Storage picker/inline links/captions and safe legacy/public bridge
- [ADMIN-LOCALIZATION-CONTRACT.md](ADMIN-LOCALIZATION-CONTRACT.md) — authenticated English/German UI and independent public/admin date/number regions
- [MEDIA.md](MEDIA.md) — media ingest/references, quota, guarded cleanup and explicitly local pre-release thumbnail consolidation; no Validation execution
- [ANALYTICS.md](ANALYTICS.md) — Matomo/reporting and operational-metrics boundary
- [ADMIN-AUTHENTICATION.md](ADMIN-AUTHENTICATION.md) — `/admin` access, optional TOTP MFA/recovery codes, password reset and transactional-mail boundary
- [ADMIN-PASSWORD-POLICY.md](ADMIN-PASSWORD-POLICY.md) — shared environment-aware password policy and browser-local generator contract
- [ADMIN-PERFORMANCE.md](ADMIN-PERFORMANCE.md) — admin performance budget and investigation rules
- [ADMIN-PROFILING.md](ADMIN-PROFILING.md) — canonical local Debugbar/DevTools/request-classification and on-demand callgraph workflow
- [PULSE-REPORT.md](PULSE-REPORT.md) — read-only Markdown/JSON export of retained Laravel Pulse aggregates, including schema, interpretation, privacy, retention and Validation usage
- [ADMIN-CONTROL-CONTRACT.md](ADMIN-CONTROL-CONTRACT.md) — canonical admin form/control rendering and persistence semantics
- [ADMIN-DIALOG-CONTRACT.md](ADMIN-DIALOG-CONTRACT.md) — shared native Filament dialog types, chrome, lifecycle and edit/command semantics
- [ADMIN-TABLE-CONTRACT.md](ADMIN-TABLE-CONTRACT.md) — canonical ordinary-table role order, alignment, typography, actions and trailing Selection
- [ADMIN-RESPONSIVE-CONTRACT.md](ADMIN-RESPONSIVE-CONTRACT.md) — task-specific Wide/Compact/Narrow/Minimal reference, split into shell, metrics, toolbars, tables and Visual Stages
- [ADMIN-NOTIFICATION-CONTRACT.md](ADMIN-NOTIFICATION-CONTRACT.md) — explicit Ticker feedback/Notification/Activity/Publication semantics, persistent inbox ownership and server-side delivery contract
- [ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md](ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md) — append-only Activity, bounded Undo recovery, shared content-addressed history payloads, Working/LIVE Commit history and reset/restore/revert semantics
- [ADMIN-BROWSER-WORKFLOW.md](ADMIN-BROWSER-WORKFLOW.md) — direct/worker browser-reconciliation modes, shared Visual Stage ownership, icon semantics and local preview loop
- [RELEASE.md](RELEASE.md) — immutable image, preview, runtime, persistence and release contract
- [SERVER-OPERATIONS-BASELINE.md](SERVER-OPERATIONS-BASELINE.md) — application/platform ownership boundary; mutable operational implementation lives in `server-platform`

These documents state current durable contracts. They are not chronological PR/worker diaries and should not encode every temporary reconciliation SHA.

## Agent work areas and living knowledge

[Agent entry](../.agents/README.md) resolves a numbered worker through its one shared [Dev](../.agents/areas/dev.md), [Docs](../.agents/areas/docs.md), [Testing](../.agents/areas/testing.md) or [Refactor](../.agents/areas/refactor.md) area file, then reads that worker's bounded live record. The current user request governs the conversation; only an **explicit replacement-and-continue request** resumes the old worker's persisted task. No separate handoff prompts or growing chat diaries are needed. Worker records are retired and removed after durable knowledge is preserved in its proper contract or shared area. [Documentation procedure](../.agents/procedures/documentation.md), [Testing safety](../.agents/procedures/testing.md), and the complete [current Admin UI contract](ADMIN-UI.md) are task-specific stable references. Retired root handoff/skill documents are preserved in Git history, not kept as competing working policies.

For the later controlled integration to `dev`, [integration boundary](../.agents/procedures/documentation.md#integration-boundary) explicitly protects the newer direct-`dev` root agent policy and existing active worker state. No automatic merge, CI or local Codex batch follows a routine Docs update.

## Live work and acceptance status

GitHub Issues and the current browser/orchestration review are the source of truth for **unfinished work, browser acceptance and current blockers**.

A source-reviewed or technically running reconciliation candidate is not automatically product accepted. A durable contract may be implemented on a temporary browser branch or directly on explicitly authorized `dev` before it reaches `main`; documentation should describe the intended/current contract without pretending that transient browser acceptance is complete.

Browser feedback from the exact current candidate overrides stale acceptance wording. Once an exact `dev` candidate is browser/product accepted, stale issue history must not be treated as proof that already-resolved UI defects still exist; update/close the affected acceptance issues and promote that exact candidate through the `main`/release gates. Temporary candidate references and relevant acceptance state belong in concise, living worker/topic records when needed, not in timeless architecture contracts or standalone chat handoffs.

## Migration and cutover evidence

These remain relevant until the legacy site is explicitly retired:

- [MIGRATION-PLAN.md](MIGRATION-PLAN.md) — remaining reconciliation, browser/editorial acceptance, Validation and cutover sequence
- [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md) — source-to-target reconciliation guarantees, historical-migration self-containment and forward canonicalization rules
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
- transient browser-candidate SHAs/ports are kept only in the relevant short-lived living work record when actually needed, not in durable architecture contracts;
- do not turn closed issue/PR numbers into permanent architecture dependencies;
- migration evidence may describe legacy names, but runtime docs use current domain language: **Gallery**, **Site Node**, **Journal**, **Custom Page**, **Navigation Node**, **Storage** and reusable **Contact component**;
- database/model names may retain historical persistence terminology where renaming adds migration risk; document that boundary explicitly rather than exposing the old name as product language;
- distinguish `MediaAsset` being referenced from it being publicly deliverable;
- keep the central Rich Text/media stack singular rather than documenting editor-specific parallel implementations;
- shared admin geometry belongs to shared tokens/primitives; do not document page-local compensations as architecture;
- security/account-recovery details belong in `ADMIN-AUTHENTICATION.md`; concrete SMTP topology/credentials remain platform-owned;
- never include secret values, production dumps, private media or access tokens.
