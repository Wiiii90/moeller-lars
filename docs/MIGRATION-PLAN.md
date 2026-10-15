# Migration and cutover plan

The original v0.3 product-composition gate is historical background, **not proof that the current integration candidate has been accepted**. The v0.4 editorial/admin/public browser acceptance and cleanup work is still progressing on `dev` (as of 2026-10-13). Once the user approves a specific candidate, its exact SHA can be promoted through the protected `main` release gates. The subsequent v0.5 cutover scope remains a **planned milestone**, contingent on validated data/media migration and operational readiness, not an automatically triggered deployment.

Production/Validation placement, backups, deployment and rollback are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

## 1. Source boundary

Original legacy provenance inputs (not the final artist-edited composition) are **read-only evidence**:

- legacy Artwork/category tables and ordering;
- reviewed Vita/CV source + portrait;
- legacy public media required by the artist-site target;
- legacy route/presentation evidence used for comparison.

The legacy `/workshop` application/database remains outside the artist-site content target. Never commit source DB dumps, Production media, credentials or secret-bearing configuration.

## 2. Canonical target

Normalization:

- legacy artwork categories → **Gallery** persistence + Gallery Site Nodes;
- legacy Home → **Home** presentation state;
- application-owned Blog editorial model → **Journal / Blog**; this is target-schema evolution, not a legacy-source mapping;
- legacy Exhibitions → **Journal / Exhibitions**;
- legacy CV/Vita placement/content → **Custom Page/CV composition** + retained provenance;
- legacy Contact content/placement → reusable **Contact component**;
- original media → canonical `MediaAsset` originals with checksum/provenance.

Historical persistence names are not restored as runtime compatibility aliases.

