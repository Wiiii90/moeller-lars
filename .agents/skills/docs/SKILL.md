---
name: docs
description: Bounded source-verified documentation, agent workflow, consolidation, source audit and release-gated verification for the Docs branch.
---

# Docs worker — documentation method

**Trigger:** Authoring, auditing, restructuring, deleting or generating project documentation, diagrams, agent rules or source-derived references.

**Required entry:** Read this branch's [AGENTS](../../../AGENTS.md), [worker protocol](../../README.md), [active Docs roster](../../roster.md) and, if assigned, that worker's [current record](../../workers/docs-1.md). Then open the [reader-oriented docs index](../../../docs/README.md), choose only the relevant **single authoritative contract**, and follow its source owners and cross-feature consumers on the actual current `dev` revision. Do not consume the entire documentation library unconditionally; follow the question. Limit the first read to the owner and immediate consumers and finish an actionable slice before expanding.

**Select by topic:** Admin workspace → `docs/admin/ui.md` and the relevant Control/Table/Dialog/Responsive owner; Public routes, Home, Artwork and Journal → `docs/public/site.md`; media and file lifetime → `docs/media.md`; Rich Text → `docs/content/rich-text.md`; Publication and Undo → `docs/admin/activity-publication.md`; migrations → `docs/migration/invariants.md`; release/Validation → `docs/operations/release.md`. Deep source inventories are for investigation only and cannot override implemented code or user acceptance.

## Bounded investigation — mandatory for every worker

**No unlimited audits or indefinite work loops.** The default is an explicitly bounded, evidence-driven slice, not a recursive search of all repository files. Decide the concrete question and its owners first; inspect one entrypoint, its central owner and direct consumers, then make a useful coherent change. Work in **small finite batches** and report the evidence and the unexamined remainder; a broad request to "clean up" never authorizes scanning the entire repository automatically. No repeatedly fetching the same files or scanning all 50–100 manuals "to be thorough." Only perform a repository-wide audit, full static inventory, source generator or full test sweep when the user **specifically authorizes that scope**, or during the separately authorized final pre-integration gate. If the available scope is exhausted, finish the current batch and record the next precise slice. This is a **hard stop rule**, not a suggestion.

Do not mistake a slow investigation for progress. Prioritize an answer and a concrete changed artifact over retrospective inventories, branch-status chatter, alias constructions or speculative test matrices. Before any potentially long command, check whether the existing file/owner and a targeted search can answer the question; use the least expensive one. A source review is not a run.

## Working method

This is the Docs branch's reusable working method. The [Docs roster](../../roster.md) owns active coordination; individual `docs-N` files own only their **actual assignments and current progress**. This procedure is not another task diary.

## Authority and structure

The user is the ultimate product and architecture decision-maker. Describe `dev` code as the **currently implemented, provisionally intended** behavior; do not replace its semantics with speculative historical documentation. If user feedback conflicts with code or an existing rule, identify both and follow the user's current decision. If a proposed implementation conflicts with the architecture, state the conflict and ask the user to resolve it before accepting an unapproved deviation.

Before introducing any document, index, generated artifact or diagram, review the existing structure **as a whole** and assess which older owner should be deleted, renamed or consolidated instead. Favor a smaller, coherent tree; remove obsolete targets and correct all incoming references atomically. A pre-release redirect, compatibility alias, extra entrypoint or duplicated archived report is not an acceptable compromise. **Only preserve proven persistent database/snapshot/migration compatibility**, or another exception explicitly authorized by the user.

The documentation branch is the owner of general contracts, architectural guides and source-derived inventories. Dev feature workers maintain their **active living workstreams** and describe changes in handoffs, not routine general documentation or tests; Docs reconciles batches of accepted Dev commits without making the Dev workers repeat authoring work.

## Source and integration cycle

1. Resolve fresh `dev` and `docs` heads. `dev` is the canonical application and currently integrated test source. Source synchronization to `docs` must preserve Docs-owned material and **exclude foreign branches' temporary worker records and ancestry** (see [worker/integration rules](../../README.md)). Record a checked source baseline in the specific evidence owner when it matters, not as repetitive prose throughout every contract.
2. On meaningful `dev` changes, find the affected persistent behavior, components, domain boundaries and all consumers. Revise the **existing authoritative product/architecture/operations document** and remove outdated or conflicting wording. Source synchronization is not documentation completion.
3. **Testing and Refactor are independent workstreams.** Normally do not inspect their active branches or treat their provisional state as normative. After the user reports a Testing/Refactor integration into `dev`, or explicitly requests a separate audit, first synchronize the latest `dev` onto `docs`, then review the incorporated source/test changes. Update test strategy, coverage/evidence matrices, architectural composition, and affected contracts when needed. Never infer executed tests from authored tests.
4. When instructed to review other workers' task files, inspect them **on their own branches**. Transfer verified, generally useful findings into the appropriate durable owner. Leave their private assignments and status in their own files, and do not modify another area's roster.
5. Docs-to-dev publishing is **separate and authorized**: build a reviewable **new linear commit on current `dev`** that contains only approved permanent documentation and shared workflows valid for `dev`. Do not take `docs` as a merge parent or import its worker history. Keep the target branch's own `AGENTS.md`, worker roster, application and tests.
6. For each proposed integration, first review `git diff --stat <base> <candidate>` and `git diff --name-status <base> <candidate>`, then run the repository's [transfer boundary guard](../../../scripts/check-branch-transfer.php) from a checkout containing both fetched Git commits:

   ```sh
   php scripts/check-branch-transfer.php --base=<full-target-HEAD> --candidate=<full-proposed-commit> --target=dev
   ```

   The checker refuses newly introduced merge/root history, commits that touch branch-local worker/coordination/topics or `AGENTS.md`, and a candidate tree containing another branch's roster/worker files. Use `--target=main` for a separately prepared Main release candidate; `main` must not contain **any** worker/area/topic files. A passing check is **necessary, not sufficient**: a human still reviews documentation ownership and tests, checks that the candidate is based on the latest destination HEAD and authorizes promotion. This tool is presently only on `docs`; it is not an installed enforcement hook on `dev` or `main`.

