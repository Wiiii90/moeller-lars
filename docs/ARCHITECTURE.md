# Target architecture

## Selected application stack

The application technology decision is recorded in [ADR-0001: Application stack](adr/ADR-0001-APPLICATION-STACK.md): a Laravel 13/PHP 8.3+ modular monolith with Blade public rendering, custom CSS/targeted vanilla JavaScript, Filament 5 for `/admin`, Eloquent, PostgreSQL, and Pest. The public site is not a SPA; Livewire is limited to admin/Filament use where appropriate.

The ADR selects application technology only. Deployment/platform items remain open: exact PostgreSQL production version, Docker/Compose, container topology, common ingress, exact OS migration path, CI/CD, recurring backup implementation, monitoring, and Matomo deployment topology.

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

## Verified production baseline

The current production host is the working baseline documented in [SERVER-OPERATIONS-BASELINE.md](SERVER-OPERATIONS-BASELINE.md): Scaleway dev-play-1 / DEV1-S in AMS1 with 2 vCPU, 2 GB RAM, and 50 GB block storage. Ubuntu 20.04.6 is retained as a transition host with Ubuntu Pro/ESM and current security updates at audit completion. For `moeller-lars`, the host has one permanent environment: production. Independent services may share the host later; current utilization is not a downsizing signal because future services may share it.

The verified containment baseline includes UFW default-deny inbound rules, public ports 22/80/443 only, localhost-only MySQL bindings, valid renewing TLS for apex and `www`, working HTTPS/canonical-host redirects, disabled directory listing, removed public phpinfo, and blocked sensitive source/vendor/config paths.

Production is not a Git checkout and no current Git hook/deploy script was found. The historical live remote is not the current production VM, so the deployment model must be newly designed. `moeller-lars` has no permanent staging environment requirement; temporary staging/release validation remains required. Docker/Compose remains a candidate, Kubernetes is not selected, and common ingress remains undecided.

## Deployment environments

- `moeller-lars` has exactly one permanent environment: production.
- No permanent staging environment is required. Temporary isolated
  staging/release validation is created and used when required before
  high-risk production changes or cutover.
- Development and testing occur locally or in CI, never against production
  data.
- The current Scaleway host remains the baseline. Exact Docker/Compose,
  ingress, and OS-modernization choices remain implementation/platform
  decisions and do not block this logical architecture.

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

The analytics boundary is fixed: self-hosted Matomo Community/Core owns human visitor analytics, while local operational aggregates remain separate. Matomo is logically isolated, failure-tolerant, and may be co-hosted; a separate physical server is not required. The compact taxonomy, privacy, retention, dashboard and operations contract is documented in [ANALYTICS.md](ANALYTICS.md).

Production operations are part of this architecture: deployment automation, secrets, TLS renewal, monitoring, encrypted recurring backups, restore testing, rollback, and CI/CD must be designed and tested alongside the application. The current host remains the baseline; a future OS/runtime or server replacement is an explicit architecture/operations decision, not an assumption. Automated recurring offsite backups and monitoring are not yet complete.

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
- Temporary staging/release validation proves TLS, backup/restore, rollback, and the newly designed deployment process before production cutover; permanent staging is not required.
- Matomo is logically isolated from public rendering and normal admin operation; a separate physical server is not required.
- CI/CD, recurring offsite backups, monitoring, Docker/Compose use, and common ingress each have an explicit target-platform decision before production adoption; Kubernetes remains unselected.
- A cost review confirms that mandatory commercial runtime dependencies are avoided where practical, with minimized and justified server/hosting cost.
