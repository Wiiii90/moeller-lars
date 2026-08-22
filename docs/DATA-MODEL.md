# Data model

This document describes the durable application relationships. Database migrations are the source of truth for exact column types, indexes and constraints; this file intentionally avoids duplicating every migration detail.

PostgreSQL is authoritative for editorial state. Canonical uploaded originals are authoritative binary data.

## Conventions

- Ordered editorial records persist an explicit non-negative `position`; database/insertion order is never a presentation fallback.
- Published content must satisfy its domain publication prerequisites.
- Foreign keys are restrictive where deleting a referenced record could lose editorial meaning or media relationships.
- Migration provenance (`legacy_*`, migration batch/timestamp fields) is evidence only and is not a runtime fallback.
- Slugs are normalized public identities. Deliberate public slug changes may create redirects.
- Domain/application naming may differ from an intentionally retained persistence name. In particular, `ArtworkCategory` is the persistence model behind the product concept **Gallery**.

## Site structure

### `site_sections`

`SiteSection` is the persisted public-site/navigation tree projection.

Important persisted concepts:

- `type`
- optional Journal `template`
- title and optional navigation label
- optional public slug
- publication state
- navigation visibility
- explicit position
- optional parent
- optional Gallery persistence reference

Application behavior is defined by `SiteNodeType`, not by raw persistence strings.

Supported runtime types:

- **Home** — singleton, no slug, always published, always represented in navigation, cannot be deleted or nested.
- **Gallery** — public page backed one-to-one by an `ArtworkCategory`; can contain Gallery children and can itself be nested below a Gallery or Navigation Node.
- **Journal** — public page with template `blog` or `exhibitions`; may be nested below a Navigation Node.
- **Custom Page** — public page backed one-to-one by `CustomPageSetting`; may be nested below a Navigation Node.
- **Navigation Node** — navigation-only grouping with no public content URL; can contain child nodes.

Only Home is globally unique by node type. Journal templates are not singleton application types.

### `custom_page_settings`

One-to-one with a Custom Page Site Node.

`blocks` is an ordered JSON list of supported structured components. Current component types include text, list and contact components. Publication validation checks structured content, safe links/rich text and referenced public media.

### `journal_settings`

One-to-one with a Journal Site Node. Stores Journal listing title and introduction independently from individual entries.

## Gallery and artwork

### `artwork_categories`

Persistence model: `ArtworkCategory`.

Application concept: **Gallery**.

A Gallery stores its persistent identity/content data such as name, slug, description, homepage eligibility and migration provenance. It has exactly one matching Gallery `SiteSection`, which owns public placement, hierarchy, publication/navigation state and site order.

A Gallery has many Artworks.

### `artworks`

An Artwork belongs to one Gallery through `artwork_category_id`.

Core editorial concepts include:

- stable slug/title;
- medium/dimensions/description;
- normalized year/date metadata;
- draft/publication state and timestamps;
- explicit Gallery-relative position;
- optional homepage tie-break/feature state;
- migration provenance.

New Artwork drafts append to their Gallery. Reordering persists explicit positions. Moving an Artwork appends it to the destination Gallery and must preserve its media usages.

Publication requires valid public metadata/media and a publishable Gallery. The exact invariant is enforced by the application/domain services rather than inferred from UI state.

### `artwork_media`

Explicit usage relation between Artwork and canonical `MediaAsset` originals.

It stores role, position and an optional usage-specific ALT override. Removing a usage does not implicitly delete the asset. Primary-media replacement updates the relationship while preserving referential integrity.

## Journals

### Blog Journal / `blog_posts`

Every Blog Post belongs to a Journal Site Node whose template is `blog`.

Important concepts:

- slug/title/body/excerpt;
- draft, scheduled, published, unpublished or archived state;
- explicit Journal-relative position;
- optional cover media;
- publication/schedule timestamps;
- migration provenance.

Public visibility is determined from the post lifecycle together with the publication state of its Journal Site Node. Scheduled visibility is evaluated from `scheduled_at`; it does not require a background promotion job.

### Exhibitions Journal / `exhibitions`

Every Exhibition belongs to a Journal Site Node whose template is `exhibitions`.

Important concepts include:

- slug/title;
- draft/publication state and explicit Journal-relative position;
- date text plus optional normalized start/end dates;
- kind, venue, city/country/location text;
- opening information and constrained rich text;
- optional external/directions links;
- migration provenance.

Current/upcoming/past status is derived from normalized dates at read time rather than stored as mutable state.

### `exhibition_media`

Ordered many-to-many usage relation between Exhibitions and `MediaAsset`, including role and optional ALT override. Referenced media cannot be destructively removed.

## Custom/CV/contact content

The old fixed `vita` and `contact` SiteSection types no longer exist in runtime architecture. Migration converts them to normal **Custom Page** nodes with structured components.

Structured historical CV records remain available through `CvEntry`/migration data where required for reconciliation and editorial workflows, but their site placement is not represented by a dedicated Site Node type.

`PublicContentSetting` provides typed site-wide settings scopes:

- `general` — public email visibility, private contact recipient, social links, legal text and favicon reference;
- `contact` — contact-surface state/status configuration;
- `vita` — retained profile/CV support data used by the migrated/custom-page presentation.

These settings are fixed typed records and are not deletable editorial pages.

## Media

### `media_assets`

Canonical original uploaded or migrated asset.

Key durable identity includes generated storage key, original filename, content-derived MIME type, byte size, SHA-256, state, optional dimensions/metadata and editorial ALT/credit/copyright fields.

Available originals are reusable across content. Quarantined/deleted state is explicit. A referenced asset cannot be destructively deleted.

### `media_variants`

Generated derivative of one canonical original. Variants are rebuildable and never authoritative. Missing required public derivatives are explicit integrity/readiness failures, not a reason to serve an arbitrary fallback.

See [MEDIA.md](MEDIA.md) for ingest and storage rules.

## General supporting models

### Redirects

`redirects` stores intentional retired-path mappings for the new application. It is not a blanket compatibility layer for legacy PHP/query URLs. Redirects must not create loops or unsafe targets.

### Audit and admin actions

`audit_events` records durable administrative changes and is append-only at the database boundary. Additional admin action receipt/stat models support operational/admin feedback without replacing the audit trail.

### Operational metrics

`daily_metrics` and related operational reporting models store application-level aggregates only. Human visitor analytics remain authoritative in Matomo and are not duplicated as raw visit/event rows in PostgreSQL.

### Users/sessions/cache/jobs

Laravel framework tables support authenticated admin users and runtime infrastructure. No legacy user/password/session data is imported.

## Deletion rules

Default behavior is conservative:

- referenced media deletion is blocked;
- Journal entries reference their owning Site Node restrictively;
- dangerous parent/content deletion must use an explicit application contract rather than relying on incidental database cascade behavior;
- normal editorial actions should preserve auditability and avoid silent content loss.

Where a later functional acceptance decision defines a specific cascade/restrict rule, tests should protect that product contract and this document should be updated only if the durable model changes.

## Migration boundary

Legacy artwork tables, Vita text/media and legacy SiteSection shapes are migration inputs only.

The configurable-site migration maps the earlier fixed placement types to the current model:

- legacy Home → Home
- artwork category sections → Gallery
- legacy Blog → Journal / Blog
- legacy Exhibitions → Journal / Exhibitions
- legacy CV/Vita → Custom Page
- legacy Contact → Custom Page

Protected Validation/Production state evolves through forward Laravel migrations; source import is not rerun destructively against canonical non-empty data.
