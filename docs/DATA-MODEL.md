# Production data model

This is the clean target model for moeller-lars. PostgreSQL is the selected
database. This document defines the model only; it does not initialize Laravel,
implement migrations, or prescribe a production PostgreSQL version or topology.

## Conventions

- Every table uses a PostgreSQL-generated bigint primary key named id unless
  stated otherwise. The Laravel foundation owns migration syntax.
- Timestamps are timestamptz in UTC.
- Slugs are lowercase, ASCII, URL-safe, and unique within their entity table.
  A published slug is stable. A deliberate slug change creates a redirect.
- Editorial states are constrained values where applicable: draft, published,
  hidden, archived. Blog posts use the lifecycle defined in their section.
- position is a required non-negative integer for ordered editorial records;
  lower values render first. For artwork it is the category-relative editorial
  presentation order and is authoritative over work_date for category galleries.
  It is established by editorial or migration reconciliation and never inferred
  from source ID, target ID, insertion order, database order, or another field
  used as a secondary tie-breaker.
- Required business data has one canonical source. Missing, duplicate,
  ambiguous, or contradictory required data is an invariant failure and must
  not be repaired at runtime by choosing a legacy/raw value, unrelated field,
  placeholder, alternate media source, or accidental database ordering.
- Explicit product precedence is not fallback behavior. For example, a nullable
  usage-specific media ALT override intentionally supersedes the asset-level ALT
  value when present; otherwise the asset-level value is the canonical source.
- Foreign keys are restrictive by default. Archive or detach records before
  deletion.
- Migrated entities retain legacy_id and legacy_source. New records leave them
  null. Use a partial unique constraint on (legacy_source, legacy_id) when both
  are present.
- The target does not preserve the legacy table-per-category schema.

## Main relationships

- artwork_category has many artwork records.
- artwork has many artwork_media usage rows and one required primary original
  media asset when published.
- media_asset has many media_variant derivative rows.
- exhibition optionally references one hero media asset.
- cv_entry optionally references one image media asset.
- blog_post optionally references one cover media asset.
- blog_settings is a singleton independent of blog_post state.
- audit_event optionally records an admin_user actor.
- daily_metric is an independent lightweight operational aggregate.

## artwork_category

Purpose: a generic, artist-managed artwork grouping entity. It is not a
table-per-category architecture or a generic taxonomy system. Categories have
public routes, labels, ordering, and editorial meaning. Legacy category names
and mappings are migration data only; they do not define application route
semantics or the maximum category set. Additional categories are normal
editorial data and require no schema or code change.

Required fields:
- id bigint primary key
- slug varchar(80), stable and unique; category identity is editorial data and
  is not a production application constant.
- name varchar(160)
- state: published or hidden, default hidden for newly created categories
- position integer, default 0, check position >= 0
- show_in_navigation boolean, default false
- show_on_home boolean, default false
- created_at, updated_at

Nullable fields:
- description text
- legacy_id bigint
- legacy_source varchar(160)

Indexes and uniqueness:
- unique slug
- partial unique legacy_source, legacy_id where both are non-null
- index on state, position, id
- published categories shown in navigation require a unique position

Deletion: referenced categories are not hard-deleted. A category is publicly
available only while published; hidden is the editor-controlled unavailable
state. `position` is editorial ordering, `show_in_navigation` controls visible
category navigation, and `show_on_home` controls home eligibility. Public
category slugs are stable. A deliberate new-application slug change may create
a generic redirect record; reserved application paths cannot be category
slugs. Hard deletion requires a hidden, unreferenced category and an audit
decision.

## artwork

Purpose: canonical editorial record for one artwork, independent of files.

Required fields:
- id bigint primary key
- artwork_category_id bigint, foreign key to artwork_category.id
- slug varchar(180), stable and globally unique
- title varchar(240)
- state: draft, published, hidden, archived
- position integer, non-negative; explicit display order for the applicable
  public artwork sequence. The complete publish-ready category order must be
  unambiguous; no implicit tie is permitted.
