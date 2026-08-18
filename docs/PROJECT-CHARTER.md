# Project charter

## Goal

Replace the legacy Lars Möller website with a secure, maintainable application while preserving the public site's artistic identity, content meaning and recognisable presentation. Administration and operations are rebuilt rather than modernised in place.

The target is a Laravel application with a purpose-built artist administration surface, a normalized PostgreSQL data model, controlled media handling, privacy-conscious self-hosted analytics and an immutable application release consumed by `Wiiii90/server-platform`.

## Public product contract

- Preserve Lars Möller's artistic identity, meaningful information architecture, artwork/content integrity and intended ordering.
- Use clean modern canonical URLs. Legacy PHP/query URL syntax is source evidence, not a compatibility requirement by itself.
- Preserve the legacy-derived visual language closely enough to pass real-browser comparison; accessibility and reliability improvements are allowed where they do not redesign the site.
- Keep artwork categories data-driven. No category identity may require production-code branching.
- Treat direct artwork pages and the fullscreen artwork viewer as first-class public experiences.
- Keep CV/biography and Exhibitions as independent public/editorial domains.
- Keep Contact inside the CV/biography experience rather than adding another primary-navigation destination.
- Keep Blog publicly invisible until the artist explicitly enables it.
- Preserve canonical originals and migration provenance; generated derivatives are rebuildable.
- Broken legacy behaviour is not a compatibility requirement. Intended target behaviour is explicit and tested.

Detailed public behaviour belongs in [PUBLIC-IMPLEMENTATION-CONTRACT.md](PUBLIC-IMPLEMENTATION-CONTRACT.md).

## Artist administration

`/admin` is an authenticated Filament workspace for the artist, not a generic site builder. It covers:

1. **Dashboard** — editorial overview, warnings, recent activity and common actions.
2. **Artworks and categories** — content, media, publication, ordering and public visibility.
3. **Exhibitions** — independent exhibition records, dates, venue/location, media and links.
4. **Vita / CV / Contact** — biography content, portrait/profile data and configurable contact presentation/delivery.
5. **Blog** — draft/scheduled/published lifecycle, stable slugs, media and explicit feature enablement.
6. **Media** — validated originals, derivatives, ALT/credit/copyright metadata and safe deletion/reference handling.
7. **Analytics** — artist-useful Matomo reports plus visually separate local operational aggregates.
8. **Settings** — deliberate site-level settings rather than raw implementation rows.

Every mutation remains server-authorized and auditable. Rich text and external links pass through the shared safe rendering boundary.

## Analytics contract

Self-hosted Matomo Community/Core is the source of truth for human visitor analytics. Browser tracking and admin reporting are separate runtime capabilities. The application may maintain lightweight operational aggregates, but it does not duplicate raw human visitor analytics into the editorial database.

Analytics failure must never break public rendering or normal administration. See [ANALYTICS.md](ANALYTICS.md).

## Cost and ownership constraints

- Avoid mandatory paid third-party services, commercial plugins and SaaS runtime dependencies where practical.
- Hosting/resource cost is allowed but must be minimized and justified against reliability, backups, analytics and maintenance requirements.
- `moeller-lars` owns application source, schema/migrations, importer/reconciliation logic, tests, application runtime/configuration contracts and the release image.
- `Wiiii90/server-platform` owns production/validation orchestration, ingress, networks, host paths, secrets, Matomo runtime, backups, monitoring, deployment and rollback.
- Production secrets, database dumps and private media archives never belong in public Git.

## Explicit exclusions

- No general-purpose website builder or free-form layout editor.
- No public registration, customer accounts, marketplace or social feed.
- No migration of legacy authentication/session/password material.
- No requirement to preserve legacy PHP dispatcher/query URLs without a concrete SEO/external-link reason.
- No mandatory commercial analytics plugin or hosted analytics SaaS.
- No production Compose/Caddy/host-placement implementation in this repository.

## Definition of done

The rebuild is complete only when all of the following are accepted:

- public route/content/media/visual regression comparison;
- reliable desktop/mobile/touch/keyboard artwork-viewer behaviour;
- production-usable artist administration;
- lossless artwork/media/CV/Exhibition migration reconciliation;
- secure authentication, authorization, media and rich-text boundaries;
- Matomo tracking/reporting integration and privacy review;
- immutable application release with passing verification;
- tested application persistence restore/rollback contract;
- temporary HTTPS release validation through `server-platform`;
- editorial approval by Lars;
- production cutover and stabilization validation.

Implementation alone is not production acceptance. GitHub Issues and milestones are authoritative for remaining gates; [PROJECT-STATUS.md](PROJECT-STATUS.md) provides a dated orientation snapshot.
