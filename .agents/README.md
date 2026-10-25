# Worker identities, coordination and living knowledge

This is the **one** agent/worker operating protocol. A work-area label (Dev, Docs, Testing or proposed Refactor) names a coordination lane and its normal Git branch; a **numbered worker slot** identifies one continuing conversation/work context. Neither a greeting nor a slot is an automatic job queue. Current user instructions govern what happens.

## Start or replace a worker

1. Read root [AGENTS](../AGENTS.md), then the relevant **shared area file**: [Dev](areas/dev.md), [Docs](areas/docs.md), [Testing](areas/testing.md) or [Refactor](areas/refactor.md). Do not load every worker or every contract.
2. **Explicit numbered slot:** `Du bist Dev Worker 2` selects `dev-2`; similarly `Docs Worker 1`, `Testing Worker 1`. Read its numbered [worker record](workers/) and the area's current claims. If no record exists, allocate it with epoch 1 and an empty subject; do not invent an old task. If the slot is already active in another chat, selecting its name alone does not revoke the current owner.
3. **New unspecified worker:** `Du bist ein Dev Worker` creates the next unused Dev number (from the area's `Next new worker number`), initializes a separate worker record and adds a row to that area. Generic names do not silently inherit someone else's state. A mere question about workers does not allocate a slot.
4. **Explicit replacement:** `Ersetze Dev Worker 2 und mach da weiter, wo er aufgehört hat` retains `dev-2` and its file, increments its **epoch** in both files, and treats the old chat as superseded. Read the persisted discussion/task and resume *only because the user explicitly requested continuation*. If the earlier state was never saved, say exactly which parts are missing; don't fabricate it.
5. **Ordinary opening or discussion:** `Du bist Docs Worker 1`, a question, or a fresh task may supersede a previous focus; do **not** automatically execute a recorded action. For a new assignment, clarify only essential missing scope, then act as requested.
6. Check the actual target Git branch and current code/contract owners before claiming progress or writing. Do not treat an area name as permission to create a branch, deploy, run unsafe tests or merge.

## What is stored where

| Owner | Stores | Does not store |
| --- | --- | --- |
| **Area file** `areas/<lane>.md` | Number allocator, active/available/paused slots with epoch and file link, *current shared ownership/overlap* and cross-worker dependencies | Full task history, repeated global rules or every commit |
| **Worker file** `workers/<lane>-<number>.md` | Current subject (or none), explicit decisions, verified source state, owned scope, test/browser evidence, current blockers/questions, optional next task | Long chat transcripts, copies of product contracts, unrelated team-wide status |
| **Permanent contracts** under `docs/` | Accepted behavior, domain ownership, cross-feature architecture, long-lived verification method | Ephemeral slot IDs or every temporary attempt |
| **Git history** | Prior revisions, retired worker notes and old evidence | The current plan or a substitute for reading live state |

Each newly allocated worker receives its **own** file, even before a first concrete task. Multiple workers may share a branch; their area file is the common coordination surface. Separate areas have separate allocator/ownership files. Numbering is monotonically increasing per area; never recycle a retired slot number, because it would make old conversations ambiguous.

## Updates, parallel workers and replacement safety

- Before concurrent work, register a short feature/file claim in the shared area. If a proposed change touches another claim or a shared primitive, agree on a split, serialize the change, or stop and surface the conflict. Do not claim a text table can physically lock code.
- At meaningful changes to scope, decisions, verified results or blockers, **replace** the relevant worker paragraphs and update the area's claim in the same coordination commit on `docs`. Do not append a per-turn diary; closed decisions move into their one normative owner. Not every conversational reply requires a commit.
- A chat adopts the slot's `epoch` when it starts. **Before each write**, reread the current slot/area epoch and both the coordination and code branch heads. If the epoch changed, this chat is superseded: do not write as that worker. An explicit takeover increments the epoch and updates the two files together. Use an expected-head, fast-forward commit to prevent lost coordination updates; independently recheck the implementation branch before its code commit. These are optimistic protections, not atomic locks across Git branches.
- A worker that reaches the context limit may be replaced by a new chat with the *same* numbered identity; the surviving facts are exactly the ones saved in the living files and Git, not hidden reasoning or an uncommitted browser session. Do not create a separate handoff document, copied start prompt, or continuation log.
- Keep only source-grounded decisions. A present worker can discuss alternatives without a scheduled next action, and a later chat may choose a different task.

## Cleanup and retirement

Mark a completed slot **available** (no active claim) if its identity should remain reusable for a later discussion; keep only the bounded context needed for intentional resumption. **Retire** a no-longer-needed worker only after transferring durable decisions and still-relevant unresolved issues to their normative owners or the area file. Remove the slot row and **delete its temporary worker file** in the same commit. Git history retains its old version. Do not retire a slot claimed by another live chat or erase its only surviving unresolved information. The area's next-number counter never decreases. Remove completed ownership rows and stale coordination notes continuously, not merely at chat end.

## Work areas, methods and rollout boundary

- **Dev:** `dev` application implementation; current `dev/AGENTS.md` remains authoritative for live development. [Dev area](areas/dev.md).
- **Docs:** `docs` documentation and this prototype. [Docs area](areas/docs.md); [documentation procedure](procedures/documentation.md).
- **Testing:** active `testing` curation with future controlled reintegration into `dev`. [Testing area](areas/testing.md); [safe testing procedure](procedures/testing.md).
- **Refactor:** planned area only; no Git branch, no code write until authorized. [Refactor area](areas/refactor.md).

**Currently only `docs` has this new entrypoint and these living files.** Existing Dev and Testing chats do not magically follow it. A later authorized rollout must arrange for them to read and update the **same canonical coordination files on `docs`**, not create independently diverging copies on `dev` and `testing`, and must preserve their active work, existing worktrees, Codex workflows and branch-specific safety rules.

Read [feature owners](../docs/README.md) and [cross-feature compositions](../docs/architecture/cross-feature-compositions.md) as needed. Never replace concrete user/browser acceptance with an agent's speculative simplification.
