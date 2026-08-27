# Admin browser acceptance discipline

This document defines the durable browser-review and visual-repair rules for the authenticated artist admin.

It exists because a source-coherent implementation can still be an unacceptable product candidate. Static review, passing focused tests and successful container startup are necessary evidence for some changes, but none of them proves visual consistency or editorial quality.

## 1. Acceptance states are separate

Use these states deliberately:

- **source reviewed / source coherent** — the code, contracts and diff were inspected;
- **runtime verified** — a concrete runtime behavior was actually exercised;
- **browser reviewed** — the current built candidate was inspected in the browser;
- **browser/product accepted** — the user accepted the current presentation and behavior;
- **release qualified** — the release workflow and canonical verification for promotion were completed.

Do not call a visual worker result `accepted` without saying which state is meant. A worker handoff cannot establish browser/product acceptance by itself.

## 2. Current browser feedback beats stale presentation contracts

For the candidate currently under review, the user's browser observation is the primary presentation requirement.

If current feedback rejects a layout, card structure, width, typography, metrics treatment, toolbar, table geometry or wording, do not preserve that rejected presentation merely because:

- it already exists in the branch;
- a previous worker described it as accepted;
- a static test asserts its markup;
- an old issue or prompt specified it;
- changing it would create a larger diff.

Preserve valid domain behavior, persistence, safety guards, routing, publication rules and shared technologies. Presentation is allowed to be replaced when the current presentation itself is the defect.

## 3. Reference-first visual work

When the user identifies another current admin surface as the visual reference, the worker must inspect that exact implementation at the shared base before changing the target page.

The reference is authoritative for the dimensions the user named, for example:

- content width;
- page heading placement;
- metric-strip geometry;
- control labels and control heights;
- table header/row geometry;
- checkbox, drag, position and action columns;
- typography;
- spacing;
- empty/add-row treatment.

The worker should reuse existing shared Blade primitives, tokens and CSS families wherever possible. Do not translate the reference into a loose prose approximation and then invent a new local implementation.

If the task surface genuinely differs, preserve that task model while reusing the shared shell and geometry. Consistency does not mean turning every page into the same data surface.

## 4. No new local design language by default

Do not create a page-specific parallel design system merely to complete one repair.

Before adding page-local UI structure or CSS, check whether the same need is already solved by:

- `x-admin.workspace`;
- `x-admin.metrics` / `x-admin.metric`;
- shared section/table/toolbar/empty-state primitives;
- existing admin control tokens;
- an already accepted/current reference page.

New feature-local CSS is appropriate only for genuinely feature-specific layout that cannot be expressed by the shared grammar.

Warnings that require extra review:

- a new page-specific shell width;
- another card/panel family;
- another toolbar grammar;
- another table header/row system;
- another metric-card implementation;
- repeated pixel patches that reproduce a shared geometry already present elsewhere.

## 5. Presentation reset rule

When a page has survived repeated repair passes while retaining the same rejected visual structure, stop layering patches onto it.

Re-audit in this order:

1. current user feedback;
2. user-designated reference surface(s);
3. `ui-skills.md`;
4. shared Blade/CSS primitives;
5. target page's required domain behavior and interactions.

Then rebuild the presentation layer around those authorities.

Do not delete or rewrite domain services, persistence rules or safety guards just because the UI is being reset. Conversely, do not preserve rejected Blade/CSS merely because the underlying domain code is valuable.

## 6. Prompt requirements for visual repair workers

A visual-repair prompt must state:

- exact repository/base SHA;
- current candidate and whether it is merely source/runtime reviewed or browser accepted;
- the complete browser findings for the current review slice;
- any user-designated reference pages;
- which domain/runtime behavior must be preserved;
- which rejected presentation is explicitly **not** a preservation requirement;
- the shared primitives/technologies that must be reused;
- narrow checks required before handoff;
- explicit instruction that the worker cannot self-declare visual acceptance.

Do not over-specify unreviewed pixel geometry or markup from the broken candidate as a contract. If the user says a page should match a reference, require the worker to inspect the reference code instead of describing a new design from scratch.

## 7. Review batching

Review one coherent browser slice at a time.

For each slice:

1. let the user inspect the current candidate;
2. collect the complete set of findings;
3. do not launch a worker after the first complaint unless the user explicitly says the slice is complete;
4. classify the complete findings;
5. create one coherent repair scope;
6. review the returned source independently;
7. rebuild only when the intended set of fixes for that cycle is ready.

This avoids spending build/review time on temporary intermediate states.

## 8. Verification during browser iteration

Use the narrowest evidence that proves the changed behavior.

Do not automatically run broad suites, rebuild Docker or reinstall dependencies for every visual change. Focused runtime checks are appropriate when a concrete integration defect was found.

Tests created during browser work should represent durable product/domain behavior. Avoid durable test names tied to repair rounds, branch names, browser-pass labels or candidate chronology when a stable behavior-based name is available.

Do not turn an intermediate browser candidate into a broad regression contract before the presentation is actually accepted.

## 9. Reconciliation discipline

A source-reconciliation pass proves only that accepted source diffs were combined coherently. It does not prove that the combined presentation is good.

After reconciliation:

- verify functional cross-branch consequences narrowly;
- build one combined candidate;
- return to browser review;
- treat browser rejection as new authoritative input;
- do not defend the current UI because multiple workers or static reviews previously approved their own slices.

If several workers each introduced their own local visual grammar, the correct reconciliation may be to replace those presentation forks with the shared/reference implementation rather than merging every visual detail into a larger union.

## 10. Follow-up handoff requirements

A continuation prompt for an active browser review must carry:

- exact current Git/runtime candidate;
- source/runtime/browser acceptance states separately;
- browser findings already collected;
- rejected presentation patterns that must not be preserved;
- user-designated visual references;
- the remaining review queue;
- an explicit instruction to collect the complete feedback for the current slice before creating a worker prompt.

Transient candidate SHAs and current review findings belong in the follow-up prompt. The general rules in this document are durable and should not be duplicated as changing issue history.