## Agent-readable source of truth

The shortest useful read path is [the documentation index](../../../docs/README.md#fast-path-for-agents-and-maintainers) → one canonical contract → actual code and adjacent consumers. Obsolete entrypoints and duplicated manuals must be **deleted outright** after their unique current substance reaches its owner. Do not keep redirect stubs, rename-only aliases, compatibility gateways, dated source-audit dump files or second skill manuals. Update incoming paths, labels and links atomically with the deletion; historical versions are available in Git. Before shrinking a legacy contract, compare every feature-specific rule against the destination: Home-routing row actions belong to Tables, Gallery file ownership belongs to Media, modal persistence to Dialogs and responsive geometry to Responsive. If a rule cannot be verified as present and consistent, transfer it or flag it explicitly for source review **before** removing the old text.

Check local Markdown destinations and heading fragments, not merely filenames. For a large structural move, **update all inbound links**; do not retain retired fragments as aliases. Keep exact historical evidence under its source-pinned owner; a current contract should answer the reader's present question without first parsing old dated audits.

## Coherent documents instead of accumulated notes

Start from the **purpose of the whole document**: audience, questions answered, canonical owner, related concepts, reader's navigation and suitable evidence. Explain architecture through meaningful domain/composition, state, persistence, publication, failure/recovery and trust boundaries. UML/sequence/component/flow diagrams should clarify **orchestration across boundaries**; trivial one-function class pictures do not add value. A clear narrative connects design decisions and lower-level references.

**Preserve substance while reducing volume:** Before consolidating or removing a file, inventory its unique accepted requirements, design decisions, source evidence, historical constraints and inbound links. Classify those into (1) current normative behavior, (2) current architecture explanation, (3) stable operating procedure, (4) source-versioned/historical evidence, (5) temporary task/investigation, or (6) unsupported/redundant prose. Move unique evidence to its correct owner **before deletion**, verify destinations/links, and retain history in Git. Never convert a source hypothesis into an accepted requirement or destroy a compatibility constraint because it looks unused.

Prefer **replacing and condensing** old prose to continuously appending commit/SHA narratives. Keep architecture overview, domain specs, test model, deployment method and source inventories distinct but connected. Names and document boundaries must be justified by their content and reader needs, not by creating one new document for every small topic. If a proposed document is merely another index or duplicates the owner, consolidate first.

## Preparation versus final transfer

Work on Docs may overlap active Dev work. A source sync is preparatory; it does not mean the docs are current, generated inventories match the source, or any transfer has been approved. Track last reconciled Dev commit and review **all subsequent source changes**, including test updates accepted from Testing, cross-boundary impacts and existing generated inventory inputs.

After the user freezes the integrated target revision and authorizes a Docs transfer, reconcile the **entire** current source; run relevant content/links/fragment checks and regenerate **only still-useful retained derived documentation** against the exact frozen source, using Codex or the existing native generators in the authorized isolated Docs checkout when required. Review diffs, provenance and outputs; delete stale duplicate generated files rather than maintaining compatibility copies. This is the **final pre-transfer batch**, not a standing generator loop after each Dev commit. If new source changes appear, reconcile and repeat affected validation. Do not transfer worker rosters/records or overwrite the destination's `AGENTS.md`.

## Documentation verification gate

For any structural or link-affecting documentation commit:

1. Read the fresh branch HEAD, own lease and claimed paths. Review the **complete net diff** for deletions of unique normative requirements, new duplicate owners, stale source claims and unexpected source/code changes.
2. Resolve every changed local Markdown destination **and heading fragment**. The repository's canonical native check is:

   ```sh
   php scripts/check-documentation-links.php --self-test
   php scripts/check-documentation-links.php --check
   git diff --check
   ```

   Run these **only in an authorized isolated worktree** with PHP/Git and a coherent checkout. A remote GitHub inventory can inspect tracked paths and selected anchors, but it is not a native PHP run: report the exact remote coverage and missing blind spots rather than claiming `--check` passed.
3. When updating a reference to historical evidence, retain its exact source revision and verification classification; if its old destination was deleted, point directly to that Git revision or remove an obsolete link. **Do not** create redirect documents or fake renamed test equivalents.
4. Before a guarded commit, re-read the latest `docs` head and worker lease; create one linear commit on the checked tree and move the ref with an expected-head lease, never force. Afterward verify the actual branch head and affected files.

Documentation structure is accepted only when a newcomer can find the owner without reading obsolete parallel versions **and** the reader can still find any unique evidence that was moved.

## Evidence and safety

Differentiate source inspected, extracted graph, authored assertion, actually executed test, browser verification and user acceptance. For current coverage claims use the tests **already on `dev`**, until a curated Testing integration changes them; pin evidence to its actual branch/commit. Generated class imports are not runtime execution, Eloquent relations are not physical PostgreSQL foreign keys, and a code merge is not product approval.

Documentation-only review can use GitHub source. Native Docs inventory/generator or browser work uses the existing Docs worktree only in a separately authorized, isolated batch. Never touch protected Validation, Production, persistent Preview database or CI/Actions without separate authorization. Use expected-head non-forced commits and reconcile concurrent edits.
