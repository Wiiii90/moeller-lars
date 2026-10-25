# Architecture coverage and verification map

This is the **short entry for architectural completeness and evidence**, not a second feature specification or an unbounded task backlog. Application design is owned by [system architecture](../ARCHITECTURE.md); accepted behavior by [product contracts](../README.md#normative-ownership-and-conflict-resolution); focused traces and generated references by the [architecture index](README.md).

**Evidence boundary:** This is a current **navigation and verification** guide, not an old-run inventory. Source-versioned audit drafts and detailed test inventories were removed from the working tree; their original content remains recoverable in Git history (e.g. prior documentation commit `2404b774`). An old reported defect or test cannot be assumed current or executed.

## Warum die vorhandene Menge allein nicht genügt

Architecture completeness is about explaining what crosses a boundary and what protects it: entrypoints, authoritative state, publication/preview visibility, persistence, errors/no-ops, permissions, locks, recovery and consumer behavior. File counts, class-import diagrams, ORM relationship extraction and existing assertions are **different evidence types**. They do not establish real execution, PostgreSQL foreign keys, UI behavior or user acceptance. The original extraction grouped nine explanatory diagrams and 34 source-reference artifacts; they remain pinned to their generator source, not refreshed test evidence.

## Verbindliche fachliche Abdeckungsmatrix

The table tells an agent **where to begin and what evidence is insufficient**. It is not a claim that each historical defect remains reproducible today.

| Architecture boundary | Primary explanatory owner | Verification beyond static source |
| --- | --- | --- |
| Runtime layers, HTTP/Filament entrypoints, authentication | [System runtime](application-runtime-boundaries.md), [HTTP/route trace](runtime-entrypoints.md), [authentication](../admin/authentication.md) | Effective routes, middleware, access denial and Preview/Public differences in an isolated application |
| Admin workspace, controls, dialogs, notifications | [Admin UI](../admin/ui.md), [controls](../admin/controls.md), [dialogs](../admin/dialogs.md), [notifications](../admin/notifications.md) | Native changes, no-op/validation, durable audit and Notification count; actual responsive/browser acceptance |
| Home, Custom Page, structured children and Rich Text | [Structured components](structured-components.md), [editorial mutations](editorial-mutation-paths.md), [Rich Text](../content/rich-text.md) | Child/parent identity, editing under reorder, media reference symmetry and published rendering |
| Journal, Blog, Exhibitions and Map | [Journal lifecycle](journal-content-lifecycle.md), [Exhibition Map](exhibition-map.md), [Exhibition editorial](../content/exhibitions.md) | Active/inactive template publication, dates, media/Map and real editor/display behavior |
| Gallery/Artwork, ordering, eligibility and Viewer | [Artwork publication](artwork-gallery-publication.md), [readiness comparisons](import-boundaries.md), [Public behavior](../public/site.md) | One consistent readiness decision across operations, valid primary media, viewer keyboard/navigation and concurrent change cases |
| Working, LIVE, Activity, Undo, Commit, Restore, Revert | [Publication](publication.md), [recovery/lock operations](publication-working-operations.md), [Activity](activity-undo-lifecycle.md) | Two-session ordering, confirmed-target rechecks, true no-op, rollback and history/media retention |
| Storage, media identity, usages and cleanup | [Media contract](../media.md), [reference/cleanup trace](media-reference-and-cleanup.md) | All reader/writer reference types, byte/variant integrity, retry, queue and historical protection |
| Public SPA, routing, CSP, SEO, privacy and analytics | [Public contract](../public/site.md), [SPA](public-spa-lifecycle.md), [security/privacy](public-security-privacy-boundaries.md) | GET/HEAD/Preview boundaries, actual CSP/network/browser enforcement, consent status and delivery/ingress behavior |
| PostgreSQL schema, Legacy migration and cutover | [Data model](../DATA-MODEL.md), [schema evolution](schema-evolution.md), [migration invariants](../migration/invariants.md) | Disposable migrated database, actual catalog constraints, version-specific restore and cutover rehearsal |
| Build, deployment interface and recovery | [Application runtime](application-runtime-boundaries.md), [Release contract](../operations/release.md), [server baseline](../operations/server-boundary.md) | Built image/runtime behavior and separately authorized infrastructure, backup/restore and release checks |

For an old source-based negative case, inspect the named canonical owner and **recheck the current implementation**; original October audit drafts are in Git history, not live task documents. Do not open a fresh repair task from a historical row alone.

## Testabdeckung: eine dev-Testsuite, authored Assertions

The [Testing contract](../operations/testing.md) owns the actual discovery, execution and database isolation requirements. An authored or source-reviewed assertion does not prove that the test ran or that a later build still passes. User-facing browser acceptance and release qualification are independent evidence. Historical test-inventory snapshots are in Git history, not today's test report.

A new verification entry must give the checked commit, command/environment where applicable, targeted behavior, outcome and gaps. Never transplant old test totals or branch state into this table.

## Endlicher Restlücken-Check: Quellenabschluss gegen Runtime-/Produkt-Backlog

For a concrete task, record only:
1. **Requirement/owner:** precisely what observable or persistent invariant matters and where it is defined.
2. **Source evidence:** exact inspected revision and real entrypoint/consumer, including failure and no-op.
3. **Missing evidence:** the smallest still-required isolated test, browser interaction, database query, concurrency case or external runtime check.
4. **Disposition:** accepted, still reproducible, superseded, or unverified. A suspected issue is never silently promoted to an implementation order.

Older gap matrices are preserved in Git history, **not** retained as a permanent bug tracker. Actual unfinished implementation lives in its responsible current task/issue owner.

## Normative Konsolidierung, Konfliktstand und Artefaktentscheidung

Keep exactly one normative owner for each durable concern. The [project documentation index](../README.md#normative-ownership-and-conflict-resolution) is the authority map; architecture traces explain **how** multiple owners compose without redefining their contracts.

The original source review classified nine explanatory A diagrams and 34 source-inventory B artifacts; these counts apply only to their historical generator baseline. The [architecture index](README.md#generated-and-historical-material) is the reader entry. These are intentionally source-pinned, **not** today's regenerated diagrams. A later rerun must report its source commit and reproducibility separately.

## Abnahmekriterien für das Ende der *inhaltlichen* Arbeit

An architecture explanation is ready when a reader can:
- identify real upstream caller, canonical service/state and downstream consumers without guessing from a class name;
- follow persisted Working/Committed/history state, No-op, validation, publication, failure and recovery paths;
- distinguish public, editor Preview and authenticated Admin trust and media boundaries;
- identify the relevant schema/migration, safe verification method and release/host owner;
- tell **source inspected** apart from **test executed**, **browser verified**, **user accepted** and **deployed**.

Do not extend diagrams or invent a new owner for an unresolved product defect. Accepted requirements live in their single normative contracts; prior audit criteria remain in Git history.

## Bekannte bereits belegte Lücken sind keine leeren Diagrammkästen

The October source review identified specific possible mismatch classes (e.g., stale editor identity, media-reference coverage, publication lock timing and viewer/media readiness). Their original source notes must be re-evaluated against the current implementation before being called bugs. An explanatory diagram cannot itself close them.
