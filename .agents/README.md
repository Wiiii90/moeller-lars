# Branch-local worker protocol

This is the reusable lifecycle procedure. **Every worker belongs to one working branch**, never to a global roster on `docs`. Only actual activation creates an individual record.

| Branch | Area/roster | Individual worker records |
| --- | --- | --- |
| `dev` | `.agents/areas/dev.md` | `.agents/workers/dev-N.md` |
| `docs` | `.agents/areas/docs.md` | `.agents/workers/docs-N.md` |
| `testing` | `.agents/areas/testing.md` | `.agents/workers/testing-N.md` |
| `refactor` | `.agents/areas/refactor.md` | `.agents/workers/refactor-N.md` |
| `main` | **None** | **None** |

The relevant files are on **that branch**, not read/written remotely on another branch. Rollout is incomplete until the receiving branch actually has its corresponding workflow files; do not pretend a phrase alone bootstraps a branch without repository access. Root `AGENTS.md` and the current branch's implementation/test safety rules remain in force.

## Activation, takeover and sign-out

1. On `Du bist ein neuer Testing Worker` or `Du bist ein Testing Worker`, select the **lowest positive unoccupied** number on `testing` and register it. The same rule applies to the other branches. A free number has no active `.agents/workers/<area>-N.md`.
2. `Du bist Testing Worker 1` selects or initializes slot 1 on `testing`; this may register an **already-running chat** using its own real current assignments and progress. It must not fabricate past tasks or automatically restart an old task. A second chat cannot silently take over an occupied slot.
3. `Ersetze Testing Worker 1` deliberately supersedes that slot's current chat. Increment the **monotonic lease token** in the area file and worker file together. Continue previous work **only** on an explicit continuation instruction.
4. **Sign in:** create the own task/progress record and a corresponding active area-roster row in one concurrency-checked commit on the **working branch**. Record the actual user assignments, status, completed steps, current evidence, blockers and outstanding actions, not a chat transcript. Do not store another worker's private tasks in this file.
5. **Sign out:** when a worker explicitly ends/retires, first consolidate verified durable findings into their owning product document or common workflow, and transfer any still-owned unfinished action to an explicitly identified owner. Then **delete the temporary worker file and remove its roster row/claims in one guarded commit**. Its number becomes free. A paused worker keeps its file and row until resumed or explicitly replaced/retired.
6. If a chat reaches its context limit without sign-out, the user can deliberately assign a replacement chat to the **same numbered slot**. It reads only the persisted task state and current Git source; unsaved chat details cannot be recovered.

Each area owns its independent **next lease token**. A new allocation or replacement consumes this token (then increments it) even when a previously retired *number* is reused. A stale chat must reread its current branch's shared roster and personal lease token **before each write**, stopping if they no longer match. Reread the branch HEAD, compare other claims, and commit with an expected-head non-forced update; retry from fresh state on a race. This is optimistic concurrency control, not a physical Git lock.

**Every substantive worker report starts with its exact registered identity** (e.g., “Docs Worker 1”). A named role or saved next step does not itself authorize code changes, tests, merges or automatic task execution.

## Knowledge and ownership

- Personal record: **this worker's actual assignments and progress**. Keep it short and replace superseded text at milestones.
- Area file: sign-in/sign-out, claimed file/feature scopes and coordination among workers **on this branch**. Do not copy entire assignments into a second file.
- Durable `docs/` and project procedures: accepted design, system behavior, architectural decisions, testing method and reusable process. A worker promotes verified lasting findings to the existing owning document; it does not preserve useful knowledge only in disposable memory.
- Git history: previous records are recoverable within the originating branch, but are **not** a substitute for current tasks or an automatically runnable handoff.

## Branch integration — prevent temporary files from escaping

**Never merge a worker-bearing branch into another branch using an unrestricted Git merge.** A deletion before merge removes the file from the final tree **but not from the merged commit ancestry**. `.gitignore`, a normal `.gitattributes` setting or a last-minute cleanup commit do not provide historical isolation.

- Bring newer `dev` application changes into `docs`, `testing` and `refactor` through an owner-reviewed source synchronization. If `dev` already contains private Dev-worker commits, use a **filtered source transfer** rather than a full ancestry merge; verify actual source parity, not a misleading ahead/behind count.
- Bring completed `docs` changes back to `dev` with a **reviewed, path-filtered commit built on the current `dev` HEAD**, retaining `dev` code, active Dev-worker state and root branch policy. Import only approved *durable* documentation and appropriate shared skill/procedure changes. Do **not** merge `docs` history into `dev`.
- Likewise, return verified `testing` tests and `refactor` code to `dev` as curated changes without importing their rosters, individual notes or unrelated branch policies/history.
- For `dev` → `main` cutover, use the same filtered release/export gate and **verify that the resulting tree and imported ancestry contain no worker records or branch-local rosters**. Do not treat a full `dev` ancestry merge as privacy-safe merely because a file was deleted.
- **Allowed to cross branches** only after review: permanent feature/architecture documentation, shared procedures and skills which are valid for the destination, and accepted application/test changes. **Must remain local:** `.agents/areas/`, `.agents/workers/`, branch-specific `AGENTS.md` and temporary `.agents/topics/`/investigation notes. Docs-only helpers also need an explicit justification before export.

Implement the integration as an explicit reviewed Git diff/commit, check tracked paths and target branch tree before advancing the ref, and keep the target's own agent scope unchanged. No automatic branch promotion or CI interaction is authorized by this protocol.
