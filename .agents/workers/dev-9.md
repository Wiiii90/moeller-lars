# Dev Worker 9 — Navigation

**Branch:** `dev`
**Lease:** 21
**State:** Active — icon-only nested navigation source change committed; browser acceptance pending
**Claimed work:** Public cyclic inline navigation and its direct presentation. Coordinate shared General/Menu and Pages changes with existing workers.

## Current state
- Activation and scoped edit followed branch-local `AGENTS.md`, Dev skill, shared worker protocol and roster.
- Baseline before this change: `3f0a732c51edc60b671007ec017a71d0a4867f86`.
- The common Public submenu return button is icon-only for every child level. The parent name remains in its accessible label; the existing separator and child entries remain.
- The return control has a 44-pixel minimum horizontal target at the default root text size. Existing submenu state, selection, the return interaction and routable parent-page entries are unchanged.
- Scope: `resources/views/layouts/app.blade.php` and `resources/css/public-presentation.css`. No test source or shared Admin/Page code was changed.
- Verified: source and affected CSS owner reviewed. Not verified: local runtime, browser geometry, touch/keyboard interaction or user acceptance.

## Next
- Obtain browser review for child navigation at desktop and mobile widths, including ordinary routable parents and navigation-only Group Nodes.
- Check the Back control's focus, click target, Escape behavior, separator spacing and cyclic overflow. Testing worker may reconcile existing navigation tests in its own branch.
