# Data model

This document describes durable application relationships. Database migrations remain the source of truth for exact columns, indexes and constraints.

PostgreSQL is authoritative for editorial state. Canonical uploaded originals are authoritative binary data.

## Conventions

- ordered editorial records persist explicit non-negative `position`; insertion order is never a presentation fallback;
- published content must satisfy its domain publication prerequisites;
- foreign keys are restrictive where deletion could lose editorial meaning or reusable media relationships;
- migration provenance (`legacy_*`, migration batch/timestamp fields) is evidence only and not a runtime fallback;
- slugs are normalized public identities;
- domain/application naming may differ from intentionally retained persistence names. `ArtworkCategory` is the persistence model behind the product concept **Gallery**.

## Site structure

### `site_sections`

`SiteSection` is the persisted public-site/navigation tree projection.

Persisted concepts include:
- typed node kind;
- optional Journal template;
- title/navigation label;
- optional public slug;
- publication/navigation visibility;
- explicit position;
- optional parent;
- optional Gallery persistence reference.

Runtime behavior is defined by `SiteNodeType` / `JournalTemplate`, not raw persistence strings.

Supported runtime node types:
- **Home** — singleton root, no slug, always published/navigation-visible;
- **Gallery** — public Artwork collection backed by `ArtworkCategory`;
- **Journal** — Blog or Exhibitions collection;
- **Custom Page** — structured component page;
- **Navigation Node** — navigation-only grouping with no public content URL.

Contact is not a Site Node type. It is a reusable structured component. CV/Vita is composed through Custom Page content rather than represented by a dedicated runtime node type.

Parent nodes are not destructively removed while descendants remain. Journals are not removed while owned entries remain.

### `custom_page_settings`

One-to-one with a Custom Page Site Node.

`blocks` is an ordered JSON list of supported structured components. Publication validation applies safe content/link/media rules.

### `journal_settings`

One-to-one with a Journal Site Node. Stores collection-level title/introduction independently from entries.

## Gallery and Artwork

### `artwork_categories`

Persistence model: `ArtworkCategory`.

Application concept: **Gallery**.

A Gallery stores persistent identity/content data and has one matching Gallery `SiteSection`, which owns placement, hierarchy, publication/navigation state and site order.

### `artworks`

An Artwork may belong to a Gallery through nullable `artwork_category_id`.

`null` is a real **unassigned** state used by the Gallery detach/remove workflow. It is not a hidden fake Gallery and is distinct from deleting the Artwork.

Core editorial concepts include:
- stable slug/title;
- Material text (`medium` remains a compatibility persistence field);
- dimensions/description;
- normalized year/date metadata;
- draft/publication state and timestamps;
- Gallery-relative position while assigned;
- optional homepage feature/tie-break state;
- migration provenance.

When assigned, Artwork ordering is explicit within its Gallery. Moving to another Gallery appends/reorders under the domain service and preserves media usages.

Detaching from a Gallery:
- requires lifecycle invariants to remain valid; currently a published Artwork must be unpublished first;
- sets `artwork_category_id` to `null`;
- removes it from Gallery ordering;
- preserves the Artwork itself;
- preserves `ArtworkMedia` and reusable `MediaAsset` records.

Publication requires a publishable Gallery and valid public metadata/media.

### `artwork_media`

Explicit usage relation between Artwork and canonical `MediaAsset` originals.

It stores role, position and optional usage-specific ALT override. Removing a usage does not implicitly delete the asset. Primary-media replacement changes the relation while retaining reusable MediaAssets.

Gallery primary visual media is image/video-aware. Audio support in the Files library does not by itself make audio a primary Gallery visual role.

### `artwork_material_presets`

Reusable artist convenience list for Material suggestions.

- names are unique;
- presets are suggestions, not canonical normalization of historical Artwork material text;
- removing a preset never rewrites existing Artworks.

Structured dimensions remain stored in the existing Artwork dimensions text field for compatibility. The editor may parse/compose Height × Width × optional Depth with unit while preserving an unparseable/custom freeform value unchanged.

