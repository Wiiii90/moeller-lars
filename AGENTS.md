# Agent workflow contract

This file is the default working contract for coding agents, review/orchestration chats and parallel workers in `Wiiii90/moeller-lars`.

Task-specific companion documents are part of this contract:

- [`ui-skills.md`](ui-skills.md) — canonical artist-admin UI grammar and browser-review conventions;
- [`followup-skill.md`](followup-skill.md) — how to create a lossless continuation prompt when a long orchestration chat is handed to a fresh chat.
- [`DOCS_SKILL.md`](DOCS_SKILL.md) — living `docs`-branch work state, verified architecture inventory, outstanding tasks and fresh-chat resumption procedure; keep current on documentation commits.
- [`.agents/skills/documentation/SKILL.md`](.agents/skills/documentation/SKILL.md) — stable documentation work method; `DOCS_SKILL.md` remains the living checkpoint.
- [`.agents/skills/admin-ui/SKILL.md`](.agents/skills/admin-ui/SKILL.md) — task-oriented Admin UI entrypoint, linking the accepted shared Filament contracts.
- [`.agents/skills/public-ui/SKILL.md`](.agents/skills/public-ui/SKILL.md) — public website entrypoint, linking public routing, composition, media and presentation owners.
- [`.agents/skills/handoff/SKILL.md`](.agents/skills/handoff/SKILL.md) — compact current-branch scope/commit/verification handoffs; historical worker procedures are not active.

## Browser-accepted dialog scroll-lock contract — do not regress

