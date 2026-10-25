# Documentation maintenance and source evidence

This is the stable procedure for the `docs` documentation workstream. **Current subject/discussion state** is in the numbered [Docs Worker 1](../workers/docs-1.md), coordinated via the [shared Docs area](../areas/docs.md), not in this procedure. Root `AGENTS.md` supplies branch and safety rules.

## Routine source reconciliation

1. Resolve fresh `docs` and `dev` HEADs from GitHub and check `testing` when making test-suite claims. `dev` owns application implementation and interim tests; the **active `testing` workstream curates the intended canonical suite** for a controlled return to `dev` before cutover. A Testing Worker is a role, not automatically a branch authorization; a possible `refactor` branch requires explicit approval and concrete scope. No competing root policy.
2. Compare new `dev` commits to the last source-reviewed SHA in the bounded active Docs worker record. Triage affected files by existing [normative owner](../../docs/README.md) and [composed flows](../../docs/architecture/cross-feature-compositions.md); don't rebuild the full architecture after each commit.
3. Prefer current source/accepted product behavior over stale documentation, but separate **code present**, **authored assertion**, **actually executed test**, **browser reviewed** and **artist accepted**. Pin SHA of relevant source. Preserve historical inventory provenance; a regenerated image doesn't prove a live system.
4. Update the **one canonical owner** of each affected rule; retire contradictory old wording instead of appending a new changelog section. Link across subjects. Use lifecycle/end-to-end diagrams for meaningful transaction, read, render, failure and rollback boundaries; class/import diagrams are structural inventories only.
5. Keep the [architecture index](../../docs/architecture/README.md), [coverage/evidence matrix](../../docs/architecture/completion-criteria.md), and the current [Docs Worker 1 record](../workers/docs-1.md) in sync. The latter is a short changing checkpoint, not a historic diary.
6. GitHub-only source/relative-reference checks are normal. Native PHP link checks/generator writes **only in a rare, expressly authorized Codex batch** in the existing `docs` worktree. No routine instructions asking the user to run local Docs commands.
7. Confirm HEAD again before a docs-only commit. If it moved, compare and reconcile, then write to existing `docs` using a normal fast-forward expected-SHA update. `dev` remains read-only unless the user separately authorizes integration.

## Source and environment boundaries

- Inferred import, relation or method declaration is **not** an observed runtime call; Eloquent relation is not a physical PostgreSQL foreign key. Runtime controller/Livewire discovery, URL/asset policy and browser gestures require their respective evidence.
- Document Working, Committed/LIVE, checkpoints and immutable media/historical protection separately; avoid false claims from a clean-looking single render.
- A physical catalog export belongs to a fully migrated, **independently verified disposable** PostgreSQL only. The artist-curated Validation environment is protected cutover material, not a test database.
- Never run, inspect or operate Continuous Integration (CI); no deployment/release or Production/Validation writes.

## Integration boundary

The user's eventual `docs` → `dev` integration is a separate authorized task. Treat current `dev/AGENTS.md` as authority; preserve live `dev` worker and Rights state, and reconcile newer dev documentation by owner. This Docs-only change may remove old Docs root names but **cannot delete the corresponding files on `dev`** until the future integration. See the [single worker protocol](../README.md) and [shared coordination files](../areas/docs.md). Worker names provide context, never automatic permission to start a prior task; live records may contain open discussions without executable steps.
