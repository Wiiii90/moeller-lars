# Dev Worker 8 — Dialog header action alignment

**Branch:** `dev`
**Lease:** 40
**State:** Active; scoped source changes integrated, runtime and browser review pending.

## Assignment and accepted outcome
- Keep the native Filament close X in the rightmost upper-header slot. In submitted Create, Command and Confirm dialogs, Submit is immediately to its left; Create's optional Clear stays farther left.
- In Create/Command draft forms, the X signals discarded unsaved changes with the existing danger treatment. Normal Submit uses the Stage's success-green semantics. Danger Confirm submit stays red; non-danger Confirm submit is green. Edit/Viewer X and contextual actions stay unchanged.
- Scope is the shared dialog action factory, header chrome, focus styling and direct submitted-dialog consumers, not feature-local form schemas or unrelated stages.

## Current source
- Starting implementation baseline: `a1329ad0f4d10fd071d9939f49970cb3cc6a7605`.
- `app/Filament/Support/Dialogs/AdminDialog.php`: source-level action color roles.
- `resources/css/admin/dialog-contract.css`: native X/Submit placement and shared semantic action variables.
- `resources/css/admin/dialog-interactions.css`: keyboard-visible focus inherits the action's semantic color without reintroducing a pointer-hover backdrop.
- Reference: `resources/views/filament/pages/journal-workspace.blade.php` and `resources/css/admin/task-surfaces.css` for Stage Discard (danger) and Submit (success).

## Verification and remaining gates
- Reviewed the published change at `dev@aefc67971b430403daffe37f35d0a60c69cd87cf` against the canonical factory and shared styles. Ten focused source/lease/head invariants passed. No local application checkout or authenticated browser was available for runtime qualification.
- Browser acceptance pending: Create with Clear, Command, danger/non-danger Confirm, Edit/Viewer preserved, light/dark, keyboard focus, narrow widths, modal close/submit behavior and overlapping hit targets.
- The previous order remains in `docs/admin/dialogs.md` and needs a later reviewed Docs reconciliation. Avoid unrelated documentation edits on this Dev assignment.
- No branch transfers, deployment, full test suites or application data writes authorized.

## Next
1. Perform targeted browser verification when an authorized running app is available.
2. Request a reviewed Docs reconciliation of the outdated dialog order rule after acceptance; do not change Docs as routine Dev work.
