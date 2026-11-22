# Dev Worker 8 — Shared Admin dialog header actions

**Branch:** `dev`
**Lease:** 40
**State:** Active, source-integrated; actual browser acceptance still pending.

## Assigned scope and accepted semantics
- Filament native X remains at far right. For submitted Create/Command/Confirm, Submit sits immediately left; optional Create Clear stays farther left. Edit/Viewer action rails unchanged.
- Create and Command X stays neutral for untouched opening defaults; turns danger-red when data that would be discarded has changed, including text typed before its blur; returns to neutral when fully restored and after close/reopen. No new confirmation/close implementation.
- Create Submit green if opted-in editorial readiness fields are present, orange when still creatable but editorially incomplete; native disabled/busy Submit remains muted. Dangerous Confirm remains red. The advisory indicator never changes validation, submission or default title/slug generation.
- Opt-in readiness: Gallery Artwork Create requires a title and primary Media File to show green; Journal Exhibition Create requires a title and start date to show green. Other dialogs remain unchanged unless intentionally opted in later.

## Canonical source and cause
- Filament Actions 5.7.6 uses `mountedActions[index].data`, **not** `mountedActionsData`; the earlier visual observer read a nonexistent path, explaining the neutral X. Inputs configured for blur-only synchronization additionally need local immediate input values for visual feedback.
- `app/Filament/Support/Dialogs/AdminDialog.php`: shared attributes, readiness-field opt-in and existing action semantics.
- `resources/js/admin-dialog-draft.js`: single visual-state comparison of the current native mounted Action data with opening baseline, plus input/change patches until Livewire synchronizes. Resets on native open/closed lifecycle.
- `resources/css/admin/dialog-contract.css`: color variables for danger, success, amber/incomplete and disabled actions.
- Direct consumers: `GalleryWorkspaceArtworkDialogs::addArtworkAction()`, `JournalWorkspace::addExhibitionAction()`.
- Existing `resources/js/admin-viz.js` imports the same shared module. No new parallel form persistence, custom modal or domain validation.

## Verification and next
- Verify the committed code on current `dev`, then run bounded syntax and pure state checks; neither replaces browser validation.
- Browser review still required: freshly opened Create defaults, instant typing, revert, Clear, reopen, Media picker, partial date, nested Command, no-readiness forms, enabled/disabled Submit, Edit/Viewer unaffected, light/dark and narrow viewports.
- Journal's *modal* exhibition title currently remains required by its schema. The Stage's untitled draft convenience is a separate owner; do not silently weaken unrelated validation in this dialog-style task.
- Current `docs/admin/dialogs.md` still describes the rejected action ordering and requires later reviewed Docs reconciliation.
- No deployment, tests on shared databases, branch transfer or broad test runs authorized.

## Next
1. Browser-qualify the interactive input/close lifecycle and source readiness projections.
2. Reconcile known source issues if acceptance identifies them, without workarounds; hand stable behavior to Docs.
