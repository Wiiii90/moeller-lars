# Agent workflow contract

This file is the default working contract for coding agents, review/orchestration chats and parallel workers in `Wiiii90/moeller-lars`.

Two companion files are part of this contract:

- [`ui-skills.md`](ui-skills.md) — canonical artist-admin UI grammar and browser-review conventions;
- [`followup-skill.md`](followup-skill.md) — how to create a lossless continuation prompt when a long orchestration chat is handed to a fresh chat.

## Source of truth

Use, in this order:

1. the exact current branch/SHA and its code;
2. current browser feedback from the user for the candidate being reviewed;
3. open GitHub Issues for unfinished product scope, acceptance and blockers;
4. durable contracts in this repository;
5. PR discussion only for implementation-specific context.

Browser feedback against the current candidate overrides stale acceptance wording. Do not reconstruct requirements from old issue history, old worker branches or earlier release-candidate SHAs when current code/user review supersedes them.

## Product language

Use the current artist-facing concepts:

- Home;
- Gallery;
- Journal with Blog and Exhibitions templates;
- Custom Page;
- Navigation Node;
- Files;
- reusable Contact component inside Custom Page content.

Legacy names such as `CV`, `Vita`, persistence model/table names or old migration terms may remain as migration/data-model evidence. They are not permission to recreate obsolete admin IA or parallel runtime concepts.

## Current orchestration model

`dev` is the daily integration branch. `main` is the protected acceptance/release branch.
Only `main` and `dev` are permanent. Worker branches use `feat/<scope>`, `fix/<scope>`,
`chore/<scope>` or `docs/<scope>` and are deleted immediately after integration into `dev`.
Do not create archive branches/tags or persistent repair/reconcile/integration/browser branches.

Normal flow: named scope branch -> source review -> `dev` -> browser/product acceptance
and release qualification -> `main`. Integration does not itself establish browser acceptance.

Remote workers start from the exact `dev` SHA named in their prompt and use only their
assigned branch. They never push to `main` or deploy Validation without explicit instruction.
For parallel workers, freeze one shared dev base, review each base-to-head net diff, then
integrate into the latest dev deliberately. Resolve shared files as a union; delete each
integrated worker branch. Do not create another combined reconciliation branch.

Keep browser-accepted presentation unchanged during source reconciliation unless the
current task explicitly changes it. Repeated visual defects require inspection of the
actual browser DOM/computed styles when available; do not claim measurements without a browser.

Do not rebase public history, merge to `main`, deploy Production or retarget PRs unless
explicitly authorized. Keep `dev` and the current task base distinct when dev advances.

## Orchestrator and worker roles

A General-/Orchestration-/Browser-Review chat is primarily the **State Keeper, Scope Designer, Worker-Prompt Author, Diff Reviewer and Reconciliation-/Browser-Review Orchestrator**.

Keep the orchestrator context focused on:

- exact branches and SHAs;
- confirmed findings and root causes;
- user/product decisions;
- worker handoffs and their review status;
- reconciliation state;
- runtime/browser acceptance state.

Do not use the orchestrator as the default implementation worker for large source passes when a worker is intended for that scope. In particular, avoid loading large series of source files/diffs into the general chat and then producing many implementation commits there. Larger source work belongs in dedicated worker chats so implementation detail and diff volume do not consume the orchestration context.

This is scope/context discipline, not a ban on direct changes. The orchestrator may implement when the user explicitly asks it to, or when the change is very small, clearly bounded and cheaper to perform directly than to hand off.

## Routine source-inspection and remote I/O discipline

Routine browser fixes and other small bounded changes must be investigated from the concrete symptom inward, not by re-auditing the repository.

Treat `AGENTS.md`, `ui-skills.md` and the current orchestration/handoff context as already-established working context. Do not repeatedly re-read them in full during the same task unless they changed or a concrete conflict requires it.

When the canonical local checkout is available, use `P:\moeller-lars` for normal source inspection, grep/search, diffs, status and history. Remote GitHub reads are for facts that are genuinely remote — for example branch heads, worker branches, PRs, CI state or final push verification — or when the required source is not available locally. Do not substitute repeated remote reads for cheap local inspection.

