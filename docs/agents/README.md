# Agent workspaces and living project context

This is the repository entry for any **worker role**, including implementation, documentation, testing, architecture and refactoring. Root `AGENTS.md` links here. **A role is not a Git branch, an automatic task queue, or a command to begin coding.** A chat may start with a request, a question, a review of earlier decisions or just a discussion.

## How to start a conversation

A user may say `Du bist Dev Worker 1, arbeite`, `Du bist Docs Worker`, `Du bist Testing Worker`, `Wir müssen über das Refactoring sprechen`, or simply give a task. The agent must:

1. Read the current root `AGENTS.md`, this index and [workspace registry](WORKERS.md). Resolve a named workspace only when that identity is actually registered or unambiguous; do not equate a generic `worker 1` with `Dev Worker 1` without context.
2. Read that workspace's current **living subject/state**, if one exists, and the relevant [product contracts](../README.md) and [end-to-end architecture](../architecture/cross-feature-compositions.md). Check the target branch HEAD before making code claims. Do not read all documents indiscriminately.
3. **Respond to the user's current message first.** A new request may change the focus; an exploratory question is not an authorization to execute backlog work. Never silently start the previous "next action" solely because a worker name was mentioned. If the user explicitly asks to continue previous work and that work is recorded, continue it.
4. If no active subject is recorded, converse normally and use the information supplied in the message. Only ask for genuinely missing information. Do not fabricate details from a previous ChatGPT chat.
5. Before edits, resolve the **authorized working branch** for the concrete request. The words `Dev`, `Docs`, `Testing` or `Refactor` can identify an area of work but do not independently create/authorize a branch, worktree, merge, deployment or destructive test. Different workers may legitimately read the same branch; a worker can coordinate across branches without writing to all of them.

## Living knowledge, not handoff files

Everything useful for continuing is maintained **as normal repository documents**:

- **Normative product behavior and architecture** → the one existing owning contract; accepted/rejected UX decisions and non-regression constraints belong here.
- **Current work and discussion** → the relevant existing topic/workspace record under [workers](workers/) (small, bounded, updated **in place**). May have open questions, alternatives under consideration and unresolved decisions; it does **not** need a current executable task.
- **Worker roles and branch scopes** → [registry](WORKERS.md); this index changes only when a role or ownership changes.
- **Verification claims and historical source snapshots** → [architecture evidence](../architecture/completion-criteria.md) and Git history, never an ever-growing session diary.

At substantive milestones, update the **relevant files directly**, replacing superseded statements and preserving facts, decisions, known failures, and unresolved questions. Do not create a "handoff", "continuation prompt", "session report" or new skill document for every chat. A discussion that has not yet reached a durable decision need not generate a commit for every message; save material facts/choices before the conversation gets long or stops, when authorized to write.

No mechanism can perfectly retain uncommitted chats, invisible reasoning or another unsaved browser session. A future chat can reliably reconstruct **what was actually persisted**; do not claim otherwise. The assistant should not require the user to repeat working rules already stored in the repository.

## Branches and work areas

| Git branch | Current role | Boundary |
| --- | --- | --- |
| `dev` | Application integration, existing author-written tests and normal feature work | Direct development rules in the current `dev/AGENTS.md`; changes require an actual user task. |
| `docs` | Normative architecture and living agent/project docs | Documentation writes only here until integration is authorized. Routine work is GitHub-only, not local Codex runs. |
| `testing` | Existing historical branch ref; verify whether it has any distinct useful work before reuse | Test suites currently live on `dev`; calling oneself a Testing Worker does not automatically switch branches. |
| `refactor` | **Proposed only**, not created | A separate refactoring lane is possible if explicitly authorized and given clear integration/ownership boundaries. |

There must be no silent branch creation, new worktrees, CI interaction or unilateral integration. Protect artist-curated Validation and Production. Before shared commits, refresh branch HEAD and check concurrent owners and changed files. A future [Docs→dev integration](DOCUMENTATION.md#integration-boundary) should reconcile current `dev` working rules and living states, not clobber them with older snapshots.

## Cross-feature regression protection

Before modifying a shared primitive, consult [cross-feature compositions](../architecture/cross-feature-compositions.md) and the actual owning contract. Identify its consumers, verify previously accepted behavior, scope changes, and distinguish code inspection, authored tests, executed tests, real browser review and artist acceptance. Do not rename a regression into an accepted contract. Use [Admin UI](../ADMIN-UI.md), [Rich Text](../EDITORIAL-RICH-TEXT.md), [Publication](../ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md) and [Testing](TESTING.md) selectively.

**The point of a worker name is contextual orientation, not automatic action.**
