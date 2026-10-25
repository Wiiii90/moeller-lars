---
name: admin-ui
description: Apply canonical Filament admin workspace, controls, tables, dialogs and notification semantics without page-local CSS workarounds.
---

# Admin UI workstream

This is the short role-specific entry, not a competing copy of UI rules. The repository-root [`ui-skills.md`](../../../ui-skills.md) is a compatibility gateway to the same owners.

## Choose the exact authority

- **Primary visual grammar:** [Admin UI](../../../docs/ADMIN-UI.md) (admin only). Read the relevant sections for the task, not the full contract for every small edit. The active code may already implement newer user-reviewed decisions.
- **Controls:** [ADMIN-CONTROL-CONTRACT](../../../docs/ADMIN-CONTROL-CONTRACT.md); implementation in `app/Filament/Support/Controls/AdminControl.php`, reusable `resources/views/components/admin/` controls and `resources/css/admin/`.
- **Tables:** [ADMIN-TABLE-CONTRACT](../../../docs/ADMIN-TABLE-CONTRACT.md); fixed role/order geometry, shared selection and row-actions. Page-local styling must not shadow the shared owner.
- **Responsive task composition:** [ADMIN-RESPONSIVE-CONTRACT](../../../docs/ADMIN-RESPONSIVE-CONTRACT.md), covering shell, metrics, toolbars, tables and Visual Stages in **one** coherent contract. Shared primitive implementation stays with the Control/Table/Dialog contracts.
- **Dialogs:** [ADMIN-DIALOG-CONTRACT](../../../docs/ADMIN-DIALOG-CONTRACT.md); native Filament modal lifecycle, shared sizes/controls, explicit autosave and notifications. Never introduce a second scroll-lock runtime.
- **Activity/Undo/Commit:** [ADMIN-ACTIVITY-PUBLICATION-CONTRACT](../../../docs/ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md); domain save, append-only audit, Undo receipt, AdminNotifier and Working/LIVE are **separate** responsibilities.
- **Browser acceptance process:** [ADMIN-BROWSER-WORKFLOW](../../../docs/ADMIN-BROWSER-WORKFLOW.md). Exact accepted browser state is authoritative over old worker/candidate commentary. Do not invent or memorialize unaccepted CSS values.

## Source-first implementation order

1. Identify the page entry (`app/Filament/Pages`, Resources, Livewire) and its related `Concerns` Traits. Confirm the actual rendered Blade and component wrappers; never assume a large Page class owns every Action.
2. Identify the existing **shared** primitive for the change. Make shared spacing, controls, icon axes, selection and responsive pressure changes at its **single owner** rather than duplicating them in page CSS.
3. Preserve the real editorial transaction and the Admin Audit/Notification contract. Failed validation/no-op must not emit success; changes in Working are not automatically committed to LIVE.
4. Check keyboard, focus, scroll ownership and responsive states by the safest available methods. A source review is not a passed browser check. Do not add pixel snapshot/HTML-class tests whose only purpose is freezing a reconciliation candidate.
5. After accepted change, remove proven obsolete CSS/JS and redundant compatibility code; never delete historical schema/Undo payloads just because a UI control disappeared.

## Protected boundaries

The user allowed long-lived `docs` and `testing` **as separate exceptional workstreams**. Ordinary application/Filament editing remains on `dev` as authorized by [AGENTS.md](../../../AGENTS.md). This skill is an **entrypoint**, not a new branch policy. No new worktrees/branches. During documentation-only work follow [documentation skill](../documentation/SKILL.md): changes stay on `docs` and CI is never touched.

This short file deliberately does **not** duplicate hundreds of accepted UI measurements. Those stay with their canonical source until the tracked source-review consolidation is finished.
