# Migration plan

## 0. Freeze and safeguard

1. Record live URLs, screenshots at desktop and mobile sizes, redirects, sitemap, robots settings, and external integrations.
2. Export the legacy database and media only into encrypted, access-controlled backup storage; do not commit either to Git.
3. Rotate any legacy credentials that have appeared in source control before creating a public archival repository.
4. Inspect the live server's deployment hook, PHP/runtime version, DNS, TLS, and backup mechanism without changing production.

## 1. Characterise the visitor experience

Create a route-and-content checklist from `larsmoeller`. Treat it as acceptance criteria for the new templates. Capture artwork categories, ordering, image sizes, CV rendering, contact form behaviour, and all non-empty public pages.

## 2. Design the clean model and importer

Map legacy artwork tables and text-based CV content to the new model. Import into a local/staging database repeatedly until counts, media checksums, ordering, and text formatting match the inventory. Legacy data is input only; legacy PHP is not a dependency.

## 3. Build vertical slices

Implement in this order:

1. read-only public gallery and page templates;
2. artwork/media editorial flow;
3. CV and exhibitions;
4. blog;
5. authentication, audit log, and analytics;
6. contact form and final SEO/redirects.

Every slice receives a public regression comparison and an admin permission test.

## 4. Staging and cutover

Deploy the new app under a staging hostname with TLS. Import a fresh content copy, obtain editorial sign-off, back up production, lower DNS TTL in advance, then switch traffic. Keep the old deployment intact for a defined rollback window.

## Source use policy

`glassygallery` is an exploration source only. Its data-model ideas and deployment notes may inform decisions, but its website builder and current implementation must not be merged wholesale. Its public API/auth assumptions require independent security review.
