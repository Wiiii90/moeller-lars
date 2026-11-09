# Dev Worker 6 — Home preview improvements

- **Branch:** `dev`
- **Lease:** 12 (active)
- **Registration baseline:** `28550e2a9cfbbbe2e9d20308594a804c67aaa733`
- **Scope:** Pages → Home → Hero Artwork, Under Construction, Custom. Investigate and implement the 2026-11-09 preview findings: coherent settings/dialog layout and hints; remove obsolete candidate filters; Storage-backed Add Candidate/unassigned-media selection; candidate table/search/status and pagination; stage rotation simulation; consistent deletion permissions and shared component picker across Home and custom pages. Check rotation interval units and video eligibility prior to changing semantics.
- **Ownership boundaries:** Reuse canonical Home, Page, Gallery, Storage, editor, dialog, publication and notification owners. Coordinate with Dev Worker 5 on Pages settings, and avoid edits to Dev Workers 1–4 scopes; do not edit test sources or general documentation, trigger workflows, deploy or promote branches.
- **Status:** Home settings, Hero candidate filter removal, source-table and persistent Gallery unpublish behavior committed. Admin-only Hero stage simulation now preview-steps one day every three seconds for Sequential, samples Random every ten seconds, and leaves Ordered stable. Canonical public resolver remains unchanged. Home Add component action icon now matches Custom Page. Dialog schemas still require consolidation. Browser/runtime verification outstanding.
- **Verification:** No execution or browser acceptance yet.
- **Next:** Calendar-month rotation is implemented in the canonical resolver and stage; exercise Jan 31/leap-year boundaries on Testing. Continue Storage-backed Hero candidates and Home/Custom component deletion/editor parity; browser acceptance remains pending.
