# Data model

This document describes durable application relationships. Database migrations remain the source of truth for exact columns, indexes and constraints.

PostgreSQL is authoritative for editorial state. Canonical uploaded originals are authoritative binary data.

## Conventions

- ordered editorial records persist explicit non-negative `position`; insertion order is never a presentation fallback;
- admin may display a 1-based Position while persistence remains zero/sparse/internal;
- published content must satisfy domain publication prerequisites;
- foreign keys are restrictive where deletion could lose editorial meaning or reusable media relationships;
- migration provenance (`legacy_*`, migration batch/timestamp fields) is evidence only and not a runtime fallback;
- slugs are normalized public identities;
- application/product naming may differ from intentionally retained persistence names.

## Site structure

### `site_sections`

`SiteSection` is the persisted public-site/navigation tree projection.

Persisted concepts include typed node kind, optional Journal template, title/navigation label, optional public slug, publication/navigation visibility, explicit position, optional parent and optional Gallery persistence reference.

Supported runtime node types:

- **Home** — singleton root;
- **Gallery** — public Artwork collection backed by `ArtworkCategory`;
- **Journal** — Blog or Exhibitions presentation;
- **Custom Page** — structured component page;
- **Navigation Node** — navigation-only grouping.

Contact is not a Site Node type. CV/Vita is composed through Custom Page content.

### `home_presentation_settings`

One canonical Home presentation record owns the active Home template and its configuration.

Supported templates:

- Artwork;
- Under Construction;
- Skip Home;
- Custom.

Under Construction/Custom configuration stores ordered components. Direct image components reference `MediaAsset` IDs; text components may contain canonical Rich Text `media:<id>` references.

Heading and Rich Text intentionally share persisted component type `text`; editor-only subtype state must not become another storage format.

### `custom_page_settings`

One-to-one with a Custom Page Site Node.

`blocks` is an ordered JSON list of supported structured components. Component and child ordering is explicit. Direct Image components reference `MediaAsset`; Text/List rich text uses canonical `media:<id>` references.

### `journal_settings`

One-to-one with a Journal Site Node. Stores collection-level title/introduction independently from entries.

The SiteSection's Journal template determines the active Blog/Exhibitions workspace/public projection. Switching the template is **non-destructive**: BlogPost and Exhibition rows belonging to that Journal may coexist as retained inactive content. They are not converted/deleted merely because the active template changes.

## Gallery and Artwork

### `artwork_categories`

Persistence model: `ArtworkCategory`; application concept: **Gallery**.

A Gallery has one matching Gallery SiteSection, which owns placement, hierarchy, publication/navigation state and site order.

### `artworks`

An Artwork may belong to a Gallery through nullable `artwork_category_id`; `null` is the real unassigned state.

Core concepts include slug/title, material/dimensions/description, normalized year/date metadata, lifecycle state/timestamps, Gallery-relative position, optional Home feature/tie-break state and migration provenance.

Detaching from a Gallery preserves the Artwork and its reusable media relations. Publication requires a publishable Gallery and valid public metadata/media.

### `artwork_media`

Explicit usage relation between Artwork and canonical `MediaAsset` originals.

It stores role, position and where supported an Artwork usage-specific ALT override. Removing/replacing a usage does not implicitly delete the MediaAsset.

Gallery primary visual media is image/video-aware. Files audio support does not automatically create an Artwork primary-audio contract.

## Journals

### `blog_posts`

Every Blog Post belongs to a Journal SiteSection. Important concepts include slug/title/body/excerpt, lifecycle, explicit position, publication/schedule timestamps and provenance.

Body is canonical Markdown and may reference Media Files through `media:<id>`.

Structured Cover/Gallery usage lives in `journal_entry_media`.

### `exhibitions`

Every Exhibition belongs to a Journal SiteSection. Important concepts include:

