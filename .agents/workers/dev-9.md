# Dev Worker 9 — Admin Live Preview

**Branch:** `dev`  
**Lease:** 28 (active)  
**State:** Cursor-anchored wheel zoom source-corrected after artist feedback; real-browser review pending.

## Ownership and current decisions

- Own the shared `x-admin.live-preview` controls and gestures in General and Gallery. Coordinate with Dev Worker 7 on shared device controls; Gallery Focus/selection and General Appearance remain with their respective owners.
- Artist confirmed that the scroll-owned zoom surface, complete edge reachability, middle-button press/drag and clickable Public menu work better. Preserve that accepted interaction and the existing zoom buttons/percentage reset.
- **Current trial (2026-11-20):** unmodified wheel inside Live Preview now zooms toward the cursor; hold Shift while using the wheel inside the Public iframe for vertical Public-page scrolling. The same-origin iframe and enclosing preview viewport use a single controller. Ctrl/Command + wheel is deliberately not intercepted, because browser zoom belongs to the browser; no browser-wide listener is added. Outside the Preview the normal wheel behavior is unchanged.
- At zoomed sizes, the middle button plus drag (or Space plus left drag) pans both axes. No persistent Pan toggle or idle overlay blocks Public links. Touch still uses native one-finger page interaction and a two-finger movement gesture for a zoomed viewport, unverified in a real device.
- Shared bulb Help remains immediately right of Open Preview, reusing `x-admin.help` and its opt-in toolbar variant; its instructions now reflect this trial. Device picker/rotation and Gallery Focus stay unchanged.

## Implementation and evidence

- `resources/js/admin-live-preview.js`: `zoomWithWheel()` uses actual wheel deltas with bounded smooth multiplicative zoom. It delegates to the existing `fitPreview()` transform/scroll owner and anchors zoom under the pointer. The iframe registers a scoped non-passive wheel listener in `bindFrameShortcuts()` and removes it on refresh/destroy. Browser modifiers are ignored. Shift-wheel scrolls the same-origin Public document rather than zooming.
- `resources/views/components/admin/live-preview.blade.php`: local viewport wheel handling and updated Help text; no new button or separate popup.
- A bounded in-memory JavaScript syntax/behavior check passed for ordinary wheel zoom, browser modifier preservation, Shift-wheel Public scrolling, iframe listener registration/removal, percentage reset and untouched touch methods. No application build, full suite, CI, deployment or real browser/touch check was executed.
- **Product tradeoff for review:** plain wheel no longer scrolls the embedded Public document; Shift-wheel offers that route. Trackpad two-finger wheel-like scrolling may also zoom. The user asked to view and evaluate this behavior rather than freeze it as final acceptance.

- 2026-11-20: Artist confirmed that direct wheel zoom otherwise works but the displayed Help text still appeared outdated. Simplified the shared tooltip to concise gesture instructions. On `dev` the previous wording was already correct for wheel zoom, so a stale running Blade view or browser-rendered copy remains possible; no runtime cache inspection or browser verification was performed.

## Next

1. Inspect in the artist's running General and Gallery preview: wheel direction/sensitivity, cursor anchoring, mouse scrolling versus Shift-wheel, links after zoom, middle drag, Help text, narrow layout and standalone browser shortcuts.
2. Keep or revise the trial based on explicit user browser feedback. Verify touch on a real device before claiming mobile acceptance.
3. Re-read current `dev` HEAD, roster lease and Dev Worker 7's overlap before further mutations.

## 2026-11-20: Pointer-anchored zoom correction

- Artist reports that wheel zoom tends toward the top-left instead of enlarging the region underneath the cursor, despite accepting the direct-wheel interaction overall.
- `resources/js/admin-live-preview.js`: resolve iframe-local coordinates from its actual rendered rectangle and native client size rather than guessing at composite CSS scale. The Admin Stage may have an ancestor transform, so convert window-screen pixels into Preview scroll-layout pixels before computing the anchor.
- Apply anchor scrolling together with the fitted surface transform in one synchronous layout pass; avoid temporary center-based repositioning. Continue to clamp through native overflow scrolling, preserve middle-button and Space drag, normal Public link interaction, touch gestures, device controls and the concise Help.
- **Verification:** JavaScript parsing succeeded. Isolated geometry simulation with an enclosing 0.8 CSS scale, 1200x900 iframe and 400x300 Preview preserved the same cursor location across seven consecutive wheel zooms (100% to 300%) and repeat fit recalculations with less than 0.001 CSS pixel drift. This is not browser validation.
- **Open:** Verify zoom toward paintings and menu locations in the actual General and Gallery Live Preview, including an already-shifted viewport and viewport resizing. No application build, suite, CI, deployment or production action performed.

