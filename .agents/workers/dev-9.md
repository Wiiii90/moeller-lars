# Dev Worker 9 — Navigation

**Branch:** `dev`
**Lease:** 21
**State:** Active — scoped Public navigation refinements committed; browser acceptance pending
**Claimed work:** Public cyclic inline navigation and child return controls. Shared General/Menu and Pages ownership stays with assigned workers.

## Current state
- The shared child-level Back control is icon-only, retaining the parent name in its accessible label and the established separator.
- Current refinement source baseline: `b95c22899e854c4f64bfc5cb66ca7ab5d32ed01c`.
- Keyboard arrows navigate visible items on both main and child levels through the existing shared cyclic window owner; the former child-only duplicate handler was removed. Parent ArrowDown opens the child level without also moving focus in the main rail. Listeners are released on navigation disposal.
- Hover and keyboard-focus feedback ease the existing underline and icon movement without changing the one-row geometry, menu timing or cyclic-window algorithm. Motion is absent for visitors preferring reduced motion.
- Changed production owners: `resources/js/public-navigation.js` and `resources/css/public-presentation.css`. No tests or general documentation edited.
- Verified: exact source changes, existing component ownership, branch lease and publication. Not verified: running browser interaction, pointer/keyboard visual geometry, actual smoothness or user acceptance.

## Next
- Browser review at desktop/mobile widths with and without cyclic overflow. Check main/child arrow-key behavior, ArrowDown entering child levels, Back/Escape focus restoration, controls during page swaps and reduced-motion behavior.
- Testing worker should reconcile any affected existing navigation tests on `testing`; no test source changes on `dev`.
