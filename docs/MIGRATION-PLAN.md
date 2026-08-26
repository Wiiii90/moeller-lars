# Migration and cutover plan

The application is no longer in an early build phase. Remaining work is protected-state reconciliation, browser/editorial acceptance, production-readiness gating, cutover and eventual legacy retirement.

Production/Validation placement, backups, deployment and rollback are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

## 1. Source boundary

Migration inputs are read-only legacy evidence:

- legacy Artwork/category tables and ordering;
- reviewed Vita/CV source + portrait;
- legacy public media required by the artist-site target;
- legacy route/presentation evidence used for comparison.

The legacy `/workshop` application/database remains outside the artist-site content target. Never commit source DB dumps, Production media, credentials or secret-bearing configuration.

## 2. Canonical target

Normalization:

- legacy artwork categories → **Gallery** persistence + Gallery Site Nodes;
- legacy Home → **Home** presentation state;
- legacy Blog → **Journal / Blog**;
- legacy Exhibitions → **Journal / Exhibitions**;
- legacy CV/Vita placement/content → **Custom Page/CV composition** + retained provenance;
- legacy Contact content/placement → reusable **Contact component**;
- original media → canonical `MediaAsset` originals with checksum/provenance.

Historical persistence names are not restored as runtime compatibility aliases.

## 3. Vita/CV/Exhibitions source accounting

Reviewed Vita source contains 31 normalized rows:

- 2 Biography/CV rows;
- 29 Exhibition rows;
- total accounting 31/31;
- portrait/profile provenance explicit;
- Exhibition content not duplicated in CV after normalization.

Existing protected canonical data is transformed through forward migrations/reconciliation. Do not rerun source import into non-empty canonical data to apply later domain/schema changes.

## 4. Forward canonicalization after initial import

Current protected-state evolution includes deliberate forward migrations beyond the original import.

### Journal Rich Text media canonicalization

Legacy Journal embedded-image/runtime usage was converted to the central Markdown `media:<id>` contract. The migration is forward-only and rejects unresolved/ambiguous/orphaned legacy evidence rather than silently dropping it.

After canonicalization:

- Blog body and Exhibition description use central Markdown/Rich Text media references;
- structured Journal media roles are Cover/Gallery only;
- legacy inline role/token runtime is gone.

### Exhibition presentation/restore canonicalization

A later forward migration establishes the explicit Exhibition presentation contract:

- `gallery_enabled`;
- `map_enabled`;
- `map_shape` (`wide`/`square`);
- deterministic handling of historical archived rows missing `archived_from_state`;
- conservative normalization of address values where `location_text` only duplicated structured city/country information.

Historical archived rows with missing previous state infer:

- `published_at` present → previous state `published`;
- otherwise → `draft`.

Runtime restore still validates current publication readiness and safely falls back to Draft when a historical record cannot currently satisfy Published readiness.

## 5. Journal template retention

The active Journal template is presentation/editorial state, not a destructive data conversion.

Switching Blog ↔ Exhibitions:

- retains BlogPost rows;
- retains Exhibition rows;
- does not convert entries between models;
- does not delete inactive-template content;
- reactivates retained content when switched back.

Media reference/deletion accounting therefore includes both retained entry worlds for a Journal SiteSection even while only one template is public/active.

## 6. Protected Validation data

Reviewed protected Validation DB/media is canonical non-production application state.

Validation remains isolated from Production writable state: separate PostgreSQL, authoritative media, application secrets and authenticated/non-public ingress.

Validation may use restricted read-only Matomo Reporting while tracking stays disabled.

## 7. Current browser/editorial reconciliation gate

Before release-candidate qualification, admin/browser work may be reconciled on a temporary combined branch and reviewed locally/protected Validation.

The important rule is **one coherent browser cycle**, not one deployment/build per worker:

1. collect complete browser feedback for a review slice;
2. create focused worker side branches from an exact base where parallelism is useful;
3. independently review actual diffs;
4. reconcile accepted work and shared-file unions;
5. run required forward migration(s) against isolated preview data;
6. build/recreate one combined browser candidate;
7. continue browser acceptance.

A technically running candidate is not product accepted. Current browser feedback can reject UI that is functionally present but inconsistent/unusable.

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
- Files search/upload/preview/reference behavior;
- General settings persistence;
- Analytics degraded/real data behavior;
- Storage/Activity and shared dialog behavior.

## 10. Pre-cutover gate

Before Production traffic changes:

- final SHA/image/digest recorded;
- canonical application verification and browser/editorial acceptance complete for approved scope;
- migration/media reconciliation green;
- fresh recoverable Production backup exists;
- restore/rollback procedure proven/current;
- intended Production DB/media state confirmed;
- monitoring/health checks green;
- required mail/Matomo/DNS/TLS dependencies ready;
- no unresolved high-severity blocker.

Cutover is explicit platform/operator action and never triggered merely by merging application code.

## 11. Cutover

At application-contract level:

1. preserve pre-change recoverable state;
2. deploy exact approved immutable image;
3. run required migrations once under platform gate;
4. attach authoritative Production DB/media;
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

After explicit legacy retirement, migration-only evidence may be archived/removed in a dedicated cleanup.
