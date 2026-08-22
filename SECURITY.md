# Security policy

## Reporting a vulnerability

Do not open a public issue containing exploit details, credentials, tokens, private data or a reproducible attack against a deployed environment.

For a suspected vulnerability, use GitHub's private vulnerability reporting for this repository when available. If that option is not available, contact the repository owner privately before public disclosure.

Include only the information needed to reproduce and assess the problem:

- affected route/component and release SHA if known;
- impact and prerequisites;
- minimal reproduction steps;
- whether any secret/private data may already have been exposed.

Never include real production credentials or private data in the report.

## Scope

Security-sensitive application areas include:

- `/admin` authentication/authorization/session behavior;
- preview/private media access;
- Contact form abuse/delivery boundaries;
- upload/media validation and storage paths;
- rich-text/link rendering;
- public/private publication boundaries;
- secret/configuration handling;
- migration/import processing of untrusted legacy input.

Host/network/backup/runtime findings that belong to the deployment platform should be reported against `Wiiii90/server-platform` through an appropriate private channel rather than documented with exploitable detail here.

## Supported version

Until the replacement application has completed Production cutover, the supported candidate is the latest explicitly accepted release SHA. After cutover, support follows the currently deployed Production release and active release-candidate work; historical development branches are not supported security versions.
