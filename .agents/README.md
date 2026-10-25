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

## Cross-worker responsibilities

- **Dev workers:** implement and source-review application changes on `dev`. **Do not create, modify, refactor or delete tests or routine general documentation.** Update active scoped living workstream records instead; report exact source commit, affected behavior/consumers, verification actually performed, changed contract semantics and the test/documentation follow-up. Explicitly requested targeted documentation work is distinct.
- **Test workers:** own test design, fixtures, regression cleanup, browser automation and isolated test execution on their designated test branch, following its own `AGENTS.md`. Later integration of verified tests into `dev` is a separately reviewed transfer.
- **Docs workers:** maintain one authoritative document per subject, current navigation and concise shared method. **Delete obsolete files and update every inbound link in the same correction**; no historical `-skill.md` roots, redirect/alias stubs or new appendix archives to compensate for deletion. Git history stores retired texts. Worker progress belongs only in active numbered records.
- **Other workers:** work strictly within their assigned area; they do not compensate for missing ownership by adding a parallel implementation, documentation entry or test suite.

## Continuous work and integration gates

The **user decides** the scope, architecture, deviations, timing of freezes, acceptance and integrations. Source and existing contracts show the implemented state; bring conflicts back to the user rather than autonomously treating an old decision as final. A worker's architecture warning should name the failing invariant and feasible options, not stall with speculative concerns.

Usual **direction of gated progress** is `dev → testing → docs → refactor → main`, **not a mandatory single-chain merge of commit histories**. The user may work with Docs, Test or Refactor workers in parallel with many scoped Dev workers. Those branches may continuously reconcile newer `dev` code **as preparatory work**, without running full acceptance or demanding Dev stop on every commit.

- **Dev iteration:** parallel Dev workers change application code on `dev`, coordinate ownership and reconcile each other's commits. Do not write general `docs/` contracts, tests or generated manuals as part of routine feature work; keep the **active living workstream/task record** current in the same scope. Report behavior/API/architecture changes and required test/documentation follow-ups. A deliberately requested, scoped documentation correction remains an explicit exception.
- **Testing preparation:** record the last integrated source baseline and compare the **entire accumulated Dev diff**, including config, migrations, controllers, shared components and test dependencies. Reconcile source into Test's working tree under its own branch rules, then redesign the existing test suite **holistically** before adding cases: merge related fixtures, retire obsolete tests and move cases to the appropriate layer. No new parallel test frameworks or repair-specific test piles merely to keep historic names.
- **Testing final gate:** **only after the user freezes Dev**, make Testing source-equivalent to that exact frozen Dev commit, except Testing-owned tests and isolated test support. Check actual file/tree differences, not just ahead/behind counts. Use authorized Codex/native execution in a positively isolated environment for the **final pre-integration run**, correct the test suite on Testing, rerun necessary failures, and report actual results and unresolved product defects. A product defect goes back to Dev for correction; synchronize the new frozen baseline before repeating the gate. Curated tests return through a reviewed integration, never a casual branch merge carrying worker files.
- **Docs final gate:** Docs may review/reorganize in parallel against accepted `dev` source. Before Docs transfer, reconcile all incorporated application/test changes, finish documentation/source/link accuracy, and only then use authorized Codex/native generators or checkers **if the retained docs actually require generation**. Review resulting artifacts and remove obsolete outputs. Transfer only reviewed durable docs; never merge branch-local workers or histories.
- **Refactor and main:** Refactor may prepare concurrently but behavior-preserving changes are reconciled against the post-Test/Docs accepted baseline, verified and reviewed before selective integration. Promotion to `main` needs the user's explicit final approval and separate release/security checks. The stage order may change at the user's direction; an earlier stage is **never implicitly approved** by another stage's progress.

**Structure and deletion rule for every worker:** before creating a module, file, tool, test fixture, document or index, ask whether an existing owner should instead be reorganized, renamed, moved or removed. Remove superseded implementations/references in the same scoped change. Do not install adapters/aliases/compatibility shims for incomplete pre-release source merely to avoid changing callers; preserve actually persisted database/migration/snapshot compatibility when essential, and explain any proposed exception to the user.

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
