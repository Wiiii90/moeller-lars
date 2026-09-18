# Security policy

## Reporting a vulnerability

Do not open a public issue containing exploit details, credentials, tokens, private data or a reproducible attack against a deployed environment.

For a suspected vulnerability, use GitHub's private vulnerability reporting for this repository when available. If that option is not available, contact the repository owner privately before public disclosure.

Include only the information needed to reproduce and assess the problem:

- affected route/component and release SHA if known;
- impact and prerequisites;
- minimal reproduction steps;
- whether any secret/private data may already have been exposed.

Never include real production credentials, reset links, MFA secrets/recovery codes or private data in the report.

## Scope

Security-sensitive application areas include:

- `/admin` authentication, `is_admin` authorization, session behavior and optional TOTP MFA/recovery;
- admin password-reset enumeration resistance, token handling and transactional mail;
- preview/private media access;
- Contact form abuse/delivery boundaries;
- upload/media validation and storage paths;
- rich-text/link rendering;
- public/private publication boundaries;
- secret/configuration handling;
- migration/import processing of untrusted legacy input.

The durable application contract for admin login, MFA and account recovery is [docs/ADMIN-AUTHENTICATION.md](docs/ADMIN-AUTHENTICATION.md). Passwords are never recoverable plaintext and password recovery must not disclose whether an arbitrary address is an administrator account.

Concrete SMTP credentials, relay policy, TLS, ingress and mail-server operations are platform concerns and must remain outside this repository. Host/network/backup/runtime findings that belong to the deployment platform should be reported against `Wiiii90/server-platform` through an appropriate private channel rather than documented with exploitable detail here.

## Supported version

Until the replacement application has completed Production cutover, the supported candidate is the latest explicitly accepted release SHA. After cutover, support follows the currently deployed Production release and active release-candidate work; historical development branches are not supported security versions.
