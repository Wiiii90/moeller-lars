# Dev Worker 10 — image-file rights processing and Validation readiness

**Working branch:** `dev` | **Lease:** 26 | **State:** registered, source and platform verification pending.
**User assignment:** Complete the previously deferred optional rights-bearing image pipeline and determine whether it is already active on Validation; identify and resolve source-side blockers, and prepare a precise activation handoff. Do not treat the prior thumbnail conversion as proof that rights-file preparation or delivery is active.

## Scope and ownership
- Own the canonical rights artifact preparation/selection/revision flow, related database queue jobs, source-side safety gates, and the needed application/platform interface. Collaborate with existing workers on shared media upload, publication, Storage and Administration paths rather than duplicating their implementations.
- Inspect current `PUBLIC_RIGHTS_WORKSTREAM.md`, applicable media rights product contract and the platform-owned server composition as evidence. Distinguish source configuration from observed running environment; no assumed on-server state.
- Preserve immutable artist originals, corresponding canonical thumbnails, Working/Committed and publication content, backed-up Validation database/media, full-resolution delivery and feature flags default OFF. Avoid destructive backfill, migrations, snapshot resets or live configuration toggles without explicit permission.
- Never mutate `Wiiii90/server-platform`, Validation/Production, or run Continuous Integration/isolated tests without the respective authorization. Work only on `dev` and leave actual tests to Testing.
- A completed source review and handoff are not permission to turn on rights delivery; explicitly verify rights processing, old-media backfill and public delivery gates against real evidence before requesting deployment.

## Open questions and next action
1. Verify from platform configuration and available runtime evidence whether Validation has a dedicated database-queue consumer and whether preparation and delivery flags are actually enabled; check whether past successful **thumbnail cutover** is being confused with **rights artifact activation**.
2. Trace accepted media rights pipeline and current deviations (including removed transform profiles, artifact versioning, selectors, original/thumbnail integrity and cleanup), fix any concrete application defects in the canonical source owner.
3. Produce a small Testing and platform/Validation activation handoff: isolated job/backfill and public delivery acceptance; matching database+media backup; worker lifecycle; and the explicit operator switch sequence.
4. Keep this worker record concise and current. No deployment or Validation mutation in this registration task.
