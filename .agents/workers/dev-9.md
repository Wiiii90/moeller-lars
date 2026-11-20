# Dev Worker 9 — Admin Live Preview

**Branch:** `dev`  
**Lease:** 28 (active)  
**State:** Direct mouse-wheel zoom works in the artist's browser; shared Help shortened in source, rendered-view freshness to be checked.

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
