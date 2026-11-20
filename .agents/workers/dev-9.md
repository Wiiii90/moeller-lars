# Dev Worker 9 — Admin Live Preview

**Branch:** `dev`  
**Lease:** 28 (active)  
**State:** Native scrolling and middle-button drag accepted by the artist; Help retained. Conflicting modifier-wheel zoom removed. Touch and Help browser acceptance pending.

## Scope and current decisions

- Shared Admin Live Preview in General, Gallery and its direct consumers. Coordinate with Dev Worker 7's shared device-picker and styling ownership; Gallery selection/focus and General Appearance remain with feature owners.
- Artist confirmed on 2026-11-20 that native scroll-owned zoom, full edge reachability and middle-button dragging work well. Keep normal mouse wheel for page scrolling, middle-button hold-and-drag/held Space for free movement, and clickable Public links while zoomed. Persistent Pan toggle remains retired.
- Artist explicitly rejected Ctrl + mouse wheel as a Preview zoom gesture because it conflicts with native browser magnification. No modifier-wheel zoom is registered or documented. Continue to use the existing Zoom In / Zoom Out actions and fitted-percentage reset.
- The shared Help bulb appears immediately after Open Preview in the Preview toolbar, using the existing `x-admin.help` popover with the central toolbar variant. It describes the currently available mouse and touch controls. Do not create an independent tooltip design.
- Intended touch model is one-finger normal Public scrolling and taps, with two-finger panning of a magnified Preview. This remains **untested on a real touch device**.

## Source owners and verification

- `resources/js/admin-live-preview.js` retains accepted scroll-owned fitting, middle-button and Space gestures, per-iframe pointer listeners, and two-finger touch panning. Modifier-wheel listener/state and zoom interpolation were reverted, restoring the existing toolbar-only `changeZoom(step)`.
- `resources/views/components/admin/live-preview.blade.php` retains centralized Help after Open Preview; removed the parent modifier-wheel event and the obsolete Ctrl+wheel instruction.
- `resources/views/components/admin/help.blade.php` / `resources/css/admin/forms.css` retain the shared Help component's optional toolbar sizing; field Help remains the same.
- Earlier bounded in-memory tests verified basic native zoom/middle-button movement. Actual user browser feedback accepts mouse navigation; Help popover, narrow toolbar and touch are still unverified. No test suite, local preview, build, Continuous Integration, deployment or publication was run for this rollback.

## Next

1. Receive user browser review of Help placement, interaction and accessibility in General and Gallery and check existing zoom buttons/page scrolling remain unchanged.
2. Verify a real touch device or isolated controlled browser before claiming Touch behavior is supported.
3. Before any further edit recheck `dev` HEAD, lease 28 and Dev Worker 7's overlapping Preview ownership.
