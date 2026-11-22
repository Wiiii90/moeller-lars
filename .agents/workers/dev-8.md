# Dev Worker 8 — Dialog header actions

**Branch:** `dev`
**Lease:** 40
**State:** Active; source-integrated, no authenticated browser acceptance yet.

## Scope and accepted behavior
- Filament's native X stays in the last header slot. Submitted Create/Command/Confirm show Submit just left of X; the Create Clear action stays farther left. Edit/Viewer remain unchanged.
- Create and Command X are neutral at initial default state. It becomes danger-red only when the mounted task's current draft differs from its opening data, and returns to neutral on revert or reopen. No faux unsaved status from ordinary default values.
- Submit uses success-green only while actionable; native disabled/busy submit is visually muted, with no new blanket empty-field check. Domain defaults/validation remain authoritative. Destructive Confirm stays red while actionable; Confirm X remains neutral.
- Reuse the shared Journal/Gallery Stage semantic colors. No mutations to individual form schemas, defaults, autosave or confirmation logic.

## Current source and checks
- Dialog action factory: `app/Filament/Support/Dialogs/AdminDialog.php`.
- Canonical styles: `resources/css/admin/dialog-contract.css`, existing focus hardening remains in `resources/css/admin/dialog-interactions.css`.
- Draft state owner: `resources/js/admin-dialog-draft.js` imported in existing `resources/js/admin-viz.js`; reads the active action slot from Filament's `mountedActionsData`, with native modal open/close reset and Alpine reactive comparison.
- Reference: `resources/css/admin/task-surfaces.css` and Journal/Gallery Stage Submit/Discard actions.
- Started on previous source change `aefc67971b430403daffe37f35d0a60c69cd87cf`; current release commit recorded in Git history.
- No local authenticated app/browser was available. Source/syntax verification and rendered acceptance must be distinguished.

## Remaining
- Browser acceptance: Create untouched/edited/reverted/cleared, Command including nested actions, enabled/disabled Submit, default-generated empty Create, destructive/non-destructive Confirm, Edit/Viewer no regression, keyboard hover/focus and narrow/light/dark layouts.
- The existing opposite ordering in `docs/admin/dialogs.md` requires later Docs reconciliation; no routine Dev docs edits.
- No deploy, integration, broad test suite, database mutation or production action authorized.

## Next
1. Review browser action lifecycle and colors in a running authorized instance.
2. Hand accepted dialog contract to Docs for bounded update.
