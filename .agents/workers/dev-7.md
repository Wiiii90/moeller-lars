# Dev Worker 7 — General Appearance preview corrections

**Branch:** `dev`  
**Lease:** 13 (active)  
**Status:** Active; expanded Appearance height corrected in source (browser review pending).

## Assigned scope
- Appearance / Global: review frame style combinations and their selection presentation; correct site icon Storage picker popover clipping.
- Appearance / Title: restore hide/show and responsive-size persistence/preview; reconcile beside/inside/above menu placement, alignment, and narrow-screen behavior against existing accepted contract.
- Appearance / Page: review and reasonably expand content width and padding bounds without breaking menu/title or viewport geometry.
- Appearance / Menu: correct full-width divider crossing the content region and unstable live preview.
- Appearance dialog: restore a stable height based on the tallest of Global, Title, Menu, Page tabs when expanded ("More space") and preserve Preview during tab/control changes.
- Regional Formats: consistent section spacing and discoverable date/number usage hints.
- Appearance / Legal: review field purpose and artist-facing content instructions; do not fabricate legal advice.

## Boundaries
- Work directly on `dev`, coordinate shared Admin/Public controls with active workers. Do not edit tests or general documentation.
- No unsolicited Continuous Integration, deployment, release or other branch writes; preserve artist data.
- Browser behavior unverified without actual review.

## Current progress
- Replaced active-tab-dependent expanded height with the tallest mounted Appearance panel as the intrinsic grid sizing owner; compact mode retains active-tab-only scroll. No duplicated measurement or remount.
- Regional Formats now reuse Contact/Legal field geometry, with public date/number purpose help and German translations. Content width bounds widened from 640–1440 to 560–1920 pixels, and padding bounds from 24–180 to 12–320 pixels using existing viewport-safe shell and effective minimum-artwork-width logic. Browser behavior remains unverified; title/menu, persistence, picker and Legal issues remain under investigation.

## Next
- Trace and correct the disappearing Preview/persistence and public title/menu composition; then review Popover, bounds, Regional Formats and Legal content.
