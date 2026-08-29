# Follow-up chat handoff skill

Use this file when a long orchestration/review chat is becoming too large and the user asks for a **Folgeprompt / Folgechat / continuation handoff**.

The goal is a lossless operational handoff, not a summary for casual reading.

## 1. Core requirement

The next chat must be able to continue the work without asking the user to reconstruct:

- repository state;
- exact branch/SHA;
- accepted/rejected worker heads;
- current browser candidate;
- local preview/runtime details;
- migrations already run;
- known defects and accidental intermediate states;
- pending browser reviews;
- branch/reconciliation workflow;
- testing/build restrictions;
- architectural decisions already made.

A good follow-up prompt tells the next orchestrator **what to do next**, not merely what happened before.

## 2. Before writing the prompt

Read/update the durable repository documentation first when the current chat discovered reusable rules.

At minimum consider:

- `AGENTS.md` for workflow/orchestration/technology rules;
- `ui-skills.md` for reusable admin UI grammar;
- `worker-prompt-skill.md` for compact execution-only worker prompts;
- architecture/media/migration/release docs for durable contract changes.

Do not stuff every transient SHA into durable docs. Exact temporary state belongs in the follow-up prompt.

If docs need changing, create one coherent docs-only commit where practical and give the next chat the new exact head.

## 3. Required follow-up prompt sections

A continuation prompt should normally contain the following sections.

### Mission

State exactly what the new chat is taking over and the immediate goal.

Examples:

- continue browser review of a combined admin candidate;
- receive/review several worker handoffs;
- reconcile accepted side branches;
- perform one final local browser cycle.

### Repository and exact Git state

Include:

- repository;
- canonical local path;
- protected `main`/integration constraints that actually apply to the current work;
- current combined branch when one exists;
- exact current head SHA;
- relevant base SHA;
- side branches and their accepted/rejected status;
- whether the working tree should be clean.

Explicitly distinguish:

- historical feature branches;
- temporary worker side branches;
- current combined browser branch;
- integration/release branches when they actually exist.

Exact current branch names and SHAs belong here rather than in durable docs.

Also state that `P:\moeller-lars` is the **user-operated local checkout**. Remote workers/chats normally cannot see that path and must not treat the missing mount as a blocker. Local commands are executed by the user from copy-paste PowerShell supplied by the orchestrator.

### Repository hygiene state

Every continuation handoff must state:

- previous browser-cycle cleanup: `DONE` / `PENDING`;
- intentionally retained open PRs;
- intentionally retained active branches;
- obsolete PRs/branches still pending cleanup.

A new chat must not repeat cleanup already recorded as completed.

After browser acceptance or definitive supersession, repository hygiene normally happens before starting the next tranche. This is distinct from the prohibited discretionary cleanup between a source-ready candidate and its browser review.

### Current runtime/browser state

When local browser review is in progress, include exact known operational facts:

- URL and port;
- container/image names;
- database container if relevant;
- Dockerfile/helper path;
- media/database mounts relevant to rebuilding/migrations;
- migrations already applied;
- whether the current running image differs from a newer docs-only Git head.

Do not invent unknown paths. If a mount source is dynamic, show the command used to read it from the existing container.

Record whether the local preview bypasses the Production entrypoint. The Production image intentionally rejects local-style configuration; a local HTTP preview based on that image must use the normal PHP entrypoint and start Apache/Laravel without the Production guard.

### Current product acceptance state

State explicitly whether the candidate is:

- source-reviewed;
- technically running;
- browser-reviewed;
- product accepted;
- release qualified.

Never imply that “container is Up” means the UI is accepted.

### Accepted architecture/contracts

Include only the details that materially constrain future fixes, such as:

- central Rich Text stack;
- MediaAsset/reference/publication rules;
- native DnD technology;
- shared UI grammar;
- non-destructive Journal template switching;
- preview/public media distinction.

Point the next chat to the durable docs instead of duplicating every stable rule verbatim.

### Presentation freeze

If any surface has already been browser/product accepted in the current cycle, list it explicitly as frozen presentation.

A reconciliation/cleanup worker may not change its borders, separators, connector lines, widths, spacing, add-row/footer position, metric geometry, typography or action geometry merely to centralize shared CSS. If shared ownership cannot be changed without visible differences, preserve the accepted presentation and defer the cleanup.

After accepted visual side branches are reconciled, the next action is normally the local browser build/review — not another discretionary cleanup pass.

### Known dirt / incidents

Do not hide mistakes that can confuse Git history or future debugging.

Examples:

- accidental intermediate commit later repaired;
- side branch based on an older shared base;
- migration run that first failed before DB mutation and later succeeded;
- unreachable/no-op commit not referenced by a branch;
- local cache/image known to have represented an older candidate;
- local preview command that copied Production entrypoint semantics and therefore exited before Apache started;
- test suite that aborted before assertions because the test DB environment was missing.

Explain whether the **current tree is clean despite the history** and how that was verified.

### Pending review queue

List the exact next user-review slices and their order.

For browser-heavy work, instruct the new chat to:

1. collect the user's complete feedback for one slice;
2. not launch a worker after the first sentence unless the user says the review is complete;
3. consolidate findings into one coherent repair scope;
4. then create a fresh worker prompt.

### Worker workflow

State the branch strategy explicitly.

For parallel workers:

- one exact shared base;
- one side branch each;
- do not let all workers push to the combined branch;
- do not mutate historical feature branches;
- no rebase/merge/deploy unless requested.

For sequential workers:

- base the next worker on the current accepted combined head when that is intentional.

**Every new orchestrator must read `worker-prompt-skill.md` before creating a worker prompt.**

