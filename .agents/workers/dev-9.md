# Dev Worker 9 — Navigation

**Branch:** `dev`
**Lease:** 21
**State:** Active — reduced-motion countdown defect corrected; project-browser acceptance pending
**Claimed work:** Public cyclic inline navigation, including hover countdown and child Back control. General/Menu and Pages stay with assigned workers.

## Current state
- User reported that hovering `Prints` never opened its child menu after repeated source commits; prior reports did not establish browser acceptance.
- In `dev` at `577b2e3148e135ac380e373938a8f77a473b21ff`, the countdown was enclosed in `@media (prefers-reduced-motion: no-preference)`, yet its `animationend` event was the **sole** hover-triggered submenu opener. Under `prefers-reduced-motion: reduce`, no animation meant no completion event or submenu opening. A controlled real-Chromium reproduction confirmed the missing event.
- The canonical CSS animation now runs for two seconds for either motion preference. When reduced motion is requested, a `steps(1, end)` timing function keeps the underline static until the 2-second completion; ordinary mode retains the symmetric shrink. This fixes the broken functional trigger without adding JavaScript timers, parallel opening logic or a second renderer. Click, keyboard, touch and ordinary page items are unchanged.
- Controlled Chromium reproduction confirmed `animationend` on `::after` bubbles from the label to the parent control with the expected name in both motion settings after this change. This is **component-level browser evidence**, not verification of the running application or the user's browser configuration.
- Production owner changed: `resources/css/public-presentation.css`; worker record updated. No tests or general documentation edited.

## Next
- Verify live browser functionality on the actual built `dev` preview: normal 2-second symmetric shrink, hover open, early pointer leave cancellation, instantaneous click, Back/Escape, and standard page links. Confirm reduced-motion opening without movement.
- If it still fails in the project preview, inspect the loaded Vite revision, actual computed pseudo-element animation and registered pointer listeners on the live navigation; do not apply additional speculative source patches.