For a routine UI/browser defect, the normal diagnostic budget is intentionally small:

- start from the browser finding and the directly responsible Blade/CSS/JS/PHP owner;
- inspect only the few files needed to prove the current cause;
- use at most one narrow search when ownership is unknown;
- once the direct source supports a repair, implement it instead of continuing to search for absolute certainty;
- broaden the investigation only when the narrow path produces a concrete contradiction or blocker.

Do not perform repo-wide scans, full directory-tree reads, broad history archaeology or unrelated issue/PR review for a routine fix. Git history is not a default debugging tool; use it only when current source is ambiguous and history is materially needed to avoid a wrong change.

Never fetch whole compiled, generated, minified, vendor or lockfile-sized artifacts merely to find one selector, symbol or rule. Prefer the authored source, a narrow local search, or a line/range-specific read. If one large remote read stalls, truncates or is clearly disproportionate, do not repeat the same fetch. Change strategy immediately.

Do not repeatedly fetch the same file/blob/commit in one workflow when the result is already in context and still current. Reuse the fetched result. After a small direct change, verification should normally be limited to the resulting diff plus the exact remote head when a push occurred; do not re-download every touched file merely to prove that the write happened.

If a routine fix unexpectedly appears to require a broad repository audit, large vendor/generated reads, repeated remote retries or many unrelated source files, stop and report that scope expansion before spending the time. The default is fast, local, targeted inspection — not exhaustive remote archaeology.

### Audit worker

An **audit worker** is read-only.

It must:

- change no code;
- identify concrete deviations from the requested/current contract;
- provide source evidence, root cause and the affected files/components/selectors/structures;
- report findings without repairing them “while already there”.

### Implementation worker

An **implementation worker** receives confirmed findings and implements only the explicitly described scope.

It must not reopen a general redesign/audit while implementing, nor treat neighboring code as permission for opportunistic cleanup. If audit and implementation are intentionally combined in one worker, the prompt must say so explicitly and still define the audit boundary, implementation boundary and stop conditions.

### No scope expansion

When any worker discovers another bug, UI drift, architecture issue or improvement outside the explicitly allowed scope:

- do **not** repair it automatically;
- record it under `Out-of-scope findings`;
- touch it only when the requested change is technically impossible without resolving that point;
- in that exceptional case, prefer stopping and reporting the blocker rather than inventing a broader scope.

A worker must not interpret instructions such as “polish this” as permission to polish adjacent surfaces.

## Browser-review loop

Visual/admin work follows this order:

1. get a technically running combined candidate;
2. review it in the browser;
3. collect the user's complete findings for the current review slice before launching a repair worker;
4. classify findings into functional bugs, interaction defects, layout/style inconsistencies, performance, missing behavior and architecture/centralization mistakes;
5. create a fresh narrowly scoped worker from an exact base;
6. statically review the returned diff;
7. reconcile accepted work;
8. review the integrated dev candidate in one local browser cycle; Validation requires explicit authorization.

Do **not** call a candidate final merely because it boots, migrations succeed or a 500 disappears. Browser/editorial acceptance is separate evidence.

Do not rebuild after every comment or every worker. Prefer one combined build after all intended side diffs for that cycle are reconciled.

## Browser acceptance authority

Static source review cannot approve presentation. Use precise acceptance language:

- **source reviewed / source coherent** means the code and contracts were inspected;
- **runtime verified** means a concrete runtime behavior was actually exercised;
- **browser reviewed** means the current built candidate was inspected in the browser;
- **browser/product accepted** means the user accepted that current presentation and behavior.

Never collapse these states into a generic `accepted` label for UI work.

If the user rejects a page's presentation, that rejected markup/CSS/layout is **not** a contract merely because it already exists or passed static checks. Preserve working domain behavior, persistence, services and safety guards, but the presentation layer may be replaced wholesale when that is the clearest route to the accepted UI.

When the user identifies another current page as the visual reference for a dimension such as width, heading geometry, metrics, controls, table rows, typography or actions, workers must read the exact reference implementation at the shared base and reuse its shared primitives/tokens before inventing anything new. A prose prompt that merely says “keep things consistent” is insufficient evidence of visual consistency.

