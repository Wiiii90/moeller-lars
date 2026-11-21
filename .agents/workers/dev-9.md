# Dev Worker 9 — Admin Live Preview

**Branch:** `dev`  
**Lease:** 28 (active)  
**State:** Shared View Options Alpine syntax corrected; Gallery selection-driven Preview path reviewed, actual browser acceptance pending.

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



## 2026-11-20: Restore browsing through long Public pages

- User confirms cursor-anchored zoom feels better but reports inability to scroll a long embedded Public page, including when middle-button panning reaches the boundary of the initial device viewport. This is an interaction issue between the outer fitted Preview canvas and the iframe's independent scrolling document, not a canvas pan-clamp defect.
- `resources/js/admin-live-preview.js`: reserve plain wheel for native Public-page scrolling; use Shift + wheel for pointer-anchored Preview zoom. Do not intercept Ctrl/Command, Alt or wheel outside the Preview. The iframe listener remains scoped and cleaned up on refresh/destroy.
- `resources/js/admin-live-preview.js`: middle-button drag can now begin even at fitted 100% when the iframe document is scrollable. At any zoom, consume pointer displacement in the outer Preview viewport first and transfer remaining distance, normalized by render zoom, into the same-origin Public document's scroll root. Two-finger panning of the magnified Preview shares that scroll-boundary handoff. Native one-finger browsing and Public click targets remain untouched when idle.
- `resources/views/components/admin/live-preview.blade.php`: update the compact central bulb Help with plain-wheel page scrolling, Shift-wheel zoom, and drag gestures. Keep the temporary pan overlay visible during active drag at fitted zoom. Device and Gallery focus controls unchanged.
- In-memory JavaScript parsing and bounded simulated interaction succeeded: unmodified wheel not intercepted, Shift-wheel zoom active, dragging at magnification moves the outer viewport and continues into iframe scroll, and middle-drag at 100% scrolls the Public document. Simulated document page height: 4200 CSS px versus 900 CSS px iframe viewport. The drag state is disposed after release.
- **Acceptance still open:** Actual General/Gallery browser checks for native wheel scrolling across long Public pages, Shift-wheel pointer zoom, drag across both boundaries and in reverse, proper Public links and hover state, touch gestures and browser back/forward conflicts. No application build, full test suite, CI, deployment or publication executed.


## 2026-11-20: Retire conflicting Space drag

- Artist confirms that ordinary wheel scrolling, Shift + wheel zoom, and middle-mouse-button drag with inner Public-page scroll chaining now work; the Space + left-button drag behavior conflicts with normal keyboard/browser operations and is redundant.
- Shared `resources/js/admin-live-preview.js` no longer tracks `spacePan` or pointer-presence keyboard state. Removed all Space key handlers from both the outer Preview and the iframe document, including their teardown. Drag starts only on pointer button 1 (middle mouse). The iframe blur guard still cancels an active gesture. No additional drag overlays persist at rest.
- `resources/views/components/admin/live-preview.blade.php` removes window keyboard listeners and hover-state bookkeeping; the pan layer shows exclusively during active drag. Compact shared bulb Help documents middle-button drag only.
- `resources/css/admin/live-preview.css` updates the existing pan-layer comment; geometry, device controls, zoom input, and touch behavior are unchanged.
- Isolated JavaScript parse/gesture simulation: Space/left-pointer input ignored, middle-button drag scrolls the actual Public document beyond the initially visible device area, iframe keyboard listeners absent, wheel listener and gesture cleanup preserved.
- Full build, browser test, touch test, suite, CI and deployment were **not run**. Review in General/Gallery with keyboard Space, middle-button drag at fitted and enlarged sizes, native wheel scroll, Shift-wheel zoom and Public navigation clicks.


## 2026-11-21: Mouse-dismissal focus cleanup for shared toolbar Help

- Artist reports that hovering the bulb, clicking it to close the tooltip, then pressing Shift activates a persistent visible rectangular outline around the now-closed Help button.
- Root cause: the shared `x-admin.help` trigger retains native button focus after the pointer-triggered close. Pressing a keyboard modifier can activate the browser's `:focus-visible` heuristic. The normal Help focus outline and shared Preview toolbar focus border are desirable **for keyboard-driven focus**, but should not linger after dismissal by mouse.
- `resources/views/components/admin/help.blade.php`: use one centralized opt-in `toolbar` flag already carried by the canonical Help component. Track the input pointer type on the trigger; after a real **mouse** click that closes the popover, blur that closed trigger so a later Shift key cannot reveal residual focus. A keyboard-triggered click (`detail === 0`), touch click, and all non-toolbar Help controls retain their existing focus behavior. Mouse click to reopen does not blur and remains operable.
- Do **not** change `Shift + wheel` zoom, general `:focus-visible` styling, or shared Stage toolbar CSS. No duplicated Preview-local tooltip logic.
- In-memory Alpine object parsing and behavior simulation passed for toolbar mouse-close/mouse-open, keyboard-close, touch-close and ordinary field Help. Browser reproduction on actual Chromium/Firefox and keyboard accessibility acceptance remain pending; no suite, build, Continuous Integration or deployment was run.


