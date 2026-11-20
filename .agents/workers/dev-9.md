# Dev Worker 9 — Admin Live Preview

**Branch:** `dev`  
**Lease:** 28 (active)  
**State:** Shared zoom/pan interaction replacement implemented in source; runtime/browser acceptance pending.

## Ownership and current user requirements

- Shared Admin Live Preview in General and Gallery and direct uses of `x-admin.live-preview`. Coordinate component/device-picker overlap with Dev Worker 7; Gallery content selection/focus and General Appearance settings remain owned by their feature workers.
- User reports that zoom cuts off free movement, including reaching the top; the persistent Pan tool blocks Public menu/link clicks. User requests middle-mouse-button horizontal and vertical panning plus a viable touch interaction.
- Preserve single actual Public iframe, embedded native page scrolling, normal clickable navigation, selected device and rotation, zoom/fit, Gallery Focus, appearance refresh and responsive Stage geometry.

## Source implementation

- `resources/js/admin-live-preview.js`: native Stage viewport scrolling replaces manually clamped negative `panX/panY` and CSS frame offsets. The fitted page is the scroll content at current zoom; zoom preserves the content center. Middle-button drag works in the same-origin iframe and the surrounding Stage through temporary document listeners and pointer capture. Held Space + left drag remains an alternative. Two-finger touch gesture pans the zoomed viewport while one-finger page interaction remains native.
- `resources/views/components/admin/live-preview.blade.php`: removes the persistent Pan toggle; its overlay now appears only during a gesture, leaving Public links/buttons interactive the rest of the time. Mouse and touch gesture hooks live on the canonical Preview viewport.
- `resources/css/admin/live-preview.css`: replaces the clipped, centered Stage with a native scroll owner; centered fit uses auto margins, with no inaccessible negative overflow. Keeps scrollbar chrome hidden without suppressing native scrolling. The device picker, profiles, and Gallery Focus markup are unchanged.

## Verification and open gates

- In-memory JavaScript parsing and bounded simulated fit/zoom/middle-drag/reset checks passed (fitted 400x300, zoom 800x600 with centered native scroll offset; drag scrolls in both directions and cleans up listeners).
- No application/runtime build, connected browser, touch device, existing test suite, Continuous Integration, deployment, database mutation or publication performed.
- **Browser acceptance pending:** General and Gallery at 100%, 125%, 200% and 1:1 zoom; reach all four edges and full top after inner page scrolling; middle-button gesture started inside iframe and across its border; normal Public navigation clicks after pan and with zoom active; native wheel scrolling, Space+drag, keyboard, one-finger scrolling/taps and two-finger touch panning; device rotation/selection, Gallery Focus and refresh; narrow Stage and Stage expansion.
- Middle mouse behavior is press-and-drag, not a sticky click-once browser autoscroll mode. If the user specifically requires click-once autoscroll, obtain that product decision rather than adding a parallel mode.

## Next

1. Obtain the user's real-browser General/Gallery interaction feedback; isolate any event/geometry discrepancy in the shared component before further edits.
2. Re-read `dev` HEAD, roster lease and Dev Worker 7's overlapping Preview scope before the next mutation.