Worker prompts are execution instructions, not mini-specifications or design essays. The orchestrator decides the implementation direction first; the worker executes it.

Default worker prompts should be about **20–60 lines** and stay below roughly **100 lines** unless the user explicitly asks for a larger handoff. Do not repeat `AGENTS.md`, `ui-skills.md`, project history or long lists of generic exclusions inside the prompt.

A worker prompt should normally contain only:

- exact repo/base/branch;
- exact files/components to inspect or edit;
- exact requested changes;
- a short task-specific `DO NOT CHANGE` list;
- required checks;
- commit/push instruction;
- short handoff fields.

Do not delegate product/UI choices with phrases such as `improve`, `harmonize`, `make consistent`, `choose the best layout`, `refactor as needed` or `clean up related UI`. If the user has made a concrete decision, state that decision literally in the prompt. If a genuine unspecified product choice appears during implementation, the worker leaves it unchanged when safe and reports it instead of inventing a decision.

Worker prompts must be one contiguous fenced code block.

Never tell a remote worker to “work exclusively in `P:\moeller-lars` or STOP” unless that worker has explicitly demonstrated access to the user's local machine. Remote source work and user-executed local runtime work are separate phases.

### Worker review workflow

For every returned worker:

1. verify remote branch/head;
2. verify parent/base;
3. compare exact base→head;
4. inspect actual code/diff independently;
5. identify cross-branch/shared-file effects;
6. reject any product/UI decision that was not explicitly delegated;
7. accept/reject before integration;
8. avoid broad tests unless the actual change warrants them.

For visual workers, review not only whether the new target looks source-coherent but also whether any already accepted consumer of changed shared CSS could change presentation. Broad shared selector changes require explicit scrutiny.

### Reconciliation workflow

Give explicit rules:

- cherry-pick/union only accepted diffs;
- resolve shared files intentionally;
- make cross-branch compatibility fixes in a separate small commit;
- preserve browser-accepted presentation exactly while reconciling ownership;
- no new shared border/separator/connector/spacing rule without a current browser requirement;
- inspect net diff after any repair;
- push fast-forward where possible;
- do not rewrite public history merely to make it pretty when a safe forward repair is clearer;
- do not run a discretionary “final cleanup” after visual branches are accepted and before the next browser build.

### Build/migration/browser workflow

If the next chat will need it, include the exact command pattern currently proven to work.

Prefer:

- one build after the intended set of fixes is reconciled;
- only risk-appropriate checks before a browser-only review;
- verbose migration output;
- stop on migration failure;
- reuse existing network/env/media mounts;
- ensure the local preview overlay includes every changed runtime-owned area (including `config/` and `database/` when changed);
- replace only the web container;
- preserve the existing local database and media rather than resetting them;
- bypass the Production entrypoint for local HTTP preview;
- inspect status/log tail;
- verify `/up`;
- then return directly to browser review.

Do not trigger dependency reinstalls, broad Pest, a full test-suite cleanup or CI merely to look at the page. Do not destructively reset local DB/media for a browser candidate.

## 4. Command quality requirements

Commands in the follow-up prompt should be directly copy-pasteable and defensive.

PowerShell rules:

- do not use `$Home`/`$HOME` as mutable variables;
- do not use `$Args` for Docker splatting;
- use `$HomeText`, `$RunArgs`, `$MigrationArgs`, `$EnvVars`, etc.;
- verify expected HEAD before destructive/reconciliation operations;
- verify clean working tree;
- use one atomic script block for multi-step local operations:

```powershell
& {
    $ErrorActionPreference = 'Stop'
    # commands
}
```

- do not rely on a loose interactive paste where commands after `throw` can still be executed separately;
- print a success banner only inside the atomic block and only after the final health/HTTP assertion succeeds;
- show `git diff --stat`, `git diff --check`, log and status around risky manual edits.

Avoid enormous scripts when a few clear commands are enough. Include a longer block only when it genuinely prevents repeated manual mistakes.

## 5. Browser-review behavior in the next chat

The continuation prompt should explicitly tell the new chat:

- do not defend the current UI merely because it matches a worker report;
- treat the user's browser observation as authoritative acceptance input;
- distinguish a technically present feature from a good/consistent implementation;
- use `ui-skills.md` when evaluating consistency;
- read `worker-prompt-skill.md` before delegating any repair;
- treat previously browser-accepted surfaces as presentation-frozen during reconciliation;
- collect screenshots/text feedback if provided;
- do not repeatedly build while feedback is still being gathered;
- do not put broad tests/cleanup between a ready browser candidate and the user's review;
- do not call the candidate final before the user accepts it.

## 6. What not to put in the follow-up prompt

Avoid:

- generic project history that does not affect the next action;
- huge CI/test logs;
- every commit ever made;
- stale issue descriptions superseded by current browser feedback;
- secrets/credentials/private data;
- invented server topology;
- vague instructions such as “continue where we left off” without exact state.

## 7. Final delivery format

When the user asks for a follow-up prompt, normally deliver:

1. a short statement of any docs/state updates completed first;
2. any one-time local `git pull --ff-only` command needed to obtain those docs;
3. the **entire follow-up prompt as one contiguous fenced code block**.

Do not split the prompt into multiple code blocks that the user has to assemble.

## 8. Reusable request shorthand

If the user later says something like:

> Mach mir einen Folgeprompt nach `followup-skill.md`.

interpret that as a request to:

- inspect the current exact state;
- update durable docs when this chat learned reusable rules;
- capture transient Git/runtime/browser/worker state in the prompt;
- include known dirt and next actions;
- make the next chat operationally self-sufficient;
- provide one copyable continuation block.