- created_at, updated_at

Nullable fields:
- medium varchar(240)
- dimensions varchar(240); preserve legacy presentation text
- description text, rendered with contextual escaping/sanitization
- legacy_date_raw varchar(32)
- work_date date
- date_precision: unknown, year, month, or day
- legacy_id bigint
- legacy_source varchar(160)
- migration_batch_id varchar(120)
- migrated_at timestamptz
- published_at timestamptz

Date rule: convert a legacy YYYYMMDD value to work_date only after its
semantics are confirmed. If only the year is reliable, retain the raw value
and use date_precision year; do not invent a day or month. `legacy_date_raw`
is migration/reconciliation evidence and is never a runtime substitute for a
missing normalized value.

Indexes and uniqueness:
- unique slug
- partial unique legacy_source, legacy_id
- index on state, artwork_category_id, position
- index on state, work_date, position
- foreign key artwork_category_id references artwork_category.id restrictively

Constraints and deletion:
- published artwork requires a published category and exactly one published
  primary usage in artwork_media that resolves to an available original
- work_date must be consistent with date_precision
- position >= 0
- position is authoritative for category gallery presentation; `work_date`
  describes the artwork and never acts as an ordering fallback after position
- published artwork positions within one category must be unique; duplicate
  legacy positions are migration/reconciliation exceptions that must be resolved
  before the affected category is publish-ready
- gaps in positions are permitted; explicit admin reorder normalizes a complete
  category to contiguous 0..n-1
- new artwork appends to its category and a category move appends to the
  destination
- normal editorial UI does not expose numeric artwork position input
- ordering mutations are authorized and audited
- normally archive rather than delete; media deletion is restricted while used

## media_asset

Purpose: immutable original uploaded or migrated media. Originals are retained
even when derivatives exist. This table never represents a derivative.

Required fields:
- id bigint primary key
- storage_key varchar(500), application-generated and unique
- original_filename varchar(255), metadata only and never a filesystem path
- mime_type varchar(120), determined from content and allowlisted
- byte_size bigint, check byte_size > 0
- sha256 char(64), lowercase hexadecimal SHA-256 of original bytes
- state: available, quarantined, or deleted; default quarantined for newly
  created originals
- created_at, updated_at

Nullable fields:
- alt_text varchar(500); public informative images require it, while
  decorative use must explicitly use an empty value
- copyright_notice varchar(500)
- credit varchar(240)
- width integer, height integer; positive when present
- metadata jsonb, limited to non-sensitive technical metadata
- focal_point_x and focal_point_y numeric, each in [0,1], optional only

Provenance fields:
- legacy_id bigint
- legacy_source varchar(160)
- legacy_path varchar(500)
- legacy_filename varchar(255)
- legacy_byte_size bigint
- migration_batch_id varchar(120)
- migrated_at timestamptz

Indexes and constraints:
- unique storage_key
- index on state, mime_type
- index on sha256 for reconciliation
- optional unique sha256 for physical deduplication, only if every usage
  reference is preserved

Asset metadata is plain-text editorial metadata. `alt_text` is the canonical
asset-level ALT value. `artwork_media.alt_text_override` is an explicitly
optional usage-specific value that takes precedence when present. Missing
required public ALT data is an invariant failure; artwork title, filename,
legacy metadata, or placeholder text is never substituted. Original technical
identity fields, storage identity, checksums, and provenance are immutable
through the editorial UI.

