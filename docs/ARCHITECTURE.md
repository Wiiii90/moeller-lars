# Target architecture

## Technology decision gate

No application framework or language is selected yet. The choice remains open until the production server/runtime, hosting constraints, TLS and deployment path, backup/restore options, database support, maintenance burden, and explicit architecture trade-offs have been investigated. Laravel, Filament, Node, and other stacks are candidates only; none is a project requirement at this stage.

Whatever stack is selected must implement the following boundaries.

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

## Content model

- `artwork`: stable slug, category, metadata, publication state, position.
- `media_asset`: original, derivatives, metadata, alt text, checksum.
- `exhibition`: structured date range, venue, location, links, content, state.
- `cv_entry`: section, date range, title, organisation, body, position.
- `post`: title, slug, excerpt, content, cover media, publication time, state.
- `redirect`: legacy route to canonical target.
- `admin_user` and `audit_event`: least-privilege account records and immutable administrative events.
- `daily_metric`: aggregate analytics only; raw identifiers are minimized and short-lived if ever required.
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

## Analytics and operations

Use self-hosted Matomo Community/Core as the analytics target, with zero licence or SaaS cost. The target deployment must document its storage, updates, backups, access control, and retention settings. Do not add paid analytics plugins or services. Do not retain full IP addresses or raw user-agent strings in the editorial database; confirm the final Matomo configuration against applicable privacy requirements before production.

Production operations are part of this architecture: server/runtime selection, hosting cost, TLS certificates and renewal, deployment automation, secrets, monitoring, encrypted backups, restore testing, rollback, and a possible server replacement must be designed and tested alongside the application.

## Architecture acceptance tests

- A written decision record compares viable stacks against the technology decision gate before implementation is locked.
- Public templates can render the migrated content without depending on the legacy schema.
- The admin boundary is independently authenticated and authorized; no public route can mutate editorial data.
- Blog visibility is tested as a separate feature flag: disabled by default, invisible publicly, and enabled only by an explicit artist action.
- Matomo is deployable and testable in the selected hosting model without a paid plugin or SaaS dependency.
- A staging deployment proves TLS, backup/restore, rollback, and the chosen deployment process before production cutover.
