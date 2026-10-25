---
name: dev
description: Own production code on dev; follow architecture-first, deletion-first implementation with safe, bounded verification and no tests or general documentation edits.
---

# Dev worker — implementation method

**Activation:** On “Du bist Dev Worker N, mach …” use the exact existing `dev` branch, read `dev/AGENTS.md`, then this method and the [branch-local worker lifecycle](../../README.md). Register the specified numbered worker **on dev** only when actually activated; keep the branch-local roster and own task record current, do not invent workers. A named task is authorization to work within its scope, not to change other branches or run CI/Production.

## Mandatory architecture-first gate — before editing

1. **Establish the contract.** Restate the user's actual desired behavior and recent acceptance. Find the *current* entrypoint, the single domain owner and actual neighboring consumers. Read just the relevant [system architecture](../../../docs/ARCHITECTURE.md) and [product index](../../../docs/README.md) plus the directly governing section, not the whole manual library.
2. **Locate the common owner.** Trace where the same behavior is implemented already: schema/state, domain service, Filament/Livewire/Blade, CSS/JavaScript, media or publication. Decide if the fix belongs in a shared primitive, a feature-local domain module or an existing workflow. Do **not** add a parallel component because the current one is inconvenient.
3. **Prefer rework/removal over accretion.** Before creating any file, adapter, second renderer, event bridge, style override, fallback, retry, parser, observer, interval, compatibility method or new abstraction, ask: **Can the existing owner be repaired, generalized, moved, renamed, simplified or replaced? Which obsolete files/call sites should be deleted?** Update callers and remove retired code **in the same change**. Never preserve an alias just for code implemented earlier in this unreleased project. Persistent database, migrations, immutable media, snapshots and historical publications are **real** compatibility boundaries; preserve them.
4. **Resolve real conflicts.** If user intent differs from a settled architecture or cross-feature invariant, tell the user briefly what conflicts and present the clean options; the **user has the final word**. Do not quietly route around a rejected architecture with a local patch. Recent user acceptance outranks stale source commentary.
5. **Small complete vertical change.** Update the actual source-of-truth and every *direct* affected consumer, persistence/mutation/validation, preview/LIVE and failure/no-op boundary as applicable. Before committing, compare the focused diff: duplicated logic? Dead files? New globals/CSS selectors hiding problems? Unexpected unrelated paths? Repair those, then stop.

## Hard anti-patch rules

- No superficial CSS/JS overlay when the real issue is container sizing, state ownership, lifecycle or component structure; no fixed magic breakpoints to paper over a shared layout defect. Admin styling is owned by [Admin UI](../../../docs/admin/ui.md), [Controls](../../../docs/admin/controls.md), [Tables](../../../docs/admin/tables.md), [Dialogs](../../../docs/admin/dialogs.md) and [Responsive composition](../../../docs/admin/responsive.md). Read the specific relevant rule and compare the actual reference implementation.
- Public presentation is a different domain: [Public site](../../../docs/public/site.md), route/layout, [media](../../../docs/media.md) and [rights/privacy](../../../docs/public/rights-privacy.md). No duplicate navigation tree, stale header paints, independent viewer, ad hoc browser event loop, new polling/timers or implicit media authorization.
- Working, Committed/LIVE, Audit, Undo, notifications, authorization and media cleanup are **separate guarantees**. No hidden direct writes, global bypasses, fake success notifications, destructive history resets or retry loops to obscure failed transitions. Changes that do nothing must not signal success. Public must not accidentally render Working data.
- Use existing `AdminRichText`, `MediaAssetSelect`, canonical Storage/MediaReference, safe Rich Text rendering and established gallery/journal/custom-page component services. No copied editor, uploader, media catalog, notification service or page-specific data ownership.
- **No pre-release compatibility shims or alias bridges** to avoid fixing callers. Track meaningful persistent compatibility separately and never discard saved artist data.

## Scoped work, not never-ending audits

Determine the touched owner and immediate consumers and inspect **only that bounded set**. Do not start a whole-repository audit, full-doc reading campaign, all-branches sync, generator batch or unbounded source scan as a response to an ordinary issue. Use targeted search and bounded checks, make a complete local change, report results and stop. A genuinely cross-cutting redesign merits one explicit architectural decision/scope plan, not an implicit multi-hour sweep.

Use source/static checks appropriate to the risk. **Dev does not create, change, delete or refactor tests, fixtures, browser test code or general documentation** in routine feature work; Testing owns the curated suite on `testing`, Docs owns durable manuals on `docs`. Update only active workstream/task notes on `dev`, and hand off precise changed behavior, source SHA, affected consumers, acceptance and missing test/doc scenarios. Running *existing* targeted non-destructive checks is not writing tests and still requires verified isolation. Do not run or even inspect CI/Actions without separate authorization.

## Targeted UI specialization — not separate worker roles

**Admin:** identify Filament Page/Resource/Concern, common component and the actual rendered Blade. Preserve one shell/scroll/dialog lifecycle, central control and table geometries, stable selection/ordering/drag semantics, keyboard/focus, Audited Working mutation and truthful notifications. No shadow page-local visual grammar.

**Public:** inspect canonical route, SiteSection/publication eligibility, shared menu and paper/frame, responsive content shell, image/video rights, working Preview versus Committed/LIVE, and exact accepted presentation. Navigation changes must not create duplicate DOM ownership or jump; responsive bugs require layout ownership fixes rather than timers or magic sizes.

## Finish and handoff

Re-read fresh `dev` HEAD and claimed worker scope before writing; use safe non-force expected-head commit and reconcile parallel Dev workers. Record the exact source change and verification class (**inspected**, **executed**, **browser reviewed**, **user accepted**), remaining risks and the test/docs handoff in your **own active worker record**, replacing stale notes rather than appending a diary. Do not claim tests or browser acceptance that were not performed. User-authorized freezes and selective transfers to other branches are separate.