Media deletion is logical: the asset and its variants transition to deleted.
Any artwork_media, exhibition hero, CV image, or blog cover reference blocks
deletion. Storage cleanup occurs only after the durable database and audit
transaction commits. Cleanup failure is surfaced explicitly as an operation
failure; it may leave private orphaned bytes but is never reported as successful
cleanup and must never reactivate logically deleted media. Integrity
verification compares stored bytes with persisted size, SHA-256, and content
MIME and checks available derivative consistency. No hard delete is part of
normal media editorial workflow. Primary media replacement is an explicit
atomic editorial operation: it switches an existing primary usage to a newly
validated available asset without committing an intermediate media-less artwork
state. The usage-specific ALT override is cleared when the underlying primary
image changes. An old asset is logically deleted only when no artwork,
exhibition, CV, or blog reference remains; shared old assets stay available.
Physical cleanup of an unreferenced replaced asset occurs only after the durable
database and audit commit. Cleanup failure is surfaced explicitly and must not
be converted into a warning-success or alternate-success path.

## media_variant

Purpose: generated derivative of one original asset. Variants are disposable
and never authoritative.

Required fields:
- id bigint primary key
- media_asset_id bigint foreign key to the original asset
- variant_kind varchar(32), such as thumbnail, medium, large, or webp
- storage_key varchar(500), generated and unique
- mime_type varchar(120)
- byte_size bigint, check byte_size > 0
- sha256 char(64)
- transform_profile varchar(120), required and versioned
- state: available, stale, or deleted
- created_at, updated_at

Nullable fields:
- width integer, height integer; positive when present

Indexes and constraints:
- unique media_asset_id, variant_kind, transform_profile
- index on media_asset_id, state
- derivative metadata is separate from the original asset and never overwrites
  its MIME type, dimensions, byte size, or SHA-256

Deletion: variants transition to deleted when their unreferenced original is
logically deleted; neither the original nor variants are hard-deleted by the
normal media editorial workflow. Variants may be regenerated without changing
the original technical identity.

## artwork_media

Purpose: explicit ordered usage references between artworks and original assets.
It supports a primary image plus future additional views without a generic CMS
attachment abstraction.

Fields:
- id bigint primary key
- artwork_id bigint, required foreign key
- media_asset_id bigint, required foreign key
- role: primary or additional
- position integer, non-negative
- alt_text_override varchar(500), nullable
- created_at, updated_at
- unique artwork_id, media_asset_id

Constraints:
- unique artwork_id, position
- at most one primary row per artwork
- published artwork must have one primary row
- foreign keys reference artwork.id and media_asset.id restrictively
- primary usage is the artwork's first-class original-media reference; the
  required public variant is resolved from that original and never replaced by
  the original or another derivative when missing

Deletion is restrictive. Removing a usage does not delete the asset.
Replacement updates the existing primary usage row rather than deleting and
recreating it.

## exhibition

Purpose: separate structured public/editorial exhibition record. Exhibitions
are not inferred from or merged into CV entries.

Required fields:
- id bigint primary key
- slug varchar(180), stable and unique
- title varchar(240)
- state: draft, published, hidden, archived
- position integer, non-negative
- created_at, updated_at

Nullable fields:
- kind varchar(32), exactly solo or group when present
- venue varchar(240)
- city varchar(160)
- country varchar(160)
- description text
- external_url varchar(2048), only approved public URL schemes
- hero_media_asset_id bigint foreign key to media_asset.id
- starts_on date, ends_on date; if both exist, ends_on >= starts_on
- date_text varchar(160) for uncertain legacy display text
- legacy_id bigint
- legacy_source varchar(160)
- migration_batch_id varchar(120)
- migrated_at timestamptz
- published_at timestamptz

Indexes and deletion:
- unique slug
- partial unique legacy_source, legacy_id
- index on state, starts_on, position, id
- hero media deletion is restricted
- normally archive; hard deletion requires an editorial decision

Temporal state is derived at read time, never persisted: a future starts_on is
upcoming; a date on or between starts_on and ends_on is current; an exhibition
with only starts_on is current on that date and past afterwards; an end before
the requested date is past; insufficient date information is unknown.

## cv_entry

Purpose: one structured CV entry from the legacy Vita source or new editorial
input. It remains a separate entity and workflow from exhibitions.

