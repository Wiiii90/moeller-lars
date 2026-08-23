# Migration and cutover plan

The application is no longer in an early build phase. Remaining migration work is protected-state reconciliation, browser/editorial acceptance, production-readiness gating, cutover and eventual legacy retirement.

Production/Validation placement, backups, deployment and rollback are owned by [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform).

## 1. Source boundary

Migration inputs are read-only legacy evidence:
- legacy Artwork/category tables and ordering;
- reviewed Vita/CV source + portrait;
- legacy public media required by the artist-site target;
- legacy route/presentation evidence used for comparison.

The legacy `/workshop` application/database is outside the artist-site content target. It remains a platform rollback/retirement concern until explicitly retired.

Never commit source DB dumps, Production media, credentials or secret-bearing configuration.

## 2. Canonical target

The target is the current Laravel/PostgreSQL application model, not the legacy schema.

Normalization:
- legacy artwork categories → **Gallery** persistence + Gallery Site Nodes;
- legacy Home → **Home**;
- legacy Blog → **Journal / Blog**;
- legacy Exhibitions → **Journal / Exhibitions**;
- legacy CV/Vita placement/content → **Custom Page** composition + retained migration provenance;
- legacy Contact content/placement → reusable **Contact component** inside Custom Page composition; no standalone Contact runtime node;
- original media → canonical `MediaAsset` originals with checksum/provenance.

Historical persistence names are not restored as runtime compatibility aliases.

## 3. Vita/CV/Exhibitions reconciliation

The reviewed Vita source contains 31 normalized rows. The accepted target accounting is:
- 2 Biography rows;
- 29 first-class Exhibition rows;
- total source accounting remains 31/31;
- portrait/profile media provenance remains explicit;
- Exhibition content must not remain duplicated in CV after normalization.

Existing protected Validation data is transformed through forward migrations/reconciliation. Do not rerun the source importer into canonical non-empty data merely to apply later schema/domain changes.

See [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md).

## 4. Protected Validation data

A reviewed protected Validation database/media set is canonical non-production application state.

Validation remains isolated from Production writable state:
- separate PostgreSQL data;
- separate authoritative media;
- separate application secrets;
- authenticated/non-public ingress.

Validation may use a restricted read-only Matomo reporting identity while browser tracking stays disabled. This does not permit application DB/media sharing.

## 5. Release-candidate validation

For an exact candidate SHA:

1. complete repository CI;
2. produce/verify the immutable GHCR image for that exact SHA;
3. update isolated Validation through the platform contract;
4. verify `/app-release.json` release identity;
5. apply required forward migrations;
6. run `php artisan media:verify`;
7. run `legacy:validate` when the frozen migration dataset is part of the gate;
8. run application smoke checks;
9. perform required public/admin browser acceptance;
10. classify blocking findings before the candidate is considered for cutover.

A green validator or CI run is not browser/editorial acceptance.

## 6. Current browser/editorial gate

Representative acceptance must cover the current product, not old implementation shapes:

### Public
- Home, Galleries, Artwork detail/viewer;
- Custom Pages including CV/contact composition;
- Blog/Exhibitions Journals;
- responsive navigation and representative migrated content;
- media delivery/variants and viewer interaction.

### Admin
- Dashboard as real site/admin overview rather than Pages/Gallery duplicate;
- Pages typed tree/navigation;
- Gallery Contact Sheet and repaired Add/Existing-Media/batch/dialog flows;
- Files search/upload/preview/reference behavior across supported media kinds;
- General settings under the canonical event-driven no-timer persistence contract;
- Analytics real-data/degraded behavior;
- Storage with real operator-configured allowance;
- Activity and any persistent Preview/Commit/Settings shell utilities that are part of the accepted candidate;
- shared dialog/overlay behavior across representative editor flows.

Detailed legacy public evidence remains in [LEGACY-PUBLIC-CONTRACT.md](LEGACY-PUBLIC-CONTRACT.md) until retirement.

## 7. Pre-cutover gate

Before Production traffic changes:
- final application SHA/image/digest recorded;
- application CI + protected Validation acceptance green enough for the explicitly approved release scope;
- migration/media reconciliation green;
- artist/editorial acceptance complete;
- fresh recoverable Production backup exists;
- restore/rollback procedure proven/current;
- intended Production DB/media state confirmed;
- monitoring/health checks green;
- required mail/Matomo/DNS/TLS dependencies ready;
- no unresolved high-severity application/platform blocker.

Cutover is an explicit platform/operator action and is never triggered by merging application code.

## 8. Cutover

At application-contract level:

1. preserve a pre-change recoverable state;
2. deploy the exact approved immutable image;
3. run required migrations exactly once under the platform gate;
4. attach authoritative Production DB/media;
5. verify release identity + `/up`;
6. run media/application smoke checks;
7. switch/confirm public traffic only after checks succeed;
8. retain rollback capability through stabilization.

The application container never mutates the legacy application automatically and never runs a legacy import on startup.

## 9. Post-cutover and retirement

After cutover:
- monitor public/admin/contact/analytics health;
- verify backups include new authoritative PostgreSQL/media state;
- resolve Production-only findings through normal releases;
- retire legacy runtime/data only after explicit retirement acceptance and retained recovery requirements are satisfied.

After legacy retirement, migration-only evidence such as `LEGACY-PUBLIC-CONTRACT.md` and `SOURCE-INVENTORY.md` may be archived/removed in a dedicated cleanup rather than remaining active architecture documentation.