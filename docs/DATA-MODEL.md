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

Every public SiteSection, including Home, may independently enable a content
heading. The common fields are `content_heading_enabled`,
`content_heading_override` (nullable), `content_heading_alignment`
(left/center/right) and `content_heading_divider`
(none/thin/subtle/strong/dotted). All pages start without a content heading.
The separate `committed.site_sections` schema mirrors the new columns for
publication, restore and audit comparison. Changing a content heading never
changes the navigation label, title, slug or publication state.


Persisted concepts include typed node kind, optional Journal template, title/navigation label, optional public slug, publication state, explicit position, optional parent and optional Gallery persistence reference.

Supported runtime node types:

- **Home** — singleton root;
- **Gallery** — public Artwork collection backed by `ArtworkCategory`;
- **Journal** — Blog or Exhibitions presentation;
- **Custom Page** — structured component page;
- **Navigation Node** — navigation-only grouping.

Contact is not a Site Node type. CV/Vita is composed through Custom Page content.

### `home_presentation_settings`

One canonical Home presentation record owns the active Home template and its configuration.

Supported **content templates**:

- Artwork;
- Under Construction;
- Custom.

`skip_home` and `skip_target_section_id` form a separate Home routing contract owned by `HomeRoutingSettingsService`. The legacy `HomeTemplate::SkipHome` enum case exists for migrating historical data; it is **not** a current selectable content template.

Under Construction/Custom configuration stores ordered components following
the shared Custom Page component contract, including List/Contact children,
canonical Divider variants and per-level publication state. Image/List media
references point to `MediaAsset` IDs; Text/List body fields may contain
canonical Rich Text `media:<id>` references. The active template still owns
its configuration independently of other Home templates.

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

A Gallery has one matching Gallery SiteSection, which owns placement, hierarchy, publication state and site order.

### `artworks`

An Artwork may belong to a Gallery through nullable `artwork_category_id`; `null` is the real unassigned state.

Core concepts include slug/title, material/dimensions/description, normalized year/date metadata, lifecycle state/timestamps, Gallery-relative position, optional Home feature/tie-break state and migration provenance.

Detaching from a Gallery preserves the Artwork and its reusable media relations. Publication requires a publishable Gallery and valid public metadata/media.

### `artwork_media`

Explicit usage relation between Artwork and canonical `MediaAsset` originals.

It stores role, position and where supported an Artwork usage-specific ALT override. Removing/replacing a usage does not implicitly delete the MediaAsset.

Gallery primary visual media is image/video-aware. Storage audio support does not automatically create an Artwork primary-audio contract.

## Journals

### `blog_posts`

Every Blog Post belongs to a Journal SiteSection. Important concepts include slug/title/body/excerpt, lifecycle, explicit position, publication/schedule timestamps and provenance.

Body is canonical Markdown and may reference Storage media through `media:<id>`.

Structured Cover/Gallery usage lives in `journal_entry_media`.

Blog posts retain an editor-controlled `gallery_presentation` (`sequence` default,
`grid`, `mosaic`, or `slideshow`). The forward migration adds it to
`public.blog_posts` and `committed.blog_posts` together; preexisting records
keep their original sequential presentation. This is a presentation attribute,
not a second Journal media table or a modification to the ordered media usages.

### `exhibitions`

Every Exhibition belongs to a Journal SiteSection. Important concepts include:

- slug/title/lifecycle;
- explicit Journal-relative position;
- display/normalized dates and vernissage/opening information;
- venue + venue website;
- `location_text` as street address only;
- optional `postal_code` (no country inferred), plus separate city/country;
- latitude/longitude/geocoding evidence;
- canonical Markdown description;
- external link;
- `gallery_enabled`;
- `map_enabled`;
- no configurable map shape; all public maps use the same responsive dialog;
- `archived_from_state` for safe restore;
- migration provenance.

The historical `exhibitions.map_shape` column is retained physically for Publication snapshot/schema compatibility, but it is excluded from active fillable/editorial data, not read by presentation and not written by domain services. A later explicitly coordinated schema migration may retire the column once historical snapshot restore compatibility is addressed.

Map visibility is explicit presentation state, not inferred merely from coordinates/timing. Gallery visibility is explicit and does not delete stored gallery usages when turned off.

Historical archived rows that predate `archived_from_state` are forward-reconciled from publication evidence; restore still respects current readiness.

### `journal_entry_media`