Required fields:
- id bigint primary key
- section varchar(120), controlled values such as education, exhibitions,
  publications, and awards
- title varchar(240)
- state: draft, published, hidden, archived
- position integer, non-negative within section
- date_precision: unknown, year, month, or day
- created_at, updated_at

Nullable fields:
- organisation varchar(240)
- location varchar(240)
- body text, sanitized if rich text is allowed
- external_url varchar(2048), HTTPS only
- image_media_asset_id bigint foreign key to media_asset.id
- year_text varchar(80)
- starts_on date, ends_on date, only when semantics are confirmed
- legacy_id bigint
- legacy_source varchar(160)
- migration_batch_id varchar(120)
- migrated_at timestamptz
- published_at timestamptz

Indexes and deletion:
- index on state, section, position, id
- partial unique legacy_source, legacy_id
- archive rather than delete; retain the legacy vita.txt source as the
  migration reference and do not collapse its exhibition lines into this table
- txt/vita.txt remains a migration reference and is not discarded
- image media deletion is restricted

## blog_post

Purpose: blog post with an independent editorial lifecycle. Public blog
enablement is controlled by blog_settings, not by blog_post state.

Required fields:
- id bigint primary key
- slug varchar(220), stable and unique
- title varchar(240)
- body text, required when published
- state: draft, scheduled, published, unpublished, archived
- position integer, non-negative
- created_at, updated_at

Nullable fields:
- excerpt text
- cover_media_asset_id bigint foreign key to media_asset.id
- published_at timestamptz
- scheduled_at timestamptz
- legacy_id bigint
- legacy_source varchar(160)
- migration_batch_id varchar(120)
- migrated_at timestamptz

Preview is authenticated admin preview of non-public content. It does not
require a persistent preview-token column and does not change public
eligibility. Blog body is rendered only through the project's constrained,
sanitized content path shared with later rich-text/editor work; raw untrusted
HTML is never rendered. Immediate publication uses published state plus
published_at. Scheduled publication uses scheduled state plus scheduled_at.

Indexes and constraints:
- unique slug
- partial unique legacy_source, legacy_id
- index on state, published_at descending, position
- scheduled requires scheduled_at in the future at scheduling time; published
  requires non-empty title/body and published_at; unpublished and archived
  posts are never public
- PostgreSQL requires body and published_at for published posts and requires
  scheduled_at for scheduled posts; future-time validation remains application
  logic
- a post is publicly eligible only when state is published, published_at is due,
  and blog_settings.public_enabled is true
- cover media deletion is restrictive; normally archive posts

## blog_settings

Purpose: singleton controlling whether the blog is public. It is independent
of individual post draft/published state.

Fields:
- id smallint primary key, constrained to 1
- public_enabled boolean, required, default false
- listing_title varchar(240), nullable
- listing_intro text, nullable
- created_at, updated_at

There must be exactly one row. Public blog routes, navigation, sitemap
entries, and blog_post pages require public_enabled true and an eligible post.
Migration must create it disabled by default.

## redirect

Purpose: maps retired public paths of the new application to canonical target
paths. Legacy dispatcher/query URLs are migration evidence only and are not a
blanket compatibility surface.

Required fields:
- id bigint primary key
- source_path varchar(512), normalized internal path only and unique
- target_path varchar(2048), internal canonical path or approved HTTPS URL
- status_code smallint: 301, 308, or deliberate temporary 302
- enabled boolean, default true
- created_at, updated_at

Nullable fields:
- reason varchar(240)
- legacy_id bigint
- legacy_source varchar(160)
- migration_batch_id varchar(120)
- migrated_at timestamptz

Indexes and constraints:
- index on enabled, source_path
- reject loops, fragments, unsafe schemes, and source equal to target
- source paths begin with a single `/` and contain no query string or fragment;
  targets are normalized internal paths or approved `https://` URLs. Database
  validation does not perform hostname allowlisting.
- prefer disabling over deletion after route reconciliation

