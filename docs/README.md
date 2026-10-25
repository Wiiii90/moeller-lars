# Project documentation

This is the human entry to the **Lars Möller website application**. The public site, artist administration and publication system are a single Laravel application; `server-platform` supplies deployment/host operations. Start with the system architecture, then follow the question or change you are investigating. The goal is **comprehensive but layered explanation**, not a flat library of unrelated source excerpts.

## Fast path for agents and maintainers

Start from **the current task**, not a full-document reading marathon.

1. **Establish scope:** current branch and exact `dev` source revision; the user's most recent accepted behavior; the entrypoint/component/domain actually affected. Use the branch's own root `AGENTS.md` and worker registration where applicable.
2. **Locate one canonical owner:** use the table below for the relevant product contract, then inspect its concrete implementation and consumers. UI adapters, domain services, publication, database migrations and server infrastructure have different owners; a similarly named component is not automatically authoritative.
3. **Make a small, coherent change:** preserve unchanged cross-feature invariants; prefer the existing central primitive or service over local duplication. No task-log prose, source-SHA chronology or copied test report in a normative contract.
4. **Verify the right claim:** distinguish inspected code, authored assertions, an executed isolated test, a rebuilt browser candidate, user acceptance and release qualification. Record exact evidence and outstanding checks where it belongs.
5. **Close the loop:** update the **existing** affected contract, reconcile links/anchors and remove proven obsolete text. Record still-open tasks only in the active worker/task owner, never in multiple knowledge files.

For shared Admin work, use the canonical [UI](ADMIN-UI.md), [Controls](ADMIN-CONTROL-CONTRACT.md), [Dialogs](ADMIN-DIALOG-CONTRACT.md), [Tables](ADMIN-TABLE-CONTRACT.md), [Responsive](ADMIN-RESPONSIVE-CONTRACT.md), [Notifications](ADMIN-NOTIFICATION-CONTRACT.md) and [Browser review](ADMIN-BROWSER-WORKFLOW.md) owners directly. Do not add a second overview or root skill.

## Choose a starting point

