# Target architecture

## Provisional implementation direction

The legacy deployment is PHP/MySQL-based, so the default fast path is a current PHP application with a maintained ORM and a focused admin framework (for example Laravel plus Filament) backed by MySQL or MariaDB. This is a decision to confirm after checking the production server's supported PHP version, Composer availability, backups, and deployment hook.

The chosen stack must implement the following boundaries even if another framework is selected.

```text
Public pages (legacy-compatible templates)
        |
        v
Application / content queries ---- Image processing + media storage
        |                                      |
        v                                      v
Relational database                        Generated derivatives
        |
        +---- Artist-only admin ---- Audit log / analytics aggregates
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

## Security baseline

- Password hashing using a current adaptive algorithm; no plaintext comparison or storage.
- Server-side sessions with secure, HttpOnly, SameSite cookies; CSRF protection for every state-changing request.
- Rate-limited login, password reset, and contact endpoints; optional TOTP MFA for the artist account.
- Authorization checked on every action, not just in the interface.
- Allowlisted media types verified from file contents, generated filenames, size limits, image re-encoding, and media served without executable permissions.
- Environment-only secrets, encrypted backups, dependency updates, structured logs, and a tested restore procedure.
- TLS certificate, HTTP-to-HTTPS redirect, HSTS after validation, CSP, frame restrictions, and secure response headers.

## Analytics rule

Use first-party, privacy-conscious measurements: daily unique approximations, page views, referrers, device class, and content popularity. Do not retain full IP addresses or raw user-agent strings in the editorial database. Confirm the final configuration against applicable privacy requirements before production.
