# Documentation

The documents in this directory are split by purpose so current application contracts are not mixed with legacy investigation notes or unfinished-work diaries.

## Architecture inventory (in progress)

- [Architecture source inventory and models](architecture/README.md) — evidence-backed, source-versioned inventory with concrete Publication and Exhibition Map flows; coverage gaps are explicit. This is a staged replacement, not a second normative source of contracts.

## Task-specific UI entrypoints

- [Admin UI skill](../.agents/skills/admin-ui/SKILL.md) — Filament page composition, shared controls, tables, dialogs and responsive rules (normative details remain in `ui-skills.md` and the dedicated admin contracts).
- [Public UI skill](../.agents/skills/public-ui/SKILL.md) — public routes, navigation, Home, journals, media and presentation (normative details remain in the public contract and live source).

## Local documentation link verification

Run `php scripts/check-documentation-links.php --check` from the repository root to check relative Markdown file links and heading fragments without Laravel, a database, CI or network access. `--summary` reports counts without failing and `--self-test` runs isolated parser fixtures. The scanner covers simple inline Markdown links and reference definitions, ignoring fenced/inline code and non-local URI schemes; HTML anchors, dynamic MDX, multiline destinations and arbitrary complex CommonMark are **outside its contract**.

The [architecture completion report](architecture/completion-criteria.md#abnahmekriterien-für-das-ende-der-inhaltlichen-arbeit) records the newer **GitHub-tree source audit on `docs@8b3bc15b`**: **77 Markdown files, 1,008 local relative destinations and 107 heading references (52 distinct target/fragment pairs)**. No relative target was missing; two obsolete media heading fragments were corrected on `docs@9d5233a4`. This independent source inspection is **not** a native execution of the PHP link checker or a full CommonMark/HTML/external-link audit. The earlier PHP 8.4 isolated fixture checks remain prior evidence, not an equivalent current full checkout run.

## Documentation method

- [Documentation skill](../.agents/skills/documentation/SKILL.md) — maintain versioned architecture against `dev`, including its **single active test structure**; distinguish authored assertions from executed verification and consolidate source evidence before safe bundled generator checks.
- [Current test strategy](TESTING.md#layers-and-ownership) — four current Pest layers, Node JS, three Playwright projects and separate real-process PostgreSQL concurrency; [nine selected authored `dev` assertion families](architecture/completion-criteria.md#testabdeckung-eine-dev-testsuite-authored-assertions), **no claims of executed tests**.

## Living documentation work state

- [DOCS_SKILL.md](../DOCS_SKILL.md) — persistent root-level working memory for the long-lived `docs` workstream. Resume here in a new chat; update after meaningful architecture/doc changes, newly merged `dev` work and verification results. This is a handoff/checkpoint, not a duplicate normative product contract.

## Branch ownership for documentation

The **`docs`** branch remains the explicitly authorized documentation-only exception to ordinary direct-`dev` work; `main` remains the release branch. The former `testing` workstream has been **integrated into `dev`** (`dev@25bc40144`), and there is **one active suite**, not two independently managed test branches. Read current `dev` source/tests, write **only `docs`**, and import coherent dev batches only with conflict-reviewed **genuine two-parent merges**. This does not authorize new branches/worktrees or CI interaction. See [AGENTS.md](../AGENTS.md).

## Normative ownership and conflict resolution

Each behavior has **one normative owner**. `ARCHITECTURE.md` is the overview; `architecture/` contains versioned source evidence and test gaps, **not** replacement rules. An inspected `dev` implementation, authored `dev` test assertion or old generator is not an accepted product change; historically pinned `testing@...` links refer only to former source snapshots.

| Responsibility | Contract owner | Scope boundary |
| --- | --- | --- |
| Admin controls, dialogs, table roles, responsiveness | [Control](ADMIN-CONTROL-CONTRACT.md), [Dialog](ADMIN-DIALOG-CONTRACT.md), [Table](ADMIN-TABLE-CONTRACT.md), [Responsive](ADMIN-RESPONSIVE-CONTRACT.md), [admin UI grammar](../ui-skills.md) | Feature documents refer to shared primitives instead of restating geometry, scroll locks or sorting |
| Admin messages and recipient-specific inbox | [Notification](ADMIN-NOTIFICATION-CONTRACT.md) | [Activity/Publication](ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md) owns audit, Undo and Commit state—not message delivery |
| Working, LIVE, Commit, Restore and Revert | [Activity/Publication](ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md) | Publication protects history recovery roots; [Media](MEDIA.md) owns reference eligibility and physical deletion |
| Media ingest, reference discovery and physical cleanup | [Media](MEDIA.md) | [Public](PUBLIC-IMPLEMENTATION-CONTRACT.md) defines user-facing eligibility/presentation; [Rights](PUBLIC-RIGHTS-AND-PRIVACY.md) owns incomplete rights/privacy requirements |
| Public navigation, Home, Gallery, Journal, SEO and viewer | [Public](PUBLIC-IMPLEMENTATION-CONTRACT.md) | Descriptive source discrepancies do not automatically alter accepted rendering rules |
| Matomo tracking/reporting, event data and failure isolation | [Analytics](ANALYTICS.md) | [Public integration](PUBLIC-IMPLEMENTATION-CONTRACT.md#analytics-boundary) links here; [Rights](PUBLIC-RIGHTS-AND-PRIVACY.md#d-matomo-consent-and-real-privacy-data) owns deferred consent |
| Static Dashboard announcements/changelog and release gates | [Release](RELEASE.md) | Code-owned Markdown is not a per-user Notification record |
| Test layers/discovery/safe isolated DB and cutover | [Testing](TESTING.md), [Migration invariants](MIGRATION-INVARIANTS.md), [Migration plan](MIGRATION-PLAN.md), [Release](RELEASE.md), [platform boundary](SERVER-OPERATIONS-BASELINE.md) | [Completion matrix](architecture/completion-criteria.md) records evidence, not a second test/release contract |

**Known unresolved norm/source differences:** [Gallery first-image-eager](PUBLIC-IMPLEMENTATION-CONTRACT.md#gallery-delivery-and-loading) is the normative target; inspected `dev@11f7e101` Blade still marks *two* images eager. Editor-valid external HTTPS images may violate strict [Public CSP `img-src`](architecture/public-security-privacy-boundaries.md#4-rich-text-url-validierung-vs-csp-ladefreigabe). Invalid *enabled* Matomo URL is distinct from an analytics network outage and has a [source-identified Contact-response risk](architecture/public-security-privacy-boundaries.md#7-fachlicher-source-abschluss-public-read-csp-und-auslieferungsgrenzen). These require product/test decisions, **not** silent documentation redefinition.

[Normative consolidation and derived-artifact classification](architecture/completion-criteria.md#normative-konsolidierung-konfliktstand-und-artefaktentscheidung) records the reconciliation, not a second normative contract.

## Current application contracts

These describe the durable current application architecture and behavior. Browser/product milestone acceptance, release-candidate promotion and environment-specific evidence are tracked separately through the current review/release workflow:

- [PROJECT-CHARTER.md](PROJECT-CHARTER.md) — product scope and public/admin principles
- [ARCHITECTURE.md](ARCHITECTURE.md) — application boundaries, typed site structure and ownership
- [DATA-MODEL.md](DATA-MODEL.md) — durable persistence/domain relationships
- [TESTING.md](TESTING.md) — test layers, placement, discovery, naming, database safety and browser-testing direction
- [PUBLIC-IMPLEMENTATION-CONTRACT.md](PUBLIC-IMPLEMENTATION-CONTRACT.md) — public routing/publication/Home/Journal/media behavior
- [MEDIA.md](MEDIA.md) — media ingest/references, whole-site Storage allowance, capacity/distribution UI and guarded/reclaimable storage behavior
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

## Admin workflow skills

Repository-root workflow documents are intentionally kept close to `AGENTS.md` because they govern coding/review behavior rather than public architecture:

- [AGENTS.md](../AGENTS.md) — branch/orchestration/worker/reconciliation contract and central technology rules
- [ui-skills.md](../ui-skills.md) — admin-only UI grammar for heading/action rows, metrics, filters, selection, tables, grids, DnD, dialogs and browser acceptance
- [Handoff skill](../.agents/skills/handoff/SKILL.md) — concise current-branch continuation and implementation-report workflow, respecting direct-`dev` / `docs` / `testing` and no CI
- [followup-skill.md](../followup-skill.md) — older extended context checklist, retained pending detailed historical workflow cleanup
- [ADMIN-BROWSER-WORKFLOW.md](ADMIN-BROWSER-WORKFLOW.md) — use when the user is doing live browser acceptance and may intentionally choose direct work on `dev` rather than worker-only orchestration

The active collaboration mode is part of the handoff state. A continuation chat should not silently replace a successful direct browser-repair loop with worker delegation, or vice versa.

## Live work and acceptance status

GitHub Issues and the current browser/orchestration review are the source of truth for **unfinished work, browser acceptance and current blockers**.

A source-reviewed or technically running reconciliation candidate is not automatically product accepted. A durable contract may be implemented on a temporary browser branch or directly on explicitly authorized `dev` before it reaches `main`; documentation should describe the intended/current contract without pretending that transient browser acceptance is complete.

Browser feedback from the exact current candidate overrides stale acceptance wording. Once an exact `dev` candidate is browser/product accepted, stale issue history must not be treated as proof that already-resolved UI defects still exist; update/close the affected acceptance issues and promote that exact candidate through the `main`/release gates. Temporary worker branches, candidate SHAs and local container state belong in the current continuation handoff, not in timeless architecture docs.

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
- exact transient browser-candidate SHAs/ports belong in continuation prompts, not architecture docs, except where a temporary evidence record is explicitly required;
- do not turn closed issue/PR numbers into permanent architecture dependencies;
- migration evidence may describe legacy names, but runtime docs use current domain language: **Gallery**, **Site Node**, **Journal**, **Custom Page**, **Navigation Node**, **Storage** and reusable **Contact component**;
- database/model names may retain historical persistence terminology where renaming adds migration risk; document that boundary explicitly rather than exposing the old name as product language;
- distinguish `MediaAsset` being referenced from it being publicly deliverable;
- keep the central Rich Text/media stack singular rather than documenting editor-specific parallel implementations;
- shared admin geometry belongs to shared tokens/primitives; do not document page-local compensations as architecture;
- security/account-recovery details belong in `ADMIN-AUTHENTICATION.md`; concrete SMTP topology/credentials remain platform-owned;
- never include secret values, production dumps, private media or access tokens.
