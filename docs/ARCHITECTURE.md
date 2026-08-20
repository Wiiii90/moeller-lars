# Target architecture

## Selected application stack

The application technology decision is recorded in [ADR-0001: Application stack](adr/ADR-0001-APPLICATION-STACK.md): a Laravel 13/PHP 8.3+ modular monolith with Blade public rendering, custom CSS/targeted vanilla JavaScript, Filament 5 for `/admin`, Eloquent, PostgreSQL, and Pest. The public site is not a SPA; Livewire is limited to admin/Filament use where appropriate.

The ADR selects application technology only. Production platform ownership and deployment are provided by the authoritative [Wiiii90/server-platform](https://github.com/Wiiii90/server-platform) contract; exact PostgreSQL production version and application integration details remain application/platform gates.

The selected application must implement the following boundaries.

## Cost constraint

Avoid mandatory paid third-party services and commercial runtime dependencies. Prefer self-hosted or open-source components where practical. Server and hosting cost is allowed, but must be minimized and justified. Deployment/platform decisions should therefore prefer capabilities that can be self-hosted or are already available in the chosen hosting environment, without changing the selected application stack without a separate architecture decision.

## Components

The system is one Laravel modular monolith plus logically separate
infrastructure services:

- public Laravel/Blade application;
- artist-only Filament `/admin`;
- PostgreSQL application database;
- original media storage and disposable generated derivatives;
- migration/import tooling;
- Matomo On-Premise analytics;
- lightweight operational metrics and logging; and
- deployment, backup, and monitoring operations.

This is not a speculative microservice split.

## Data ownership

- PostgreSQL owns editorial/domain data; its detailed model is defined in
  [DATA-MODEL.md](DATA-MODEL.md).
- Immutable original media owns authoritative uploaded/migrated binary assets;
  generated derivatives are disposable and rebuildable.
- Matomo owns human visitor analytics; `daily_metrics` owns only lightweight
  operational aggregates and disposable analytics cache, as detailed in
  [ANALYTICS.md](ANALYTICS.md).
- Legacy databases and media are migration sources only and never runtime
  authorities after cutover; migration reconciliation is defined in
  [MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md).
- Secrets and production configuration are operational state outside Git.

## Trust boundaries

- Public visitors have read-only access to published public content plus
  explicitly public form endpoints.
- `/admin` is authenticated and separately authorized; server-side
  authorization is enforced on every mutation.
- Uploads are untrusted input until validated and quarantined. Legacy
  migration input is untrusted, read-only source material.
- Matomo/API failure or compromise must not become a dependency for public or
  admin correctness.
- PostgreSQL and internal service interfaces are not publicly exposed, and
  production secrets are not stored in repository content.
- Production and Validation are distinct runtime/data trust boundaries. Validation must never share a writable application database or authoritative media path with Production.

## Verified production and platform baseline

The current production host is the working baseline documented in [SERVER-OPERATIONS-BASELINE.md](SERVER-OPERATIONS-BASELINE.md): Scaleway dev-play-1 / DEV1-S in AMS1 with 2 vCPU, 2 GB RAM, 50 GB block storage, and Ubuntu 24.04.4 LTS. The platform may host logically separate shared workloads in addition to `moeller-lars`; current utilization is not a downsizing signal because future services may share it.

The verified containment baseline includes UFW default-deny inbound rules, localhost-only database bindings, valid TLS/canonical-host handling, disabled directory listing, removed public phpinfo, and blocked sensitive source/vendor/config paths. Public port ownership and the exact currently exposed service set remain platform-owned runtime state and must be verified from `server-platform` evidence rather than copied into application code.

The verified platform containment and deployment details are maintained by
`server-platform`; this repository consumes that contract and does not define
production ingress, `/srv` placement, deployment transport, or host-level
runtime topology.

`moeller-lars` owns application code/tests, Dockerfile/build/runtime contract, migrations, application configuration templates, health/readiness, CI/build artifacts, persistence declarations, and migration expectations. `server-platform` owns Production/Validation manifests, deployed artifact/image references, Compose, networks, Caddy, host ports, resource limits, runtime secret placement, monitoring, backups, and deployment/rollback. Do not duplicate platform implementation here.

Current platform gates are tracked in `Wiiii90/server-platform`, especially production cutover #11, post-cutover stabilization #12, readiness #14, shared mail #24, application capacity #30, host maintenance #33, bounded log growth #35, and documentation reconciliation #36. Historical closed issues remain evidence but are not active gates.

## Deployment environments

- **Production** is the only public authoritative application environment.
- **Validation** is a platform-owned, non-production release-validation environment with isolated PostgreSQL data, isolated media, isolated secrets and separate authenticated ingress. Its lifecycle may be temporary, but while it exists it is a distinct environment and must remain separate from Production.
- Validation may read Production Matomo aggregate reports through a restricted View-only reporting identity while browser/event tracking remains disabled (`MATOMO_TRACKING_ENABLED=false`, `MATOMO_REPORTING_ENABLED=true`). This does not permit Production database/media writes from Validation.
- Development and testing occur locally or in CI, never against Production data.
- The current Scaleway host remains the baseline; `server-platform` owns its physical runtime, ingress and environment lifecycle.
- A successful Validation review does not itself authorize Production deployment, migration, routing or cutover. Those require the explicit readiness and artist-approval gates.

## Non-functional requirements

The architecture requires security and least privilege, accessible and
responsive public pages, reliable artwork-viewer interaction, SEO with stable
canonical URLs and redirects, graceful analytics failure,
migration/reconciliation integrity, tested backup/restore and rollback,
maintainability on a small self-hosted environment, image-heavy-page
performance appropriate to the site, and no mandatory commercial
runtime/plugin/SaaS dependency where avoidable.

The detailed contracts remain in [PUBLIC-IMPLEMENTATION-CONTRACT.md](PUBLIC-IMPLEMENTATION-CONTRACT.md),
[MIGRATION-INVARIANTS.md](MIGRATION-INVARIANTS.md),
[SERVER-OPERATIONS-BASELINE.md](SERVER-OPERATIONS-BASELINE.md), and
[ANALYTICS.md](ANALYTICS.md).

Exhibitions and CV entries remain separate entities and separate admin workflows
even when they share presentation primitives. The artwork viewer is a
first-class public feature with a reliable navigation state, not an incidental
gallery script.

## Security baseline

- Password hashing using a current adaptive algorithm; no plaintext comparison or storage.
- Server-side sessions with secure, HttpOnly, SameSite cookies; CSRF protection for every state-changing request.
- Rate-limited login, password reset, and contact endpoints; optional TOTP MFA for the artist account.
- Authorization checked on every action, not just in the interface.
- Allowlisted media types verified from file contents, generated filenames, size limits, image re-encoding, and media served without executable permissions.
- Environment-only secrets, encrypted backups, dependency updates, structured logs, and a tested restore procedure.
- TLS certificate, HTTP-to-HTTPS redirect, HSTS after validation, CSP, frame restrictions, and secure response headers.

## Analytics ownership and operations

The analytics boundary is fixed: self-hosted Matomo Community/Core owns human visitor analytics, while local operational aggregates remain separate. The application consumes a platform-provided Matomo base URL and site ID; optional reporting uses a separate restricted read-only API identity/token outside Git. Runtime topology, networking, persistence and ingress remain owned by `server-platform`. Matomo failure cannot block public or admin application behaviour. The compact taxonomy, privacy, retention, dashboard and operations contract is documented in [ANALYTICS.md](ANALYTICS.md).

The application owns browser tracking integration, event taxonomy, consent/privacy behaviour, site-specific configuration, reporting client/dashboard, and application-local operational aggregates. The platform owns Matomo containers/database, persistence, Caddy, secrets, resource limits, health, archiving, upgrades, and backup integration.

Production operations are part of this architecture, with platform-owned deployment automation, secrets, TLS ingress, monitoring, encrypted recurring backups, restore testing, rollback, and CI/CD integration. Application-specific release identity and persistence/restore expectations are documented in [RELEASE.md](RELEASE.md); deployment/cutover evidence remains in `server-platform`.

The hosting and cost baseline is recorded in [ADR-0002](adr/ADR-0002-HOSTING-COST-BASELINE.md); it does not decide deployment topology, ingress, OS migration, or other platform items that remain open.

## Architecture acceptance tests

- A written decision record compares viable stacks against the technology decision gate before implementation is locked.
- Public templates can render the migrated content without depending on the legacy schema.
- The admin boundary is independently authenticated and authorized; no public route can mutate editorial data.
- Blog visibility is tested as a separate feature flag: disabled by default, invisible publicly, and enabled only by an explicit artist action.
- Matomo is deployable and testable in the selected hosting model without a mandatory commercial plugin or SaaS dependency and is the source of truth for human analytics.
- Analytics failure is tested as isolated from public rendering and normal admin workflows; operational aggregates remain distinguishable from Matomo data.
- Analytics acceptance covers traffic sources, geography, devices, content interaction, and separate bot/error/performance metrics without exposing unnecessary raw identifiers.
- Logical separation of analytics from the public application is demonstrated; physical server separation is not required.
- Validation proves the application contract behind platform HTTPS, isolated data/media, backup/restore, rollback and pre-cutover behaviour before Production cutover; Validation does not share writable Production persistence.
- Matomo is logically isolated from public rendering and normal admin operation; a separate physical server is not required.
- CI/CD, recurring offsite backups, monitoring, Docker/Compose placement, and common ingress are verified through the server-platform contract; Kubernetes is not selected.
- A cost review confirms that mandatory commercial runtime dependencies are avoided where practical, with minimized and justified server/hosting cost.
