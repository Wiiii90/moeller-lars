# Migration and cutover plan

The application is no longer in an early build phase. The current v0.3 admin/browser product-composition gate is accepted; remaining release work is exact-candidate promotion to `main`, isolated Validation/release-candidate qualification, production-readiness gating, cutover and eventual legacy retirement.

Production/Validation placement, backups, deployment and rollback are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

## 1. Source boundary

Original **legacy provenance inputs** (not the final desired editorial presentation) are read-only evidence:

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

After the original source import, the **artist-curated protected Validation content and matching originals** are the intended v0.5 Production-content candidate. Its approval and content freeze, rather than a new automatic Legacy import, determine the final editorial structure. See [#209](https://github.com/Wiiii90/moeller-lars/issues/209).

## 3. Vita/CV/Exhibitions source accounting

Reviewed Vita source contains 31 normalized rows:

- 2 Biography/CV rows;
- 29 Exhibition rows;
- total accounting 31/31;
- portrait/profile provenance explicit;
- Exhibition content not duplicated in CV after normalization.

Existing protected canonical data is transformed through forward migrations/reconciliation. Do not rerun source import into non-empty canonical data to apply later domain/schema changes. The 31-row inventory proves original provenance; editorially approved Biography/Exhibition changes on Validation may legitimately change final target counts or presentation, provided each source row remains accounted for. See [#31](https://github.com/Wiiii90/moeller-lars/issues/31).

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

The protected Validation database and canonical MediaAsset originals are **durable, authoritative pre-release editorial work** after the original Legacy import. Lars may deliberately change Gallery order, content, images, page composition, navigation, identity and settings there. Intermediate `dev`/Validation deployments must **preserve this state** and evolve its schema via compatible forward migrations; Validation is never reset just because another preview image was built.

Validation remains isolated from Production writable state: separate PostgreSQL, authoritative media, application secrets and authenticated/non-public ingress. The local development/test database is *not* a source for cutover.

The original Legacy snapshot remains provenance and source-accounting evidence, and later Legacy changes are separately reconciled as reviewed deltas. Neither can silently override the artist's intentional Validation edits. Validation may use restricted read-only Matomo Reporting while tracking stays disabled.

**Final v0.5 cutover source:** freeze and approve the exact edited Validation content and matching original-media corpus. Reconstruct a **fresh isolated Production target** from a consistent protected snapshot; never point Production directly at the running Validation database or blindly transfer credentials, sessions, tokens, mail/runtime settings and testing artifacts. Retain publication/Working/LIVE relationships, referential integrity, historical recovery evidence as appropriate, and original-media checksums. The reviewed content-vs-runtime transfer matrix and sanitization are part of #209.

A fresh Legacy import remains useful in isolated migration rehearsals, but is no longer the mandatory *content source* for the final artist-approved Production presentation.

## 7. Application acceptance and candidate promotion

The current v0.3 admin/browser composition has completed its product-acceptance pass. Historical issue comments describing older rejected browser candidates are evidence of those earlier states, not instructions to recreate defects that are no longer present in the accepted current candidate.

For the release-candidate line:

1. keep the accepted candidate on the exact current `dev` integration line while durable documentation and issue bookkeeping are completed;
2. promote that exact accepted `dev` head to protected `main`;
3. let the canonical `main` release workflow verify and publish the immutable image for the exact promoted SHA;
4. deploy isolated Validation from that same `main` SHA/image digest through the platform contract;
5. perform the remaining release-candidate/environment checks there.

Further local/protected browser-reconciliation cycles remain valid when a concrete regression or later milestone changes presentation, but they are not a standing blocker once the accepted milestone composition is complete. A technically running candidate still does not establish future browser/product acceptance by itself.

Detailed admin UI expectations are in [Admin UI contract](ADMIN-UI.md).

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
- intended Production DB/media state reconstructed and verified from the artist-approved frozen Validation dataset, with safe environment-specific sanitation;
- monitoring/health checks green;
- required mail/Matomo/DNS/TLS dependencies ready;
- no unresolved high-severity blocker.

Cutover is explicit platform/operator action and never triggered merely by merging application code.

## 11. Cutover

At application-contract level:

1. preserve pre-change recoverable state;
2. deploy exact approved immutable image;
3. run required migrations once under platform gate;
4. attach the isolated, verified Production target reconstructed from approved Validation database and matching original media;
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
