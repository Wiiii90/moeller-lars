# Dev Worker 9 — Admin Live Preview

**Branch:** `dev`  
**Lease:** 28 (active)  
**State:** Existing mouse navigation browser-accepted by the artist; Help and modifier-wheel zoom source-integrated, browser acceptance pending.

## Ownership and accepted behavior

- Own shared `x-admin.live-preview` controls and gestures across General and Gallery. Coordinate with Dev Worker 7's device picker and shared Admin Select styling; Gallery focus/selection and General Appearance settings remain with their owners.
- Artist confirms (2026-11-20) that native scroll-owned zoom, middle-button drag and fully reachable page edges now work substantially better. Maintain ordinary clickable Public menu, normal unmodified mouse-wheel scrolling, Space+drag alternative and toolbar zoom/reset.
- The persistent Pan toggle has been retired; only an active gesture may temporarily cover the embedded page. Touch remains untested by the artist.

## Current implementation

- `resources/js/admin-live-preview.js`: native scrollable Stage viewport and same-origin Public iframe with temporary middle-button/Space/two-finger gestures. New Ctrl/Command + mouse-wheel zoom at pointer focus; native wheel scrolling remains untouched without the modifier, input is normalized by wheel delta mode and per-notch accumulation. One owner binds/unbinds the iframe's non-passive, capture-phase wheel listener on load/refresh.
- `resources/views/components/admin/live-preview.blade.php`: one shared Help bulb to the right of Open Preview in every General/Gallery consumer, using existing `x-admin.help`; parent viewport captures modifier-wheel zoom. Existing Gallery Focus action, Open Preview behavior and device profile selection remain intact.
- `resources/views/components/admin/help.blade.php` and `resources/css/admin/forms.css`: opt-in `toolbar` variant applies established Admin action-button geometry to the existing popover's trigger; ordinary field-label Help remains unchanged.

## Verification and open gates

- JavaScript parsed in isolation. An in-memory gesture simulation confirmed unmodified wheel does not modify zoom, Ctrl-wheel prevents browser zoom, pointer-anchored zoom increments, iframe wheel binding and listener removal. These are **not** browser or touch acceptance.
- **Still unverified:** hover/click/keyboard Help placement in General and Gallery, toolbar fit across narrow widths, Ctrl-wheel inside the iframe and on its edge with actual Chromium, touch one-finger scroll/tap and two-finger pan, zoom with native scroll and clickable navigation in the actual running preview.
- No application build, persistent tests, CI, deployment, database mutation or production action performed.

## Next

1. Review the real General/Gallery browser after updating to the new source, focusing on Help popover and Ctrl-wheel without losing plain-wheel scrolling or link clicks.
2. Test the touch gestures on a device or isolated browser only when appropriate; do not claim verified touchscreen behavior from source review.
3. Before any further edit, confirm exact `dev` HEAD, lease 28 and overlap with Dev Worker 7.