Do not create a new page-local design language to satisfy one slice. In particular, do not introduce a parallel shell, card system, content width, toolbar grammar, table grammar or typography system when an accepted/current reference already exists.

If repeated visual repair passes preserve the same rejected structure, stop patching around it. Re-audit from the accepted reference and rebuild the presentation layer while keeping the domain/runtime behavior intact.

Do not encode rejected or unreviewed presentation details into durable tests or docs merely because a worker implemented them. Current browser acceptance is the authority for presentation.

## Admin theme enforcement

[`ui-skills.md`](ui-skills.md) owns the admin presentation contract, accepted references,
shared primitives, CSS ownership and visual review checklist. Read it before UI work.
Prompts must identify the exact reference files and workers must enumerate reused primitives
and justify feature-local CSS. Review actual Blade/CSS changes for bypasses before reconciliation.

## Verification discipline

Run the narrowest checks that prove the changed behavior while iterating.

Unless a concrete risk requires more, browser-polish workers should not automatically run:

- full Pest;
- full PHPStan;
- npm tests/build;
- Docker rebuilds;
- CI waits;
- Validation deployments.

Static review, `git diff --check`, tiny syntax checks and focused contract checks are appropriate. CI verifies PRs to `dev` and `main`; `docs/RELEASE.md` owns the workflow details. Local Feature/Pest suites are forbidden against the persistent browser database. Source-string tests are scaffolding unless they protect a durable invariant.

Tests added during browser work should encode durable product/domain behavior, not temporary orchestration states. Avoid durable test names tied to repair rounds, browser passes, branch choreography or candidate labels when a stable behavior-based name is available. Do not add a broad “acceptance” test merely to memorialize an intermediate browser candidate.

The user's repeated request to inspect a change quickly is not an invitation to reinstall dependencies or recreate infrastructure.

## Git and remote verification

Never trust a worker handoff text by itself. For every returned worker candidate:

- verify the remote branch head;
- verify the expected parent/base;
- compare exact base→head;
- inspect the actual changed files and critical implementation;
- map every material prompt requirement to the actual base→head **net diff**;
- inspect unexpected files, non-requested layout/domain changes, missing requirements, unnecessary parallel primitives and any scope expansion;
- distinguish reported checks from checks actually evidenced;
- reject unexpected scope before reconciliation.

A worker handoff is a navigation aid, not proof of scope compliance. The orchestrator's review is requirement → diff, not merely “does this diff look plausible?”.

When a side branch was intentionally created from an older shared base, review it against that base, then reconcile onto the newest combined head deliberately.

Avoid force pushes during browser reconciliation. If an accidental bad commit is already public but a normal forward repair can restore the correct tree, prefer a repair commit and verify the **net tree diff** afterward.

## PowerShell safety

The canonical local repository path is `P:\moeller-lars`.

PowerShell variables are case-insensitive. Do not use automatic/read-only variable names for script state. In particular:

- never use `$Home`/`$HOME` for file content;
- never use `$Args` for Docker argument splatting;
- use names such as `$HomeText`, `$RunArgs`, `$DockerArgs`, `$EnvVars`.

For scripted file replacement:

- verify the expected source pattern count before writing;
- stop immediately on a failed read/assignment;
- inspect `git diff --stat` and `git diff` before committing;
- treat unexpectedly large deletion counts as a blocker;
- run `git diff --check` before commit.

## Local project-disk hygiene

Use only `P:\moeller-lars` for this repository.

- no sibling scratch clones/copies;
- no repository searches or scans outside `P:\moeller-lars`;
- no Git worktrees;
- no root-drive helper clutter;
- local snapshots/tooling state stays inside the repository and outside Git;
- `storage/app/private/**` is protected local user data, never cleanup material;
- do not create local snapshot/recovery tool trees or a second permanent test database;
- do not delete or overwrite `.env`, credentials or current media during cleanup.

## Local browser preview

The local browser preview is an iteration aid, not the canonical release topology. Reuse the existing preview stack instead of recreating it from scratch.

