# Dev Worker 6 — Home preview improvements

- **Branch:** `dev`
- **Lease:** 12 (active)
- **Registration baseline:** `28550e2a9cfbbbe2e9d20308594a804c67aaa733`
- **Scope:** Pages → Home → Hero Artwork, Under Construction, Custom. Investigate and implement the 2026-11-09 preview findings: coherent settings/dialog layout and hints; remove obsolete candidate filters; Storage-backed Add Candidate/unassigned-media selection; candidate table/search/status and pagination; stage rotation simulation; consistent deletion permissions and shared component picker across Home and custom pages. Check rotation interval units and video eligibility prior to changing semantics.
- **Ownership boundaries:** Reuse canonical Home, Page, Gallery, Storage, editor, dialog, publication and notification owners. Coordinate with Dev Worker 5 on Pages settings, and avoid edits to Dev Workers 1–4 scopes; do not edit test sources or general documentation, trigger workflows, deploy or promote branches.
- **Status:** Registered; investigation pending.
- **Verification:** No execution or browser acceptance yet.
- **Next:** Locate Home owners and targeted architecture/UI contracts; implement finite complete slices, guarding each commit against concurrent edits.
