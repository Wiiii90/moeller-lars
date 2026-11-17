# Dev Worker 8 — Shared Admin dialog presentation

**Branch:** `dev`  
**Lease:** 15 (active)  
**Status:** Central dialog styling and structural review in progress; browser acceptance pending.

## Ownership and user decisions
- Own shared Admin dialog window presentation, semantic grouping rules and the Stage-to-Toolbar completeness contract.
- Keep native Filament dialog semantics and the existing `AdminDialog`, `AdminControl`, `AdminForm::section()` and shared CSS owners. No second dialog or control family.
- Use a very small outer dialog corner radius rather than framework-rounded window corners.
- Retain the present dialog background pending an explicit color decision; user is considering a different dialog surface color.
- Every Stage operation must have a corresponding Toolbar/Settings/Add/Manage path. Pure visualization may remain supplementary. Do not implement another domain mutation owner.
- Avoid editing domain-specific dialog schemas owned by active Dev Workers 1–7 without reviewing their concurrent changes and coordinating scope. Do not edit tests, trigger CI, deploy or change other branches.

## Inspected architecture and source findings
- Read current `AGENTS.md`, `.agents/README.md`, Dev skill, branch roster, `docs/ARCHITECTURE.md`, Admin UI, Dialog, Controls, Responsive and Browser-review contracts.
- Read current `AdminDialog` semantic adapter (Create, Edit, Command, Confirm, Viewer), global `AdminControl::register()`, `AdminForm::section()`, shared dialog/control/form styles and modal-geometry script.
- Reviewed dialog callers and schemas across General, Dashboard, Activity, Pages, Gallery, Home, Journal, Custom Page and Storage and related Resource classes.
- Shared Action dialogs consistently use the central adapter in reviewed source; Filament-owned authentication/security dialogs retain native workflow. This is source inspection, not rendered-browser proof.
- Grouping is incomplete: Home and Pages Settings are largely flat grids; Blog uses named sections while Exhibition's large schema is flatter; Storage uses grouped rights and metadata sections.
- Source-rule violations: one persistent helperText in `JournalWorkspace::journalSettingsAction()`; five in `ListMediaAssets::mediaEditSchema()`. Their feature ownership remains with other workers; no cross-feature edits yet.
- Stage/Toolbar functional parity is a new required acceptance gate. No comprehensive runtime/browser parity check has been performed.

## Change scope
- Set the shared native Filament modal window radius in `resources/css/admin/dialog-contract.css`. Do not alter vendor lifecycle/scroll lock, native close control, form border radii or dialog motion.
- Update the existing normative Admin Dialog content layout and Admin UI Stage contracts at the user's explicit request. No separate documents or policy aliases.
- Further semantic grouping will be executed in bounded, owner-coordinated slices after reconciling current dialog schemas. Do not claim that all existing forms have been regrouped.

## Verification and next actions
- Read GitHub source and verify published changed files at the committed HEAD.
- Local build, screenshot/browser review, complete toolbar parity and user visual acceptance remain unverified.
- Review the small-radius dialog in Light/Dark and native account/security dialogs before changing any further shared appearance.
- Discuss a distinct restrained dialog surface color rather than silently forcing one before user selection.
