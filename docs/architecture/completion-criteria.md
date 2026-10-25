# Architecture coverage and verification map

This is the **short entry for architectural completeness and evidence**, not a second feature specification or an unbounded task backlog. Application design is owned by [system architecture](../ARCHITECTURE.md); accepted behavior by [product contracts](../README.md#normative-ownership-and-conflict-resolution); focused traces and generated references by the [architecture index](README.md).

**Evidence boundary:** The original detailed review was pinned to several October 2026 source revisions. Its 50 KB of matrices, exact test names, generator counts, negative examples and dated technical runs is preserved **without editing its claims** in the [October 2026 audit archive](source-audit-archive-2026-10.md). Do not treat that archive as evidence of the current HEAD or reclassify its authored tests as executed. When source changes, recheck a concrete invariant in the canonical owner and record only new verified evidence.

## Warum die vorhandene Menge allein nicht genügt

Architecture completeness is about explaining what crosses a boundary and what protects it: entrypoints, authoritative state, publication/preview visibility, persistence, errors/no-ops, permissions, locks, recovery and consumer behavior. File counts, class-import diagrams, ORM relationship extraction and existing assertions are **different evidence types**. They do not establish real execution, PostgreSQL foreign keys, UI behavior or user acceptance. Historical A/B/C/D artifact classification and original measurements are in the [archive](source-audit-archive-2026-10.md#warum-die-vorhandene-menge-allein-nicht-genügt).

## Verbindliche fachliche Abdeckungsmatrix

The table tells an agent **where to begin and what evidence is insufficient**. It is not a claim that each historical defect remains reproducible today.

| Architecture boundary | Primary explanatory owner | Verification beyond static source |
| --- | --- | --- |
| Runtime layers, HTTP/Filament entrypoints, authentication | [System runtime](application-runtime-boundaries.md), [HTTP/route trace](runtime-entrypoints.md), [authentication](../ADMIN-AUTHENTICATION.md) | Effective routes, middleware, access denial and Preview/Public differences in an isolated application |
| Admin workspace, controls, dialogs, notifications | [Admin UI](../ADMIN-UI.md), [controls](../ADMIN-CONTROL-CONTRACT.md), [dialogs](../ADMIN-DIALOG-CONTRACT.md), [notifications](../ADMIN-NOTIFICATION-CONTRACT.md) | Native changes, no-op/validation, durable audit and Notification count; actual responsive/browser acceptance |
| Home, Custom Page, structured children and Rich Text | [Structured components](structured-components.md), [editorial mutations](editorial-mutation-paths.md), [Rich Text](../EDITORIAL-RICH-TEXT.md) | Child/parent identity, editing under reorder, media reference symmetry and published rendering |
| Journal, Blog, Exhibitions and Map | [Journal lifecycle](journal-content-lifecycle.md), [Exhibition Map](exhibition-map.md), [Exhibition editorial](../EXHIBITION-EDITORIAL-CONTRACT.md) | Active/inactive template publication, dates, media/Map and real editor/display behavior |
| Gallery/Artwork, ordering, eligibility and Viewer | [Artwork publication](artwork-gallery-publication.md), [readiness comparisons](import-boundaries.md), [Public behavior](../PUBLIC-IMPLEMENTATION-CONTRACT.md) | One consistent readiness decision across operations, valid primary media, viewer keyboard/navigation and concurrent change cases |
| Working, LIVE, Activity, Undo, Commit, Restore, Revert | [Publication](publication.md), [recovery/lock operations](publication-working-operations.md), [Activity](activity-undo-lifecycle.md) | Two-session ordering, confirmed-target rechecks, true no-op, rollback and history/media retention |
| Storage, media identity, usages and cleanup | [Media contract](../MEDIA.md), [reference/cleanup trace](media-reference-and-cleanup.md) | All reader/writer reference types, byte/variant integrity, retry, queue and historical protection |
| Public SPA, routing, CSP, SEO, privacy and analytics | [Public contract](../PUBLIC-IMPLEMENTATION-CONTRACT.md), [SPA](public-spa-lifecycle.md), [security/privacy](public-security-privacy-boundaries.md) | GET/HEAD/Preview boundaries, actual CSP/network/browser enforcement, consent status and delivery/ingress behavior |
| PostgreSQL schema, Legacy migration and cutover | [Data model](../DATA-MODEL.md), [schema evolution](schema-evolution.md), [migration invariants](../MIGRATION-INVARIANTS.md) | Disposable migrated database, actual catalog constraints, version-specific restore and cutover rehearsal |
| Build, deployment interface and recovery | [Application runtime](application-runtime-boundaries.md), [Release contract](../RELEASE.md), [server baseline](../SERVER-OPERATIONS-BASELINE.md) | Built image/runtime behavior and separately authorized infrastructure, backup/restore and release checks |

To investigate a specific historical risk or source-based negative case, use the [complete original matrix](source-audit-archive-2026-10.md#verbindliche-fachliche-abdeckungsmatrix) and **recheck that issue at the currently relevant revision**. Do not open a fresh repair task from a historical row alone.

## Testabdeckung: eine dev-Testsuite, authored Assertions

The [Testing contract](../TESTING.md) owns the actual discovery, execution and database isolation requirements. An authored or source-reviewed assertion does not prove that the test ran or that a later build still passes. User-facing browser acceptance and release qualification are independent evidence. The [historical test inventory](source-audit-archive-2026-10.md#testabdeckung-eine-dev-testsuite-authored-assertions) pins concrete test filenames and the exact original source revision; it is **not** today's live test report.

A new verification entry must give the checked commit, command/environment where applicable, targeted behavior, outcome and gaps. Never transplant old test totals or branch state into this table.

## Endlicher Restlücken-Check: Quellenabschluss gegen Runtime-/Produkt-Backlog

For a concrete task, record only:
1. **Requirement/owner:** precisely what observable or persistent invariant matters and where it is defined.
2. **Source evidence:** exact inspected revision and real entrypoint/consumer, including failure and no-op.
3. **Missing evidence:** the smallest still-required isolated test, browser interaction, database query, concurrency case or external runtime check.
4. **Disposition:** accepted, still reproducible, superseded, or unverified. A suspected issue is never silently promoted to an implementation order.

The dated [ten-area gap matrix](source-audit-archive-2026-10.md#endlicher-restlücken-check-quellenabschluss-gegen-runtime-produkt-backlog) remains historical source evidence, not a permanently accumulating bug tracker. Actual unfinished implementation lives in its responsible current task/issue owner.

## Normative Konsolidierung, Konfliktstand und Artefaktentscheidung

Keep exactly one normative owner for each durable concern. The [project documentation index](../README.md#normative-ownership-and-conflict-resolution) is the authority map; architecture traces explain **how** multiple owners compose without redefining their contracts.

Historical generated inventory/diagram families were classified by their extraction boundary and retained in the [artifact decision matrix](source-audit-archive-2026-10.md#abgeleitete-artefakte-ad-nach-tatsächlicher-prüfung): nine explanatory A diagrams and 34 B reference artifacts at that historical source baseline. The [architecture index](README.md#generated-and-historical-material) is the reader entry. These are intentionally source-pinned, **not** today's regenerated diagrams. A later rerun must report its source commit and reproducibility separately.

## Abnahmekriterien für das Ende der *inhaltlichen* Arbeit

An architecture explanation is ready when a reader can:
- identify real upstream caller, canonical service/state and downstream consumers without guessing from a class name;
- follow persisted Working/Committed/history state, No-op, validation, publication, failure and recovery paths;
- distinguish public, editor Preview and authenticated Admin trust and media boundaries;
- identify the relevant schema/migration, safe verification method and release/host owner;
- tell **source inspected** apart from **test executed**, **browser verified**, **user accepted** and **deployed**.

Do not keep extending architecture diagrams or invent a new owner for an unresolved product defect. Preserve the original detailed [acceptance conditions](source-audit-archive-2026-10.md#abnahmekriterien-für-das-ende-der-inhaltlichen-arbeit) for provenance.

## Bekannte bereits belegte Lücken sind keine leeren Diagrammkästen

The October source review identified specific possible mismatch classes (e.g., stale editor identity, media-reference coverage, publication lock timing and viewer/media readiness). Their [original evidence and status](source-audit-archive-2026-10.md#bekannte-bereits-belegte-lücken-sind-keine-leeren-diagrammkästen) must be re-evaluated against current implementations before being called bugs. An explanatory diagram cannot itself close them.

## Historische Prüfprotokolle und Quellabgleiche

Full dated source checks, native generators, link-check output, environment limits and later reconciliation notes were transferred **verbatim** to the [October 2026 source audit archive](source-audit-archive-2026-10.md#historische-prüfprotokolle-und-quellabgleiche). Their historical status was not upgraded by this document restructure.

### Technischer source-only Abschluss (19.10.2026)

[Original technical report](source-audit-archive-2026-10.md#technischer-source-only-abschluss-19102026).

### Selektiver Quellabgleich und Integrationsvorbereitung (22.10.2026)

[Original revision comparison](source-audit-archive-2026-10.md#selektiver-quellabgleich-und-integrationsvorbereitung-22102026).

### Neuer Quellabgleich (25.10.2026)

[Original 25 October source review](source-audit-archive-2026-10.md#neuer-quellabgleich-25102026).
