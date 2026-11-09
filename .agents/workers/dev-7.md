# Dev Worker 7 — General Appearance preview corrections

**Branch:** `dev`  
**Lease:** 13 (active)  
**Status:** Assigned; source investigation pending.

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

## Next
- Trace Appearance entrypoint, state and rendering contracts and implement finite root-cause corrections.