Current durable local interface assumptions:

- application URL: `http://127.0.0.1:8001`;
- application container: `moeller-lars-local-web`;
- preview image: `moeller-lars-local-preview`;
- PostgreSQL container commonly used by the preview: `moeller-lars-postgres-1`;
- image runtime listens internally on port `8080`;
- preview Dockerfile: `docker/Dockerfile.local-preview`;
- canonical private media mount destination: `/var/www/html/storage/app/private`.

The local browser database is the one persistent local development database. Do not run
`migrate:fresh`, `migrate:refresh`, `migrate:reset` or `db:wipe` locally. Do not spoof CI flags
to bypass the guard. Local
Feature/Pest tests and `migrate:fresh` against it are fail-closed; GitHub Actions
uses an explicit disposable-database context instead.

Exact transient branch SHAs, mount source paths and commands belong in the current follow-up prompt, not as timeless architecture facts in this file.

## Fast Validation preview

When protected Validation is actually needed, use the established preview/release workflow in `docs/RELEASE.md`. Do not invent server commands, hostnames or topology.

Local browser acceptance and protected Validation are different environments. Do not deploy Validation merely because local source review is ready.

## Environment safety

- no Production deployment/cutover/DNS/mail/database mutation without explicit authorization;
- no protected Validation mutation merely because code is ready;
- no secrets, credentials, private Production data or authoritative private media in Git;
- `server-platform` owns runtime topology, ingress, backups, operational mail, resource limits and deployment/rollback.

## Issue / PR discipline

Issues contain durable current product scope, acceptance criteria, dependencies and blockers. PRs contain implementation detail, changed files, technical decisions and verification evidence.

Do not use issues as worker diaries. Temporary branch SHAs and browser-candidate choreography belong in handoffs/orchestration, not durable product issues unless they establish a blocker that needs tracking.

## Iterative redesign discipline

The admin is still under browser acceptance. Existing work is a starting point, not automatically accepted UI.

- preserve working domain behavior while correcting presentation;
- do not reimplement stale issue descriptions already solved by current code;
- do not turn concrete browser feedback into a speculative full-page rewrite;
- prefer an existing shared primitive over page-local CSS/interaction forks;
- when a genuinely shared defect is discovered, fix the shared authority intentionally;
- do not introduce a second Rich Text, media-selection, table, modal or drag/drop technology to unblock one page;
- consult `ui-skills.md` before changing admin workspace geometry;
- when the user explicitly rejects the current presentation or asks for a reset, do not preserve that rejected structure merely to minimize the diff.

## Central technology rules

### Rich Text

The canonical stack is:

```text
AdminRichText / Filament MarkdownEditor
  -> Markdown
  -> RichTextMediaReference
  -> SafeRichTextRenderer
  -> public HTML
```

Canonical embedded Media Files images use `media:<id>`. Do not resurrect TipTap/RichEditor, legacy `[[journal-image:...]]` runtime syntax, arbitrary external-image embeds or a second parser/editor.

### Media

`MediaAsset` is the canonical reusable original. `MediaReferenceQuery` answers whether an asset is referenced; `PublicMedia` answers whether it is actually public. Protected preview does not create another asset type.

### Ordering

Use native Livewire sorting (`wire:sort`, `wire:sort:item`, `wire:sort:handle`) and canonical domain ordering services. Do not build parallel HTML5 drag state machines.

## Persistence and audit

- normal edits persist independently of any future logical Commit utility;
- text fields do not write per keystroke under the current admin contract;
- persist changed text on the normal change/blur path only when normalized content changed;
- toggles/selects/media choices may persist on discrete changes;
- Activity/Audit records successful writes but is not the persistence trigger;
- publication, destructive operations and media references continue through canonical domain services.

## Worker prompt delivery

Whenever a worker, repair, or handoff prompt is emitted for copy/paste, the entire prompt must be inside exactly one contiguous fenced Markdown code block. Do not split it across multiple fenced blocks, use quote/indent formatting for the prompt, or place any part of the prompt outside that code block.

A worker prompt should state:

