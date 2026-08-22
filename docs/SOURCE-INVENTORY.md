# Migration source inventory

This file records external/legacy sources still relevant to migration and cutover evidence. It is not an application architecture map.

## `larsmoeller` — legacy public-site source

Purpose: read-only evidence for public content, legacy ordering/presentation, migration provenance and browser comparison.

Relevant reviewed inputs include:

- legacy artwork records and category/table mapping;
- public artwork metadata/order;
- original media and thumbnails used for source reconciliation;
- `txt/vita.txt` and the public Vita portrait;
- legacy header/navigation/public presentation evidence;
- intended Contact form fields/outcome;
- public metadata/sitemap/robots evidence.

The legacy implementation is not reused as target runtime code. Authentication, sessions, SQL helpers, upload logic, credentials, debug behavior and deployment mechanics remain outside the new application.

### Vita source accounting

The reviewed Vita textual source contains exactly **31 source rows**.

Approved migration accounting remains:

- **2 Biography/CV source rows** retained as canonical structured migration/editorial data;
- **29 Exhibition rows** in the Exhibition domain;
- no duplication of Exhibition rows as public CV entries;
- the portrait relationship/provenance remains reconciled by source identity, byte size and SHA-256.

The public placement of migrated Vita/CV content is now a **Custom Page**, not a dedicated runtime Site Node type.

## Legacy workshop

The legacy `/workshop` subtree/database is outside the rebuilt artist-site content target.

It is preserved only as required by the legacy rollback/retirement boundary until the platform explicitly retires it. It must not create target Galleries, Site Nodes, content records or media imports unless a later separately approved scope says otherwise.

## `glassygallery` — historical exploration source

`glassygallery` informed early exploration of media metadata, roles, structured data and deployment concerns. It is not a source code or architecture dependency of the current application.

Do not copy its general website-builder UI, API/auth assumptions, placeholder analytics or unfinished admin implementation into `moeller-lars`.

No active documentation should treat `glassygallery` as an alternate implementation path.

## `moeller-lars` — canonical application

This repository is the canonical target application:

- Laravel/PHP application source;
- PostgreSQL migrations/model;
- public and admin presentation;
- domain/editorial services;
- tests;
- migration/reconciliation tooling;
- Docker/release contract;
- CI and immutable GHCR image publication.

Current site placement is modeled through typed Site Nodes: Home, Gallery, Journal, Custom Page and Navigation Node.

No legacy source tree, production database dump, authoritative production media corpus, Matomo token, mail credential or production secret belongs here.

## `server-platform` — authoritative runtime/operations source

[`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform) owns mutable infrastructure/runtime evidence and operational implementation:

- Production/Validation placement;
- ingress/TLS/networking;
- secrets;
- databases/runtime services;
- persistent volumes;
- backups/restores;
- monitoring;
- deployment/rollback;
- host lifecycle and legacy retirement.

`moeller-lars` should link to that contract instead of copying mutable host facts into application docs.

## Retention of migration evidence

`SOURCE-INVENTORY.md`, [LEGACY-PUBLIC-CONTRACT.md](LEGACY-PUBLIC-CONTRACT.md) and [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md) remain active only while migration/cutover/legacy-retirement evidence is required.

After explicit legacy retirement, review them in a dedicated cleanup and archive/remove details that no longer protect an operational or legal/product requirement.
