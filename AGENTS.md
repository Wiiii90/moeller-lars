# Agent workflow contract

This file is the default working contract for coding agents and parallel workers in `Wiiii90/moeller-lars`.

## Source of truth

Use, in this order:

1. the current branch and current code;
2. open GitHub Issues for unfinished product scope, browser acceptance and blockers;
3. durable contracts under `docs/`;
4. PR discussion only for implementation-specific context.

Do not reconstruct current requirements from stale issue history, old branches, previous release-candidate SHAs or closed coordination threads.

## Branch and integration workflow

- `main` is protected. Never commit or force-push directly to `main`.
- The current admin-completion tranche integrates on `integration/admin-v0.3-final`.
- Page/slice workers branch from the current integration head and open PRs back to that integration branch.
- Do not retarget a worker PR to `main` unless explicitly instructed by the orchestrator.
- Worker PRs into the integration branch use risk-appropriate targeted checks; they do not need to trigger the canonical full release workflow merely for iteration.
- The combined integration candidate is browser-reviewed on protected Validation.
- One final integration PR to `main` receives the complete canonical verification gate.
- Do not create extra integration branches for unrelated one-off work.

See `docs/RELEASE.md` for the exact release/preview contract.

## Fast Validation preview

For a branch or combined integration candidate:

1. run targeted checks appropriate to the changed area;
2. push the exact branch/SHA;
3. use `scripts/validation-preview.ps1 <branch-or-sha>` to build an exact-SHA preview image;
4. Validation deployment is performed with the existing platform helper printed by that script;
5. browser acceptance is evidence separate from CI.

Agents must not invent server commands, hostnames or platform topology.

## Environment safety

- No Production deployment, cutover, DNS, mail, database or other Production mutation unless the user explicitly authorizes it.
- Do not mutate protected Validation merely because code is ready; the orchestrator/user decides when the combined candidate should be deployed for browser review.
- Never commit secrets, credentials, private Production data or private media.
- `server-platform` owns runtime topology, host paths, operator quota injection, ingress, backups and operational mail infrastructure.

## Issue / PR discipline

Issues contain durable current product scope, acceptance criteria, dependencies and blockers.

PRs contain implementation details, changed files, technical decisions, targeted verification and transient branch/CI information.

Do not use issues as worker diaries. Do not routinely post commit SHAs, CI run IDs or branch chatter into issues.

## Admin product contract

The artist admin is an editorial tool, not a generic SaaS dashboard.

Preserve these system-level rules unless a current issue explicitly changes them:

- one deliberate visible heading per normal workspace;
- generous useful desktop width and one shared workspace axis/gutter strategy;
- task-specific layouts instead of one universal card template;
- no repeated generic SaaS/LLM card walls;
- dense/list-oriented Files workspace;
- visual contact-sheet Gallery workspace;
- analytical Analytics composition;
- capacity-specific Storage visualization;
- coherent timeline/history Activity presentation;
- restrained neutral palette and shared control geometry;
- use shared tokens/primitives before page-local pixel patches;
- do not reintroduce obsolete `Website`/`Library` wrapper IA or expose persistence model names as artist-facing concepts.

## Dialog and overlay contract

Shared/native dialog behavior is owned by #61 and is a cross-page primitive.

Do not create page-local fake dialogs or navigation disguised as modals. Dialog-driven flows must use the shared/native system and preserve:

- real overlay/backdrop behavior;
- correct width/height/scroll containment;
- Escape/close behavior;
- focus trap and restoration;
- keyboard accessibility;
- responsive behavior;
- correct nested select/popover layering;
- originating workspace state after close/save.

If a page worker discovers a shared dialog defect, fix it only when it is clearly within the shared primitive; otherwise report the blocker rather than cloning a workaround.

## Parallel page ownership

Workers should keep their diff inside their assigned page/domain plus directly necessary tests/services.

Do not casually edit global shell/theme files from multiple page branches. Shared visual primitives, shell geometry and dialog infrastructure should be changed deliberately and kept compatible with all admin pages.

For the current tranche, preserve accepted behavior outside the assigned page. In particular, do not redesign accepted Gallery contact-sheet/metrics/analytics, Files density or canonical Site Node architecture just to make a local implementation easier.

## Persistence and audit

- Normal edits persist independently of the future `Commit` utility.
- Text fields must not persist per keystroke and must not use timer/debounce autosave under the current General/admin contract.
- Persist changed text on the normal change/blur path only when the normalized value actually changed.
- Toggles/selects/media choices may persist on their discrete change event.
- Activity/Audit records successful writes; it is not the persistence trigger.
- Destructive/publication/media-reference operations must continue through canonical domain services and audit paths.

## Verification

Run the narrowest checks that prove the changed behavior while iterating. Add focused regression coverage for changed domain invariants.

The final PR to `main` must pass the canonical full gate in `.github/workflows/release.yml`:

- Composer dependency/security verification;
- frontend build;
- Pest;
- PHPStan;
- Pint;
- JavaScript tests.

CI success alone is not browser/product acceptance for visual or interaction work.

## Handoff

A worker handoff should normally contain only:

- branch;
- head SHA;
- PR number/link if opened;
- actual changes;
- relevant tests run;
- real blockers or browser-acceptance points.

Do not produce long implementation diaries unless specifically requested.
