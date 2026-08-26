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
- protected integration/main constraints;
- current combined branch;
- exact current head SHA;
- relevant base SHA;
- side branches and their accepted/rejected status;
- whether the working tree should be clean.

Explicitly distinguish:

- historical feature branches;
- temporary worker side branches;
- current combined browser branch;
- integration/release branches.

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

### Known dirt / incidents

Do not hide mistakes that can confuse Git history or future debugging.

Examples:

- accidental intermediate commit later repaired;
- side branch based on an older shared base;
- migration run that first failed before DB mutation and later succeeded;
- unreachable/no-op commit not referenced by a branch;
- local cache/image known to have represented an older candidate.

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

Worker prompts must be one contiguous fenced code block.

### Worker review workflow

For every returned worker:

1. verify remote branch/head;
2. verify parent/base;
3. compare exact base→head;
4. inspect actual code/diff independently;
5. identify cross-branch/shared-file effects;
6. accept/reject before integration;
7. avoid broad tests unless the actual change warrants them.

### Reconciliation workflow

Give explicit rules:

- cherry-pick/union only accepted diffs;
- resolve shared files intentionally;
- make cross-branch compatibility fixes in a separate small commit;
- inspect net diff after any repair;
- push fast-forward where possible;
- do not rewrite public history merely to make it pretty when a safe forward repair is clearer.

### Build/migration/browser workflow

If the next chat will need it, include the exact command pattern currently proven to work.

Prefer:

- one build after the intended set of fixes is reconciled;
- verbose migration output;
- stop on migration failure;
- reuse existing network/env/media mounts;
- replace only the web container;
- inspect status/log tail;
- then return directly to browser review.

Do not trigger dependency reinstalls or a full test suite just to look at the page.

## 4. Command quality requirements

Commands in the follow-up prompt should be directly copy-pasteable and defensive.

PowerShell rules:

- do not use `$Home`/`$HOME` as mutable variables;
- do not use `$Args` for Docker splatting;
- use `$HomeText`, `$RunArgs`, `$MigrationArgs`, `$EnvVars`, etc.;
- verify expected HEAD before destructive/reconciliation operations;
- verify clean working tree;
- stop when a command fails;
- show `git diff --stat`, `git diff --check`, log and status around risky manual edits.

Avoid enormous scripts when a few clear commands are enough. Include a longer block only when it genuinely prevents repeated manual mistakes.

## 5. Browser-review behavior in the next chat

The continuation prompt should explicitly tell the new chat:

- do not defend the current UI merely because it matches a worker report;
- treat the user's browser observation as authoritative acceptance input;
- distinguish a technically present feature from a good/consistent implementation;
- use `ui-skills.md` when evaluating consistency;
- collect screenshots/text feedback if provided;
- do not repeatedly build while feedback is still being gathered;
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