Canonical structured usage relation for Blog/Exhibition Cover/Gallery media.

Each row belongs to exactly one supported Journal entry, references one `MediaAsset`, stores role/position and may retain historical compatibility columns.

Runtime Journal ALT semantics are canonical `MediaAsset.alt_text`. Legacy `alt_text_override` values are not runtime authority; newly synchronized rows store the override as null.

Disabling an Exhibition Gallery does not delete its Gallery rows. They remain canonical references even though public media policy excludes them until `gallery_enabled=true`.

Legacy inline Journal Rich Text media rows/token identifiers are not a runtime content system. Forward canonicalization converted legacy embedded occurrences to central Markdown `media:<id>` references.

The historical `exhibition_media` table is no longer a runtime domain model or reference source. It is retained only as an inert Publication-schema artifact while retained checkpoints still use the older snapshot schema; canonical Exhibition media is exclusively `journal_entry_media`. Do not add new runtime reads, writes, relations or audit entities for `exhibition_media`.

## Custom Page / CV / Contact

CV/Vita and Contact are not fixed runtime SiteSection types.

Current CV/Vita composition is built from **Custom Page structured components** (including List and Rich Text), not active `CvEntry` rows. The `CvEntry` PHP model survives as a historical import/compatibility artifact; forward migration `2026_09_16_235901_finalize_legacy_cv_retirement.php` removed `cv_entries` from both `public` and `committed`. Its historical `imageMediaAsset()` relation must not be misread as a current SQL foreign key or an active CV editor. See [structured components](architecture/structured-components.md) and [schema evolution](architecture/schema-evolution.md).

Contact is a reusable structured component with bounded child kinds such as public email, social links and Contact form. Global identity/recipient values remain owned by General/runtime contracts rather than duplicated per child row.

## General settings

`PublicContentSetting::general()` is the canonical fixed site-wide settings record for favicon/site identity media reference, public email/visibility, private Contact recipient, social links, default copyright/global legal text.

SMTP/DKIM/TLS/runtime secrets are not stored here.

## Media

### `media_assets`

Canonical original uploaded or migrated asset.

Durable technical identity includes generated storage key, content-derived MIME type, bytes, SHA-256, lifecycle state and media metadata including canonical asset-level ALT.