| You want to understand… | Start here | Follow into |
| --- | --- | --- |
| **How the entire application fits together** | [System architecture](ARCHITECTURE.md) | [Cross-feature source traces](architecture/README.md) |
| **What the website and artist administration must do** | [Project charter](PROJECT-CHARTER.md) | [Public presentation](PUBLIC-IMPLEMENTATION-CONTRACT.md), [Admin interaction design](ADMIN-UI.md) |
| **How editing, publishing, recovery and permissions work** | [System edit-to-public model](ARCHITECTURE.md#3-the-central-transaction-edit--audit--publication--read) | [Activity / Publication](ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md), [Media](MEDIA.md), [History/concurrency evidence](architecture/publication-working-operations.md) |
| **Where data and media live** | [Data model](DATA-MODEL.md) | [Media lifecycle](MEDIA.md), [component structure](architecture/structured-components.md), [schema evidence](architecture/schema-evolution.md) |
| **How the public pages and shared frontend behave** | [Public implementation](PUBLIC-IMPLEMENTATION-CONTRACT.md) | [Exhibition editorial](EXHIBITION-EDITORIAL-CONTRACT.md), [Journal lifecycle](architecture/journal-content-lifecycle.md), [document navigation](architecture/public-spa-lifecycle.md) |
| **How Admin controls, dialogs, tables and editors are shared** | [Admin UI design and interaction](ADMIN-UI.md) | [Controls](ADMIN-CONTROL-CONTRACT.md), [Dialogs](ADMIN-DIALOG-CONTRACT.md), [Tables](ADMIN-TABLE-CONTRACT.md), [Rich Text](EDITORIAL-RICH-TEXT.md) |
| **What was actually verified or still requires a check** | [Test architecture](TESTING.md) | [Concise architecture evidence map](architecture/completion-criteria.md), [migration/cutover](MIGRATION-PLAN.md) |

## Architecture, reference and historical evidence

[ARCHITECTURE.md](ARCHITECTURE.md) is the **current system design map**: scope, connected components, owner decisions, principal operational sequences, failure/rollback and trust boundaries. It is not a second copy of each feature contract. The [architecture evidence index](architecture/README.md) arranges deeper analyses by engineering question. Some generated relationship/route/source inventories are **historical artifacts** with older pinned source references; they are neither runtime traces nor evidence of current test success. The original detailed October source reviews are retained as a separate [historical audit](architecture/source-audit-archive-2026-10.md), not in the daily-reading evidence map.

Before changing a shared service or UI primitive, follow its consumers across Admin, Public, Preview, publication, media and History. The [cross-feature impact reference](architecture/cross-feature-compositions.md) shows known seams. Document accepted behavior in the **owning feature contract**, not in each worker's short-lived notes.

## Evidence and freshness

The implementation source on current `dev` is the baseline for documenting application behavior and currently integrated tests. Detailed source audits and generated diagrams may be pinned to an **older** commit; their explicit provenance remains in [the evidence index](architecture/README.md) and [verification matrix](architecture/completion-criteria.md). Do not mistake authored tests for executed checks or browser acceptance. Integrating newer source into `docs` is not the same as reviewing and correcting every affected document.

## Normative ownership and conflict resolution

There is one authoritative home for each requirement. A feature may participate in several system flows without becoming a second owner of shared behavior.

| Authority | Owns |
| --- | --- |
| [Public implementation](PUBLIC-IMPLEMENTATION-CONTRACT.md) and [Exhibition editorial](EXHIBITION-EDITORIAL-CONTRACT.md) | Public routing, viewer/navigation, Home/Gallery/Journal presentation; Exhibition-only venue, organizer, map and opening rules |
| [Admin design](ADMIN-UI.md), [Controls](ADMIN-CONTROL-CONTRACT.md), [Dialogs](ADMIN-DIALOG-CONTRACT.md), [Tables](ADMIN-TABLE-CONTRACT.md), [Responsive](ADMIN-RESPONSIVE-CONTRACT.md) | Shared admin design grammar and its canonical component implementations; page-specific forms should reuse them |
| [Rich Text](EDITORIAL-RICH-TEXT.md) | Editor/render/picker, safe formatting and reusable media references across Home, Journal, Custom Page |
| [Publication/Activity](ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md), [Notification](ADMIN-NOTIFICATION-CONTRACT.md) | Working versus committed, versioning, Undo, audit versus actor-scoped messaging |
| [Media](MEDIA.md), [Rights/privacy](PUBLIC-RIGHTS-AND-PRIVACY.md), [Analytics](ANALYTICS.md) | Asset identity, ingest, eligibility, file lifetime, rights/consent versus tracking/reporting |
| [Data model](DATA-MODEL.md), [Migration](MIGRATION-INVARIANTS.md) | Meaning of persistent identities/relations and forward-compatible migration |
| [Testing](TESTING.md), [Release](RELEASE.md), [server operations boundary](SERVER-OPERATIONS-BASELINE.md) | Safe validation, qualification and application/platform responsibilities |

Additional specialized Admin contracts cover authentication, localization, password policy, performance, notifications and browser review. Their unique requirements remain valid until checked against current owners and consolidated; no independent page contract may redefine a shared primitive.

## Migration and cutover evidence

The following are deliberately **cutover-specific**, not the permanent application architecture: [migration plan](MIGRATION-PLAN.md), [migration invariants](MIGRATION-INVARIANTS.md), [source inventory](SOURCE-INVENTORY.md), [legacy public behavior](LEGACY-PUBLIC-CONTRACT.md). Keep unique migration/recovery facts until legacy retirement is actually approved, then archive or consolidate them in a deliberate cleanup.

Retained architecture decisions: [application stack](adr/ADR-0001-APPLICATION-STACK.md) and [hosting cost baseline](adr/ADR-0002-HOSTING-COST-BASELINE.md).

## Maintaining documentation

A document must earn its place by answering a specific question for a reader. Explain actual system connections, choices and behavior before enumerating source details; use substantial component, sequence, state or data diagrams when they reveal nontrivial cross-boundary relationships. Never turn one function into a decorative three-box graph. Consider the **entire purpose and structure** of an existing document before revising it.

Consolidate superseded sections instead of appending source-diff histories. For structural edits, use the [documentation verification gate](../.agents/procedures/documentation.md#documentation-verification-gate), including the native Markdown destination/fragment checker when the isolated checkout is available. Identify and preserve each unique accepted constraint before removing a document. Current design, historical evidence and temporary tasks have distinct homes; only durable product, architecture and operating information belongs here. Documentation maintenance follows the owning feature and architecture contracts; temporary worker status does not belong in the project manual.

## Repository policies

[Security](../SECURITY.md), [contribution](../CONTRIBUTING.md) and [pull-request template](../.github/pull_request_template.md) are repository policies, not additional design authorities. Server topology and secrets belong to the independently maintained `server-platform` project.