The **11 October 2026 accepted** Filament dialog lock is a protected behavior:
scrollable pages retain the native document scrollbar and scroll position
while blocking background scroll; short pages must not gain a scrollbar,
gutter or close-animation flash. Conditional fields must not move the
dialog window. The [ADMIN-DIALOG-CONTRACT](docs/ADMIN-DIALOG-CONTRACT.md#scrolling-and-responsive-behavior)
owns the precise long/short lock, frame geometry, pinned build patch,
focus/close lifecycle, browser/test references and version-upgrade guards.
Filament remains the single lock owner: do not replace its patched lifecycle
with a second JS lock, global wheel interceptor or permanent scrollbar gutter.

## Active public-rights implementation workstream (temporary)

The agreed cross-cutting public-rights/privacy/media/SEO implementation is
tracked in root [`PUBLIC_RIGHTS_WORKSTREAM.md`](PUBLIC_RIGHTS_WORKSTREAM.md).
Before relevant `dev` code changes or public-rights continuation prompts, read
and update this living execution state and consult the durable
[`docs/PUBLIC-RIGHTS-AND-PRIVACY.md`](docs/PUBLIC-RIGHTS-AND-PRIVACY.md).
Update it in the **same commit** as implementation changes. It is temporary:
upon full implementation and acceptance, transfer durable rules into `docs/`,
remove this pointer and delete the temporary root file. The `docs` branch
maintains its own separate [`DOCS_SKILL.md`](DOCS_SKILL.md) checkpoint and
must not edit the concurrently owned rights execution state on `dev`.

## Public CSP and rights protection

Public scripts, styles, image features and animations MUST use the existing
strict nonce-based CSP in `app/Http/Middleware/SecurityHeaders.php`. Never
copy the permissive Admin/Pulse policy into Public, or add `unsafe-inline`,
`unsafe-eval` or wildcard hosts as a workaround. Use Vite-bundled resources
and the established nonce path; verify Livewire navigation too.

Preserve the public HTML/media TDM rights reservation and its
`/.well-known/tdmrep.json` discovery route, and the SEO-compatible robot
opt-outs. Neither TDM nor robots.txt protects against downloads. Full-quality
public Artwork Viewer originals are a deliberate product requirement.
Matomo tracking must not operate without legally sufficient prior consent.
Read `docs/PUBLIC-RIGHTS-AND-PRIVACY.md` before new public privacy work.

## No workarounds — mandatory stop rule

This project builds coherent, canonical systems. **Workarounds, symptom-level patches, compensating hacks and parallel implementations are prohibited by default.** The moment an agent starts considering a workaround rather than a root-cause correction, it **MUST STOP IMMEDIATELY**: do not implement it, do not commit it, and do not continue by layering additional conditions, listeners, timers, retries, CSS overrides, state flags, fallback lifecycles or alternate persistence paths.

A stop means: identify the underlying owner/invariant that is failing, inspect the smallest relevant source and previously accepted implementation, and report the cause and the clean architectural correction. If the root cause cannot be established, leave the code unchanged and explicitly report the blocker. Resume implementation only once the repair directly restores the canonical owner and eliminates the defective path; **never disguise an unresolved defect by changing diagnostics, build scripts or unrelated infrastructure**.

Do not preserve an obsolete implementation alongside its replacement. Remove obsolete files, hooks, imports, tests and documentation *as part of the same scoped correction*. Do not modify tests to make a broken implementation appear correct. An exceptional workaround is allowed **only if the user explicitly authorizes that specific, fully explained temporary exception**; convenience, time pressure, regressions and framework limitations do not constitute permission. This rule overrides generic instructions to “just make it work.”

## Mandatory mutation/feedback completion contract

For any user-facing authenticated editorial admin mutation, changing
the database is **not** a complete UI action. Inbox-only state such as
marking a Notification read/unread, pinning or dismissing it is an explicit
exception: it must not generate a self-referential new Notification.
Domain persistence, append-only Activity/audit, and
`AdminNotifier::notification()` are distinct owners: neither domain save nor
audit automatically delivers a Notification. The Action/Livewire mutation
boundary **must** consume the domain change result, refresh the relevant
projection, and call the single canonical notifier on a real successful
change. A no-op or failed/rolled-back mutation must not emit success.
For a common sub-operation, own the notification once at its existing
mutation boundary; never add duplicate form-level, modal-level or
page-local feedback paths. Domain callbacks reused outside the admin
must not unconditionally create UI messages.

**Mandatory verification before reporting an admin fix complete:**
1. Trace user input / form event to the actual persisted record and
   reload/reopen behavior; distinguish `afterStateUpdated` from modal
   `wire:change`, and identify exactly one mutation owner.
2. Trace `AdminAuditService` evidence and the actual call to
   `AdminNotifier`; the audit context supplies presentation only, **not**
   dispatch. Verify the notifier reaches both mailbox and header event.
3. Assert that a genuine change yields persisted data, expected audit and
   **one action-level Notification**: a single-Activity edit normally yields
   one mailbox row and one immediate header event; one action containing
   multiple Activity presentations yields **one** aggregated message with
   all contributing `metadata.audit_event_ids`. No-op and invalid state
   must not create success. Check close/reopen, cross-field edits and
   duplicate source IDs; mailbox deduplication alone does not deduplicate
   repeated header delivery.
4. Test both the domain transition and the real Filament/Livewire event
   path, including dynamically shown/hidden fields and nested editors.
5. If runtime tests or browser checks cannot be performed, mark them
   **unverified**, never call static source review or unexecuted tests
   proof of functional correctness. Include a reproducible acceptance
   checklist in the handoff.

See `docs/ADMIN-NOTIFICATION-CONTRACT.md`,
`docs/ADMIN-DIALOG-CONTRACT.md`, and `docs/TESTING.md`.

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
- Storage;
- reusable Contact component inside Custom Page content.

Legacy names such as `CV`, `Vita`, persistence model/table names or old migration terms may remain as migration/data-model evidence. They are not permission to recreate obsolete admin IA or parallel runtime concepts.

## Documentation branch exception and unified dev tests

`docs` remains the expressly authorized documentation/architecture workstream,
not a place for runtime application changes. The former dedicated `testing`
workstream has been merged into `dev` (integrated since `dev@25bc40144`);
there is **one active test structure on `dev`**. Do not maintain two
independent suites, testing worktrees or testing-to-dev handoffs.

- On `docs`, read implementation **and active authored tests** from the current
  `dev` SHA. Historic references to `testing@...` are historical evidence only,
  never a claim of executed tests or a presently divergent branch.
- Import coherent relevant changes from `dev` onto `docs` only with a real,
  conflict-reviewed **two-parent merge** preserving both histories; do not
  rewrite either branch or import each new commit by reflex.
- The user authorized one local documentation worktree at
  `P:\moeller-lars-docs` on the existing `docs` branch for the source-only
  documentation closeout. Reuse it; leave `P:\moeller-lars` and the existing
  testing checkout untouched. No other branches/worktrees/PRs are authorized.
  Do not merge `docs` to `dev` or `main` without separate authorization.
- The existing prohibition on CI interaction remains absolute for this workstream.
  Source-only local checks are not CI, but never execute a checker that touches
  a persistent Preview/Production database or assumes a safe schema by filename.
- Source-/test-/runtime-verification claims must remain separate. Machine-generated
  diagrams must state their extraction limitations, version/inputs and unverified
  dynamic edges.

The following direct-`dev` instructions govern **direct application development
on `dev`**, not the expressly approved documentation workstream.

## Direct-`dev` collaboration (2026-10-12)

Admin and Public work may proceed in separate chats directly on `dev`, but each
chat owns only its requested task scope. Concurrent edits require explicit file
ownership, fresh-HEAD reconciliation and deliberate merging of shared Domain
or presentation changes before any commit. Never assume the branch remained
unchanged between work sessions.

Hard restrictions for all direct-`dev` work:

- **Commit directly to `dev` only.** Never create side/worker/integration/review
  branches, worktrees or duplicate local checkouts. Never merge/push to `main`,
  start a release or deploy unless the user expressly authorizes a separate task.
- **No CI interaction whatsoever:** do not run, dispatch, monitor, read status,
  inspect jobs, fetch logs, retry, cancel or troubleshoot GitHub Actions/CI.
  Local targeted tests and source checks are distinct from CI and may be run
  only where an appropriate isolated environment exists; never claim tests or
  browser review ran when they did not.
- **Every write is concurrency-aware.** Read `dev` and record its exact HEAD
  at the start. Immediately before building a commit, read HEAD again. If
  another chat advanced it, compare old HEAD -> new HEAD, inspect changed paths,
  reconcile overlapping edits from the new source, and re-review the intended
  net diff. Only then create a commit whose parent is the newest HEAD and
  advance `refs/heads/dev` using an atomic expected-SHA lease/fast-forward.
  On lease failure, re-read and reconcile again; never force-push, reset,
  rewrite shared history, discard someone else's changes or blindly retry.
  Single-file GitHub Contents API updates also require a current blob SHA and
  HEAD reconciliation; for multi-file changes prefer one atomic tree commit.
- Avoid concurrent writes to the same file where possible. Shared Domain
  contracts, public/admin layout boundaries and root documentation need
  special attention. Distinguish **source-verified**, **tests actually run**,
  **browser-reviewed** and **user-accepted** states.
- No symptom hacks or parallel UI/persistence/notification systems. Follow
  the root-cause stop rule above; do not weaken tests to force passing results.

`dev` remains the working integration branch; `main` remains protected. The
older orchestration/worker/PR/CI procedures elsewhere in this document are
historical or optional **future** modes, not permission to override this direct
`dev`/no-CI instruction. Change execution modes only on explicit user request.

## Current collaboration model

Application and artist-facing interface work follows the user's **direct-`dev`** permissions, with file ownership and HEAD reconciliation across simultaneous chats. Admin and Public share existing Domain, Media, Publication, Routing and Rich Text services; one surface must not create a second service implementation to bypass the other. `docs` is the explicit long-lived documentation exception; the former testing workstream is integrated into `dev`. Browser acceptance comes from the user's actual candidate review, not from source checks.

Publishing to `main`, launching CI or deployment are **not** part of this workflow. Any later release requires an explicit separate authorization under [RELEASE.md](docs/RELEASE.md).



## Routine source-inspection and remote I/O discipline

Routine browser fixes and other small bounded changes must be investigated from the concrete symptom inward, not by re-auditing the repository.

Treat `AGENTS.md`, `ui-skills.md` and the current orchestration/handoff context as already-established working context. Do not repeatedly re-read them in full during the same task unless they changed or a concrete conflict requires it.

When the canonical local checkout is available, use `P:\moeller-lars` for normal source inspection, grep/search, diffs, status and history. Remote GitHub reads are for current branch heads, required remote diffs and final push verification, or when the needed source is not available locally. CI metadata, logs and status remain prohibited. Do not substitute repeated remote reads for cheap local inspection.

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

## Scoped audits and implementation

An explicitly **read-only audit** changes no source. It identifies deviations from the user's current accepted contract, names the directly responsible files/classes/selectors and distinguishes evidence from hypotheses. An authorized **implementation** modifies only the agreed scope; it does not become a repo-wide redesign.

When a different bug or architectural issue is discovered during a task, record it under **out-of-scope findings**. Do not fix it automatically. If the requested work genuinely cannot be completed without crossing scope, identify the blocker and obtain a separate decision. No new worker/feature branch, worktree or CI operation is implied by separating audit from implementation.


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

For standard admin geometry, the durable contracts are an implementation gate. Before editing a toolbar, table or Visual Stage:

- use the existing shared primitive/owner first;
- derive toolbar widths from longest legitimate content **and** the shared ruler, never from guessed spans;
- preserve the terminal Selection count/select-all/row-checkbox axis exactly;
- do not add page-local standard-toolbar grids, Selection offsets or duplicate shared-stage geometry;
- if the shared owner cannot represent the requested composition, stop and extend the shared owner deliberately instead of bypassing it locally;
- where practical, architecture/source tests must reject new page-local ownership of these canonical geometries.

A user restating an existing contract is not permission to invent a second implementation. Re-read the owning contract and repair the shared/consumer boundary.

## Verification discipline

Run the narrowest checks that prove the changed behavior while iterating.

Unless a concrete risk requires more, browser-polish workers should not automatically run:

- full Pest;
- full PHPStan;
- npm tests/build;
- Docker rebuilds;
- CI waits;
- Validation deployments.

Static review, `git diff --check`, tiny syntax checks and focused contract checks are appropriate. CI is outside the current workflow entirely; `docs/RELEASE.md` describes a separately authorized future release. Local Feature/Pest suites are forbidden against the persistent browser database. Source-string tests are scaffolding unless they protect a durable invariant.

Tests added during browser work should encode durable product/domain behavior, not temporary orchestration states. Avoid durable test names tied to repair rounds, browser passes, branch choreography or candidate labels when a stable behavior-based name is available. Do not add a broad “acceptance” test merely to memorialize an intermediate browser candidate.

The user's repeated request to inspect a change quickly is not an invitation to reinstall dependencies or recreate infrastructure.

## Git and remote verification

Before each authorized commit, inspect the real source changes against the user's requested behavior. Verify the active branch, refresh its **current HEAD**, compare all intervening commits, reconcile overlapping changes and recheck the net diff. Use an atomic expected-SHA fast-forward update; on a failed lease, reread the branch and reconcile. Do not reset, rebase or force-push over another chat's changes.

On `docs`, review the incoming `dev` file list, merge with **both parents** and record conflicts explicitly. On direct `dev`, commit only onto its latest reconciled HEAD. `testing` is independently owned. Verify requirements against actual source/diff and distinguish claimed checks from those run. Accidental public changes require a forward repair when safe; check the final tree, not just the last commit title.


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
- Compose service: `preview`;
- PostgreSQL container commonly used by the preview: `moeller-lars-postgres-1`;
- image runtime listens internally on port `8080`;
- preview build target: `local-preview` in the root `Dockerfile`;
- canonical Windows helper: `scripts/local-preview.ps1`;
- canonical copy/paste command from any PowerShell location: `& 'P:\moeller-lars\scripts\local-preview.ps1'`;
- when the user asks to rebuild/run the local preview, give that exact command; do not invent a new helper, launcher, inline build recipe or alternative local build path unless the user explicitly asks for one;
- the helper reloads the existing local `storage/app/analytics-demo.php` fixture after the preview container is healthy; do not fold the old large analytics-normalization block back into the Docker build;
- canonical private media mount destination: `/var/www/html/storage/app/private`.

The local browser database is the one persistent local development database. Do not run
`migrate:fresh`, `migrate:refresh`, `migrate:reset` or `db:wipe` locally. Do not spoof CI flags
to bypass the guard. Local
Feature/Pest tests and `migrate:fresh` against it are fail-closed; GitHub Actions
uses an explicit disposable-database context instead.

When disposable local admin history needs to be cleared without touching editorial content,
Working/LIVE state or retained publication snapshots, use `docker exec -it moeller-lars-local-web php artisan admin:reset-local-history` against the running local preview container.
That command is local-only and clears Activity/audit history, Undo receipts, notifications/feed
pins, ordering projections, action stats, publication event state and Audit↔Commit links. It must
not be expanded into a general database reset.

Exact transient branch SHAs, mount source paths and commands belong in the current follow-up prompt, not as timeless architecture facts in this file.

## Fast Validation preview

When protected Validation is actually needed, use the established preview/release workflow in `docs/RELEASE.md`. Do not invent server commands, hostnames or topology.

Local browser acceptance and protected Validation are different environments. Do not deploy Validation merely because local source review is ready.

## Environment safety

- no Production deployment/cutover/DNS/mail/database mutation without explicit authorization;
- no protected Validation mutation merely because code is ready;
- no secrets, credentials, private Production data or authoritative private media in Git;
- `server-platform` owns runtime topology, ingress, backups, operational mail, resource limits and deployment/rollback.

## Issue discipline

Issues describe current durable product scope, acceptance criteria, dependencies and blockers. Do not use them as worker diaries or store temporary candidate SHAs as product requirements. Record implementation evidence in commits and current handoffs. A PR/release workflow is not authorized by the existence of an issue; it requires a separate decision and does not override the no-CI rule.


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

## Responsive system

Current admin Wide/Compact/Narrow/Minimal priorities and monotone responsiveness are owned by the [Admin Responsive Contract](docs/ADMIN-RESPONSIVE-CONTRACT.md), split into Shell, Metrics, Toolbars, Tables/Selection and Visual Stages. The shared implementation authorities remain the Admin Control, Table and Dialog contracts, not page-local CSS.

The accepted Wide composition is retained until real content pressure requires a named transition. A Sidebar/Burger collapse must not re-expand already compacted task controls, metrics, table columns or stages. Presentation-only responsive state remains CSS/Alpine, never a new persisted Livewire resize model or polling system. Browser acceptance requires continuous resizing through intermediate widths, not a code review alone. Historical public legacy breakpoints are not current target values.

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

Canonical embedded Storage media images use `media:<id>`. The **same** `AdminRichText` editor additionally supports explicitly validated external **HTTP(S)** image URLs via `SafeRichTextRenderer` / `SafeLinkPolicy`; this does **not** turn those URLs into Storage `MediaAsset` references. Reject unchecked/unsafe external-image schemes and credentials. Do not resurrect TipTap/RichEditor, legacy `[[journal-image:...]]` runtime syntax or a second parser/editor. The [canonical Rich Text and reusable media contract](docs/ADMIN-CONTROL-CONTRACT.md#rich-text-and-reusable-media-selection) owns this precise boundary.

### Media

`MediaAsset` is the canonical reusable original. `MediaReferenceQuery` answers whether an asset is referenced; `PublicMedia` answers whether it is actually public. Protected preview does not create another asset type.

### Ordering

Use native Livewire sorting (`wire:sort`, `wire:sort:item`, `wire:sort:handle`) and canonical domain ordering services. Do not build parallel HTML5 drag state machines.

## Persistence and audit

Editorial controls persist changed **Working** state on discrete semantic
change/blur, independently of a later Publication Commit. Search uses the
shared **read-only 300 ms** throttle; its timer never triggers writes.
[ADMIN-CONTROL-CONTRACT.md](docs/ADMIN-CONTROL-CONTRACT.md) owns the precise
autosave/search distinctions, [MEDIA.md](docs/MEDIA.md) owns event-driven
Storage/capacity refresh, and
[ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md](docs/ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md)
owns Audit/Undo and Working/LIVE. Activity records successful mutations but
does not initiate persistence; feedback is an explicit,
[separate notifier](docs/ADMIN-NOTIFICATION-CONTRACT.md). Presentation-only
timing may not be repurposed into polling or persistence.

## Task and continuation handoffs

The canonical [handoff skill](.agents/skills/handoff/SKILL.md) covers both scoped implementation returns and complete follow-up prompts. The root [followup-skill.md](followup-skill.md) remains only as a compatibility entrypoint; it no longer governs temporary worker branches or a `dev`-only workflow. A document-only continuation reads the current [DOCS_SKILL.md](DOCS_SKILL.md). A handoff never grants permission for a new branch, worktree, CI run or release.


## Completion gates

A feature, redesign, browser repair or reconciliation is not complete merely because its new path works. Where applicable, completion requires independent browser/product acceptance, shared consistency/reconciliation against current authorities, source/product cleanup, and final verification.

After browser/product acceptance, audit the accepted change for source it created or superseded and remove only what reference-search proves obsolete: superseded routes/pages, dead views/partials, unused CSS/selectors, obsolete aliases/compatibility paths, duplicate presentation paths, stale implementation-specific tests, and imports/classes that became unused.

Preserve still-required compatibility and domain behavior. Audit references, call sites, routes and tests before deletion; never delete from naming alone. This cleanup scope covers only artifacts made obsolete by the accepted change; unrelated cleanup remains out of scope.

Run authorized final verification against the cleaned final state, not a pre-cleanup candidate. Browser/product acceptance and source cleanup are separate gates: passing one does not imply the other. In the current direct-`dev` mode **CI is prohibited**, including reads and dispatches; a later release qualification is a separate user-authorized workflow.

## CI restriction

**No CI interaction is authorized in the current workstreams.** Do not start, cancel, monitor, inspect status/metadata, download logs, repair workflow configurations or use CI as a validation substitute. Source-only checks and safe local isolated tests are distinct, and must be reported truthfully. Release/CI work requires a new explicit user authorization under [RELEASE.md](docs/RELEASE.md); old worker-run procedures remain historical Git evidence, not present instructions.
