# Dev Worker 9 — Navigation

**Branch:** `dev`
**Lease:** 21
**State:** Active — hover underline visibility repaired; application-browser acceptance pending
**Claimed work:** Public cyclic inline navigation including timed hover, child return, and direct presentation. General/Menu and Pages remain with their owners.

## Current state
- User confirmed that hovering `Prints` opens its submenu after about two seconds but the countdown line does not visibly shrink.
- Root cause: a `prefers-reduced-motion: reduce` CSS override imposed `steps(1,end)` on the functional underline animation. At one second in real Chromium, the CSS transform stayed at `scaleX(1)` under reduced motion, whereas ordinary mode reached about `scaleX(.5)`. The completion event was unaffected, explaining why the submenu opened without visible shrink.
- Removed the defective override from canonical `resources/css/public-presentation.css` at baseline `8af2a7b10a4f909da9b261162b54a11b9ffd4ddd`. Both motion settings now use the same two-second linear, symmetric underline collapse and its existing completion event. No timers, alternate renderers or navigation lifecycle changes were introduced.
- Controlled Chromium reproduction verified the computed midway transform with and without the override for both motion settings. This is component-level evidence; the full application has not been browser-tested in this worker session.
- Previous icon-only child Back control, immediate click/keyboard access, pointer exit cancellation and cyclic whole-item navigation are unchanged.
- No test source or general docs edited.

## Next
- Review the current built public preview in a browser using `Prints`, with normal/reduced Windows motion settings, verifying smooth shrinking over exactly two seconds, early leave cancellation, link/click behavior and expected submenu opening.
- If an application build still behaves differently, inspect its actually loaded stylesheet revision and the computed pseudo-element `animation-timing-function` and `transform` instead of applying further speculative patches.