## 2026-11-21: Scaled iframe page-background paint gap

- User confirmed that the centralized Help bulb focus fix worked. New General Preview screenshot shows the page-background gradient abruptly terminating at a horizontal line while the embedded Public page scrolls, exposing white underneath; standalone Public Preview displays the background correctly. This concerns **initial loaded/scrolled iframe painting**, not saved appearance values.
- Root cause hypothesis grounded by source: Public body and sticky header both use `background: var(--public-page)` with `background-attachment: fixed` in `resources/css/public-presentation.css`. The Admin Preview scales the embedded iframe's rendering via a CSS transform, which can expose its default white root canvas during inner-document scrolling/repaint. CSS background propagation between body and root is a plausible source of the gap; browser-specific proof is not available.
- `resources/css/public-presentation.css`: add a narrowly scoped `html.public-preview--embedded` rule to paint the root canvas using the **same** dynamic `--public-page` variable and fixed attachment. No new hard-coded color or different Public appearance mode.
- `resources/js/admin-live-preview.js`: for a loaded same-origin iframe whose body is a Public site, mark its root as embedded; register the corresponding remove on the existing iframe listener/observer disposal. Standalone Public and standalone Preview never receive this class. No additional iframe, polling, scroll listeners, page height overrides, viewport geometry changes, or Public background-setting mutations.
- Verified by in-memory JavaScript parsing and lifecycle simulation that the embedded marker is set for the Public iframe and removed when shortcuts unbind. This does not prove paint correctness in Chromium/WebKit; app build, browser test, CI, server deployment and production changes were not run.
- **Browser acceptance pending:** General and Gallery initial load at fitted 100%, scroll across the former horizontal boundary, fixed gradient consistency and sticky header; zoom and phone/desktop device selection; standalone Preview rendering unchanged.


## 2026-11-21: Shared Admin pointer-origin focus state

- User verified that the previous Lightbulb focus workaround stopped its outline, but pressing Shift now visibly outlines the Gallery Settings button instead. Both actions belong to the canonical `.admin-action` visual grammar; simply blurring the Help trigger was too local.
- `resources/js/admin-viz.js`: a single document-delegated pointerdown listener marks the most recently **mouse-activated** Admin action. Modifier-only keydown (Shift, Control, Alt, Meta) intentionally preserves this pointer origin, while a real key action (especially Tab / Shift + Tab) clears it *before* keyboard focus navigation. This retains native focus and click/modal handling rather than forcibly blurring Settings or other actions. Touch does not create the mouse marker. No per-button event handlers.
- `resources/css/admin/primitives.css`, `stage.css`, `live-preview.css`, `forms.css`: the existing common action, Stage tab, Live Preview button and shared Help focus styles show keyboard focus only when the current focused control is not marked as mouse-activated. Hover and active/pressed styles remain unchanged.
- `resources/views/components/admin/help.blade.php`: remove the superseded mouse-blur special case. The Help popover retains its existing central show/hide behavior; the shared Admin action policy handles its trigger without a local exception.
- Isolated event-handling simulation checked mouse click on Settings/Help, Shift without navigation, Shift + Tab, touch and Enter keyboard action. Source-level focus selector checks completed; real browser rendering in General/Gallery, Filament Settings dialog focus restoration and accessibility remain pending. No application build, full suite, Continuous Integration, server deployment or production changes performed.


## 2026-11-21: Return to native focus behavior (artist decision)

- Artist clarified that Shift highlights whichever control was last clicked and wants all special focus handling removed for both Settings and Help, even if the native browser focus indicator remains after pressing Shift.
- Reverted the custom pointer-origin focus tracker in `resources/js/admin-viz.js` and its conditional focus selectors in `resources/css/admin/primitives.css`, `stage.css`, `live-preview.css`, and `forms.css`. These were introduced in the previous worker change and are no longer wanted.
- The Help-specific mouse-dismissal `blur()` workaround had already been removed from the canonical `resources/views/components/admin/help.blade.php`; keep it removed. No new focus event listeners, styles, or per-button exceptions.
- Settings buttons, Help triggers, hover/focus-visible states, and Shift follow normal native browser behavior. The shared Live Preview wheel zoom/scroll, middle-button drag, and existing Help popover functionality remain unchanged.
- Verified the affected source blobs match their pre-override versions and the Help component contains no special focus-reset logic. No build, suite, connected browser, Continuous Integration or deployment run.


