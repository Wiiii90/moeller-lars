# Agent workflow contract

This file is the default working contract for coding agents, review/orchestration chats and parallel workers in `Wiiii90/moeller-lars`.

Two companion files are part of this contract:

- [`ui-skills.md`](ui-skills.md) — canonical artist-admin UI grammar and browser-review conventions;
- [`followup-skill.md`](followup-skill.md) — how to create a lossless continuation prompt when a long orchestration chat is handed to a fresh chat.

Additional browser-acceptance discipline is documented in [`docs/ADMIN_BROWSER_ACCEPTANCE.md`](docs/ADMIN_BROWSER_ACCEPTANCE.md).

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

`main` is protected. The admin-completion integration line remains `integration/admin-v0.3-final` until the tranche is explicitly accepted and promoted.

Browser-heavy reconciliation may use one temporary combined branch such as `reconcile/admin-v0.3-browser`. That branch is a review candidate, not a release branch and not proof of product acceptance.

When several browser-fix workers run in parallel:

1. freeze one exact shared base SHA;
2. create one side branch per worker directly from that SHA;
3. workers do **not** all push directly to the combined reconciliation branch;
4. each worker returns branch + exact head + changed files + actual checks;
5. the orchestrator verifies the remote ref and reviews the real base→head diff independently;
6. accepted side diffs are reconciled/cherry-picked onto the latest combined branch;
7. shared files are resolved deliberately as a union, never by blindly preferring one worker's version;
8. cross-branch consequences are fixed explicitly in a small reconciliation commit;
9. only after the combined source state is coherent is one browser build/migration/review cycle performed.

Do not mutate the original feature branches merely to create a browser candidate. Do not rebase, merge to `main`, deploy Production or retarget PRs unless the orchestrator explicitly asks.

## Browser-review loop

Visual/admin work follows this order:

1. get a technically running combined candidate;
2. review it in the browser;
3. collect the user's complete findings for the current review slice before launching a repair worker;
4. classify findings into functional bugs, interaction defects, layout/style inconsistencies, performance, missing behavior and architecture/centralization mistakes;
5. create a fresh narrowly scoped worker from an exact base;
6. statically review the returned diff;
7. reconcile accepted work;
8. perform one new local/Validation browser cycle.

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

See `docs/ADMIN_BROWSER_ACCEPTANCE.md` for the durable review and prompting rules that follow from this distinction.

## Verification discipline

Run the narrowest checks that prove the changed behavior while iterating.

Unless a concrete risk requires more, browser-polish workers should not automatically run:

- full Pest;
- full PHPStan;
- npm tests/build;
- Docker rebuilds;
- CI waits;
- Validation deployments.

Static review, `git diff --check`, tiny syntax checks and focused contract checks are appropriate. Full canonical verification belongs to the final PR to `main` as defined in `docs/RELEASE.md`.

Tests added during browser work should encode durable product/domain behavior, not temporary orchestration states. Avoid durable test names tied to repair rounds, browser passes, branch choreography or candidate labels when a stable behavior-based name is available. Do not add a broad “acceptance” test merely to memorialize an intermediate browser candidate.

The user's repeated request to inspect a change quickly is not an invitation to reinstall dependencies or recreate infrastructure.

## Git and remote verification

Never trust a worker handoff text by itself. For every returned worker candidate:

- verify the remote branch head;
- verify the expected parent/base;
- compare exact base→head;
- inspect the actual changed files and critical implementation;
- distinguish reported checks from checks actually evidenced;
- reject unexpected scope before reconciliation.

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
- no Git worktrees;
- no root-drive helper clutter;
- local snapshots/tooling state stays inside the repository and outside Git;
- retained local Validation data belongs under `storage/local-validation-snapshot/`.

## Local browser preview

The local browser preview is an iteration aid, not the canonical release topology. Reuse the existing preview stack instead of recreating it from scratch.

Current durable local interface assumptions:

- application URL: `http://127.0.0.1:8001`;
- application container: `moeller-lars-local-web`;
- preview image: `moeller-lars-local-preview`;
- PostgreSQL container commonly used by the preview: `moeller-lars-postgres-1`;
- image runtime listens internally on port `8080`;
- preview Dockerfile: `storage/local-validation-snapshot/Dockerfile.local-preview`;
- canonical private media mount destination: `/var/www/html/storage/app/private`.

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

A prompt intended for a parallel worker must be one complete contiguous fenced code block so it has one Copy button.

A worker prompt should state:

- repository;
- exact base SHA and branch strategy;
- allowed scope;
- explicit non-goals;
- browser findings/root causes already known;
- central technologies that must be reused;
- checks that are and are not required;
- commit/push rules;
- exact handoff fields expected.

For visual work, the prompt must additionally identify the current browser acceptance state and any user-designated reference surface. Do not describe rejected current markup as a preservation requirement unless the user explicitly accepted it.

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

The orchestrator then reviews the code independently. Long implementation diaries are not acceptance evidence. A worker must not self-declare browser/product acceptance for a presentation it cannot see in the user's running candidate.

## Continuation handoffs

When the orchestration chat itself is becoming too large, follow [`followup-skill.md`](followup-skill.md). The new chat should be able to continue from exact Git/browser/runtime state without asking the user to reconstruct it manually.
