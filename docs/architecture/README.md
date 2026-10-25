# Architecture evidence and investigation index

Start with the **[system architecture](../ARCHITECTURE.md)**. It explains the running application as a single design: ownership, major domains, editing/publication, public reads, media, recovery and trust boundaries. This directory contains the **underlying focused traces, source inventories and verification limits**—not another independent architecture specification.

The useful entry point depends on the question. The historical generator figures further below have source-specific provenance; many predate the latest `dev` changes.

## Understand the main behaviors

| Engineering question | Source-based investigation |
| --- | --- |
| How does an editor change become publicly visible? | [Publication / committed reads](publication.md), [editorial mutation paths](editorial-mutation-paths.md), [public media eligibility and file lifetime](media-reference-and-cleanup.md) |
| What happens when someone undoes, resets, restores or reverts while state changes? | [Activity and Undo](activity-undo-lifecycle.md), [working operations and lock ordering](publication-working-operations.md), [publication call sites](admin-publication-calls.md) |
| How do Artwork/Gallery and Journal/Exhibition produce different public behavior? | [Artwork publication](artwork-gallery-publication.md), [Journal lifecycle](journal-content-lifecycle.md), [Exhibition Map](exhibition-map.md) |
| How are Home, Custom Page, Rich Text and nested components interpreted? | [Structured component persistence/rendering](structured-components.md), [Admin mutation surfaces](ui-surfaces.md), [cross-feature impact map](cross-feature-compositions.md) |
| How does a public page arrive and remain correct during navigation? | [Public Livewire/page lifecycle](public-spa-lifecycle.md), [runtime routes/middleware](runtime-entrypoints.md), [security, media and privacy boundaries](public-security-privacy-boundaries.md) |
| What are the build, database and external runtime boundaries? | [Build and runtime](application-runtime-boundaries.md), [schema evolution and cutover](schema-evolution.md), [import/dependency boundary findings](import-boundaries.md) |

These are **explanations of focused systems**. A dependency in an import graph, an ORM relation or a static source trace does not automatically prove a runtime call, a physical database foreign key, a browser behavior or a completed test.

## Understand the code and stored data

| Reference | Use it for | Evidence caveat |
| --- | --- | --- |
| [Application inventory](inventory.md) and [PHP source audit](php-source-audit.md) | Finding modules/types and the extraction boundary | Old source snapshots; use fresh code for a current change |
| [Class/import diagram catalog](class-import-diagrams.md) | Searching **structural** references or candidate cycles | Not a behavioral architecture and not proof of reachable code |
| [Eloquent relationship catalog](eloquent-relations.md) | Exploring declared ORM relationships | Does not establish deployed PostgreSQL keys |
| [Source-derived lifecycles](lifecycles.md) | Guards and transitions for specific entity families | Static extraction; review real domain methods |
| [UI surfaces](ui-surfaces.md) | Finding Filament/Livewire components, actions and their domain targets | Old snapshots and source-discovered declarations may not match dynamic runtime |
| [Architecture coverage and evidence map](completion-criteria.md) | Determining which areas have source evidence, which checks actually ran and what is still open | Historical evidence and cutover requirements must not be relabeled as current |

For exact persistent domain meaning, [data model](../DATA-MODEL.md) and the relevant product/feature contract own current requirements; these catalogs are reference material.

## Generated and historical material

The [generated directory](generated/) contains machine-extracted route, class/import, model-relation, lifecycle and UI artifacts. Many were built and checked against **older `docs` source**, notably `docs@8c9bd0a0` / `docs@317d255e` during 19 October 2026. The generator version and reproducibility limits are source-pinned in the individual [source audit](php-source-audit.md) and [compact evidence map](completion-criteria.md). Removed dated run logs remain in Git history; do not treat them as current implementation evidence.

**Current source checkout:** `dev@0200407133ce4696817271d2141fceaca82f8aff` was imported into `docs` with the two-parent commit `docs@a9ac270a` on 25 October 2026. This updated the application code used by the Docs branch. It did **not** regenerate the historic inventories, validate their dynamic behavior or reconcile every contract with the new code.

The inventory tools live in [`scripts/`](../../scripts/); their reproducibility and input/source limits are described in the [technical evidence matrix](completion-criteria.md). The PostgreSQL catalog exporter requires an **independently verified, disposable migrated database**; no production, protected Validation or persistent Preview database is a permissible test target. No physical schema export or full entity-relationship diagram is claimed here without real evidence.

## How to extend this section

Add a new study only when the existing system design and focused references cannot explain an actual cross-boundary behavior, failure mode or architecture decision. Link it from the **engineering question** it answers; keep one contract owner for any stable rule. Remove or consolidate stale studies after transferring their unique observations into the owning contract or historical evidence, and after verifying inbound references.

