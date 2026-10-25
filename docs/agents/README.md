# Agent entrypoint and durable continuity

This file is the **automatic role dispatcher** for agents working in `Wiiii90/moeller-lars`. Root `AGENTS.md` must direct new agents here. The user can say **"Du bist Devworker 1, arbeite."** without restating design principles, tool rules, or the previous task. The agent must read current repository state and its own durable work record **before deciding what to do**.

## Exact startup dispatch

1. Read root `AGENTS.md` and current branch HEAD. Read this index, then [Worker registry](WORKERS.md).
2. Normalize `Devworker 1`, `dev worker 1`, `worker 1` to `devworker-1` **when its role is unambiguous**. Read the record that registry points to. Do not infer a new task merely from the number.
3. Follow the **active objective and next executable action** in that record; re-read source owners and changed HEAD before editing. Restore remaining constraints from the linked normative product contracts and [cross-feature architecture](../architecture/cross-feature-compositions.md), not from a repeated user prompt.
4. On a valid active assignment, proceed without requesting instructions already recorded. On a missing, explicitly completed, conflicting or uninitialized assignment, **ask once for the missing initial assignment**; do not invent one. A role label alone cannot retrieve an unsaved chat.
5. Before work expands or another worker shares an owner, inspect the registry and reconcile collisions with the current `dev` HEAD. A worker number does not create a Git branch, worktree, persistent ChatGPT context, scheduled background process, or permission to access another chat.
6. At every meaningful implementation checkpoint and **before the chat becomes too long**, replace the worker's bounded state in its existing file in the **same authorized commit** (or an explicitly paired, HEAD-reconciled commit). This applies even if the user has not requested a handoff. Keep accepted/rejected user feedback, verified claims and known failures; drop superseded narratives and redundant logs. A new chat can then pick up the next action with no custom handoff prompt.

## Document ownership

- **Core product architecture and requirements:** [Documentation index](../README.md) and [end-to-end compositions](../architecture/cross-feature-compositions.md). Do not copy whole requirements into a worker record.
- **Shared Admin design and accepted behavior:** [Admin UI](../ADMIN-UI.md), [controls](../ADMIN-CONTROL-CONTRACT.md), [dialogs](../ADMIN-DIALOG-CONTRACT.md), [tables](../ADMIN-TABLE-CONTRACT.md).
- **Testing and isolation:** [Agent testing procedure](TESTING.md), [test architecture](../TESTING.md) and current `tests/README.md`.
- **Current Docs work:** [Docs worker state](workers/docsworker.md) and [Documentation procedure](DOCUMENTATION.md). Docs work normally uses GitHub; the local Docs worktree is only for rare expressly authorized Codex batches.
- **Chat carryover:** [Continuity rules](CONTINUITY.md). Record progress; do not create chat-by-chat reports.
- **Implementation state:** [Worker registry](WORKERS.md), pointing at exactly one current state file per active role. These records are *living*, not accepted product contracts.

## Mandatory non-regression gate

Before changing a shared component: identify all its consumers, enumerate user-accepted interactions, compare the proposed edit against each consumer, run narrow authorized checks and keep browser acceptance distinct from source/automated tests. If a supposedly simpler implementation changes root/child drag, dialogs, Gallery media, Rich Text context menus, responsive sheet geometry or Notification cardinality, **stop and reconcile the shared owner first**. Do not invent a second workaround owner.

**Standing restrictions:** Direct implementation only on existing `dev`; documentation only on existing `docs` until a separately authorized integration. No new worker branches, no CI interaction or release. Protect the artist's Validation data. Re-read HEAD immediately before any shared write and use a fast-forward expected-SHA update. Never run destructive tests on persistent Preview/Validation.

## Future Docs to dev integration (separately authorized)

Current `dev` retains active and more recent `AGENTS.md`, root Admin UI/handoff/testing legacy files and Rights workstate. Bringing this architecture to `dev` is **not** permission to replace them wholesale. Compare both branches under a fresh HEAD; preserve accepted `dev` product behavior and active Rights/worker state; transfer current `docs/` contracts and this agent entrypoint; update **`dev/AGENTS.md`** to load this directory automatically; redirect/decommission old root filenames **on dev only then**, with a reference search and a normal fast-forward shared-branch commit. No branch creation, force push, CI, deployment or local Docs worktree demand. Historical source-only generated inventories must not be mislabeled as new app-code proof.

