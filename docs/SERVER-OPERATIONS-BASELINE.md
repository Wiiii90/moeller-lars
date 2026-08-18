# Production server and operations baseline

This document records the verified production-server baseline relevant to `moeller-lars`. It contains no credentials, backup locations, hashes or secret values. `Wiiii90/server-platform` is authoritative for current platform implementation and operational procedures.

## Production host

- Provider/plan: Scaleway `dev-play-1` / `DEV1-S`
- Region: `AMS1`
- Capacity: 2 vCPU, 2 GB RAM, 50 GB block storage
- OS: Ubuntu 24.04.4 LTS
- Docker Engine and Docker Compose available
- one permanent `moeller-lars` environment: production

The shared host may run additional services. Capacity decisions must use measured target-workload evidence rather than the old legacy workload alone.

## Verified containment baseline

The legacy production environment has already been brought to the required containment baseline:

- UFW default-deny inbound posture;
- public ports limited to 22/80/443;
- legacy MySQL listeners restricted to localhost;
- valid TLS for apex and `www` with renewal in place;
- HTTP to canonical HTTPS redirect behaviour;
- public `phpinfo` removed;
- directory listing disabled;
- sensitive source/vendor/config paths blocked from public access.

These are baseline controls, not a substitute for ongoing patching, monitoring, least privilege and application security testing.

## Current platform model

Production is not a Git checkout and does not use `git pull` on the host.

The target platform model is owned by `Wiiii90/server-platform` and includes:

- Caddy as public ingress on 80/443;
- platform-managed workload placement and private networking;
- exact immutable application release references;
- restricted deployment activation/rollback path;
- production secret placement outside Git;
- Matomo workload integration;
- baseline platform monitoring;
- temporary isolated `moeller-lars` release-validation capability;
- prepared rebuilt-site cutover/rollback routing contract;
- defined backup/restore contract.

Application release-image production and app-specific runtime/persistence expectations are owned by this repository and documented in [RELEASE.md](RELEASE.md).

## Recovery posture

Manual off-server legacy recovery material was verified during containment work. The target production backup/recovery path is now governed by `server-platform` rather than by ad-hoc application-repository procedures.

As of 2026-08-18, the remaining platform recovery gates are:

- `server-platform#9` — automated encrypted off-server backups;
- `server-platform#10` — proven isolated restore/full recovery.

Both are required before final rebuilt-site cutover readiness can be accepted.

Application-side restore/media validation remains defined in [RELEASE.md](RELEASE.md) and tracked by `moeller-lars#38`.

## Remaining production gates

The completed platform foundation must not be confused with cutover completion. The remaining open platform sequence is:

1. automated backup implementation (`server-platform#9`);
2. restore/recovery proof (`server-platform#10`);
3. combined production readiness review (`server-platform#14`);
4. production cutover (`server-platform#11`);
5. stabilization/capacity review (`server-platform#12`);
6. legacy runtime retirement only after stabilization (`server-platform#13`).

The application has its own acceptance sequence in `moeller-lars`, including public regression, persistence validation, production-readiness review, editorial approval, cutover validation and stabilization.

## Ownership constraints

`moeller-lars` owns:

- application code/tests and migrations;
- application Dockerfile/release image;
- application configuration variable contract;
- health/readiness behaviour;
- importer/reconciliation logic;
- application persistence/migration/rollback expectations;
- Matomo browser/reporting integration.

`server-platform` owns:

- production/validation Compose and placement;
- Caddy/HTTPS/canonical-host ingress;
- private networks and host ports;
- secrets;
- Matomo runtime/database/persistence;
- monitoring;
- recurring backup/restore automation;
- release activation, rollback and production traffic switch.

Do not copy platform topology or secret operational state into this application repository merely to make application documentation self-contained.
