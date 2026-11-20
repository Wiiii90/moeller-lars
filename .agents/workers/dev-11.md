# Dev Worker 11 — public-site presentation and CMS-consistent small fixes

**Working branch:** `dev` | **Lease:** 27 | **State:** Active — registered; first concrete defect pending.
**User assignment:** Handle a continuing sequence of small, user-reported issues on the public-facing pages of the artist homepage. Resolve each coherently through the existing content management system (CMS) architecture rather than making isolated presentation patches. No specific defect was assigned in the activation request.

## Scope and owner boundaries
- Own bounded public-page rendering, layout, responsive presentation and interaction fixes that do not already belong to another worker. The public Blade/views, authored styles/scripts and existing domain projections are the starting points, not new or duplicated presentation systems.
- Trace each issue from the public observation through its canonical CMS configuration, Working/Committed publication boundary, public projection and actual renderer. Retain preview/committed parity, artist-defined settings, accessibility, the strict Public content security policy and existing shared primitives.
- Coordinate before touching an active neighboring owner's area: Dev Worker 1 (Exhibitions), 4 (Gallery/Artwork), 5 (Home and page types), 6 (Home Hero selection), 7 (General appearance and embedded Preview), 9 (public navigation) or 10 (image rights). Do not take over their active work through this general assignment.
- Do not change unrelated Admin editors, shared publication/persistence contracts, media lifecycle or product architecture without an explicit defect-driven scope and coordination. No routine test-source or general documentation edits, CI, deployment, Validation changes or promotion to `main`; production code only on `dev`.

## Activation evidence
- Read `dev/AGENTS.md`, `dev/.agents/skills/dev/SKILL.md`, `dev/.agents/README.md` and the branch-local roster; allocated the lowest free Dev slot and a fresh monotonic lease.
- No application source files changed; no runtime or browser checks claimed.

## Next actions
1. Take the first concrete public-page issue from the user; identify its existing canonical owner and immediate consumers using bounded inspection.
2. Implement the smallest complete root-cause correction on current `dev`, reconcile concurrent commits and preserve any other worker's accepted behavior.
3. Report exact commit, changed files, evidence actually checked, browser acceptance status and targeted Testing/Docs follow-up where applicable.
