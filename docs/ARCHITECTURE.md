# Target architecture

## Technology decision gate

No application framework or language is selected yet. The choice remains open until the production server/runtime, hosting constraints, TLS and deployment path, backup/restore options, database support, maintenance burden, and explicit architecture trade-offs have been investigated. Laravel, Filament, Node, and other stacks are candidates only; none is a project requirement at this stage.

Whatever stack is selected must implement the following boundaries.

## Cost constraint

Avoid mandatory paid third-party services and commercial runtime dependencies. Prefer self-hosted or open-source components where practical. Server and hosting cost is allowed, but must be minimized and justified. Architecture decisions should therefore prefer capabilities that can be self-hosted or are already available in the chosen hosting environment, without turning that preference into a premature framework selection.

```text
Public pages (legacy-informed templates)
        |
        v
Application / content queries ---- Image processing + media storage
        |                                      |
        v                                      v
Relational database                        Generated derivatives
        |
        +---- New artist-only admin ---- Audit log / analytics aggregates
```

## Verified production baseline

The current production host is the working baseline documented in [SERVER-OPERATIONS-BASELINE.md](SERVER-OPERATIONS-BASELINE.md): Scaleway dev-play-1 / DEV1-S in AMS1 with 2 vCPU, 2 GB RAM, and 50 GB block storage. Ubuntu 20.04.6 is retained as a transition host with Ubuntu Pro/ESM and current security updates at audit completion. For `moeller-lars`, the host has one permanent environment: production. Independent services may share the host later; current utilization is not a downsizing signal because future services may share it.

The verified containment baseline includes UFW default-deny inbound rules, public ports 22/80/443 only, localhost-only MySQL bindings, valid renewing TLS for apex and `www`, working HTTPS/canonical-host redirects, disabled directory listing, removed public phpinfo, and blocked sensitive source/vendor/config paths.

Production is not a Git checkout and no current Git hook/deploy script was found. The historical live remote is not the current production VM, so the deployment model must be newly designed. `moeller-lars` has no permanent staging environment requirement; temporary staging/release validation remains required. Docker/Compose remains a candidate, Kubernetes is not selected, and common ingress remains undecided.

## Content model

- `artwork`: stable slug, category, metadata, publication state, position.
- `media_asset`: original, derivatives, metadata, alt text, checksum.
- `exhibition`: structured date range, venue, location, links, content, state.
- `cv_entry`: section, date range, title, organisation, body, position.
- `post`: title, slug, excerpt, content, cover media, publication time, state.
- `redirect`: legacy route to canonical target.
- `admin_user` and `audit_event`: least-privilege account records and immutable administrative events.
- `daily_metric`: lightweight local aggregates for bots, errors, performance, and other operational health signals; optionally cached dashboard summaries. It is not a second store for Matomo human visitor analytics.
- `blog_settings`: explicit public-enable state, independent of draft/publish state, defaulting to disabled.

Exhibitions and CV entries remain separate entities and separate admin workflows even when they share presentation primitives. The artwork viewer is a first-class public feature with a reliable navigation state, not an incidental gallery script.

## Security baseline

- Password hashing using a current adaptive algorithm; no plaintext comparison or storage.
- Server-side sessions with secure, HttpOnly, SameSite cookies; CSRF protection for every state-changing request.
- Rate-limited login, password reset, and contact endpoints; optional TOTP MFA for the artist account.
- Authorization checked on every action, not just in the interface.
- Allowlisted media types verified from file contents, generated filenames, size limits, image re-encoding, and media served without executable permissions.
- Environment-only secrets, encrypted backups, dependency updates, structured logs, and a tested restore procedure.
- TLS certificate, HTTP-to-HTTPS redirect, HSTS after validation, CSP, frame restrictions, and secure response headers.

## Analytics ownership and operations

Self-hosted Matomo Community/Core is the source of truth for human visitor analytics. It must cover traffic sources/referrers, geography, devices, page and artwork visits, and meaningful content interaction. Do not duplicate Matomo human analytics in the editorial database. Local `daily_metric`/operational storage may contain lightweight bot, error, performance, and deployment aggregates, plus optionally cached summaries for dashboard convenience; it must not become a parallel human-analytics warehouse. Separate bot and operational metrics from human visitor metrics in the admin presentation.

Matomo/API/log-parser failure must never affect public rendering or normal admin functionality. Analytics collection and dashboard reads are asynchronous or failure-tolerant, and the public application must have a clear degraded mode. Logical separation from the public application is required; a separate physical server is not required.

The Matomo deployment must document storage, updates, backups, access control, retention, and its operational cost. Do not make a commercial analytics plugin or SaaS service mandatory. Do not retain full IP addresses or raw user-agent strings in the editorial database; confirm the final Matomo configuration against applicable privacy requirements before production.

Production operations are part of this architecture: deployment automation, secrets, TLS renewal, monitoring, encrypted recurring backups, restore testing, rollback, and CI/CD must be designed and tested alongside the application. The current host remains the baseline; a future OS/runtime or server replacement is an explicit architecture/operations decision, not an assumption. Automated recurring offsite backups and monitoring are not yet complete.

## Architecture acceptance tests

- A written decision record compares viable stacks against the technology decision gate before implementation is locked.
- Public templates can render the migrated content without depending on the legacy schema.
- The admin boundary is independently authenticated and authorized; no public route can mutate editorial data.
- Blog visibility is tested as a separate feature flag: disabled by default, invisible publicly, and enabled only by an explicit artist action.
- Matomo is deployable and testable in the selected hosting model without a mandatory commercial plugin or SaaS dependency and is the source of truth for human analytics.
- Analytics failure is tested as isolated from public rendering and normal admin workflows; operational aggregates remain distinguishable from Matomo data.
- Analytics acceptance covers traffic sources, geography, devices, content interaction, and separate bot/error/performance metrics without exposing unnecessary raw identifiers.
- Logical separation of analytics from the public application is demonstrated; physical server separation is not required.
- Temporary staging/release validation proves TLS, backup/restore, rollback, and the newly designed deployment process before production cutover; permanent staging is not required.
- Matomo is logically isolated from public rendering and normal admin operation; a separate physical server is not required.
- CI/CD, recurring offsite backups, monitoring, Docker/Compose use, and common ingress each have an explicit target-platform decision before production adoption; Kubernetes remains unselected.
- A cost review confirms that mandatory commercial runtime dependencies are avoided where practical, with minimized and justified server/hosting cost.
