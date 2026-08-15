# ADR-0001: Application stack

- Status: Accepted
- Date: 2026-08-15
- Scope: application architecture and application-level test tooling
- Related: GitHub issue #7; server and deployment constraints in `SERVER-OPERATIONS-BASELINE.md`

## Decision

Build `moeller-lars` as a modular monolith with the following application stack:

- Laravel 13 on PHP 8.3 or newer.
- Blade for server-rendered public pages.
- Custom CSS and targeted vanilla JavaScript for public interaction.
- No public single-page application (SPA).
- Filament 5 for the artist-facing `/admin` application.
- Livewire may be used through Filament and other admin surfaces where useful, but it is not the public rendering architecture.
- Eloquent as the application ORM/data-mapping layer.
- PostgreSQL as the target application database.
- Pest as the primary runner for application, feature, and admin tests.

The stack is a single deployable modular monolith with explicit boundaries between public rendering, editorial/admin modules, media processing, persistence, authentication/authorization, and analytics integration.

## Context

The site is an individual artist presentation with a highly specific visual identity and a content-oriented public experience. It also needs a substantial artist-facing editorial backend for artworks, media, CV, exhibitions, and an optional blog, plus secure authentication and authorization and a controlled migration from the legacy system. The application will be maintained on a small self-hosted server, where unnecessary independently deployed frontend/API pieces increase operational and maintenance cost.

The public site therefore benefits from server-rendered HTML and direct control of markup, CSS, accessibility, metadata, and artwork-viewer behaviour. The admin has a different interaction profile: it needs consistent CRUD/resource screens, validation, authorization hooks, media workflows, drafts, and editorial usability. These needs do not justify duplicating the public frontend as a SPA or composing a separate public API and admin application by default.

The runtime audit establishes the current production host and operational constraints, but it does not decide containerization, ingress, OS migration, CI/CD, recurring backup implementation, monitoring, or Matomo topology. The selected application stack must remain deployable on the verified baseline while those platform decisions are made separately.

## Rationale

- **Individual public presentation:** Blade, custom CSS, and targeted vanilla JavaScript preserve control over Lars Möller's distinctive composition and allow subtle, deliberate viewer/UX improvements without adopting a generic application shell.
- **Content-oriented SSR:** Server-rendered public pages provide direct control of HTML, metadata, routing, accessibility, and artwork content without a public SPA hydration/runtime requirement.
- **Substantial editorial backend:** Filament 5 supplies a mature, customizable foundation for resource forms, tables, filters, media-adjacent workflows, validation, navigation, and admin UI consistency. Livewire remains an admin implementation option, not a public rendering commitment.
- **Domain fit:** A modular monolith keeps Artwork, Media, CV, Exhibition, Blog, authentication, and analytics integration in one coherent application while preserving module boundaries and independent tests.
- **Security:** Laravel's application conventions, Eloquent query layer, Filament authorization extension points, and a single controlled admin boundary reduce the number of independently secured surfaces. They do not replace explicit authorization, CSRF, upload, session, and rate-limit tests.
- **Legacy migration:** A single application can own repeatable import commands, a fresh PostgreSQL schema, checksum reconciliation, rendered-content fixtures, and redirect mapping without making the legacy schema a runtime dependency.
- **Avoiding duplication:** Blade public pages and a Filament admin avoid maintaining a separate public SPA, public API contract, and custom admin frontend for the same content.
- **Small self-hosted server:** A modular monolith reduces service count, deployment coordination, runtime duplication, and operational overhead while remaining capable of adding logically isolated Matomo integration.
- **Open/self-hosted preference:** The decision avoids making commercial frontend/runtime services mandatory and remains compatible with self-hosted/open-source operational components where practical. Hosting and operational cost must remain minimized and justified.

## Viable alternatives considered

### 1. Laravel + completely custom admin

Viable and technically compatible with the public-site requirements. Rejected because Filament covers a large amount of admin infrastructure (resource screens, forms, tables, navigation, validation integration, and extensibility) while remaining customizable for the artist's workflows. A completely custom admin would spend project effort recreating that foundation.

### 2. Symfony + admin ecosystem

Viable for a secure modular monolith and server-rendered site. Rejected because it adds implementation and integration overhead without a project-specific advantage for this content-management/editorial workload and small self-hosted operating model.

### 3. Node/TypeScript SSR + separate/custom admin stack

Viable for public SSR and a modern typed backend. Rejected because it requires more independently composed application pieces for the same functionality, increasing frontend/API duplication, deployment surface, and maintenance burden without a demonstrated benefit for this site.

### 4. Go

An excellent runtime/resource choice and viable for public SSR/backend work. Rejected because the editorial/admin application would require substantially more custom infrastructure for resources, forms, authorization integration, media workflows, and content editing.

### 5. Rust

An excellent runtime/safety choice and technically viable. Rejected because implementation complexity is disproportionate to this content-management/editorial workload and would increase the amount of bespoke admin infrastructure to maintain.

## Explicitly not decided by this ADR

This ADR does not decide:

- exact PostgreSQL production version;
- Docker/Compose;
- container topology;
- common ingress;
- exact production OS migration path;
- CI/CD implementation;
- recurring backup implementation;
- monitoring implementation;
- Matomo deployment topology.

Those decisions remain subject to the server baseline, operational testing, and separate architecture records. Kubernetes remains unselected as recorded by the server baseline; this ADR neither introduces nor requires it.

## Consequences

Positive consequences:

- One application boundary for public pages, editorial modules, authentication, migration tooling, and tests.
- Direct control over the public markup and artwork-viewer interaction.
- A consistent, customizable `/admin` without building all administrative infrastructure from scratch.
- Fewer independently deployed surfaces to secure and operate on the small production host.

Trade-offs:

- Laravel, Filament, and PHP dependencies require disciplined updates and compatibility testing.
- PostgreSQL becomes a target migration and operational dependency even though the legacy schema/database is not preserved.
- Filament conventions must be extended carefully for the artist's domain rather than treated as a generic website builder.
- Matomo and other operational services still need logical isolation and failure-tolerant integration.

## Acceptance mapping for issue #7

Issue #7 is fully addressed by this ADR at the documentation level:

- public rendering and `/admin` UX are explicit;
- security, media processing, migration, Matomo integration, and test tooling are addressed;
- deployment and maintenance constraints are addressed without deciding platform implementation;
- server-resource and self-hosted/open-source considerations are recorded;
- a recorded decision names the selected technologies and runtime approach;
- all five requested rejected alternatives are recorded with project-specific reasons.

Implementation acceptance still requires the application test plan, server/platform decision records, and deployment validation to be executed in later work.
