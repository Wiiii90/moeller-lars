# Dev Worker 10 — image-file rights processing and Validation readiness

**Working branch:** `dev` | **Lease:** 26 | **State:** registered; source/platform configuration reviewed, deployed runtime unverified.
**User assignment:** Complete the previously deferred optional rights-bearing image pipeline and determine whether it is already active on Validation; identify and resolve source-side blockers, and prepare a precise activation handoff. Do not treat the prior thumbnail conversion as proof that rights-file preparation or delivery is active.

## Scope and ownership
- Own the canonical rights artifact preparation/selection/revision flow, related database queue jobs, source-side safety gates, and the needed application/platform interface. Collaborate with existing workers on shared media upload, publication, Storage and Administration paths rather than duplicating their implementations.
- Inspect current `PUBLIC_RIGHTS_WORKSTREAM.md`, applicable media rights product contract and the platform-owned server composition as evidence. Distinguish source configuration from observed running environment; no assumed on-server state.
- Preserve immutable artist originals, corresponding canonical thumbnails, Working/Committed and publication content, backed-up Validation database/media, full-resolution delivery and feature flags default OFF. Avoid destructive backfill, migrations, snapshot resets or live configuration toggles without explicit permission.
- Never mutate `Wiiii90/server-platform`, Validation/Production, or run Continuous Integration/isolated tests without the respective authorization. Work only on `dev` and leave actual tests to Testing.
- A completed source review and handoff are not permission to turn on rights delivery; explicitly verify rights processing, old-media backfill and public delivery gates against real evidence before requesting deployment.

## Verified source/configuration state (2026-11-20)
- Local `compose.yaml` includes `media-rights-worker` using Laravel's dedicated `database --queue=media-rights` consumer.
- `config/media.php` defaults both `MEDIA_RIGHTS_PREPARATION_ENABLED` and `MEDIA_RIGHTS_DELIVERY_ENABLED` to false.
- Canonical `Wiiii90/server-platform` `main`, `platform/moeller-lars-validation/docker-compose.yml`: database queue connection is declared but no rights queue consumer or explicit rights feature flags are supplied. Production composition likewise lacks that worker. This is tracked-platform evidence, **not** observed live environment; any untracked operator override must be checked separately.
- Earlier confirmed 2026-10-30 Validation `cutover` was the thumbnail derivative conversion, **not** evidence of rights metadata preparation, batch backfill or published rights-tagged artifacts.
- `PUBLIC_RIGHTS_WORKSTREAM.md` keeps rights B2 deployment/activation as separately unaccepted and flags default off. Dev Worker 2's last active rights revision profile lookup was removed on dev; no confirmation of real old artifact inventory, job processing or delivery.
- No server access, real environment status query, application migration, background queue job, image-file modification or deployment performed in this task.

## Next owner actions
1. Validate rights artifact preparation/revision/selection with the Testing workstream using a disposable database/filesystem; check genuine old/new images, metadata readback, publication semantics, backfill and idempotent failures. Coordinate Testing's current round; do not duplicate its test authorship.
2. If code defects are demonstrated, repair the canonical `dev` owners and direct consumers without changing ordinary upload paths unnecessarily. Record precise acceptance before any enablement.
3. Hand off the platform-owned Validation worker and explicit preparation/delivery environment gates to the `server-platform` owner. The platform configuration must first be reviewed/updated under separate authorization.
4. Require matched recoverable Validation database/media backup, read-only inventory and safe staged backfill before activation, then explicit user authorization to deploy or toggle rights delivery. Do not infer deployed state from source files.
