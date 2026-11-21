# Dev Worker 7 — General Admin Page

- **Branch:** `dev`
- **Lease:** 37
- **Status:** Active; screenshot-driven Legal editor repair committed; browser acceptance pending.
- **Claimed scope:** General Legal editor and the existing shared Stage tabs/Help and Rich Text visual frame.
- **Current task:** Remove the obsolete 2×2 Legal textarea CSS and Filament wrapper spacing, suppress duplicate below-tab labels, retain original full help text as tab-attached bulbs, and style the real central Rich Text editor frame (including nested Filament root). Preserve the five persisted values, existing publication and inherited media rights; never replace or reset user-authored default copyright.
- **Current baseline:** `daa99f0cac464637a8a05c23d8b846d72ee37c7b`.
- **Verification:** `83efc6b2d93cc741acbd84798cdf80516b46cd4f` source commit verified in `dev`; no browser review, running server, migrations, CI or deployment. The current repair is source-complete but not visually accepted.
- **Next:** Inspect rendered panel width, editor border, five bulb hover/focus, missing-gap elimination, selected tab persistence and save/reopen with unchanged default copyright; check public Legal and plain media metadata separately. Recheck worker lease and current dev head before next write.
