# Agent workflow contract

This file is the default working contract for coding agents and parallel workers in `Wiiii90/moeller-lars`.

## Source of truth

Use, in this order:

1. the current branch and current code;
2. open GitHub Issues for unfinished product scope, browser acceptance and blockers;
3. durable contracts under `docs/`;
4. PR discussion only for implementation-specific context.

Do not reconstruct current requirements from stale issue history, old branches, previous release-candidate SHAs or closed coordination threads.

## Product language

Use the current artist-facing/runtime concepts when describing or changing the product:

- Home;
- Gallery;
- Journal with Blog and Exhibitions templates;
- Custom Page;
- Navigation Node;
- Files;
- reusable Contact component inside Custom Page content.

Legacy names such as `CV`, `Vita`, fixed `SiteSection` types or old persistence/resource names may appear in migration evidence, compatibility code or database/model names. They are not a license to reintroduce those concepts as current admin information architecture or new worker scopes. Biography/career content is composed through the current Custom Page/content model. Preserve migration provenance until its dedicated cleanup is proven safe.

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

1. run only targeted checks appropriate to the behavior actually changed;
2. push the exact branch/SHA;
3. use `scripts/validation-preview.ps1 <branch-or-sha>` when browser review is needed;
4. Validation deployment is performed with the existing platform helper printed by that script;
5. browser acceptance is evidence separate from CI.

Do not rerun broad suites merely because they exist. Full verification belongs to the final PR to `main` unless a concrete risk justifies broader checks earlier.

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

## Iterative redesign discipline

The admin is still being redesigned. Existing good work is the starting point, not a frozen artifact and not an invitation to rebuild a page from scratch.

Workers should improve the assigned page against the current browser findings while moving reusable improvements into coherent shared systems where that genuinely benefits later pages.

- keep structures and interactions that are already working well unless the current redesign explicitly improves them;
- do not reimplement old issue descriptions that current code has already solved;
- do not turn a few concrete defects into a speculative page rewrite;
- when a better generic primitive can solve the current problem and improve later pages, prefer that over a page-local hack;
- consistency means shared geometry, typography, controls, labels, spacing, dialogs and interaction behavior where appropriate, while each workspace keeps its task-specific content model;
- browser feedback in the current issue/user review overrides stale acceptance language.

## Admin product contract

The artist admin is an editorial tool, not a generic SaaS dashboard.

Current shared composition for normal primary workspaces, where the page actually has comparable operational metrics:

1. one visible heading whose wording matches the navigation destination;
2. no decorative kicker/eyebrow above that heading;
3. one restrained six-metric strip;
4. the page-specific action/filter/control row;
5. the actual task surface: table, grid, contact sheet, editor, tree, analytics composition, etc.

View switches must not make the task surface visibly jump vertically. When two modes have different internal chrome, compensate with shared spacing/geometry so their content starts on the same visual baseline.

Other system-level rules:

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

Dialogs must behave as browser-window overlays, not as panels constrained to the admin content column.

Required behavior:

- backdrop/overlay covers the relevant viewport;
- dialog is centered in the browser viewport, not merely inside the content workspace;
- width is responsive and bounded by the viewport;
- height is bounded by the viewport;
- oversized dialog content gets an internal vertical scroll region so controls remain reachable with wheel/touch/keyboard;
- header/footer/actions remain usable rather than being pushed outside the clickable viewport;
- stable z-index/backdrop behavior;
- Escape/close behavior;
- focus trap and focus restoration;
- keyboard accessibility;
- nested selectors/popovers remain above the dialog and usable;
- originating workspace state remains intact after close/save.

Fix shared dialog defects generically when encountered by the first page that needs them. Do not create a page-local fake modal merely to unblock one worker.

## Parallel page ownership

Workers should keep their diff inside their assigned page/domain plus directly necessary tests/services, except for deliberately generic shared primitives required by the current redesign.

Do not casually edit global shell/theme files from multiple branches for unrelated reasons. If the assigned defect belongs to a shared primitive, make the shared change intentionally and keep it compatible with the other admin pages.

## Persistence and audit

- Normal edits persist independently of the future `Commit` utility.
- Text fields must not persist per keystroke and must not use timer/debounce autosave under the current General/admin contract.
- Persist changed text on the normal change/blur path only when the normalized value actually changed.
- Toggles/selects/media choices may persist on their discrete change event.
- Activity/Audit records successful writes; it is not the persistence trigger.
- Destructive/publication/media-reference operations must continue through canonical domain services and audit paths.

## Verification

Run the narrowest checks that prove the changed behavior while iterating. Reuse existing evidence for behavior that was not changed. Add focused regression coverage only for new or modified invariants/interactions that need it.

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
- relevant targeted checks run;
- real blockers or browser-acceptance points.

Do not produce long implementation diaries unless specifically requested.