## admin_user integration boundary

The shared Laravel foundation owns administrator identity, authentication,
password hashing, sessions, authorization, and account lifecycle. This model
does not create a competing user table and does not migrate legacy credentials.

The integration contract is an opaque admin_user_id bigint wherever an actor must
be recorded, referencing the foundation's canonical admin identity with
ON DELETE SET NULL. The exact table name and key type require foundation-owner
confirmation. No content table stores passwords, sessions, reset tokens, or
authorization rules.

## audit_event

Purpose: append-only record of security-relevant and editorial mutations.

Required fields:
- id bigint primary key
- action varchar(80), such as artwork.publish or media.delete
- entity_type varchar(80), allowlisted
- occurred_at timestamptz

Nullable fields:
- admin_user_id bigint, integration foreign key with ON DELETE SET NULL
- entity_id bigint, null for bulk/system events
- request_id varchar(120)
- metadata jsonb, redacted before storage

Rules and indexes:
- application cannot update or delete events
- never store passwords, tokens, full request bodies, full IPs, or raw personal
  data in metadata
- indexes on entity_type, entity_id, occurred_at; admin_user_id, occurred_at;
  and occurred_at descending

## daily_metric

Purpose: small local operational aggregates for bot requests, HTTP errors,
response-time bands, upload failures, storage health, deployment status, and
optional cached Matomo summaries.

It must not store Matomo visitor events, visit identities, page-view logs, full
IP addresses, or raw user-agent strings. Matomo remains the source of truth for
human analytics.

Required fields:
- id bigint primary key
- metric_date date
- metric_name varchar(80), allowlisted for bots, errors, performance,
  security, and operations only
- source varchar(40): local_log, application, or matomo_cache
- value numeric, check value >= 0
- unit varchar(24): count, milliseconds, bytes, or similar
- calculated_at timestamptz

Nullable fields:
- dimension_key varchar(160), normalized values such as status:404,
  bot:googlebot, or path:/artwork
- sample_count bigint, check sample_count >= 0

Indexes and uniqueness:
- null-safe unique key on metric_date, metric_name, source, dimension_key
  (PostgreSQL NULLS NOT DISTINCT semantics)
- index on metric_date, metric_name

Cached Matomo summaries are disposable and rebuildable. Local metrics must not
become a parallel human-analytics warehouse.

## Shared migration provenance

The following fields are required on artwork, artwork_category, media_asset,
exhibition, cv_entry, and blog_post, and may be used on redirect:

- legacy_id bigint nullable: original source identifier
- legacy_source varchar(160) nullable: for example legacy:paintings,
  legacy:drawings, legacy:prints, legacy:txt/vita.txt, or legacy:redirects
- migration_batch_id varchar(120) nullable: importer snapshot/batch identifier
- migrated_at timestamptz nullable: import or reconciliation time

For media, also retain legacy_path, legacy_filename, and legacy_byte_size.
Preserve source values exactly. Every clean import must be repeatable and
idempotent, with counts, ordering exceptions, and SHA-256 reconciliation
recorded in a migration report. Duplicate or ambiguous imported ordering is an
explicit migration/editorial exception for review and must be resolved before
public readiness; it is never repaired by an implicit ID, date, slug, insertion,
or database ordering choice. Secrets must never enter the report, fixtures, or
this model.

## Analytics and failure boundary

Matomo On-Premise Community/Core is the source of truth for human analytics.
The application may read cached Matomo summaries for admin/analytics, but
daily_metric is limited to bots, errors, performance, deployment/health
signals, and optional cached dashboard values.

Analytics collection, log aggregation, and dashboard reads are asynchronous or
failure-tolerant because analytics is explicitly non-critical to the public and
editorial application path. This resilience boundary must not substitute
incorrect analytics data: a Matomo outage, archive failure, or local
metric-parser failure may omit/report analytics state, but must never invent
successful values or fail public rendering, editorial reads, authentication, or
content writes.
