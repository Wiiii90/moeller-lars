# Migration and cutover plan

The application build is no longer in an early vertical-slice phase. The remaining migration work is release-candidate validation, source/target reconciliation, browser/editorial acceptance, production cutover and eventual legacy retirement.

This plan does not define platform implementation. Production/Validation placement, backups, deployment and rollback are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

## 1. Source boundary

Migration inputs are read-only legacy evidence:

- legacy artwork database tables and their public ordering;
- the reviewed Vita/CV source and portrait;
- legacy public media required by the artist-site target;
- legacy route/presentation evidence used for comparison.

The legacy `/workshop` application/database is outside the artist-site content target. It remains a rollback/retirement concern until the platform explicitly retires it.

Never commit source database dumps, production media, credentials or secret-bearing configuration.

## 2. Canonical target

The target is the current Laravel/PostgreSQL model, not the legacy schema.

Legacy content is normalized into current application concepts:

- artwork categories → **Gallery** persistence plus Gallery Site Nodes;
- legacy Home → **Home** Site Node;
- legacy Blog → **Journal / Blog**;
- legacy Exhibitions → **Journal / Exhibitions**;
- legacy Vita/CV placement → **Custom Page** with structured migrated content;
- legacy Contact placement → **Custom Page** with the contact component;
- original media → canonical `MediaAsset` originals with checksum/provenance.

`SiteNodeType` and `JournalTemplate` define runtime behavior. Historical `SiteSection` type strings are not restored as compatibility aliases.

## 3. Import and reconciliation

A fresh target import must be repeatable and must reconcile:

- artwork counts by source/target grouping;
- canonical original-media counts, byte sizes and SHA-256 checksums;
- required media relationships and ALT semantics;
- explicit artwork/site ordering;
- Vita/CV/Exhibition source-row accounting;
- canonical Site Node projection;
- representative rendered public content.

The reviewed Vita source contains 31 rows and remains partitioned into the approved canonical content without duplication. The detailed invariant is kept in [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md).

`php artisan legacy:validate <manifest>` is a migration-reconciliation tool for the frozen source snapshot. It is not a normal application startup action.

## 4. Protected Validation data

Once a reviewed import exists in the protected Validation environment, it is canonical non-production application data.

Do not solve schema changes by deleting that database or rerunning the source importer into non-empty canonical tables. Evolve it through normal forward Laravel migrations and read-only reconciliation.

Validation must remain isolated from Production writable state:

- separate PostgreSQL data;
- separate authoritative media;
- separate application secrets;
- separate authenticated ingress.

Validation may use explicitly restricted read-only Matomo aggregate reporting while browser tracking remains disabled; this does not permit application-database/media sharing.

## 5. Release-candidate validation

For an exact candidate SHA:

1. Complete repository CI.
2. Produce/verify the immutable GHCR image for that exact SHA.
3. Update the isolated Validation application through the platform deployment contract.
4. Confirm `/app-release.json` reports the expected SHA.
5. Run required forward migrations.
6. Run `php artisan media:verify`.
7. Run the migration validator when validating the frozen imported dataset.
8. Run the application release smoke contract.
9. Perform browser acceptance across public and admin surfaces.
10. Resolve every blocking difference before designating the candidate for cutover.

A green data validator is not browser/editorial acceptance. A green CI run is not production authorization.

## 6. Browser and editorial acceptance

Acceptance should exercise representative real workflows rather than only route existence:

- public Home, Galleries, Artwork viewer/direct routes, Custom Pages and Journals;
- responsive header/navigation and representative migrated content;
- admin Pages tree and typed destinations;
- Home and Gallery workspaces;
- Artwork create/edit/publish/move/reorder flows;
- Blog and Exhibitions Journals;
- General/Contact/Media/Analytics/Storage surfaces;
- Preview behavior and public/non-public separation.

The detailed legacy public evidence remains in [LEGACY-PUBLIC-CONTRACT.md](LEGACY-PUBLIC-CONTRACT.md) until legacy retirement.

## 7. Pre-cutover gate

Before Production traffic changes:

- final candidate SHA/image/digest are recorded;
- application CI and Validation acceptance are green;
- migration/media reconciliation is green;
- artist/editorial acceptance is complete;
- a fresh recoverable Production backup exists under the platform contract;
- restore/rollback procedure is proven and current;
- Production media/database readiness is confirmed;
- monitoring and health checks are green;
- any required DNS/TLS/mail/Matomo dependencies are ready.

Cutover remains an explicit platform/operator action. It is never triggered merely by merging application code.

## 8. Cutover

`server-platform` owns the exact operational sequence. At application level the required guarantees are:

1. preserve a pre-change recoverable state;
2. deploy the exact approved immutable image;
3. run required migrations exactly once under the platform gate;
4. attach the authoritative Production database/media;
5. verify release identity and `/up`;
6. run media/application smoke checks;
7. switch/confirm public traffic only after those checks succeed;
8. retain a rollback path until post-cutover stabilization is accepted.

The application container never mutates the legacy application automatically and never performs a legacy import on startup.

## 9. Post-cutover and legacy retirement

After cutover:

- monitor public/admin health and delivery/analytics integrations;
- verify backups include the new authoritative PostgreSQL/media state;
- resolve any production-only findings through normal releases;
- retire legacy runtime/data only after the explicit platform retirement gate and retained recovery requirements are satisfied.

Once legacy retirement is complete, `LEGACY-PUBLIC-CONTRACT.md`, `SOURCE-INVENTORY.md` and migration-only material can be archived or removed in a dedicated documentation cleanup.
