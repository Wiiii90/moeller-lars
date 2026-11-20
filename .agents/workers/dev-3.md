# Dev Worker 3 — Admin Dashboard

- **Branch:** `dev`
- **Lease:** 25
- **Assignment:** Dashboard Notification readability, consecutive-edit grouping, and Activity presentation of repeated editorial edits.
- **Current state:** Source changes committed on `dev`: shared actor/target edit grouping now spans consecutive eligible edits regardless of changed field or arbitrary time gap. A different event interrupts the group. Dashboard group headers have Open (combined details), Pin all, Read/Unread all, and Delete group; confirmation follows Dashboard settings. Activity groups are expandable and use existing individual Details and safe Undo actions.
- **Ownership/invariants:** Underlying Notifications and immutable Activity events remain independent. Group actions validate exact currently visible member IDs and apply to each real Notification once. Bulk selection never introduces a synthetic group key. Pinned Notification ordering remains per entry; pinning a group pins its members individually. Activity group Undo is deliberately unavailable; each child remains independently actionable.
- **Relevant source:** `AdminActivityPresentation::editGroupKey()` and `adjacentEditGroups()`, `DashboardFeed`, Dashboard Page/View/Dialog, Activity Feed/Page/View and their existing styles.
- **Verification:** Source and branch read-back verified. No local PHP/runtime database, browser automation, isolated test suite, Continuous Integration, migration or deployment performed. Browser/Testing acceptance is still open.
- **Pending durable consolidation:** The accepted adjacent-edit group semantics and group-action identity rules belong in existing `docs/admin/notifications.md` and `docs/admin/activity-publication.md` through Docs review; do not create duplicate product contracts.

## Next

Check lease and fresh `dev` HEAD before future writes. Browser-accept expanded/collapsed group rows across wide and narrow layouts, open modal, group pin/read/delete (including confirmation), group and child selection interactions, filters, pagination, retention, navigation, accessibility, and Activity individual Undo behavior. Coordinate isolated feature/browser tests with the Testing worker on `testing`. Address any failures at the existing owners.