Available originals are reusable across consumers. A referenced asset is **not** automatically immune to deletion: an explicitly authorized `MediaAssetEditorialService::delete()` transaction removes supported canonical usages and dependent editorial references before marking the asset deleted. Its physical bytes remain governed by Working/LIVE/retained-history cleanup eligibility. See [MEDIA.md](media.md#deletion-and-cleanup).

### `media_variants`

Generated derivative of one canonical original. Variants are rebuildable and never authoritative.

### Canonical reference model

Current structured references include Artwork, Journal Cover/Gallery, Custom/Home Image and List components, and site identity. The former CV direct-image relationship is a historical `CvEntry` artifact only, not a current active SQL reference.

Rich Text references use Markdown `media:<id>` and are discovered by `RichTextMediaReference`/`MediaReferenceQuery`.

`MediaReferenceQuery` answers whether an asset is referenced anywhere. It intentionally includes retained inactive Blog/Exhibition rows for a Journal after a template switch.

`PublicMedia` is a separate policy layer deciding whether a reference is currently public.

See [MEDIA.md](media.md).

## Admin notifications

`admin_notifications` stores persistent user-scoped admin inbox entries. It is deliberately separate from append-only audit history and from the project-owned ephemeral ticker feedback channel.

The durable identity is `(user_id, source_id)`: the recipient and the server-originated source event/condition together provide idempotency. Notification rows may carry bounded structured context for their severity/type, destination action and related entity/audit/publication identifiers, but they are not a second audit payload store.

`read_at` is persistent inbox state. Deletion and retention apply only to notification rows; they never delete related Activity/audit or Publication history. Pinned feed entries remain protected by the dashboard retention contract.

Persistent notifications are created by the central server-side admin notifier. Rendered Filament DOM/classes are not a persistence source.

See [ADMIN-NOTIFICATION-CONTRACT.md](admin/notifications.md).

## Audit, Undo and editorial checkpoints

`audit_events` is durable append-only forensic evidence for successful administrative mutations. Uncommitted events are the source of the current Activity view; `publication_checkpoint_events` links them to the Commit that closes that Activity generation. Committed Activities remain inspectable through Commit details and are no longer individually Undo-capable.

`admin_activity_ordering_projections` is the mutable grouping projection for ordering Activity. It stores the exact canonical `before_state` and `after_state` of one state-continuous ordering scope; `admin_activity_ordering_events` links immutable raw reorder audit events to that projection. Every projection is represented as one visible forensic Activity, including projections whose final order equals their starting order. Identity affects only Undo eligibility, not historical visibility.

`admin_action_receipts` stores bounded actor-scoped Undo roots. Snapshot-style receipts reference immutable content-addressed JSON through `snapshot_payload_id` rather than owning a duplicate payload copy.

`publication_checkpoints` stores permanent Commit metadata/lineage. `publication_checkpoint_events` associates every audit event from the closed Activity generation with that Commit. A checkpoint's `snapshot_available` flag states whether its restore payload remains retained. `publication_version_row_manifests` maps a retained checkpoint/table/row identity to `history_payloads`; the transparent `publication_version_rows` interface reconstructs the logical complete snapshot expected by Publication services.

`history_payloads` is the shared immutable payload store for both Publication row manifests and Undo snapshot receipts. Payload rows are collected only when no manifest or receipt still references them.

A persistent notification may reference an audit event for navigation/context, but notification read/delete/prune operations do not mutate audit history. Storage reclamation may delete Undo receipts and release older unprotected Publication snapshot manifests while preserving `audit_events`, Commit metadata, the current LIVE restore snapshot and the current Working restore/revert source.

## Operational metrics

Application-local operational metrics store bounded aggregate request/error/performance/admin-health data. Human visitor analytics remain canonical in Matomo.

## Framework/runtime tables

Laravel user/session/cache/job tables support authenticated administration/runtime infrastructure. No legacy user/password/session data is imported.

## Deletion rules

- Home cannot be deleted.
- Parent Site Nodes cannot be deleted while descendants remain.
- Journals cannot be deleted while owned entries remain.
- Canonical MediaAsset deletion is reference-aware: the single Domain transaction must remove/update affected usages and editorial content, then defer physical cleanup while any Working/LIVE/retained snapshot requires those bytes. A reference is not an unconditional refusal to delete.
- disabling a presentation feature does not silently detach/delete its retained data.
- Gallery detach is Artwork unassignment, not Artwork/Media deletion.
- destructive/publication operations are authorized/audited and go through canonical domain services.
- deleting/pruning a notification never deletes related Activity/audit or Publication history;
- explicit Storage reclamation may remove bounded Undo and older restore payload roots, but never Activity events or Publication checkpoint metadata.

## Forward source delta (dev@434975e1bd09; not applied on Validation)

The current `dev` migration set adds `media_rights_artifacts` (immutable bytes, revision, SHA-256, size, MIME, source MediaAsset) and `media_rights_artifact_selections` (scope, role, source and artifact identity). These optional rights files are not replacements for `media_assets` or `media_variants`, and their Working/Checkpoint selection is a separate Publication concern.

Gallery `artwork_categories` now carries Source migrations for `landscape_detail_enabled`, `portrait_detail_enabled`, `artwork_page_surface_mode`, `edge_navigation_mode`, `artwork_divider`, `sort_mode`, `video_sound_enabled`, `show_artwork_information`, `show_media_credits`, `show_media_copyright` and `caption_layout` (plus inert legacy `landscape_detail_scale`). Public/Committed `public_content_settings` gain global paint surfaces with multi-stop gradients, responsive header/divider controls and `frame_style`. The relevant Gallery changes also extend `committed.artwork_categories` on PostgreSQL. The portrait opt-in defaults to off. The surface mode defaults to `inherit` (Follow General) and also allows `joined` (No gap) and `separate` (Separate Surface), scoped to individual Artwork detail pages of the Gallery; it does not replace General's saved setting or add a new Public surface mechanism. These are **forward code migrations**, not an observation of the artist's protected Validation schema. See the single [schema-evolution owner](architecture/schema-evolution.md).

## Migration boundary

Legacy artwork/Vita/Journal/media structures are migration inputs only.

Existing protected canonical state evolves through forward Laravel migrations. Source import is not rerun destructively against non-empty canonical data. Current forward canonicalization includes Journal Rich Text media normalization and Exhibition presentation/restore normalization; see [MIGRATION-INVARIANTS.md](migration/invariants.md).
