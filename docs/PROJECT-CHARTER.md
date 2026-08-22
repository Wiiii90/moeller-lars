# Project charter

## Product goal

`moeller-lars` is the secure, maintainable replacement application for the Lars Möller artist website and its artist-facing administration.

The public site preserves Lars Möller's artistic identity and established presentation while replacing the legacy application's security model, administration, persistence, analytics integration, migration tooling and release process.

## Public contract

Non-negotiable principles:

- preserve approved public content, artwork presentation and meaningful information architecture;
- preserve the site's recognisable artistic visual language rather than redesigning it into a generic portfolio/CMS theme;
- use clean path-based canonical URLs; legacy PHP/query syntax is not itself a compatibility requirement;
- keep HTTPS canonical and do not expose debug/admin/development surfaces publicly;
- preserve meaningful artwork ordering and media/ALT semantics through explicit canonical data;
- keep the Artwork viewer reliable across desktop/mobile/touch/keyboard interaction;
- treat broken or unsafe legacy behavior as a defect, not a compatibility requirement;
- require browser/editorial acceptance in addition to automated route/data checks before Production cutover.

## Site structure

The editable public site uses five typed node concepts:

- **Home**
- **Gallery**
- **Journal** (Blog or Exhibitions)
- **Custom Page**
- **Navigation Node**

The migrated CV/Vita and Contact placements are Custom Pages; Blog and Exhibitions are Journal templates. The application does not hard-code those migrated slugs as special runtime page types.

## Artist administration

`/admin` is a purpose-built authenticated editorial application, not a general-purpose site builder.

Core surfaces:

1. **Pages / Site Structure** — typed placement, navigation, hierarchy, ordering and publication.
2. **Home** — homepage Gallery eligibility and current/latest presentation state.
3. **Galleries / Artworks** — Artwork drafts, metadata, primary media, publication, ordering and Gallery movement.
4. **Blog Journal** — Blog drafts/publication/scheduling and Journal settings.
5. **Exhibitions Journal** — Exhibition records, dates/location/media/links and ordering.
6. **Custom Pages** — structured safe content including migrated CV/Contact surfaces.
7. **Media** — canonical reusable originals, metadata, previews, usage references and guarded deletion.
8. **General / Contact** — site identity, public/private contact settings, social links, legal text and delivery readiness without infrastructure secrets.
9. **Analytics** — privacy-conscious Matomo reporting plus clearly separate operational metrics.
10. **Activity / Storage / Dashboard** — relevant administrative/audit/capacity overview without becoming an infrastructure control panel.

## Security

- `/admin` requires authenticated/authorized access.
- Authorization is enforced server-side for mutations.
- CSRF/session/rate-limit protections use the application security boundary rather than UI visibility.
- Uploads are untrusted until validated.
- Unsafe rich text/links are rejected or sanitized through canonical policies.
- Secrets, private dumps and authoritative production media stay outside Git.
- Legacy authentication, credentials, SQL helpers, sessions and upload code are never reused.

## Media

Canonical uploaded/migrated originals are retained and checksum-addressable through application storage identity. Derivatives are rebuildable.

References are explicit. A media asset cannot be destructively deleted while supported content still references it. Publication must not hide missing required media/ALT/derivative integrity through arbitrary fallbacks.

## Analytics

Self-hosted Matomo Community/Core is the canonical source for human visitor analytics. Application-local aggregates cover operational/error/bot/performance signals only.

No mandatory paid analytics plugin or SaaS is required. Analytics availability must not become a dependency for public rendering or normal admin editing.

## Cost and operational boundary

Avoid mandatory commercial runtime/SaaS dependencies where practical. Hosting/operations cost is allowed but should remain minimized and justified against reliability, backup, security and maintenance requirements.

This repository owns the application/release contract. [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform) owns mutable Production/Validation infrastructure, secrets, ingress, backups, monitoring, deployment and rollback.

## Out of scope

- general-purpose free-form website builder;
- public user registration/customer accounts;
- marketplace/social-network functionality;
- migration of legacy credentials/users/sessions;
- mandatory commercial analytics/runtime services;
- infrastructure topology/control surfaces inside the application admin;
- preserving legacy bugs or unsafe implementation details.

## Release acceptance

A release is not accepted merely because CI is green.

Before Production cutover, the approved exact SHA/image must pass the applicable gates:

- durable automated application/security/data tests;
- migration/media reconciliation;
- isolated Validation deployment and release identity verification;
- representative admin functional acceptance;
- representative public/browser/viewer comparison;
- artist/editorial approval;
- platform backup/restore/rollback/readiness checks.

Production deployment remains an explicit authorized platform action.

## Documentation ownership

Current application contracts live under `docs/` and are indexed by [docs/README.md](README.md). Legacy-source evidence is kept separate and may be retired after explicit legacy retirement rather than allowed to define future application architecture.
