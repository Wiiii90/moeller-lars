# Legacy security baseline

This is a source-review baseline from the completed analysis. It contains no secret values. “Source-confirmed” means the class is visible in the reviewed repository code. “Live-server finding” means behaviour observed or configured on the deployed server; live exploitability, current credentials, and current runtime state still require an authorized, non-destructive verification.

## Priority scale

- **P0 — immediate containment:** public exposure or credential compromise can affect the site/server.
- **P1 — critical replacement requirement:** unauthorized data access or mutation is plausible in normal operation.
- **P2 — high hardening requirement:** significant injection, upload, session, or disclosure risk.
- **P3 — hygiene/operational:** correctness, maintainability, or defense-in-depth issue.

## `larsmoeller` findings

### P0 — committed secrets and configuration exposure (source-confirmed)

Database connection credentials are present in tracked PHP configuration. The repository also contains deployment/configuration material that must be treated as compromised once made public. Rotate affected credentials, remove them from the target history, and use deployment-local secret storage. Do not print or copy the values into tickets, migration fixtures, or documentation.

### P1 — plaintext password authentication and weak session foundation (source-confirmed)

The legacy admin login compares submitted credentials directly in a SQL query rather than using password hashing. Sessions are started in shared includes without a complete modern session lifecycle (including explicit regeneration and secure cookie policy). Admin mutations rely on page inclusion/session conventions rather than a consistently enforced authorization boundary.

### P1 — missing authorization/CSRF protections on admin mutations (source-confirmed)

The reviewed create/update/delete and Vita/file mutation paths do not establish a uniform authenticated-and-authorized request guard or CSRF token requirement. A new admin must not reuse these paths or their trust assumptions.

### P1/P2 — SQL injection and unsafe dynamic identifiers (source-confirmed)

Category/table names and query fragments are assembled from request-controlled values in several admin/public paths. Some value queries use prepared statements, but dynamic identifiers and adjacent paths are not safely allowlisted. Treat the legacy database interface as untrusted and replace it rather than patching it into the target.

### P1/P2 — XSS and unsafe rendered content (source-confirmed)

Artwork fields, comments, Vita formatting, and request-derived values are rendered through mixed escaping/raw HTML paths. The custom BBCode-like parser permits unsafe URL/style semantics. The target must use contextual output encoding and a constrained, sanitized editor model.

### P1/P2 — unsafe upload and filesystem handling (source-confirmed)

The uploader trusts filename/path components and extension checks too heavily, writes into web-served directories, and performs image processing with inconsistent validation/error handling. The target must verify file content, generate server-side names and derivatives, enforce size limits, prevent executable uploads, and retain originals safely.

### P2 — debug/error and request-data exposure (source-confirmed)

Debug output, detailed database errors, request dumps, and error-display settings exist in admin paths. These can disclose schema, filesystem paths, submitted data, or operational details. Production error responses must be generic while internal logs are access-controlled and redacted.

### P2 — insecure transport/dependency behaviour and placeholder analytics (source-confirmed/live configuration)

The Apache rule redirects to HTTP and the admin loads third-party assets over HTTP. The legacy analytics page is a placeholder rather than a reliable privacy-conscious measurement system. HTTPS, dependency integrity, and self-hosted Matomo must be addressed in the replacement.

## `glassygallery` findings

### P0/P1 — committed credentials and unsafe secret defaults (source-confirmed)

Container/configuration files contain hard-coded development database credentials, and the authentication middleware has an insecure fallback signing-secret pattern. Values are intentionally omitted here. Treat them as non-production and potentially compromised; move all secrets to deployment-local secret management and rotate anything reused.

### P1 — authentication without complete role authorization (source-confirmed)

JWT verification establishes identity, but authorization is not uniformly enforced by role/action. Several routes accept any valid token where an artist/admin policy is required. The target needs server-side, per-action authorization independent of UI visibility.

### P1 — unauthenticated state-changing API paths (source-confirmed)

Some update/delete handlers in analytics, logs, and notifications do not apply the authentication middleware consistently. This permits unauthorized mutation if the route is reachable. The target must expose no public mutation endpoint and must test every method/path combination.

### P1/P2 — upload and media API risk (source-confirmed)

Media upload handling does not establish a sufficiently strict content/size policy before writing files, derives storage paths from request metadata, and serves media from a public static tree. The target needs content sniffing, limits, generated names, safe storage, authorization, and deletion reconciliation.

### P2 — API exposure, CORS, rate limiting, and error handling (source-confirmed)

Public read routes and permissive no-origin CORS behaviour are broader than the artist backend requires; login has no demonstrated rate limit; and some error responses expose internal messages. Define an explicit public-read surface, restrict origins, rate-limit authentication, and redact errors.

### P2/P3 — analytics and draft implementation gaps (source-confirmed)

The analytics UI contains placeholder/dummy behaviour and the API is CRUD-shaped rather than a defined human-analytics pipeline. Draft state is process-global rather than a durable, isolated editorial store. These are reliability/design gaps, not foundations to copy into production.

## Source findings versus live-server findings

The classes above are findings in the reviewed source repositories. The live server's exact deployed revision, runtime versions, TLS certificate/configuration, firewall, backups, hooks, and current secret validity were not established by this source review. The HTTP redirect behaviour is a source/configuration defect and must be verified against the live host before cutover; it is not evidence of a permitted live exploit.

## Remediation position

Remediation is by replacement and containment, not by reusing legacy authentication, sessions, SQL helpers, upload handlers, secrets, or authorization assumptions. Preserve only verified public content and approved visitor behaviour. The new admin/authentication foundation must be independently designed, tested, and in place before any writable editorial slice is accepted.
