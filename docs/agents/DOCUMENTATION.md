# Documentation maintenance and source evidence

This is the stable procedure for the `docs` documentation workstream. **Current subject/discussion state** is in [Docsworker](workers/docsworker.md), not in this procedure. Root `AGENTS.md` supplies branch and safety rules.

## Routine source reconciliation

1. Resolve fresh `docs` and `dev` HEADs from GitHub. `dev` owns implementation and the **only** active test suite. Do not mistake the still-existing historical `testing` branch for a second active suite. A Testing Worker is a role and can inspect/tests on an authorized checkout; a possible `refactor` branch requires explicit approval and a concrete scope. No competing root policy.
2. Compare new `dev` commits to the last source-reviewed SHA in the bounded Docsworker record. Triage affected files by existing [normative owner](../README.md) and [composed flows](../architecture/cross-feature-compositions.md); don't rebuild the full architecture after each commit.
3. Prefer current source/accepted product behavior over stale documentation, but separate **code present**, **authored assertion**, **actually executed test**, **browser reviewed** and **artist accepted**. Pin SHA of relevant source. Preserve historical inventory provenance; a regenerated image doesn't prove a live system.
4. Update the **one canonical owner** of each affected rule; retire contradictory old wording instead of appending a new changelog section. Link across subjects. Use lifecycle/end-to-end diagrams for meaningful transaction, read, render, failure and rollback boundaries; class/import diagrams are structural inventories only.
5. Keep the [architecture index](../architecture/README.md), [coverage/evidence matrix](../architecture/completion-criteria.md), and [Docsworker record](workers/docsworker.md) in sync. The latter is a short changing checkpoint, not a historic diary.
6. GitHub-only source/relative-reference checks are normal. Native PHP link checks/generator writes **only in a rare, expressly authorized Codex batch** in the existing `docs` worktree. No routine instructions asking the user to run local Docs commands.
7. Confirm HEAD again before a docs-only commit. If it moved, compare and reconcile, then write to existing `docs` using a normal fast-forward expected-SHA update. `dev` remains read-only unless the user separately authorizes integration.

## Source and environment boundaries

- Inferred import, relation or method declaration is **not** an observed runtime call; Eloquent relation is not a physical PostgreSQL foreign key. Runtime controller/Livewire discovery, URL/asset policy and browser gestures require their respective evidence.
- Document Working, Committed/LIVE, checkpoints and immutable media/historical protection separately; avoid false claims from a clean-looking single render.
- A physical catalog export belongs to a fully migrated, **independently verified disposable** PostgreSQL only. The artist-curated Validation environment is protected cutover material, not a test database.
- Never run, inspect or operate Continuous Integration (CI); no deployment/release or Production/Validation writes.

## Integration boundary

The user's eventual `docs` → `dev` integration is a separate authorized task. Treat current `dev/AGENTS.md` as authority; preserve live `dev` worker and Rights state, and reconcile newer dev documentation by owner. This Docs-only change may remove old Docs root names but **cannot delete the corresponding files on `dev`** until the future integration. See [agent work areas](README.md) and [worker registry](WORKERS.md). Worker names provide context, never automatic permission to start a prior task; live records may contain open discussions without executable steps.