- slug/title/lifecycle;
- explicit Journal-relative position;
- display/normalized dates and vernissage/opening information;
- venue + venue website;
- `location_text` as street address only;
- separate city/country;
- latitude/longitude/geocoding evidence;
- canonical Markdown description;
- external link;
- `gallery_enabled`;
- `map_enabled`;
- `map_shape` (`wide`/`square`);
- `archived_from_state` for safe restore;
- migration provenance.

Map visibility is explicit presentation state, not inferred merely from coordinates/timing. Gallery visibility is explicit and does not delete stored gallery usages when turned off.

Historical archived rows that predate `archived_from_state` are forward-reconciled from publication evidence; restore still respects current readiness.

### `journal_entry_media`

Canonical structured usage relation for Blog/Exhibition Cover/Gallery media.

Each row belongs to exactly one supported Journal entry, references one `MediaAsset`, stores role/position and may retain historical compatibility columns.

Runtime Journal ALT semantics are canonical `MediaAsset.alt_text`. Legacy `alt_text_override` values are not runtime authority; newly synchronized rows store the override as null.

Disabling an Exhibition Gallery does not delete its Gallery rows. They remain canonical references even though public media policy excludes them until `gallery_enabled=true`.

Legacy inline Journal Rich Text media rows/token identifiers are not a runtime content system. Forward canonicalization converted legacy embedded occurrences to central Markdown `media:<id>` references.

## Custom Page / CV / Contact

CV/Vita and Contact are not fixed runtime SiteSection types.

`CvEntry` remains first-class editorial/migration data used by CV List composition. It may reference a direct image MediaAsset and canonical Rich Text body media.

Contact is a reusable structured component with bounded child kinds such as public email, social links and Contact form. Global identity/recipient values remain owned by General/runtime contracts rather than duplicated per child row.

## General settings

`PublicContentSetting::general()` is the canonical fixed site-wide settings record for favicon/site identity media reference, public email/visibility, private Contact recipient, social links, default copyright/global legal text.

SMTP/DKIM/TLS/runtime secrets are not stored here.

## Media

### `media_assets`

Canonical original uploaded or migrated asset.

Durable technical identity includes generated storage key, content-derived MIME type, bytes, SHA-256, lifecycle state and media metadata including canonical asset-level ALT.

Available originals are reusable across consumers. A referenced asset cannot be destructively deleted.

### `media_variants`

Generated derivative of one canonical original. Variants are rebuildable and never authoritative.

### Canonical reference model

Structured references include Artwork, Journal Cover/Gallery, CV direct image, Custom/Home direct Image and site identity.

Rich Text references use Markdown `media:<id>` and are discovered by `RichTextMediaReference`/`MediaReferenceQuery`.

`MediaReferenceQuery` answers whether an asset is referenced anywhere. It intentionally includes retained inactive Blog/Exhibition rows for a Journal after a template switch.

`PublicMedia` is a separate policy layer deciding whether a reference is currently public.

See [MEDIA.md](MEDIA.md).

## Audit and editorial checkpoints

`audit_events` is durable append-only admin history. Domain writes are persisted/audited independently of any future logical Commit/checkpoint feature.

## Operational metrics

Application-local operational metrics store bounded aggregate request/error/performance/admin-health data. Human visitor analytics remain canonical in Matomo.

## Framework/runtime tables

Laravel user/session/cache/job tables support authenticated administration/runtime infrastructure. No legacy user/password/session data is imported.

## Deletion rules

- Home cannot be deleted.
- Parent Site Nodes cannot be deleted while descendants remain.
- Journals cannot be deleted while owned entries remain.
- Referenced MediaAssets cannot be destructively deleted.
- disabling a presentation feature does not silently detach/delete its retained data.
- Gallery detach is Artwork unassignment, not Artwork/Media deletion.
- destructive/publication operations are authorized/audited and go through canonical domain services.

## Migration boundary

Legacy artwork/Vita/Journal/media structures are migration inputs only.

Existing protected canonical state evolves through forward Laravel migrations. Source import is not rerun destructively against non-empty canonical data. Current forward canonicalization includes Journal Rich Text media normalization and Exhibition presentation/restore normalization; see [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md).