## 2026-11-21: macOS trackpad gesture parity

- Artist requires usable Mac controls for the same Public Live Preview operations as a Windows middle-mouse-button drag, while retaining the previously chosen Shift + wheel zoom and native scrolling. Do not add a persistent zoom mode, browser-reserved Command/Control wheel zoom, or Space drag.
- Shared `resources/js/admin-live-preview.js`: add **Option/Alt + primary-button drag** as a second way to pan the same outer Preview and inner Public scroll root. It uses the existing cross-iframe pointer conversion, scroll chaining, listener cleanup and pointer capture logic; no new scroller or divergent Mac component. Middle-button dragging remains unchanged and available with physical mice on either system.
- Option + simple click must remain a click, because Safari uses Option-click on a link as Download Linked File. Start a deferred pan candidate on Option+primary pointerdown. Activate only after >=5 screen pixels of movement; no pointer capture or click suppression while waiting. During an actual drag cancel native dragstart and suppress the following pointer-generated click to avoid unintended Public navigation/download. Keyboard-generated clicks remain functional. All listeners and capture are cleaned up when the gesture ends or is cancelled. Command/Control/Shift modified clicks do not start the gesture.
- Shared `resources/views/components/admin/live-preview.blade.php`: adds capture-phase click/dragstart coordination to the canonical Preview viewport, and concise Help text for Mac trackpad, Shift-wheel zoom, mouse drag and touch. The iframe receives matching centralized listeners via the controller; no Preview-specific Alt wheel handler or toolbar-only workaround.
- Native two-finger trackpad scrolling emits normal wheel events and therefore scrolls the Public page. Shift + wheel/trackpad scroll remains the chosen Preview zoom gesture. On touchscreens, native one-finger taps/scroll and existing two-touch movement are unchanged. These are distinct input types, not new hardware emulation.
- Validation: JavaScript parsing and isolated gesture simulations passed for no-modifier left click, Command + Option left click, Option click without drag, movement threshold, actual Option pan at fitted zoom, continuation from an enlarged outer scroll boundary to the Public page, unchanged middle-button pan, pointer-generated click suppression and keyboard-click preservation, plus frame click handler binding and teardown. **No actual Mac hardware/Safari/Firefox/Chromium acceptance, full suite, build, Continuous Integration or deployment.**


## 2026-11-21: Gallery View Options Alpine parse error and selection Preview review

- User reports `Unexpected token 'if'` in the Gallery List View Options `x-init`, with rendered source `const key = 'admin-view.gallery'         if (key)`, and also that selecting an Artwork fails to change Live Preview although Auto-focus is enabled. Separate preloaded-asset warning is not a confirmed causative factor.
- Root cause for the parse error: canonical `resources/views/components/admin/view-options.blade.php` had no statement delimiter immediately following `@js($storageKey)`. Blade can collapse that directive's trailing whitespace/newline into a single line. Fixed the shared template with an explicit semicolon; Gallery and any other consumer of View Options use the same owner. In-memory JavaScript parsing of the generated expression succeeded for both a string storage key and a null key; no full browser runtime has run.
- Traced direct Gallery selection: List/Dense checkbox calls `GalleryWorkspace::toggleArtworkSelection`, which changes `selectedArtworkIds` and calls `syncGalleryPreviewFocus`. The canonical PHP owner dispatches `gallery-preview-updated` for a **changed** valid preview target; the common `x-admin.live-preview` listens for it and updates the same iframe. This pipeline was already wired and is not modified as part of the syntax fix.
- Important accepted Gallery behavior is in `docs/admin/responsive.md`: Auto-focus shows individual Artwork Working preview only if **exactly one** selected row is previewable. Zero/multiple selected rows or an unavailable route show Gallery. Active staged queue/selection has precedence. The `can_preview` producer requires Artwork Pages enabled, an available slug, one viable primary with usable media/ALT, and non-archived state; unpublished Working drafts can qualify. These limitations can independently make the Preview appear unchanged. Do not bypass them and generate a 404 route or duplicate focus logic in JavaScript without a product decision.
- Browser review pending after the shared View Options syntax fix: check that console parse error disappears, select one previewable Artwork in List/Dense/Grid with Auto-focus active and Upload/Settings panel selected; check actual iframe navigation and returning to Gallery on multi-select or deselect. If one eligible selection still does not change Preview, this remains a separate unresolved runtime issue; no build, suite, CI, deployment or data changes occurred.
