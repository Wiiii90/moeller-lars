# Migration plan

## 0. Freeze and safeguard

1. Record live URLs, screenshots at desktop and mobile sizes, redirects, sitemap, robots settings, and external integrations.
2. Export the legacy database and media only into encrypted, access-controlled backup storage; do not commit either to Git.
3. Rotate any legacy credentials that have appeared in source control before creating a public archival repository.
4. Inspect the live server's runtime, DNS, TLS, hosting cost, deployment hook, backup mechanism, and replacement options without changing production.

## 1. Characterise the visitor experience

Create a route-and-content checklist from `larsmoeller`. Treat it as acceptance criteria for the new templates, while recording broken legacy behaviours as defects to fix rather than compatibility requirements. Capture artwork categories, ordering, image sizes, artwork viewer interactions, CV rendering, contact form behaviour, and all non-empty public pages. Record subtle visual/UX improvements separately so they can be reviewed without obscuring the identity-preservation requirement.

## 2. Design the clean model and importer

Map legacy artwork tables and text-based CV content to the new model. Exhibitions must be modelled and migrated separately from CV entries. Import legacy content and media losslessly into a fresh target database repeatedly until record counts, original-media checksums, file sizes, ordering, required fields, and rendered text match the reconciliation inventory. Retain original media even when derivatives are generated. The legacy schema is not preserved; legacy data is input only and legacy PHP is not a dependency.

## 3. Build vertical slices

Implement in this order:

1. read-only public gallery, reliable artwork viewer, and page templates;
2. artwork/media editorial flow;
3. separate CV and exhibitions workflows;
4. blog drafts and preview with public visibility disabled by default;
5. authentication, audit log, and self-hosted Matomo integration;
6. contact form and final SEO/redirects;
7. production operations: TLS, deployment, backups, restore, monitoring, and rollback.

Every slice receives a public regression comparison and an admin permission test. Viewer tests include keyboard/touch navigation and previous/next artwork where implemented. Migration tests reconcile counts and checksums after each repeatable import. Blog tests prove that no public blog route or navigation is visible until Lars explicitly enables it.

## 4. Staging and cutover

Deploy the selected application under a staging hostname with TLS. Import a fresh content copy, deploy and validate self-hosted Matomo, rehearse backup/restore and rollback, obtain editorial sign-off, back up production, lower DNS TTL in advance, then switch traffic. Keep the old deployment intact for a defined rollback window. If the current server cannot satisfy the runtime, TLS, backup, cost, or deployment requirements, evaluate and document a server replacement before cutover.

## Migration acceptance checklist

- Every legacy artwork and required content record has a target record or an explicitly documented exception.
- Every original media file is present in target storage and passes checksum/count reconciliation; derivative generation never replaces the original.
- Fresh target-database imports are repeatable and do not require the legacy schema at runtime.
- Public routes, artwork viewer behaviour, metadata, and redirects pass the approved comparison suite.
- Admin publication states, separate CV/exhibition editing, and blog-disabled defaults pass acceptance tests.
- Staging proves HTTPS, deployment, Matomo operation, backups, restore, rollback, and monitoring before production is changed.

## Source use policy

`glassygallery` is an exploration source only. Its data-model ideas and deployment notes may inform decisions, but its website builder and current implementation must not be merged wholesale. Its public API/auth assumptions require independent security review.