## Journals

### Blog / `blog_posts`

Every Blog Post belongs to a Blog Journal Site Node.

Important concepts include slug/title/body/excerpt, lifecycle state, explicit Journal-relative position, optional cover media, timestamps and migration provenance.

Scheduled visibility is evaluated from persisted schedule timestamps. Published/scheduled posts must leave that lifecycle before normal deletion. Reusable MediaAssets remain.

### Exhibitions / `exhibitions`

Every Exhibition belongs to an Exhibitions Journal Site Node.

Important concepts include slug/title, publication state, Journal-relative position, date text + normalized dates, venue/location fields, opening information, constrained rich text, external/directions links and provenance.

Ordering is Journal-scoped. Published Exhibitions must be unpublished before normal deletion. Media usages are removed without deleting reusable MediaAssets.

### `exhibition_media`

Ordered usage relation between Exhibitions and `MediaAsset`, including role and optional ALT override.

## Custom Page / CV / Contact

The old fixed `vita` and `contact` runtime SiteSection types no longer exist.

- CV/Vita is structured Custom Page content;
- Contact is a reusable component that can be included in a Custom Page composition;
- legacy CV/Contact persistence/provenance may remain where required for migration compatibility, but it does not recreate those old runtime page types.

Historical `CvEntry` data remains available where required for migration reconciliation and transformed editorial content.

## General settings

`PublicContentSetting::general()` is the canonical fixed site-wide settings record for:
- favicon/site identity media reference;
- public email + visibility;
- private Contact-form recipient;
- social links;
- default media copyright/global legal text.

This record is not an editable page and is not deletable. SMTP/DKIM/TLS/runtime secrets are not stored here.

## Media

### `media_assets`

Canonical original uploaded or migrated asset.

Durable technical identity includes generated storage key, content-derived MIME type, bytes, SHA-256, lifecycle state and relevant media metadata.

Current canonical media kinds include image, video and audio under `MediaTypePolicy`. Consumers may impose stricter type rules.

Available originals are reusable across content. Quarantined/deleted state is explicit. A referenced asset cannot be destructively deleted.

### `media_variants`

Generated derivative of one canonical original. Variants are rebuildable and never authoritative.

Images currently use generated derivatives where required. Video/audio do not imply a transcoding/poster/waveform derivative contract unless explicitly added later.

See [MEDIA.md](MEDIA.md).

## Audit and editorial checkpoints

### `audit_events`

Durable append-only administrative history.

Domain writes are persisted and audited independently of any future logical Commit/checkpoint feature. Activity reflects successful writes; it is not the persistence trigger.

A future editorial checkpoint may group already-persisted audit events without turning `Commit` into a Save operation or Git integration.

## Operational metrics

Application-local operational metrics store bounded aggregate error/request/performance/admin health data. Human visitor analytics remain canonical in Matomo and are not duplicated as raw visitor history in PostgreSQL.

## Framework/runtime tables

Laravel user/session/cache/job tables support authenticated administration/runtime infrastructure. No legacy user/password/session data is imported.

## Deletion rules

- Home cannot be deleted.
- A parent Site Node cannot be deleted while descendants remain.
- A Journal cannot be deleted while owned entries remain.
- Referenced MediaAssets cannot be destructively deleted.
- Removing Artwork from a Gallery is detach/unassignment, not Artwork or MediaAsset deletion.
- normal direct Artwork deletion is restricted by Artwork lifecycle and preserves reusable MediaAssets;
- published/scheduled Blog and published Exhibition records must leave the public lifecycle before normal deletion;
- destructive actions are authorized/audited and never silently cascade through reusable content.

## Migration boundary

Legacy artwork tables, Vita content/media and historical SiteSection shapes are migration inputs only.

Current normalization maps historical placement/content into Home, Gallery, Journal, Custom Page and reusable Contact components. Protected Validation/Production state evolves through forward Laravel migrations; source import is not rerun destructively against canonical non-empty data.