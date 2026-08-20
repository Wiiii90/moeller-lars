# Migration plan

## 0. Freeze and safeguard

1. Record live URLs, screenshots at desktop and mobile sizes, redirects, sitemap, robots settings, and external integrations.
2. Export the legacy database and media only into encrypted, access-controlled backup storage; do not commit either to Git.
3. Rotate any legacy credentials that have appeared in source control before creating a public archival repository.
4. Use the verified [server and operations baseline](SERVER-OPERATIONS-BASELINE.md) and authoritative [server-platform](https://github.com/Wiiii90/server-platform) contract for platform placement, ingress, recovery material, and deployment integration.
5. Record the cost baseline, avoid mandatory paid third-party services and commercial runtime dependencies, and prefer self-hosted/open-source components where practical; server/hosting options remain allowed only when minimized and justified.

## 1. Characterise the visitor experience

Create a route-and-content checklist from `larsmoeller`. Treat it as acceptance criteria for the new templates, while recording broken legacy behaviours as defects to fix rather than compatibility requirements. Capture artwork categories, ordering, image sizes, artwork viewer interactions, CV rendering, contact form behaviour, and all non-empty public pages that belong to the artist-site target. Record subtle visual/UX improvements separately so they can be reviewed without obscuring the identity-preservation requirement.

The legacy `/workshop` subtree and `larsMoellerWorkshop` database are explicitly outside the rebuilt artist-site migration target. They must not create target categories, routes, navigation, CV/content records, or media imports. They remain preserved only as part of the legacy rollback/recovery boundary until `server-platform` completes the explicit legacy-retirement gate; removal from the live host is not part of migration or validation.

## 2. Design the clean model and importer

Map legacy artwork tables and text-based Vita content to the new model. The reviewed `txt/vita.txt` inventory has exactly 31 source rows and the approved canonical split is exactly 2 Biography rows in `cv_entries` plus 29 Exhibition rows in `exhibitions`. These targets partition the 31 source rows; exhibitions must not remain duplicated in the CV target. The first Biography row retains the migrated Vita portrait relationship and the portrait asset retains source path/name, byte-size and SHA-256 provenance.

Import legacy content and media losslessly into a fresh target database repeatedly until record counts, original-media checksums, file sizes, ordering, required fields, portrait provenance, `SiteSection` projection and rendered text match the reconciliation inventory. Retain original media even when derivatives are generated. The legacy schema is not preserved; legacy data is input only and legacy PHP is not a dependency.

Protected Validation data is not disposable migration scratch space. Once the reviewed source import exists there, schema evolution must use normal forward application migrations and read-only reconciliation; do not solve changed target structure by deleting Validation data or re-running the source importer against non-empty canonical tables.

## 3. Build vertical slices

Implement in this order:

1. read-only public gallery, reliable artwork viewer, and page templates;
2. secure admin/authentication/session foundation, including authorization, CSRF protection, rate limits, and audit primitives;
3. artwork/media editorial flow;
4. separate CV and exhibitions workflows;
5. optional blog drafts and preview with public visibility disabled by default;
6. self-hosted Matomo integration and separate local bot/error/performance/operational aggregates;
7. contact form and final SEO/redirects;
8. production operations: TLS, deployment, backups, restore, monitoring, rollback, and cost review.

No writable admin slice may precede or bypass the secure admin/authentication/session foundation. Every subsequent editor receives an admin permission test and must use the established session, CSRF, rate-limit, and audit primitives. Every slice receives a public regression comparison. Viewer tests include keyboard/touch navigation and previous/next artwork where implemented. Migration tests reconcile counts and checksums after each repeatable import. Blog tests prove that no public blog route or navigation is visible until Lars explicitly enables it. Analytics tests prove Matomo/API/log-parser failure cannot break public rendering or normal admin functionality and that bot/operational metrics remain separate from human analytics.

## 4. Validation and cutover

Produce a deployable immutable application artifact/image, health/readiness contract, migrations and migration expectations, persistence declaration for PostgreSQL and authoritative media, and runtime/configuration contract. `server-platform` provides Production and the isolated non-production Validation runtime, ingress, deployment orchestration, resource limits, backup/restore integration, and rollback/cutover.

Validation and Production must remain separate in PostgreSQL, media, secrets and writable application state even when they share a physical host. Validation may use a restricted View-only Production Matomo Reporting API identity while `MATOMO_TRACKING_ENABLED=false` and `MATOMO_REPORTING_ENABLED=true`; this is aggregate analytics read access, not shared application persistence.

Validation remains mandatory before final cutover. A candidate update must preserve Validation data/media unless a separately authorized lifecycle action explicitly says otherwise. Run migrations, `media:verify`, `legacy:validate <reviewed-manifest>` and the release smoke contract against the isolated candidate, then perform the browser/public comparison and artist acceptance. Final cutover is coordinated with server-platform #11, backup/restore evidence with the platform backup/restore contracts, and platform readiness with #14. Production traffic is not switched merely because CI or Validation reconciliation is green.

## Migration acceptance checklist

- Every in-scope legacy artwork and required content record has a target record or an explicitly documented exception; the legacy Workshop subtree/database is an explicit out-of-scope retirement-only exception.
- The 31 reviewed Vita source rows reconcile exactly to 2 Biography + 29 Exhibition targets with no CV/Exhibition duplication, and the portrait relationship/provenance is intact.
- Every original media file is present in target storage and passes checksum/count reconciliation; derivative generation never replaces the original.
- The canonical `SiteSection` projection has exactly one Home/Vita/Blog/Exhibitions singleton and exactly one Gallery section per artwork category with matching hierarchy.
- Fresh target-database imports are repeatable and do not require the legacy schema at runtime; an already-populated protected Validation database evolves through forward migrations rather than destructive re-import.
- Public routes, artwork viewer behaviour, metadata, and redirects pass the approved comparison suite.
- Admin publication states, separate CV/exhibition editing, and blog-disabled defaults pass acceptance tests.
- Validation proves HTTPS, application deployment contract, Matomo operation, isolated analytics failure, backups/restore contract, rollback/readiness checks and monitoring before Production is changed.
- The deployment plan accounts for the verified non-Git production host and does not assume a historical Git hook or remote.
- CI/CD, recurring offsite backups, monitoring, Docker/Compose placement, and ingress are provided through the server-platform contract rather than reimplemented here.
- Analytics acceptance covers traffic sources, geography, devices, content interaction, and separate bot/error/performance/operational metrics without unnecessary raw identifiers.
- The secure admin/authentication/session foundation is proven before any writable artwork, media, CV, exhibition, or blog slice is accepted.
- Cost reconciliation documents recurring dependencies, confirms practical use of self-hosted/open-source components, and shows minimized and justified server/hosting cost.
- Browser/artist acceptance remains distinct from data reconciliation. A green migration report must not be used to close visual/editorial gates without the required evidence.

## Source use policy

`glassygallery` is an exploration source only. Its data-model ideas and deployment notes may inform decisions, but its website builder and current implementation must not be merged wholesale. Its public API/auth assumptions require independent security review.
