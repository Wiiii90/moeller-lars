---
name: documentation
description: Maintain source-verified architecture and concise canonical documentation for the long-lived docs branch of moeller-lars, with controlled dev merges and explicit verification boundaries.
---

# Documentation workstream

## Start and ownership

1. Read repository [AGENTS.md](../../../AGENTS.md), then [DOCS_SKILL.md](../../../DOCS_SKILL.md) **in full**. The root DOCS_SKILL is the changing work state; this file is the stable method. Read [docs/README.md](../../../docs/README.md) for current normative document owners and [architecture/README.md](../../../docs/architecture/README.md) for the evidence catalog.
2. Fetch fresh remote HEADs for **`docs`, `dev` and `testing`** at the start of work. Write documentation, inventories and skills only to **`docs`**. `dev` is the implementation/contract authority; `testing` is a separately owned **read-only test-design and assertion-evidence source**. Inspect relevant tests and `tests/Pest.php`/test discovery on both branches before assessing coverage; always record independent SHAs. Do not write to either branch or create branches/worktrees.
3. Triage changed `dev` paths periodically rather than chasing every commit. Only import coherent relevant batches via a real conflict-reviewed **two-parent merge** on `docs`, with expected-HEAD guard and no force-push. **Never merge `testing` into `docs`** to obtain tests: branch histories, application code, fixtures and discovery can diverge. Never merge `docs` to `dev`/`main` without an explicit decision.
4. **No CI interaction** whatsoever: do not dispatch, inspect, poll, re-run, cancel, read reports/statuses, or edit workflows. `testing` assertions/fixtures may be **read and compared**, but never written, executed or reported as passing by this docs workstream. Standalone safe source checks are permitted; never touch persistent Preview/Production data.

## Source evidence before claims

- Prefer the real current PHP/Blade/CSS/JS, relevant migrations, API contracts and verified browser feedback to superseded descriptions. Pin a source SHA in each generated inventory. Use direct file links/line anchors wherever practical; identify the owner class/method, not just a filename.
- **Separate evidence types and branch provenance:** `dev` application/contract source; `testing` or `dev` authored test assertions/fixtures with their own SHA; parser fixtures; executed tests against a compatible application SHA; real browser acceptance; physical migrated PostgreSQL catalog. Test filenames/file counts are not case counts and authored assertions are not executions. Never infer runtime verification or cross-branch compatibility from reading sources alone.
- An import, use-declaration or constructor type is **not a runtime call**. An Eloquent method is **not a physical foreign key**. A proposed state transition is **not a proven temporal/scheduler transition**. Laravel discovery, DI, Livewire and JS events require their own traced entrance points.
- Domain/lifecycle diagrams must show guards, no-op paths, error/rollback handling, indirect mutations and whether state is persisted or derived. Public Working/LIVE reads, History payloads, JSON component media references and release/cutover environment boundaries are separate models.
- For SQL architecture: only a **fully migrated, explicitly disposable isolated PostgreSQL database** may be used to query real `pg_catalog`. Read-only schema exporter may run there; do not `migrate:fresh`, reset or mutate a live/preview application database.
- A source generator must clearly declare its coverage (parsed syntax, dynamic exclusions, skipped files and expected source SHA) and its exact outputs. Generated files are derived, not hand-maintained. Execute `--write` then `--check` **only if a real checkout and safe local runtime exist**; otherwise build a labeled source audit and keep the native check open.

## Editing and de-duplication

- **Normative application behavior has one documented owner.** Before editing a paragraph, search related contracts, inspect its actual code owner and replace obsolete assertions in the owning document. In other files use links instead of copying the same policy.
- Do not transplant historical v0.3 acceptance into the actively evolving v0.4 candidate; exact browser-approved SHA and explicit user decision control acceptance. v0.5 cutover is planned but contingent on validation and platform readiness.
- Keep legacy/import compatibility, live runtime, schema-hash-only artifacts and deployment operations distinct; never delete a migration/model/view simply because current UI no longer exposes it.
- Avoid creating generic new UI rules. For accepted admin UI, consult the actual [ui-skills.md](../../../ui-skills.md), current per-topic contracts and accepted code before any normative rewrite. The separate Public UI grammar must likewise be derived from actual source and accepted feedback, not invented from admin CSS.
- Prefer smaller task-specific documents, brief technical explanations, source-linked tables and code-backed Mermaid. Avoid repetition, chronological worker diaries, rejected browser candidates as current truth, decorative diagrams and brittle pixel/HTML-class assertions.
- Remove/replace obsolete files **only** after reference search and proving their contents superseded. New generated diagrams live under `docs/architecture/generated/` and should be indexed, not merely dumped into the root.

## End of each working unit

1. Update the owning documents, architecture index and **DOCS_SKILL.md in the same commit or explicitly paired commit** with the new findings, source versions, actual verification and next concrete step. Retire stale checkpoint claims instead of endlessly stacking contradictory statuses.
2. Re-read remote `docs` HEAD before updating refs; reconcile concurrent commits and `dev` merges.
3. For changed documentation links, use the local, read-only `php scripts/check-documentation-links.php --check` when a full checkout exists; it validates ordinary Markdown paths and heading fragments, not HTML/MDX/dynamic destinations. Do not claim this full-repo command ran if only GitHub source/tree comparisons or isolated fixtures were available.
4. Report committed paths/SHA, what is proved, what is still not tested and any distinct product defect uncovered. Do not silently “fix” a runtime discrepancy on the documentation branch.
5. A new chat resumes from `DOCS_SKILL.md` rather than repeating a full repository audit.

## Current architecture index

See [docs/architecture/README.md](../../../docs/architecture/README.md) for the list of source inventories: all 248 PHP types, typed relations, 20 import-class diagrams, Eloquent relationships, UI composition, JSON content, Request/Publication flows and working operations. Those inventories do **not** imply a completed full-checkout PHP run or an executed PostgreSQL schema export.
