# Agent workflow contract

## Immediate numbered-worker bootstrap and non-negotiable method

A new chat assigned **“Du bist Dev Worker N, mach …”** must first read **this branch's `AGENTS.md`**, the [same-branch Dev skill](.agents/skills/dev/SKILL.md), the [shared worker protocol](.agents/README.md), and the [Dev-only roster](.agents/roster.md). It must register or explicitly take over its numbered slot with a checked lease **before** investigating or changing the assigned feature. The user's one-line assignment activates the durable architecture/quality rules without requiring another long prompt. Only that worker's own branch-local progress file belongs in `.agents/workers/dev-N.md`; do not create compatibility aliases, parallel skills, or import Docs' worker records.

**Tool-access guard for fresh worker chats:** Before declaring this repository inaccessible, follow [the branch-local repository-access preflight](.agents/README.md#repository-access-in-tool-restricted-chats--mandatory-preflight): find and use the connected GitHub tools even if the terminal has no checkout or network access. A failed shell clone is not evidence that GitHub cannot be read or changed. Continue the authorized work through the connector; distinguish code commits from unavailable local runtime verification.

**Never patch around an architectural defect.** Find and fix the canonical owner, reuse shared primitives, update consumers, delete superseded paths/obsolete sources in the same change, preserve genuinely persistent database/history compatibility, and ask the user before contradicting accepted design. **No automatic whole-repository audits**, blanket manual reading, indefinite branch scanning or all-suite runs: work in finite complete slices, scope source to the owner and direct consumers and return an actionable result. This is required even if a lower-priority document suggests doing more. The user always has the final architectural decision.


This file is the default working contract for coding agents, review/orchestration chats and parallel workers in `Wiiii90/moeller-lars`.

## Abbreviations in user-facing communication — mandatory

**On first use in each user-facing reply, report, handoff, explanation or standalone document, write out every abbreviation in full and immediately explain what it means in plain language.** This applies to technical initialisms, acronyms, file formats, protocols, systems and project-specific shorthand, even if they are familiar to developers or were explained in an earlier chat. Format: **full term (abbreviation)**, followed by a short explanation of its purpose when the term is not self-explanatory.

For example: **International Press Telecommunications Council (IPTC)** — a standard for descriptive information in image files; **Extensible Metadata Platform (XMP)** — a format for embedding metadata in files. Afterwards the short forms may be used within that response.

If the expansion is unknown, verify it before using the abbreviation, or avoid the abbreviation. Never present a string of unexplained short forms as a progress report; describe what was built, what the artist can see or do, and what is still missing in ordinary language first.

Literal identifiers in source code, paths, commands, configuration keys and existing product labels must remain unchanged; explain their meaning in the surrounding text rather than renaming them merely to satisfy this communication rule.

**Canonical Admin references:** [Admin UI](docs/admin/ui.md), [Responsive](docs/admin/responsive.md), [Dialogs](docs/admin/dialogs.md) and [Browser review](docs/admin/browser-review.md). They own the rules directly; no separate root UI or handoff skill exists.

## Browser-accepted dialog scroll-lock contract — do not regress

The admin dialog behavior accepted in browser review on 2026-10-11 is a
**protected product invariant**, not disposable styling or a temporary workaround:

- **Long pages:** Keep the existing native document scrollbar visible and in its
  exact place during blocking dialogs; prevent background scrolling; preserve
  document geometry and scroll position on close.
- **Short pages:** Never manufacture a document scrollbar, right gutter, or
  horizontal shift. Opening **and closing**, including the leave animation,
  must not briefly flash a scrollbar. Keep the short-page body in normal flow;
  the root overflow lock suffices when there was no document scroll range.
- **All dialogs:** Conditional field/checkbox changes must not move the window.
  Preserve the persistent root-owned geometry variables and declarative Filament
  modal CSS across Livewire morphs; retain framework focus/Escape/X lifecycle.
- **Single owner:** Filament's existing modal lock and counter remain authoritative.
  The reviewed, version-locked build-time adjustment lives in
  `scripts/patch-filament-scroll-lock.php`; do not remove it, revert to upstream
  `overflow: hidden` on pages with scrollbars, add a second JS lock or wheel
  interceptor, or substitute `scrollbar-gutter: stable` / permanent padding.
- **Upgrade/build:** `composer.json` applies the patch before
  `filament:upgrade` publishes assets; the Docker production image carries those
  assets. An upgrade to Filament Support requires reviewing and adapting the
  version guard and patch, rather than silently removing this behavior.
- **Acceptance:** Keep the long/short/nested lifecycle tests in
  `tests/js/filament-scroll-lock-patch.test.mjs` and the real browser checks in
  `tests/browser/e2e/admin/dialog-scroll-lock.spec.mjs`. A passing source-level test
  does **not** imply browser acceptance. Retain the last accepted behavior unless
  the user explicitly changes this contract.

See `docs/admin/dialogs.md` and `docs/admin/ui.md` for shared UI geometry.

## Active public-rights implementation workstream (temporary)

The agreed cross-cutting public-rights/privacy/media/SEO implementation is
tracked in root [`PUBLIC_RIGHTS_WORKSTREAM.md`](PUBLIC_RIGHTS_WORKSTREAM.md).
**Before any relevant code change or continuation prompt, read and update that
living workstream**, then consult the durable
[`docs/public/rights-privacy.md`](docs/public/rights-privacy.md).
It records the accepted scope, current slice, completion evidence and
cross-chat handoff. Update it in the **same commit** as implementation changes;
reconcile concurrent `dev` commits before editing. It is temporary: when the
full workstream is genuinely complete, transfer durable rules to docs,
remove this link, and delete the temporary root file.

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
Read `docs/public/rights-privacy.md` before new public privacy work.

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
3. Assert a genuine change yields persisted data, expected audit,
   exactly one persisted Notification and one immediate
   `admin-header-notification` event; verify no-op and invalid state do
   not create a success message. Check close/reopen and cross-field saves.
4. Test both the domain transition and the real Filament/Livewire event
   path, including dynamically shown/hidden fields and nested editors.
5. If runtime tests or browser checks cannot be performed, mark them
   **unverified**, never call static source review or unexecuted tests
   proof of functional correctness. Include a reproducible acceptance
   checklist in the handoff.

See `docs/admin/notifications.md`,
`docs/admin/dialogs.md`, and `docs/operations/testing.md`.

## User authority and architectural disagreement

**The user has the final word on product behavior, architecture, technical direction, approved exceptions, stage freezes and acceptance.** Source and current contracts are the strongest evidence of what has been implemented; they do not outrank a newer, explicit user decision. If a requested implementation appears to contradict the current architecture, report the exact conflict, affected owners and simplest clean options, then ask the user to choose **before** breaking the established design. Do not silently implement a parallel workaround, but do not treat past architectural decisions as unchangeable without the user's consent.

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

## Cross-feature acceptance preservation gate — mandatory before refactoring

An accepted interaction is a **behavioral constraint**, not permission to
replace its implementation during unrelated cleanup. A refactor, component
reuse, dependency upgrade, CSS change, import cleanup, or test consolidation
that can affect another editor is **cross-feature work** even if its commit
message mentions only one editor.

Before changing a shared owner or a previously browser-reviewed interaction:

1. Name every consumer of the touched owner (including indirectly through
   shared components, sortable groups, styles, entrypoints, and lifecycle).
   Compare the actual before/after behavior and the existing accepted
   requirements for **each** consumer, not just the requested page.
2. Preserve all already accepted behaviors unless the user explicitly
   authorizes their replacement. Do not call a new implementation
   equivalent because it has fewer files, fewer listeners or uses more native
   framework APIs. Technical simplification is not acceptance evidence.
3. For Pages, preserve both **root-level ordering** and **deliberate child
   placement** across first/middle/last parents, including returning a child
   to the top level. A drop meant to reorder a root must never silently
   change its parent. Custom Page Image-child ordering/placement is a
   distinct consumer: do not change Pages while refactoring Custom Page,
   or vice versa, without explicit cross-feature scope and verification.
   The concrete interaction contract is in `docs/admin/ui.md` section 9.
4. Verify the actual combined browser gestures in the affected editor(s),
   using the current candidate, before asserting a working replacement. A
   domain test that calls the sort method does not establish which drop zone
   the browser chose. If browser verification is unavailable, stop before
   an unrelated cross-feature architectural change; if the user expressly
   requests a directly affected experimental fix, mark it **unverified**
   and obtain real acceptance before treating it as established.
5. Review the net diff against the requested scope. If an unrequested editor,
   shared interaction owner or accepted behavior would change, **do not
   silently include it**: report the dependency and request explicit scope
   approval. Do not weaken, replace or rewrite the accepted behavior in
   documentation or tests to match an unaccepted candidate.

When fixing a rejected candidate, first isolate and remove that candidate's
unverified change without rolling back unrelated concurrent work; do not add
a compensating drag/drop runtime in a new file by default.

## Test ownership and documentation hygiene — mandatory

**Dev workers must not create, edit, move, delete, refactor or consolidate test code, fixtures or browser-test sources.** Nor may routine Dev workers edit general `docs/` contracts, generate architecture inventories or reorganize general agent manuals. **Active living workstream files, current issue/task notes and the worker's own progress must be kept synchronized** with implementation in the same scope; report relevant durable contract changes for Docs to reconcile later. An explicitly user-requested, narrow documentation correction is an exception, not routine feature work. Development implements production application code on `dev`. The dedicated testing workstream owns new tests, modifications, suite restructuring and isolated test execution on its separate branch. Accepted tests are returned to `dev` only through an expressly authorized, reviewed integration; this is not a routine Dev-worker task.

Dev may inspect the existing integrated test suite and, where an independently verified isolated environment is available, run already-existing focused checks without changing their source. Report the exact code revision, observed behavior, what was source-reviewed versus genuinely executed, current browser/user acceptance, and concrete regression cases required for the testing owner. **Do not author "a quick regression test" on `dev`.** No test result from an older source revision certifies the latest code.

**Deletion-first design for every worker:** Before adding *any* module, document, test grouping, UI pattern, helper, workflow or generated artifact, reconsider the existing structure and whether a previous owner should be renamed, consolidated or **deleted**. Do not accumulate wrappers, compatibility gateways, parallel implementations, alias paths, transitional shims or duplicate files in unreleased code merely to avoid updating callers. Proven persisted database/migration/snapshot/history compatibility must be preserved; any other actual compatibility requirement needs the user's explicit decision. Remove superseded paths and references as part of the same scoped correction. Keep private host configuration, pricing, access paths, credentials and personal data out of the public repository.

## Direct-`dev` collaboration

Application implementation is direct on `dev` across public, admin and shared Domain code; test authorship belongs exclusively to the separate testing workstream. Multiple chats can operate on disjoint scopes but must reconcile concurrent commits and shared contracts.
If multiple chats resume concurrently, each must establish explicit file
ownership and reconcile shared changes. Never assume the branch remained
unchanged between work sessions.

Hard restrictions for all direct-`dev` work:

- **Commit directly to `dev` only.** Never create side/worker/integration/review
  branches, worktrees or duplicate local checkouts. Never merge/push to `main`,
  start a release or deploy unless the user expressly authorizes a separate task.
- **No unsolicited Continuous Integration (CI) interaction:** An agent may dispatch, inspect or troubleshoot GitHub Actions only after the user explicitly requests it, using bounded summaries and relevant failed-step excerpts. Never start indefinite polling or consume entire multi-minute logs. Local targeted tests and source checks are distinct from CI and may run only with verified safe isolation; never claim tests or browser review ran when they did not.
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

`dev` is the normal development branch and `main` is protected. Running CI and promoting a release require separate user authorization; source edits to CI configuration do not authorize runs.

## Cross-branch workflow and integration

The normal *user-directed* progression is `dev → testing → docs → refactor → main`, but Test, Docs and Refactor workers may prepare **in parallel** with many Dev implementation commits, and the user can alter this order. They reconcile full accepted source changes into their own working branches without making Dev workers maintain tests or general docs. Development branches and worker records are **not** to be merged wholesale across ownership boundaries.

- **During Dev activity:** scoped Dev workers implement, reconcile concurrent changes and update only relevant active workstream/task state. No routine test or general documentation edits and no routine full-suite/Codex generation.
- **When the user freezes Dev:** Testing must be source-equivalent to the **exact frozen Dev code tree** (apart from Testing-owned tests), accounting for the complete diff since its last accepted baseline. Testing workers rethink grouping/fixtures/framework placement, remove obsolete tests and run/correct the suite with Codex/native isolated tools **as their last pre-integration verification step**; test coverage must be behavior-based, not one new regression per Dev commit. If a real product defect is found, Dev fixes it, the target freeze/source parity must be renewed, and affected tests rerun.
- **Docs gate:** after accepted code/testing changes, Docs reconciles against the exact integrated source; authoritative docs and necessary retained generated documentation are reconciled/checked, using Codex/native generators **only in the final pre-transfer batch if needed**, not continuously. General docs are then imported as reviewed durable content, not as worker-branch history or rosters.
- **Refactor/release gate:** any Refactor work is reconciled against the integrated baseline and checked for preserved behavior before user-approved transfer. Promotion to protected `main`, release tooling and Production remain independently authorized.

**No integration is automatic:** check latest head and exact source parity, review imported paths/conflicts, record actual verification and ask for the user's approval at the declared gate. An unexecuted test, a Docs source review or parallel preparation is not a completed stage.

## Current orchestration model

Direct development uses scoped Audit + Implementation + Diff-Review sessions without delegated worker branches.
Admin and public are parallel tasks, **not parallel implementations of a
feature**. Reuse the existing Domain/persistence services and shared media,
publication, routing and Rich Text contracts. A public-only change must not
fork those services; an admin-only change must not reshape public presentation
without verifying the impact. The user can supply screenshots and feedback in
either session; a passing source check is not browser acceptance.

When an actual release to `main` is later requested, follow the protected
promotion contract in `docs/operations/release.md` under a **new, explicit authorization**.
Until then there is no branch choreography, CI handoff or deployment step.

## Source responsibility

The user may authorize the current chat to inspect and implement directly on dev. Scope changes to confirmed causes and current contracts; independently review the net diff. Read-only audits must not silently become implementations. A separate coding chat follows the same direct-dev rules and reconciles HEAD before any write; historical worker branches are not a current workflow.

## Routine source-inspection and remote I/O discipline

Routine browser fixes and other small bounded changes must be investigated from the concrete symptom inward, not by re-auditing the repository.

Treat `AGENTS.md`, `docs/admin/ui.md` and the current orchestration/handoff context as already-established working context. Do not repeatedly re-read them in full during the same task unless they changed or a concrete conflict requires it.

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

### Scope boundary

Do not repair unrelated defects, adjacent layouts or unrequested architecture found during a narrow task. Report out-of-scope findings separately. Keep canonical Domain, Public and Admin owners singular.

## Browser-review loop

1. Inspect the actual responsible source and establish current browser behavior/root cause.
2. Implement only the agreed scope, reusing shared contracts and canonical owners.
3. Review the diff and reconcile newer dev changes.
4. Build one coherent local preview candidate; avoid redundant rebuilds.
5. Review the integrated candidate in the browser; distinguish runtime evidence from user acceptance. Validation requires separate authorization.

A container boot, source check or disappearing HTTP 500 is not product acceptance.

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

[`docs/admin/ui.md`](docs/admin/ui.md) owns the admin presentation contract, accepted references,
shared primitives, CSS ownership and visual review checklist. Read it before UI work.
Prompts must identify the exact reference files and workers must enumerate reused primitives
and justify feature-local CSS. Review actual Blade/CSS changes for bypasses before reconciliation.

For standard admin geometry, the durable contracts are an implementation gate. Before editing a toolbar, table or Visual Stage:

- use the existing shared primitive/owner first;
- derive toolbar widths from longest legitimate content **and** the shared ruler, never from guessed spans;
- preserve the terminal Selection count/select-all/row-checkbox axis exactly;
- do not add page-local standard-toolbar grids, Selection offsets or duplicate shared-stage geometry;
- if the shared owner cannot represent the requested composition, stop and extend the shared owner deliberately instead of bypassing it locally;
- identify missing architectural coverage in the testing handoff; Dev workers must not add source-string or markup tests.

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

Static review, `git diff --check`, tiny syntax checks and focused contract checks are appropriate. CI workflow source may be prepared on `dev`, but CI runs and logs may only be requested or inspected with explicit user authorization and bounded results; `docs/operations/release.md` owns separately authorized release qualification. Local Feature/Pest suites are forbidden against the persistent browser database. Source-string tests are scaffolding unless they protect a durable invariant.

Dev workers do not add or edit tests during browser work. They hand specific stable domain/browser regression scenarios to the test-owning worker; an unaccepted presentation cannot become a regression contract.

The user's repeated request to inspect a change quickly is not an invitation to reinstall dependencies or recreate infrastructure.

## Git and remote verification

Before any dev write, establish HEAD; immediately before committing, re-check and reconcile newer changes. Inspect the actual net diff for unrelated files and broken shared contracts. Commit only against the latest HEAD and push with a normal expected-HEAD fast-forward lease. No force, resets, rebases, side branches or automatic promotion. Another chat's handoff is not proof: inspect its source changes and check evidence.

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

When protected Validation is actually needed, use the established preview/release workflow in `docs/operations/release.md`. Do not invent server commands, hostnames or topology.

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
- consult `docs/admin/ui.md` and `docs/admin/responsive.md` before changing admin workspace geometry;
- when the user explicitly rejects the current presentation or asks for a reset, do not preserve that rejected structure merely to minimize the diff.

## Responsive system

Responsive behavior is content-driven, not device-name driven. Issue #15 owns the current public/admin responsive contract.

- preserve accepted wide/desktop presentation unless current browser feedback changes it;
- use semantic width states (Wide, Compact, Narrow, Minimal) as behavior states, not fixed device classes;
- admin shell-level changes may follow viewport width, but task components should prefer their actually available container/workspace width;
- shell changes must be monotonic: collapsing the desktop sidebar into the burger/mobile shell must never promote a task component back into a wider responsive state merely because the content area became wider;
- do not track browser width through Livewire/Laravel or persist resize state;
- admin toolbars are exactly one row at every supported responsive state, with canonical order Query/Filter -> Task actions -> Selection; two-row toolbars are forbidden;
- width pressure is solved within that one row by redistributing width, collapsing lower-priority filters behind a local Filters overlay when necessary, and switching actions from complete labels to icon-only/overflow states; never show clipped pseudo-labels such as `O…` or `P…`;
- any filter overlay state is presentation-only client state and must not introduce resize-driven Livewire traffic;
- Selection remains the terminal table utility and its selected-count, select-all and row checkboxes stay on one rail while the surface remains tabular;
- Metric separators are the preferred soft alignment grid for toolbar regions and major table columns; align to the page's six-cell metric boundaries when semantics and fit allow;
- Dashboard toolbar keeps right-side controls intrinsically compact; Search absorbs the remaining width and may shrink only to one metric cell, so right-side controls consume Search width only when required;
- filter-reset UI is named Clear, not Reset, and uses the shared Clear action/icon; Selection triggers use the shared multi-selection icon;
- ordinary admin tables must fit without horizontal scrolling or reserved horizontal-scrollbar space; reduce supportive/optional columns and compact actions instead;
- shared Position/Drag rails and hierarchy ordering cells must never clip their square position badge or drag control; Pages/Custom Page/Journal consume that shared geometry rather than page-local offset hacks;
- Pages, Custom Page and Journal toolbars remain one row with elastic Search and compact right-side controls; Gallery follows the same principle in its custom toolbar; Analytics, Storage and Activity also remain one row, with Storage/Activity preserving all four filters and compacting Clear/View/Selection before any wrap;
- burger/sidebar collapse is monotonic across Dashboard, General, Pages, Gallery, Custom Page, Journal, Analytics, Storage and Activity; freed workspace may not reintroduce wider metrics, hidden stage panes, table columns, labels or denser grids;
- metrics reduce deliberately by feature semantics; Dashboard, General, Pages/Gallery/Custom Page/Journal and Analytics/Storage/Activity use 6 / 6 / 3 / 2 with their feature-specific priorities recorded in `docs/admin/responsive.md`; every two-metric Minimal strip is exactly 50/50 and no metric strip becomes a one-column list;
- responsive metric geometry is centralized in `.admin-metrics`: Narrow = three equal tracks with container-owned dividers at 1/3 and 2/3; Minimal = two equal tracks with one divider at 1/2. Feature rules may only choose visibility/priority and must not assign responsive metric spans or their own vertical dividers;
- Analytics/Storage/Activity Narrow metrics explicitly own columns 1/2/3 with full-cell stretch and both dividers; Minimal explicitly owns columns 1/2 with one centered divider, so source order or prior grid spans cannot produce 1/3–2/3 geometry;
- shared Visual Stage desktop geometry does not imply generic mobile stacking. Analytics/Storage/Activity keep their desktop multi-cell stage axes through Narrow and collapse only at Minimal: Analytics -> full-width Map, Storage -> full-width Upload + compact capacity/actions, Activity -> full-width Next Publication with Review/Reset/Commit preserved; the shared fixed stage height remains unchanged across these transitions;
- General removes Live Preview at Narrow/Minimal and expands Appearance to the full stage; burger/sidebar collapse must not reintroduce the preview; Site icon keeps a stable reserved field footprint so selected media never shifts subsequent controls;
- shared Visual Stage height is fixed across responsive widths via the single `--admin-visual-stage-height` token; do not redefine that token in breakpoints or add page-local responsive stage-height tokens unless the product contract is explicitly changed;
- shared stage orbit size is canonical via `--admin-stage-orbit-size`; paired stage visuals must consume it directly and must not introduce page-local responsive orbit-size overrides;
- Dashboard specifically keeps all three stage panes (Storage | Activity | Analytics) visible through Wide, Compact and Narrow; only Minimal may use the local selector and show one stage pane at a time; burger/sidebar collapse alone is not a Dashboard stage breakpoint;
- Dashboard stage captions/facts remain one-line and visible through Narrow, and its visual row must scale smoothly without footer-driven height jumps;
- Dashboard feed priority is monotonic: action labels yield before Title is damaged, Sender yields next, then Type + Date + Time fold together into complete Title metadata; burger/sidebar collapse may add breathing room but never unfold a state already reached;
- responsive action density may progress from label+icon to icon-only to essential icons plus an overflow menu, while preserving accessible labels and semantic order;
- legacy public breakpoint values are historical evidence only, not target layout authorities;
- browser review must sweep continuously through transition widths, including sidebar-expanded/constrained desktop states, to catch broken intermediate compositions.

## Central technology rules

### Rich Text

The canonical stack is:

```text
AdminRichText / Filament RichEditor (Tiptap)
  -> visual editor document -> validated visual HTML
  -> RichTextMediaReference (canonical Storage image IDs, legacy Markdown compatibility)
  -> SafeRichTextRenderer (with ArtistImageLayout and ArtistTextSpacing)
  -> public HTML
```

Embedded visual-editor images reference available canonical Storage assets by `data-id`; legacy Markdown `media:<id>` references remain readable and are converted only on editing. Do not reintroduce a separate editor, external-image uploads, legacy `[[journal-image:...]]` syntax, or an alternate public renderer. See `docs/content/rich-text.md`.

### Media

`MediaAsset` is the canonical reusable original. `MediaReferenceQuery` answers whether an asset is referenced; `PublicMedia` answers whether it is actually public. Protected preview does not create another asset type.

### Ordering

Use native Livewire sorting (`wire:sort`, `wire:sort:item`, `wire:sort:handle`) and canonical domain ordering services. Do not build parallel HTML5 drag state machines.

## Persistence and audit

- normal edits persist independently of any future logical Commit utility;
- text fields do not write per keystroke under the current admin contract;
- persist changed text on the normal change/blur path only when normalized content changed;
- toggles/selects/media choices may persist on discrete changes;
- read-only workspace search/typeahead is live with the one canonical 300 ms debounce; it never submits a normal form, navigates, or writes editorial state;
- local animation, clock and browser-render scheduling may use timing APIs when they remain presentation-only; do not confuse those with persistence/search transport rules;
- Storage data/capacity refresh is event-driven by completed uploads or explicit refresh, never timer/poll driven;
- Activity/Audit records successful writes but is not the persistence trigger;
- publication, destructive operations and media references continue through canonical domain services.

## Worker prompt delivery

Whenever an implementation, audit, repair, or handoff prompt is emitted for copy/paste, the entire prompt must be inside exactly one contiguous fenced Markdown code block. Do not split it across multiple fenced blocks, use quote/indent formatting for the prompt, or place any part of the prompt outside that code block.

A worker prompt should state:

- repository;
- exact current dev HEAD and direct-dev update strategy;
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

When a chat reaches its context limit, update the active numbered worker task record when one exists; otherwise provide a compact task-specific handoff containing exact checked HEAD, accepted behavior, owned source, actual verification, remaining work and safety boundaries. Do not create another root handoff-skill file.

## Completion gates

A feature, redesign, browser repair or reconciliation is not complete merely because its new path works. Where applicable, completion requires independent browser/product acceptance, shared consistency/reconciliation against current authorities, source/product cleanup, and final verification.

After browser/product acceptance, audit the accepted change for source it created or superseded and remove only what reference-search proves obsolete: superseded routes/pages, dead views/partials, unused CSS/selectors, obsolete aliases/compatibility paths, duplicate presentation paths, stale implementation-specific tests, and imports/classes that became unused.

Preserve still-required compatibility and domain behavior. Audit references, call sites, routes and tests before deletion; never delete from naming alone. This cleanup scope covers only artifacts made obsolete by the accepted change; unrelated cleanup remains out of scope.

Run authorized final verification against the cleaned final state, not a pre-cleanup candidate. Browser/product acceptance and source cleanup are separate gates: passing one does not imply the other. Routine direct-`dev` work must not dispatch or inspect CI without explicit user authorization; a final qualification is separately authorized and uses bounded run summaries rather than long polling.

## CI and release authorization

Direct-dev agents may dispatch, inspect or troubleshoot GitHub Actions **only when specifically authorized**; use bounded summaries and the relevant failure excerpt, without long polling or complete job logs. The CI workflow files can be maintained as source when requested. The release, browser-profiling, runtime-image-smoke and validation-preview workflows are manually dispatched only; ordinary `dev` pushes no longer trigger them. Promotion to main is always independently authorized. The current PHP test structure is Unit, Integration, Feature and Migration, plus JS and browser suites. The special concurrent PostgreSQL runners have their own isolated execution contract. See `tests/README.md`, `docs/operations/testing.md` and `docs/operations/release.md`.
