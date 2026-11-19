# Dev Worker 9 — Navigation

**Branch:** `dev`
**Lease:** 21
**State:** Active — public submenu hover countdown implemented; browser acceptance pending
**Claimed work:** Public cyclic inline navigation and child return controls. General/Menu and Pages remain under their current owners.

## Current state
- Source baseline for the current change: `50491001c6c159a1566cafc7aeff6a271ff749a9`.
- All root items with a child menu keep immediate click and keyboard opening. On fine-pointer hover, their existing underline visibly shrinks from both ends into its center for 1.9 seconds, then opens the existing submenu via the existing `showChildren` operation.
- A mouse exit cancels the countdown without opening. Countdown styling requires the initialized Public navigation, so scripts-disabled pages do not show a misleading countdown. Root items without children retain ordinary hover underlines. Coarse pointers, touch and reduced-motion settings keep the existing click behavior.
- The countdown derives from the CSS animation completion event; there is no independent JavaScript timer, duplicate child renderer or change to the cyclic window algorithm. Hover does not steal focus except when the parent is already the active keyboard-focused element.
- The previous icon-only child Back control and unified keyboard navigation remain.
- Production scope: `resources/js/public-navigation.js` and `resources/css/public-presentation.css`; Dev has not edited tests or general docs.
- Verified: current branch lease, source-level event/style integration and checked Git commit. Browser rendering, hover duration, pointer exit, focus and responsiveness remain unverified.

## Next
- Browser-review a parent item with children: full line on hover, two-sided linear shrink for 1.9 seconds, then immediate child-level switch. Confirm pointer exit aborts and the next entry starts anew.
- Check ordinary page items, immediate parent clicks, keyboard opening/Back/Escape, root/child cyclic overflow, touch/mobile, reduced-motion settings, active-item styling and navigation after document swaps. Testing owner can reconcile automated tests on `testing`.
