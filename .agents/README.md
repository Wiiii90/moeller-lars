# Worker coordination

The root [AGENTS.md](../AGENTS.md) is the always-on entry. This file owns **only** worker lifecycle and shared coordination. Stable domain/architecture requirements belong in [docs](../docs/README.md); task-specific procedures belong in [skills](skills/) and [procedures](procedures/). There are no chat handoffs or automatic backlog actions.

## Open a worker

Read the relevant shared [Dev](areas/dev.md), [Docs](areas/docs.md), [Testing](areas/testing.md) or [Refactor](areas/refactor.md) area file. Resolve the user message as follows:

| Request | Action |
| --- | --- |
| `Du bist ein Dev Worker` | Allocate the **lowest positive free** `dev-N` slot: a number is free if there is no live `.agents/workers/dev-N.md` on canonical `docs`. Analogously for other areas. |
| `Du bist Dev Worker 2` | Select that identity; create its file only if absent. Do not execute an old task merely from the greeting. If another chat already owns it, do not silently replace it. |
| `Ersetze Dev Worker 2` | Keep that numbered file and its documented context, but issue a **new lease token** and supersede the previous chat. Continue old work only when explicitly requested (for example, `... und mach weiter`). |
| A conversation about the worker system | Discuss normally. Do **not** allocate an identity or invent a task. |
| `Dev Worker 2 kann weg` | Follow the retirement procedure below; the number becomes free only when its file is actually deleted. |

**Allocate only on actual activation.** Do not pre-register placeholder worker files for old chats, hypothetical workers or an area with no workers. The shared area file always exists, even if its active-worker table is empty.

Each area maintains a **Next lease token**: a monotonically increasing number independent of the reusable worker ID. On **every** allocation and replacement, consume that token for the worker record and area row, then increment the counter. Thus deleting `dev-1` and later reusing number 1 cannot make an old chat with a former lease appear current. Never reset the lease counter merely because the area is empty.

**Allocation/replacement must be one concurrency-checked commit on canonical `docs`**, updating the shared area and the individual file together. Read the current remote `docs` head and available worker paths immediately before creating the commit; use an expected-head, non-forced ref update. If a competing writer advances the branch, reread and reconcile; do not create the same identity twice. No other branch's stale copy determines occupancy.

Every work report identifies its registered worker role and number (for example, Docs Worker 1).

## Live ownership and writing

- **Individual** `.agents/workers/<area>-N.md`: above all the **assignments actually given by the user**, current work status/progress, completed actions, next outstanding actions and blockers, plus decisions and evidence needed to resume. It is that worker's memory only; never mix another worker's task state into it. It may contain a discussion without an action. Never put another worker's private task state into this file.
- **Shared** `.agents/areas/<area>.md`: current area-wide file/feature claims, worker number + lease token + lifecycle state, cross-worker dependencies and conflicts. No chat transcripts or repeated stable rules.
- **Durable** `docs/`: accepted product design, canonical architecture and ownership, current system behavior, persistent workflows and reusable verification conclusions.
- **Git history**: former states and retired worker files. Historical commits are not an automatically executable task queue.

On each worker's **activation and exit**, update the matching shared area roster; retirement unregisters the worker and deletes its private file after knowledge consolidation. An individual worker does not silently remain listed after leaving.

Before changing either code or coordination, the chat must recheck its worker lease token **in the canonical shared area and its own record**. If missing or changed, the chat is superseded: stop writing under that identity. Before an application-branch commit also recheck its current branch head and other workers' current scopes. Reconcile shared owners and overlapping edits; the area table is **not** a real cross-branch mutex.

Write meaningful state changes at milestones and before a likely context limit, replacing superseded text. A new chat can recover only what was saved. An uncommitted conversation, external browser session or hidden reasoning cannot be recovered from the worker name.

## Consolidate and retire

1. Resolve a worker's completed or durable **decisions and facts into their own record first** while work continues; propagate the stable results into their **one owning contract or procedure** when confirmed. Transfer genuinely shared dependencies into the area's coordination file. Move unresolved work into the proper active owner or a deliberately maintained topic; do not drop it or copy it into another worker's private file.
2. At retirement, verify there is no ongoing chat/writer relying on that identity, no unresolved information existing solely in its temporary record and no current file ownership claims. A paused worker **retains** its file and still occupies its number.
3. In one expected-head commit remove the area row/claims and **delete** its individual file from `docs`. The lowest now-unused positive number is available for the next allocation; the area's lease counter only increases.
4. Remove outdated references and obsolete topic/procedure fragments at the same consolidation boundary. Git retains the old file version; no separate archive or handoff file is created.

Periodic cleanup should replace/update the canonical documents rather than append dated source-diff sections. Do not delete historical migrations, accepted behavior or unique open evidence solely for cosmetic tidiness.

## Scope and rollout

This version exists **only on `docs`**. `dev` is active implementation; `testing` is an active curated suite; `refactor` is now an existing branch initially based on current `dev` at its creation; no numbered Refactor Worker has been opened. A worker role does not automatically create or authorize any Git branch. On a later approved rollout, every worker must reference the **same** shared coordination on `docs` rather than diverging copies in separate working branches. Keep existing live agents, worktrees, test isolation and implementation policy intact until rollout is verified.
