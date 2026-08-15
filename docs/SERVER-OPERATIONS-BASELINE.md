# Production server and operations baseline

This document records the verified production-server audit. It contains no credentials, backup locations, hashes, or other secret values. It is the operational baseline for the `moeller-lars` system; future platform decisions must be recorded separately.

## Production host

- Provider/plan: Scaleway `dev-play-1` / `DEV1-S`.
- Region: `AMS1`.
- Capacity: 2 vCPU, 2 GB RAM, and 50 GB block storage.
- This current production host remains the working baseline for `moeller-lars`.
- Current utilization is not a valid downsizing signal: future services may share the host, so capacity decisions must consider the target architecture and service set.
- For `moeller-lars`, this host has one permanent environment: production. Independent services may share the host later; this does not create a permanent `moeller-lars` staging environment.

## OS and runtime posture

- Ubuntu 20.04.6 is retained as a transition host.
- Ubuntu Pro/ESM is enabled.
- Security updates were current at audit completion.
- Moving to a current Ubuntu LTS remains an architecture/operations decision; this audit does not select the target OS or application runtime.

## Security containment verified at audit completion

- UFW defaults to deny inbound and allow outbound.
- Publicly exposed ports are limited to 22, 80, and 443.
- MySQL ports 3306 and 33060 are bound to localhost only.
- Valid TLS is configured for the apex and `www` hostnames with automatic renewal.
- HTTP redirects to HTTPS and the canonical host behaviour is working.
- The public `phpinfo` endpoint has been removed.
- Directory listing is disabled.
- Sensitive source, vendor, and configuration paths are blocked from public access.

These controls are the verified baseline, not a substitute for ongoing patching, least-privilege review, application security testing, and monitoring.

## Recovery material

- A manual off-server recovery copy for the webroot and legacy databases was verified.
- Additional pre-maintenance recovery material exists for configuration, packages, and firewall state.
- Recovery copies were verified with SHA-256.
- Automated recurring offsite backups remain future work and are required before treating operations as complete.

Backup locations, credentials, hashes, and secret values are intentionally excluded from this repository.

## Deployment findings

- Production is not a Git checkout.
- No current Git hook or deployment script was found on the production host.
- The historical live-remote IP is not the current production VM.
- The future deployment model must therefore be newly designed and verified; it must not be copied from the historical remote.

## Architecture and operations constraints

- `moeller-lars` has one permanent environment on this host: production; no permanent `moeller-lars` staging environment is required.
- Temporary staging/release validation remains required before production cutover or high-risk maintenance.
- Matomo belongs to the `moeller-lars` system but must be logically isolated from public rendering and normal admin operation. A separate physical server is not required.
- Docker/Compose is a candidate only, not a decision.
- Kubernetes is not selected.
- A common ingress remains undecided.
- CI/CD, automated recurring offsite backup, and monitoring are target-platform work.
- Server/hosting cost is allowed but must be minimized and justified; the project does not assume a new server unless the target requirements make it necessary.

## Audit acceptance and open work

The facts above are verified baseline conditions and are safe to use in architecture planning without exposing secrets. Open work is limited to designing and testing the target deployment model, temporary release validation, recurring offsite backups, monitoring, CI/CD, Matomo isolation, and any future OS/runtime or server replacement decision.
