# Documentation maintenance

This is the Docs branch's reusable working method. The [Docs roster](../areas/docs.md) owns active coordination; individual `docs-N` files own only their **actual assignments and current progress**. This procedure is not another task diary.

## Source and integration cycle

1. Resolve fresh `dev` and `docs` heads. `dev` is the canonical application and currently integrated test source. Source synchronization to `docs` must preserve Docs-owned material and **exclude foreign branches' temporary worker records and ancestry** (see [worker/integration rules](../README.md)). Record a checked source baseline in the specific evidence owner when it matters, not as repetitive prose throughout every contract.
2. On meaningful `dev` changes, find the affected persistent behavior, components, domain boundaries and all consumers. Revise the **existing authoritative product/architecture/operations document** and remove outdated or conflicting wording. Source synchronization is not documentation completion.
3. **Testing and Refactor are independent workstreams.** Normally do not inspect their active branches or treat their provisional state as normative. After the user reports a Testing/Refactor integration into `dev`, or explicitly requests a separate audit, first synchronize the latest `dev` onto `docs`, then review the incorporated source/test changes. Update test strategy, coverage/evidence matrices, architectural composition, and affected contracts when needed. Never infer executed tests from authored tests.
4. When instructed to review other workers' task files, inspect them **on their own branches**. Transfer verified, generally useful findings into the appropriate durable owner. Leave their private assignments and status in their own files, and do not modify another area's roster.
5. Docs-to-dev publishing is **separate and authorized**: build a reviewable **new linear commit on current `dev`** that contains only approved permanent documentation and shared workflows valid for `dev`. Do not take `docs` as a merge parent or import its worker history. Keep the target branch's own `AGENTS.md`, worker roster, application and tests.
6. For each proposed integration, first review `git diff --stat <base> <candidate>` and `git diff --name-status <base> <candidate>`, then run the repository's [transfer boundary guard](../../scripts/check-branch-transfer.php) from a checkout containing both fetched Git commits:

   ```sh
   php scripts/check-branch-transfer.php --base=<full-target-HEAD> --candidate=<full-proposed-commit> --target=dev
   ```

   The checker refuses newly introduced merge/root history, commits that touch branch-local worker/coordination/topics or `AGENTS.md`, and a candidate tree containing another branch's roster/worker files. Use `--target=main` for a separately prepared Main release candidate; `main` must not contain **any** worker/area/topic files. A passing check is **necessary, not sufficient**: a human still reviews documentation ownership and tests, checks that the candidate is based on the latest destination HEAD and authorizes promotion. This tool is presently only on `docs`; it is not an installed enforcement hook on `dev` or `main`.

## Agent-readable source of truth

The shortest useful read path is [the documentation index](../../docs/README.md#fast-path-for-agents-and-maintainers) → one canonical contract → actual code and adjacent consumers. Legacy entrypoints such as `ui-skills.md` remain **links**, not second normative copies. Before shrinking a legacy contract, compare every feature-specific rule against the destination: Home-routing row actions belong to Tables, Gallery file ownership belongs to Media, modal persistence to Dialogs and responsive geometry to Responsive. If a rule cannot be verified as present and consistent, transfer it or flag it explicitly for source review **before** removing the old text.

Check local Markdown destinations and heading fragments, not merely filenames. For a large structural move, preserve old fragment IDs by redirecting or explicitly updating inbound links. Keep exact historical evidence under its source-pinned owner; a current contract should answer the reader's present question without first parsing old dated audits.

## Coherent documents instead of accumulated notes

Start from the **purpose of the whole document**: audience, questions answered, canonical owner, related concepts, reader's navigation and suitable evidence. Explain architecture through meaningful domain/composition, state, persistence, publication, failure/recovery and trust boundaries. UML/sequence/component/flow diagrams should clarify **orchestration across boundaries**; trivial one-function class pictures do not add value. A clear narrative connects design decisions and lower-level references.

**Preserve substance while reducing volume:** Before consolidating or removing a file, inventory its unique accepted requirements, design decisions, source evidence, historical constraints and inbound links. Classify those into (1) current normative behavior, (2) current architecture explanation, (3) stable operating procedure, (4) source-versioned/historical evidence, (5) temporary task/investigation, or (6) unsupported/redundant prose. Move unique evidence to its correct owner **before deletion**, verify destinations/links, and retain history in Git. Never convert a source hypothesis into an accepted requirement or destroy a compatibility constraint because it looks unused.

Prefer **replacing and condensing** old prose to continuously appending commit/SHA narratives. Keep architecture overview, domain specs, test model, deployment method and source inventories distinct but connected. Names and document boundaries must be justified by their content and reader needs, not by creating one new document for every small topic. If a proposed document is merely another index or duplicates the owner, consolidate first.

## Evidence and safety

Differentiate source inspected, extracted graph, authored assertion, actually executed test, browser verification and user acceptance. For current coverage claims use the tests **already on `dev`**, until a curated Testing integration changes them; pin evidence to its actual branch/commit. Generated class imports are not runtime execution, Eloquent relations are not physical PostgreSQL foreign keys, and a code merge is not product approval.

Documentation-only review can use GitHub source. Native Docs inventory/generator or browser work uses the existing Docs worktree only in a separately authorized, isolated batch. Never touch protected Validation, Production, persistent Preview database or CI/Actions without separate authorization. Use expected-head non-forced commits and reconcile concurrent edits.
