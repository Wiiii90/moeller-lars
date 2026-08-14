# Migration plan

## 0. Freeze and safeguard

1. Record live URLs, screenshots at desktop and mobile sizes, redirects, sitemap, robots settings, and external integrations.
2. Export the legacy database and media only into encrypted, access-controlled backup storage; do not commit either to Git.
3. Rotate any legacy credentials that have appeared in source control before creating a public archival repository.
4. Inspect the live server's runtime, DNS, TLS, hosting cost, deployment hook, backup mechanism, and replacement options without changing production.
5. Record the cost baseline and reject any additional software, licence, plugin, or SaaS dependency with a non-zero EUR cost; server/hosting options remain allowed only when minimized and justified.

## 1. Characterise the visitor experience

Create a route-and-content checklist from `larsmoeller`. Treat it as acceptance criteria for the new templates, while recording broken legacy behaviours as defects to fix rather than compatibility requirements. Capture artwork categories, ordering, image sizes, artwork viewer interactions, CV rendering, contact form behaviour, and all non-empty public pages. Record subtle visual/UX improvements separately so they can be reviewed without obscuring the identity-preservation requirement.

## 2. Design the clean model and importer

Map legacy artwork tables and text-based CV content to the new model. Exhibitions must be modelled and migrated separately from CV entries. Import legacy content and media losslessly into a fresh target database repeatedly until record counts, original-media checksums, file sizes, ordering, required fields, and rendered text match the reconciliation inventory. Retain original media even when derivatives are generated. The legacy schema is not preserved; legacy data is input only and legacy PHP is not a dependency.

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

## 4. Staging and cutover

Deploy the selected application under a staging hostname with TLS. Import a fresh content copy, deploy and validate self-hosted Matomo and its logical separation, rehearse backup/restore and rollback, obtain editorial sign-off, back up production, lower DNS TTL in advance, then switch traffic. Keep the old deployment intact for a defined rollback window. If the current server cannot satisfy the runtime, TLS, backup, cost, or deployment requirements, evaluate and document a server replacement before cutover. Confirm that additional software, licence, plugin, and SaaS cost is 0 EUR and that server/hosting spend is minimized and justified.

## Migration acceptance checklist

- Every legacy artwork and required content record has a target record or an explicitly documented exception.
- Every original media file is present in target storage and passes checksum/count reconciliation; derivative generation never replaces the original.
- Fresh target-database imports are repeatable and do not require the legacy schema at runtime.
- Public routes, artwork viewer behaviour, metadata, and redirects pass the approved comparison suite.
- Admin publication states, separate CV/exhibition editing, and blog-disabled defaults pass acceptance tests.
- Staging proves HTTPS, deployment, Matomo operation, isolated analytics failure, backups, restore, rollback, and monitoring before production is changed.
- Analytics acceptance covers traffic sources, geography, devices, content interaction, and separate bot/error/performance/operational metrics without unnecessary raw identifiers.
- The secure admin/authentication/session foundation is proven before any writable artwork, media, CV, exhibition, or blog slice is accepted.
- Cost reconciliation proves 0 EUR for additional software, licences, plugins, and SaaS, with minimized and justified server/hosting cost.

## Source use policy

`glassygallery` is an exploration source only. Its data-model ideas and deployment notes may inform decisions, but its website builder and current implementation must not be merged wholesale. Its public API/auth assumptions require independent security review.
