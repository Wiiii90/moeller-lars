# Server and operations boundary

This repository does not own mutable host topology. [`Wiiii90/server-platform`](https://github.com/Wiiii90/server-platform) is the authoritative source for Production/Validation placement, ingress, secrets, backups, monitoring, resource limits, deployment and rollback.

This document records only the durable application/platform boundary that `moeller-lars` depends on.

## Environments

### Production

Production is the authoritative public application environment.

Requirements:

- exact immutable `moeller-lars` application image;
- PostgreSQL application database;
- persistent canonical media originals;
- HTTPS/canonical-host ingress;
- runtime secrets supplied outside Git;
- health monitoring and recoverable backups;
- controlled migration/deployment/rollback sequencing.

Production is not a Git checkout and application deployment is not `git pull`.

### Validation

Validation is a separate non-production release-review environment.

It may share physical infrastructure with Production only when platform isolation/resource controls preserve distinct trust boundaries. It must not share writable Production application database, authoritative media paths or application secrets.

Validation may use a deliberately restricted read-only Matomo reporting identity when required for dashboard review while browser tracking is disabled. That does not permit shared application persistence.

## Application-owned contract

`moeller-lars` owns:

- application source and migrations;
- Docker build/runtime interface;
- exact Git-SHA release identity embedded in the image;
- `/up` application health endpoint;
- PostgreSQL migration command/expectations;
- canonical media persistence declaration;
- `media:verify` and migration/reconciliation commands;
- application release smoke behavior;
- required environment-variable names;
- CI verification and GHCR image publication.

The application container listens on its documented internal HTTP interface. Concrete host ports, proxy/container network names and filesystem volume placement are platform details.

## Platform-owned contract

`server-platform` owns:

- VM/provider/OS lifecycle;
- firewall and SSH exposure;
- Caddy/public ingress and TLS;
- container/Compose placement;
- host paths and persistent-volume mounts;
- PostgreSQL runtime placement/credentials;
- Matomo runtime/database/networking;
- mail-server/runtime integration;
- secret placement;
- CPU/RAM/PID/resource limits;
- logs and retention;
- recurring offsite backups and restore orchestration;
- monitoring/alerting;
- Validation lifecycle;
- production deployment, activation, status and rollback;
- legacy runtime retirement.

Mutable operational facts should be maintained there instead of copied into this application repository and allowed to drift.

## Security expectations

The platform contract must provide, at minimum:

- HTTPS canonical ingress;
- no public database exposure;
- least-privilege runtime/deploy access;
- bounded service/resource exposure;
- secrets outside Git and image layers;
- recoverable persistent state;
- monitoring for application/ingress/storage/backup health;
- a controlled patching/maintenance process.

Application security remains separately responsible for authentication, authorization, CSRF/session policy, rate limiting, input/media validation and safe public/admin behavior.

## Persistence and recovery

Authoritative application state consists of:

- PostgreSQL editorial/domain data;
- canonical original media.

Generated media variants may be backed up conservatively but are conceptually rebuildable.

Recovery must attach database and media from a consistent recoverable state to an explicitly identified application release and then run the application-level verification described in [RELEASE.md](RELEASE.md).

A backup is not considered proven merely because files exist; the platform restore procedure and application integrity checks must be executable in an isolated recovery context.

## Deployment gate

A successful CI run or GHCR image publication does not authorize Production mutation.

Before Production deployment/cutover, the exact candidate must pass the project release gates, including isolated Validation/browser acceptance where required and current platform backup/rollback readiness.

See [RELEASE.md](RELEASE.md) and [MIGRATION-PLAN.md](MIGRATION-PLAN.md).
