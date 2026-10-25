# Admin UI – canonical documentation gateway

This compatibility entry keeps existing `ui-skills.md` links valid. **It is not an independent UI specification.** The former monolithic rules were reconciled with the current owners below; do not copy old snapshots, conflicting CSS measurements or historical worker instructions from Git history back into active implementation.

| Task | Canonical owner |
| --- | --- |
| Shared Admin grammar, layouts, metrics, toolbars, drag, hierarchy and accepted style | [Admin UI](docs/ADMIN-UI.md) |
| Field/control semantics, picker and session presentation state | [Controls](docs/ADMIN-CONTROL-CONTRACT.md) |
| Create/Edit/Command/Confirm/Viewer, persistence and no-op | [Dialogs](docs/ADMIN-DIALOG-CONTRACT.md) |
| Table/row/selection roles, Pages Home row, responsive column yielding | [Tables](docs/ADMIN-TABLE-CONTRACT.md) |
| Wide/Compact/Narrow/Minimal and General Appearance Stage | [Responsive](docs/ADMIN-RESPONSIVE-CONTRACT.md) |
| Header, durable mailbox, notifications and transaction boundaries | [Notifications](docs/ADMIN-NOTIFICATION-CONTRACT.md) |
| Gallery/Artwork/Storage file lifetime and effective ALT | [Media](docs/MEDIA.md) |
| Browser review, accepted references, implementation/source-cleanup gates | [Browser workflow](docs/ADMIN-BROWSER-WORKFLOW.md) |

**Agent entry:** [Admin UI skill](.agents/skills/admin-ui/SKILL.md) identifies the appropriate implementation owner. Read the relevant section plus current code and concrete user acceptance, not every document by default. [Documentation index](docs/README.md) maps cross-feature domains and evidence.

Current acceptance must never be inferred from these documents alone: compare the exact source and the browser candidate. Preserve historical state/restore compatibility while removing only source-proven obsolete presentation code.
