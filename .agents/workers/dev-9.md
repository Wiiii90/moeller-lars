# Dev Worker 9 — Navigation

**Branch:** `dev`
**Lease:** 21
**State:** Active — hover countdown correction committed; browser acceptance pending
**Claimed work:** Public cyclic inline navigation, including the parent hover countdown and child Back control. General/Menu and Pages remain with assigned workers.

## Current state
- The previous hover countdown source relied on `(hover: hover) and (pointer: fine)`, which checks the device's primary pointer rather than the pointer actually interacting with the menu. This could block the feature despite a connected mouse. The implementation also used an unnecessarily fractional 1.9-second duration.
- At source baseline `ef585188ff56d098dd15b4a40d2ad2493844bd84`, the existing parent control now begins its underline animation on a real mouse `pointerenter`; `pointerleave` cancels it. The same underline shrinks symmetrically and its `animationend` opens the existing child level after exactly **2 seconds**. There is no additional independent timer or second menu-opening owner.
- Click/keyboard actions remain immediate; regular links have no countdown; touch and reduced-motion users retain click. Existing cyclic-window behavior, icon-only Back and shared keyboard navigation remain unchanged.
- Changed production files: `resources/js/public-navigation.js` and `resources/css/public-presentation.css`. No test or general docs edits.
- Verified: source changes, matched event/selector, checked branch publication. Not verified: browser acceptance on the user's actual device/preview environment.

## Next
- In browser, check a submenu parent with an actual mouse: line shrinks from both sides and opens at 2 seconds. Leaving early cancels and re-hover restarts. Verify immediately clicking opens, and a plain page link keeps its normal underline.
- Confirm initial entry, SPA navigation, return to main, narrow viewport, touch/coarse-primary hybrid device and reduced-motion behavior. If the countdown still does not visibly run, inspect the actual computed animation/media environment and loaded revision before changing unrelated owners.
