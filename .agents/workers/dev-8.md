# Dev Worker 8 — Dialog header action alignment

**Branch:** `dev`
**Lease:** 40
**State:** Active; scoped implementation assigned, source orientation pending.

## Assignment
- Restore the native close X to the upper-right corner of applicable Admin Create/Command form dialogs where the Submit action currently occupies that spot; exchange positions only where needed, leaving already-correct dialogs unchanged.
- Review the destructive meaning of the X versus form-clear/discard, and align Submit/Discard visual styling with the accepted Journal/Gallery Stage action grammar without changing unrelated dialog behavior.
- Own only the shared dialog action presentation and its direct affected consumers; coordinate with workers owning individual feature forms. No deployment, branch promotion, broad tests, or unrelated redesign.

## Evidence
- Current baseline at activation: `9d3ce590d6215849fd88721f775592a7c9e52f4a`.
- Registration establishes ownership only; source and rendered behavior have not yet been verified.

## Next
1. Inspect canonical AdminDialog/Filament header action owner and active Create/Edit/Command direct consumers on current `dev`.
2. Implement scoped position/style adjustments at the existing shared owner; inspect focused diff and safe checks.
3. Report precise verification limits and any browser acceptance still outstanding.
