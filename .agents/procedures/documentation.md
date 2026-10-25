# Documentation maintenance

This is the Docs branch's reusable working method. The [Docs roster](../areas/docs.md) owns active coordination; individual `docs-N` files own only their **actual assignments and current progress**. This procedure is not another task diary.

## Source and integration cycle

1. Resolve fresh `dev` and `docs` heads. `dev` is the canonical application and currently integrated test source. Source synchronization to `docs` must preserve Docs-owned material and **exclude foreign branches' temporary worker records and ancestry** (see [worker/integration rules](../README.md)). Record a checked source baseline in the specific evidence owner when it matters, not as repetitive prose throughout every contract.
2. On meaningful `dev` changes, find the affected persistent behavior, components, domain boundaries and all consumers. Revise the **existing authoritative product/architecture/operations document** and remove outdated or conflicting wording. Source synchronization is not documentation completion.
3. **Testing and Refactor are independent workstreams.** Normally do not inspect their active branches or treat their provisional state as normative. After the user reports a Testing/Refactor integration into `dev`, or explicitly requests a separate audit, first synchronize the latest `dev` onto `docs`, then review the incorporated source/test changes. Update test strategy, coverage/evidence matrices, architectural composition, and affected contracts when needed. Never infer executed tests from authored tests.
4. When instructed to review other workers' task files, inspect them **on their own branches**. Transfer verified, generally useful findings into the appropriate durable owner. Leave their private assignments and status in their own files, and do not modify another area's roster.
5. Docs-to-dev publishing is **separate and authorized**: construct a filtered, reviewable integration commit containing only permanent documentation/shared workflows valid for `dev`. Preserve `dev` application/source and its own branch-local worker records, and do not import Docs worker commits/history. Before any later dev-to-main promotion enforce the equivalent exclusion of all temporary worker/coordination data. This cannot be implemented safely merely by deleting files ahead of a regular history merge.

## Coherent documents instead of accumulated notes

Start from the **purpose of the whole document**: audience, questions answered, canonical owner, related concepts, reader's navigation and suitable evidence. Explain architecture through meaningful domain/composition, state, persistence, publication, failure/recovery and trust boundaries. UML/sequence/component/flow diagrams should clarify **orchestration across boundaries**; trivial one-function class pictures do not add value. A clear narrative connects design decisions and lower-level references.

**Preserve substance while reducing volume:** Before consolidating or removing a file, inventory its unique accepted requirements, design decisions, source evidence, historical constraints and inbound links. Classify those into (1) current normative behavior, (2) current architecture explanation, (3) stable operating procedure, (4) source-versioned/historical evidence, (5) temporary task/investigation, or (6) unsupported/redundant prose. Move unique evidence to its correct owner **before deletion**, verify destinations/links, and retain history in Git. Never convert a source hypothesis into an accepted requirement or destroy a compatibility constraint because it looks unused.

Prefer **replacing and condensing** old prose to continuously appending commit/SHA narratives. Keep architecture overview, domain specs, test model, deployment method and source inventories distinct but connected. Names and document boundaries must be justified by their content and reader needs, not by creating one new document for every small topic. If a proposed document is merely another index or duplicates the owner, consolidate first.

## Evidence and safety

Differentiate source inspected, extracted graph, authored assertion, actually executed test, browser verification and user acceptance. For current coverage claims use the tests **already on `dev`**, until a curated Testing integration changes them; pin evidence to its actual branch/commit. Generated class imports are not runtime execution, Eloquent relations are not physical PostgreSQL foreign keys, and a code merge is not product approval.

Documentation-only review can use GitHub source. Native Docs inventory/generator or browser work uses the existing Docs worktree only in a separately authorized, isolated batch. Never touch protected Validation, Production, persistent Preview database or CI/Actions without separate authorization. Use expected-head non-forced commits and reconcile concurrent edits.