- repository;
- exact base SHA and branch strategy;
- exact allowed scope boundary;
- explicit non-goals and forbidden neighboring areas;
- confirmed browser findings and known root cause;
- exact target state;
- relevant concrete files/components/selectors when already known;
- expressly allowed files/areas when the task requires a file boundary;
- functional/domain invariants that must remain unchanged;
- central technologies that must be reused;
- source-verifiable acceptance criteria;
- stop conditions;
- checks that are and are not required;
- commit/push rules;
- exact handoff fields expected.

Prompts must distinguish explicitly between:

- **CONFIRMED / FIXED** — facts already established by user feedback, current source or contract;
- **IMPLEMENTATION DISCRETION** — implementation choices the worker may actually make;
- **OUT OF SCOPE** — behavior, files or neighboring surfaces the worker must not change.

A worker must not re-decide what the user or current contract has already decided.

Keep prompts short and operational (normally 20-60 lines); link authorities instead of
copying their contents. Classify new tests as durable behavior coverage or temporary
scaffolding and remove scaffolding before handoff. Runtime PowerShell instructions must
stop on a failed prerequisite before any container replacement or mutation.

When the desired geometry/structure is already known, do not make the worker infer it primarily from phrases such as “polish this”, “make it consistent”, “make it premium”, “clean it up”, “use best judgment” or “make it like the reference”. Such phrases may supplement concrete requirements, never replace them.

For example, instead of only saying “make this clock more intuitive”, a prompt with an already-defined composition should name the structural changes: remove the extra header, keep the mode switch inside the stage, use a real dial with defined hands/current browser time, keep activity secondary, and preserve the existing publication-domain logic. This illustrates prompt precision; it is not a durable product requirement for any particular current page.

For visual work, the prompt must additionally identify the current browser acceptance state and any user-designated reference surface. Do not describe rejected current markup as a preservation requirement unless the user explicitly accepted it.

For visual work, prompts must make theme/reference reuse operational rather than aspirational: name the accepted references, require inspection of their actual source at the base, prohibit duplicate local presentation systems, and require the handoff to enumerate reused primitives and any justified feature-local CSS.

## Worker handoff

A normal handoff contains:

- branch;
- base SHA;
- new head SHA;
- changed files;
- actual behavior/root-cause changes;
- checks actually run;
- remote head verification;
- unresolved blocker if one remains.

An implementation-worker handoff must also provide a compact requirement mapping:

- requirement;
- implemented / not implemented;
- affected files;
- relevant deviation or blocker, if any.

Keep this as prompt → diff traceability, not an implementation diary.

Every worker handoff also has an `Out-of-scope findings` section containing either `none` or concrete discovered items that were intentionally not implemented.

For visual work, also include:

- accepted reference files inspected;
- shared Blade/CSS primitives reused;
- new CSS/classes/tokens added and why each is task-specific.

The orchestrator then reviews the code independently. Long implementation diaries are not acceptance evidence. A worker must not self-declare browser/product acceptance for a presentation it cannot see in the user's running candidate.

## Continuation handoffs

When the orchestration chat itself is becoming too large, follow [`followup-skill.md`](followup-skill.md). The new chat should be able to continue from exact Git/browser/runtime state without asking the user to reconstruct it manually.

## Completion gates

A feature, redesign, browser repair or reconciliation is not complete merely because its new path works. Where applicable, completion requires independent browser/product acceptance, shared consistency/reconciliation against current authorities, source/product cleanup, and final verification.

After browser/product acceptance, audit the accepted change for source it created or superseded and remove only what reference-search proves obsolete: superseded routes/pages, dead views/partials, unused CSS/selectors, obsolete aliases/compatibility paths, duplicate presentation paths, stale implementation-specific tests, and imports/classes that became unused.

Preserve still-required compatibility and domain behavior. Audit references, call sites, routes and tests before deletion; never delete from naming alone. This cleanup scope covers only artifacts made obsolete by the accepted change; unrelated cleanup remains out of scope.

Run required CI/final verification against the cleaned final state, not a pre-cleanup candidate. Browser/product acceptance and source cleanup are separate gates: passing one does not imply the other. In the normal flow above, release qualification follows only after applicable cleanup and final verification.