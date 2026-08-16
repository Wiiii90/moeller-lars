# Production server and operations baseline

This document records the verified production-server and platform baseline. It contains no credentials, backup locations, hashes, or other secret values. `Wiiii90/server-platform` is the authoritative platform reference; application-specific integration remains tracked in this repository.

## Production host

- Provider/plan: Scaleway `dev-play-1` / `DEV1-S`.
- Region: `AMS1`.
- Capacity: 2 vCPU, 2 GB RAM, and 50 GB block storage.
- This current production host remains the working baseline for `moeller-lars`.
- Current utilization is not a valid downsizing signal: future services may share the host, so capacity decisions must consider the target architecture and service set.
- For `moeller-lars`, this host has one permanent environment: production. Independent services may share the host later; this does not create a permanent `moeller-lars` staging environment.

## OS and runtime posture

- Ubuntu 24.04.4 LTS.
- Security updates were current at audit completion.
- Docker Engine and Docker Compose are installed.

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

## Platform deployment

- Production is not a Git checkout.
- Caddy is the public ingress on ports 80/443; legacy Apache listens only on `127.0.0.1:8080`.
- MySQL is local-only.
- `/srv/stacks` is platform workload placement, `/srv/data` is persistent data placement, and `/srv/releases/server-platform` is platform release staging.
- GitHub Actions transports an exact `server-platform` commit through a restricted deploy user and forced-command dispatcher supporting stage, activate, status, exact releases, and rollback.
- Production is not a Git checkout, does not use `git pull`, and intentionally has no GitHub account, token, or key.

## Architecture and operations constraints

- `moeller-lars` has one permanent environment on this host: production; no permanent `moeller-lars` staging environment is required.
- Temporary isolated release validation remains required before production cutover or high-risk maintenance; it may share this physical host only when isolated and resource constrained.
- Matomo belongs to the `moeller-lars` system but must be logically isolated from public rendering and normal admin operation. A separate physical server is not required.
- Server-platform owns the generic production deployment, ingress, placement, resource limits, and runtime lifecycle.
- Application-specific production deployment contract/integration remains future work through server-platform #4/#5.
- Automated backup/restore remains server-platform #8/#9/#10; monitoring remains server-platform #3; temporary moeller-lars validation remains server-platform #6.
- Server/hosting cost is allowed but must be minimized and justified; the project does not assume a new server unless the target requirements make it necessary.

## Audit acceptance and open work

The facts above are verified baseline conditions and are safe to use in architecture planning without exposing secrets. Open work is limited to application/platform integration, temporary release validation, recurring backup/restore automation, monitoring, and Matomo workload integration through server-platform.
