# Dev Worker 4 — branch-local progress

**Lease:** 7
**Branch:** `dev`
**State:** Active — implementing user-approved Gallery/Artwork corrections; source and browser acceptance separate.

## Assignment and scope
- User commissioned corrections to Gallery/Artwork remove/recovery, bulk selection, Materials table/autosave, ordering/drag, public presentation; narrowly scoped tests are explicitly authorized for this worker.
- Scope: Gallery-workspace actions, Artwork assignment and presentation, material preset owners, directly affected views/styles, scoped browser tests. Do not touch Dev Worker 2 media ingest/feedback, Dev Worker 3 rich text or Dev Worker 1 exhibitions.

## Evidence and progress
- Replaced Material preset Repeater/command with canonical modal table and immediate domain mutations; introduced persistent manual position and retained IDs on rename. Migration/runtime/browser pending.
- Implemented source-level recovery via Gallery Add existing: unassigned Artworks retain identity and media; filter-hidden selections are pruned and guarded before batch operations. No runtime verification available.
- Read current `dev/AGENTS.md`, `dev/.agents/skills/dev/SKILL.md`, `dev/.agents/README.md` and `dev/.agents/roster.md` before registration.
- Initial registration on dev at base `b5a7f186e3192e0e7739582ab03844b505b1b03a`.
- No application changes, tests, browser review, deployment or workflow execution.

## Next action
- Implement bounded root-cause slices, read/reconcile latest dev head before each guarded write. No CI/deploy. Browser/runtime acceptance remains unverified without an actual isolated environment.