After the initial Legacy import, the **artist-curated, protected Validation database and matching canonical original media** form the intended future v0.5 Production *content candidate* once explicitly approved and frozen. A repeat Legacy import is not the required final content source; it remains provenance/rehearsal evidence. See [cutover issue #209](https://github.com/Wiiii90/moeller-lars/issues/209).

## 3. Vita/CV/Exhibitions source accounting

Reviewed Vita source contains 31 normalized rows:

- 2 Biography/CV rows;
- 29 Exhibition rows;
- total accounting 31/31;
- portrait/profile provenance explicit;
- Exhibition content not duplicated in CV after normalization.

Existing protected canonical data is transformed through forward migrations/reconciliation. Do not rerun source import into non-empty canonical data to apply later domain/schema changes. The 31-row Legacy inventory is **source accounting**: artist-approved changes to Biography/Exhibition content on Validation may change final counts or presentation, but every source row and reviewed difference remains traceable.

## 4. Forward canonicalization after initial import

Current protected-state evolution includes deliberate forward migrations beyond the original import.

### Journal Rich Text media canonicalization

Legacy Journal embedded-image/runtime usage was converted to the central Markdown `media:<id>` contract. The migration is forward-only and rejects unresolved/ambiguous/orphaned legacy evidence rather than silently dropping it.

After canonicalization:

- Blog body and Exhibition description use central Markdown/Rich Text media references;
- structured Journal media roles are Cover/Gallery only;
- legacy inline role/token runtime is gone.

### Exhibition postal address cutover

A forward migration adds optional `exhibitions.postal_code` to both tracked
`public` and `committed` schemas. Local source records are deliberately not
rewritten as part of this schema migration: use the explicit read-only
`exhibitions:normalize-addresses` preview, then `--apply` for unambiguous
legacy strings only. Old publication checkpoints keep their original schema
fingerprints and cannot be considered restorable without an explicitly
reviewed compatible schema migration; do not falsify historical hashes or
silently reset local audit/publication histories.

### Exhibition presentation/restore canonicalization

A later forward migration establishes the explicit Exhibition presentation contract:

- `gallery_enabled`;
- `map_enabled`;
- the historical `map_shape` (`wide`/`square`) column, now ignored by runtime/editorial code and retained only for Publication snapshot schema compatibility until a coordinated future schema cutover;
- deterministic handling of historical archived rows missing `archived_from_state`;
- conservative normalization of address values where `location_text` only duplicated structured city/country information.

Historical archived rows with missing previous state infer:

- `published_at` present → previous state `published`;
- otherwise → `draft`.

Runtime restore still validates current publication readiness and safely falls back to Draft when a historical record cannot currently satisfy Published readiness.

### Publication migration stabilization

The Publication bootstrap migration freezes the tracked tables and audit entity types that existed when that migration was introduced. It does not import today's mutable `PublicationSnapshot` constants. Later changes to tracked schema/state are expressed by later forward migrations.

`blog_settings` was not legacy-site evidence. It was an application-owned table from the original dedicated Blog settings model. Its canonical Blog/Journal values were already normalized into `site_sections` / `journal_settings`, it was removed from runtime/Publication authority, and its obsolete `RESTRICT` foreign key was detached before a forward migration retired the table. Historical migrations still mention it only to reconstruct that schema evolution.

Likewise, canonical Exhibition Cover/Gallery usage lives in `journal_entry_media`. The older `exhibition_media` table has no runtime model or writer and is retained only while the current Publication snapshot schema still needs that historical table shape. Its eventual physical drop is a Publication-schema cutover, not a normal runtime cleanup.

The detailed rules are in [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md).

## 5. Journal template retention

The active Journal template is presentation/editorial state, not a destructive data conversion.

Switching Blog ↔ Exhibitions:

- retains BlogPost rows;
- retains Exhibition rows;
- does not convert entries between models;
- does not delete inactive-template content;
- reactivates retained content when switched back.

Media reference/deletion accounting therefore includes both retained entry worlds for a Journal SiteSection even while only one template is public/active.

## 6. Protected Validation and editorial authority

Protected Validation PostgreSQL and canonical `MediaAsset` originals are durable **authoritative pre-release editorial work** following the original import. The artist can intentionally edit Gallery ordering, content, pages, media assignments, navigation, identity and settings there. Ordinary `dev`/Validation deployments must preserve this content and evolve its schema through **forward migrations**, not reset the database from old Legacy.

Validation remains strictly separate from Production writable infrastructure: PostgreSQL, original media, secrets, authenticated/non-public ingress and mail/analytics runtime. The **local development/test database** is not a cutover source. Read-only Matomo Reporting on Validation is a separate operational choice while browser tracking remains disabled.

Legacy snapshots still establish provenance; later Legacy changes must be compared as reviewed deltas, not silently applied over deliberate artist changes. A clean Legacy replay stays useful for isolated migration rehearsals but is **not** mandatory for Production's final editorial presentation.

**v0.5 data gate:** freeze the precise artist-approved Validation database and matching original-media corpus as one consistent, recoverable candidate. Reconstruct a **new isolated Production database and media set**, never repurpose the running Validation database. A reviewed content-versus-runtime transfer/sanitization matrix must exclude or transform environment-specific secrets, sessions, tokens, mail settings and test-only material while preserving intended Working/LIVE, Publication history, references, asset identity/checksums and required recovery evidence. Validate the complete resulting snapshot and rollback before any traffic cutover. The application-side obligation is described here; platform restore/backup mechanics belong to [server-platform](https://github.com/Wiiii90/server-platform). Neither schema compatibility nor curator approval is a claim of performed PostgreSQL recovery.


## 7. Application acceptance and candidate promotion

The earlier v0.3 browser/product gate is a **historical acceptance checkpoint**. It does not transfer acceptance to the still-evolving v0.4 candidate. Current admin/public browser and domain changes must be reviewed against the exact current `dev` source and the user's explicit acceptance; old acceptance comments or stale issues cannot close an active v0.4 requirement.

For the release-candidate line:

1. complete v0.4 product/browser acceptance, documentation and cleanup; identify **the exact accepted `dev` SHA** rather than presuming that the current head is already accepted;
2. promote **only that accepted SHA** to protected `main`;
3. let the canonical `main` release workflow verify and publish the immutable image for the exact promoted SHA;
4. deploy isolated Validation from that same `main` SHA/image digest through the platform contract;
5. perform the remaining release-candidate/environment checks there.

Continue browser/editorial reconciliation while v0.4 acceptance remains open. A technically running candidate, green automated check or accepted earlier milestone does **not** establish acceptance of this candidate; the user explicitly decides when the product pass is complete.

Detailed admin UI expectations are in [`../ui-skills.md`](../ui-skills.md).

## 8. Release-candidate validation

For an exact candidate SHA:

1. complete appropriate repository verification;
2. produce/verify exact immutable image;
3. update isolated Validation through platform contract;
4. verify `/app-release.json` identity;
5. apply required forward migrations;
6. run `php artisan media:verify`;
7. run `legacy:validate` when frozen migration dataset is part of the gate;
8. run smoke checks;
9. perform required public/admin browser acceptance;
10. classify blockers before cutover consideration.

A green validator/CI run is not browser/editorial acceptance.

## 9. Representative browser acceptance

### Public

- Home, Galleries, Artwork detail/viewer;
- Custom Pages including CV/Contact composition;
- Blog/Exhibitions Journals;
- responsive navigation and representative migrated content;
- media delivery/variants and viewer interaction.

### Admin

- Dashboard;
- Pages typed tree/navigation;
- Home presentation modes/editorial controls;
- Gallery/Artwork contact sheet/editing/ordering/dialog flows;
- Journal Blog/Exhibitions including template switch, restore, ordering, Exhibition Gallery/Map/editor flow;
- Custom/CV/Contact hierarchy and bulk/ordering flows;
- Storage search/upload/preview/reference/capacity/reclaim behavior;
- General settings persistence;
- Analytics degraded/real data behavior;
- Activity and shared dialog behavior.

## 10. Pre-cutover gate

Before Production traffic changes:

- final SHA/image/digest recorded;
- canonical application verification and browser/editorial acceptance complete for approved scope;
- migration/media reconciliation green;
- fresh recoverable Production backup exists;
- restore/rollback procedure proven/current;
- intended Production DB/media state reconstructed and verified from the **frozen, approved Validation content plus matching originals**, after controlled environment sanitation;
- monitoring/health checks green;
- required mail/Matomo/DNS/TLS dependencies ready;
- no unresolved high-severity blocker.

Cutover is explicit platform/operator action and never triggered merely by merging application code.

## 11. Cutover

At application-contract level:

1. preserve pre-change recoverable state;
2. deploy exact approved immutable image;
3. run required migrations once under platform gate;
4. attach the **independently restored and verified** Production database/media derived from the approved Validation snapshot;
5. verify release identity + `/up`;
6. run media/application smoke checks;
7. switch/confirm public traffic only after checks succeed;
8. retain rollback capability through stabilization.

The application never mutates/reimports the legacy application automatically.

## 12. Post-cutover and retirement

After cutover:

- monitor public/admin/contact/analytics health;
- verify backups include new authoritative PostgreSQL/media state;
- resolve Production findings through normal releases;
- retire legacy runtime/data only after explicit retirement acceptance and recovery requirements are satisfied.

After explicit legacy retirement, true migration-only legacy evidence may be archived/removed in a dedicated cleanup once its recovery/reconciliation purpose has ended. Superseded application-owned schema such as the former `blog_settings` table is retired independently through normal forward migrations and is not gated on legacy-site retirement.
