# Dev Worker 1 — Journal

- **Working branch:** `dev`
- **Lease:** 34
- **Status:** Active; common Journal Stage and focused Live Preview source-integrated, runtime/browser acceptance pending.
- **Assignment:** All Journal templates (Blog and Exhibitions). Provide a shared Stage and live Public Preview, with Exhibition-specific open/focus behavior. The artist will define the left-side Editor controls in the next iteration.
- **Accepted Stage design:** Same central height/divider and device Preview as Gallery/General. Left pane is only the heading `Editor`, with no Stage tabs or new editing controls. Right pane shows the reusable interactive Live Preview with default-on Auto-focus. The existing toolbar and Journal Settings/Add/Edit actions remain authoritative; no separate editor, setting persistence, or staging data model is created.
- **Scope ownership:** `JournalWorkspace`, Journal workspace Blade, Public Exhibition deep-link focus, and shared Stage/preview primitives. Dev Worker 5 owns shared Pages/Journal template type architecture; Dev Worker 4 owns Gallery. Changes to their consumers are strictly limited to reusing the central Stage and unified preview event, preserving existing functionality.
- **Source baseline:** Registration parent `18e4ef09547cdeefb2959d50ab8fcc8cb1aeb74a`.
- **Implemented:** One direct Gallery/Journal two-pane Stage geometry owner in shared CSS with responsive editor-only Narrow/Minimal. A single existing Public iframe preview reused in Blog/Exhibitions; Blog selection targets a private post URL, Exhibition selection targets a stable public hash and automatically opens its native disclosure. Selected-count actions, auto-focus toggle and mutation refresh remain on the Journal owner. Existing toolbar functionality is preserved.
- **Verification:** Static source/target checks only; no local application runtime, full build, rendered browser or end-to-end tests executed. Not yet artist-accepted.
- **Next:** Review the published scoped diff and run local syntax/browser verification when an isolated runtime is available. Check Wide/Compact/Narrow/Minimal, unpublished posts/exhibitions, selection and deselection, auto-focus off/on, public hash navigation and closed/open disclosure, successful settings/actions, and regression in Gallery. Then design the Journal Editor with the artist without preemptively inventing forms or staging state.
