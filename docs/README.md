# Project documentation

This is the human entry to the **Lars Möller website application**. The public site, artist administration and publication system are a single Laravel application; `server-platform` supplies deployment/host operations. Start with the system architecture, then follow the question or change you are investigating. The goal is **comprehensive but layered explanation**, not a flat library of unrelated source excerpts.

## Choose a starting point

| You want to understand… | Start here | Follow into |
| --- | --- | --- |
| **How the entire application fits together** | [System architecture](ARCHITECTURE.md) | [Cross-feature source traces](architecture/README.md) |
| **What the website and artist administration must do** | [Project charter](PROJECT-CHARTER.md) | [Public presentation](PUBLIC-IMPLEMENTATION-CONTRACT.md), [Admin interaction design](ADMIN-UI.md) |
| **How editing, publishing, recovery and permissions work** | [System edit-to-public model](ARCHITECTURE.md#3-the-central-transaction-edit--audit--publication--read) | [Activity / Publication](ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md), [Media](MEDIA.md), [History/concurrency evidence](architecture/publication-working-operations.md) |
| **Where data and media live** | [Data model](DATA-MODEL.md) | [Media lifecycle](MEDIA.md), [component structure](architecture/structured-components.md), [schema evidence](architecture/schema-evolution.md) |
| **How the public pages and shared frontend behave** | [Public implementation](PUBLIC-IMPLEMENTATION-CONTRACT.md) | [Exhibition editorial](EXHIBITION-EDITORIAL-CONTRACT.md), [Journal lifecycle](architecture/journal-content-lifecycle.md), [document navigation](architecture/public-spa-lifecycle.md) |
| **How Admin controls, dialogs, tables and editors are shared** | [Admin UI design and interaction](ADMIN-UI.md) | [Controls](ADMIN-CONTROL-CONTRACT.md), [Dialogs](ADMIN-DIALOG-CONTRACT.md), [Tables](ADMIN-TABLE-CONTRACT.md), [Rich Text](EDITORIAL-RICH-TEXT.md) |
| **What was actually verified or still requires a check** | [Test architecture](TESTING.md) | [Evidence and gaps](architecture/completion-criteria.md), [migration/cutover](MIGRATION-PLAN.md) |
| **How agents/workers should collaborate** | [Worker protocol](../.agents/README.md) | [Docs coordination](../.agents/areas/docs.md), [Docs procedure](../.agents/procedures/documentation.md) |

## Architecture, reference and historical evidence

[ARCHITECTURE.md](ARCHITECTURE.md) is the **current system design map**: scope, connected components, owner decisions, principal operational sequences, failure/rollback and trust boundaries. It is not a second copy of each feature contract. The [architecture evidence index](architecture/README.md) arranges deeper analyses by engineering question. Some generated relationship/route/source inventories are **historical artifacts** with older pinned source references; they are neither runtime traces nor evidence of current test success.

Before changing a shared service or UI primitive, follow its consumers across Admin, Public, Preview, publication, media and History. The [cross-feature impact reference](architecture/cross-feature-compositions.md) shows known seams. Document accepted behavior in the **owning feature contract**, not in each worker's short-lived notes.

## Current source and verification

**Repository synchronization (25 October 2026):** `dev@0200407133ce4696817271d2141fceaca82f8aff` was imported into `docs` via the genuine two-parent merge `a9ac270a0553f822514dbb2e22d25efd3402bae3`. The Docs branch source, tests and runtime files matched that Dev snapshot. This is **not** a claim that all feature documents were updated or that tests were run. The last selective semantic documentation review preceding that merge covered `dev@35163591`. The remaining review of later Rich Text, Gallery, video and public image/layout changes is tracked in the active [Docs worker task state](../.agents/areas/docs.md).

Historical documentation tool runs are reported with their actual input and date in [evidence](architecture/completion-criteria.md); old graph statistics must not be relabeled as current. Distinguish **source inspection, generated artifact, authored tests, actually executed tests, browser review and artist acceptance**.

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

Additional active Admin contracts remain in the repository—authentication, localization, password policy, performance/profiling, Notification transport, responsive shell/metrics/toolbar/tables/stages and browser-review method. Their content must be **reviewed for unique ownership and merged where redundant**, not arbitrarily deleted. The Admin collection is under explicit Docs Worker 1 consolidation.

## Branch ownership for documentation

- **`dev`** is the implementation integration branch; it changes continuously and is authoritative for current application source.
- **`docs`** is the reviewing/consolidating documentation branch. It regularly imports current `dev` into its source checkout and updates documentation-worthy facts *in place*. A Git merge proves code incorporation, **not** documentation correctness.
- **`testing`** is actively curating the intended test suite; it imports newer `dev` changes while preserving its own test architecture. The live worker owns those edits.
- **`refactor`** is a separately authorized branch initially based on `dev@02004071`, for behavior-preserving code cleanup. Refactor changes return to `dev` only after reviewed integration.
- **`main`** is for separately authorized release/promotion.

The active working branch/permissions, coordination roster and scoped safety rules are described in [AGENTS.md](../AGENTS.md) and [agent protocol](../.agents/README.md). Docs writes do not authorize CI, release, protected Validation or Production changes.

## Migration and cutover evidence

The following are deliberately **cutover-specific**, not the permanent application architecture: [migration plan](MIGRATION-PLAN.md), [migration invariants](MIGRATION-INVARIANTS.md), [source inventory](SOURCE-INVENTORY.md), [legacy public behavior](LEGACY-PUBLIC-CONTRACT.md). Keep unique migration/recovery facts until legacy retirement is actually approved, then archive or consolidate them in a deliberate cleanup.

Retained architecture decisions: [application stack](adr/ADR-0001-APPLICATION-STACK.md) and [hosting cost baseline](adr/ADR-0002-HOSTING-COST-BASELINE.md).

## Agent work areas and living knowledge

A worker is **registered only on actual activation** in its shared [Dev](../.agents/areas/dev.md), [Docs](../.agents/areas/docs.md), [Testing](../.agents/areas/testing.md) or [Refactor](../.agents/areas/refactor.md) coordination file. Its own numbered file primarily tracks **user assignments and progress**. When it retires, consolidate enduring results into this repository's owning documents and remove its roster entry and temporary file together. Separate handoff diaries are not part of the workflow.

## Documentation rules

A document must earn its place by answering a specific question for a reader. Explain actual system connections, choices and behavior before enumerating source details; use substantial component, sequence, state or data diagrams when they reveal nontrivial cross-boundary relationships. Never turn one function into a decorative three-box graph. Consider the **entire purpose and structure** of an existing document before revising it.

Prefer consolidating and removing superseded sections to appending new date/commit sections. Keep technical evidence versioned but distinguish it visually and logically from current design. Changes to shared functionality must consider all consumers; source review alone is not live browser acceptance. Avoid undocumented new frameworks or parallel implementations.

## Repository policies

[Security](../SECURITY.md), [contribution](../CONTRIBUTING.md) and [pull-request template](../.github/pull_request_template.md) are repository policies, not additional design authorities. Server topology and secrets belong to the independently maintained `server-platform` project.
