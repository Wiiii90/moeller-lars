# Docs Worker 1 — active tasks and decisions

**Identity:** `docs-1`, lease token **1**, active. This conversation was explicitly appointed Docs Worker 1 by the user on 25 October 2026. Shared ownership and other workers are in [Docs coordination](../areas/docs.md). Only this file stores this worker's private running task state.

## Assigned work (ordered by dependencies, not automatically executed on a role greeting)

| Task / user assignment | Status | Current verifiable state / next condition |
| --- | --- | --- |
| Establish the real numbered-worker workflow with free-slot reuse, explicit chat replacement, accurate task memory, shared sign-in/sign-out and retirement | **In progress** | Existing phantom dev/docs/testing worker files were removed; docs-1 now explicitly activated. Verify same-commit sign-in, lease, and removal contract; no worker name alone grants old-task execution. |
| Docs as reviewing/consolidating arm: audit **all** documentation, reduce duplication/temporary remnants, create useful readable information architecture and one cohesive system design | **Open — systemic audit required** | Current docs have 18 root `ADMIN-*` markdown files, a huge Admin/Public contract inventory and many historical generators. User explicitly rejects the previous piecemeal diagrams and a small inserted `ARCHITECTURE.md` overview as substitutes for holistic analysis. Audit the **whole document and its purpose** before writing/restructuring; protect unique accepted facts and citations. |
| Maintain application alignment: regularly bring actual `dev` code onto `docs`, then reconcile **documentation-worthy changes in place** instead of appending SHA/date slices | **In progress** | User expects `docs` close to current `dev`; previous docs code diverged substantially. Current observed `dev@0200407133ce4696817271d2141fceaca82f8aff`; never call historical source diagrams current. Merge/reconcile safely rather than overwriting actively maintained docs or bringing older app code back to dev. |
| Preserve durable knowledge from active workers across Dev/Docs/Testing/Refactor when **instructed to review them**, and make all workers consolidate generally applicable decisions in canonical workflows/contracts | **Ongoing rule** | Individual worker first updates **its own assignments/progress**, shared scope goes to area file, permanent methods to procedure/skill and system behavior to `docs/`. Do not write worker 2's task inside worker 1's private record. |
| Bring up a behavior-preserving Refactor workstream from `dev` (dead code and shared architecture weaknesses, no redesign), coordinate safe later integration | **Branch created; operating lane not yet started** | `refactor` created at exactly `dev@0200407133ce4696817271d2141fceaca82f8aff`; no `refactor-1` worker yet. Recheck `dev` and refactor heads before any future sync. |
| Check current `testing` relationship and protect the active Testing worker | **Investigated; not modified** | `testing@b7eee549b86ac35eaa6a064ac78ef226182c170e` lacked 12 newer `dev` commits when checked; active Testing work owns the integration of new code into its curated tests. Do not overwrite its 21 independent commits or claim tests executed. |

## User-accepted design principles

- Documentation serves real engineers and the user: explain whole-system purpose, orchestrations, data/control flows, trust/transaction/recovery boundaries, alternatives and ownership. Rich diagrams (UML/sequence/state/component/container as appropriate) must illuminate nontrivial relationships across modules; a trivial three-node class/one-function translation is not documentation progress.
- A narrative and explicit navigation map must connect detailed technical evidence to the large-scale architecture. Complete detail is desired **but layered**, well presented and discoverable, not an unedited directory dump. Improve architecture **as one document/system**, not by gluing a random “overview” into it.
- Workflow instruction files replace old `DOCS_SKILL.md` / `DOCS_SKILLS.md` concepts with clearer, actively consolidated [documentation procedure](../procedures/documentation.md) and the small [Docs skill](../skills/documentation/SKILL.md).
- Skills are task entrypoints, worker records are temporary tasks/progress, area files coordinate people and branches, durable product/domain models belong to the existing canonical `docs/` owners. `topics` should only hold genuinely temporary cross-cutting unresolved investigations, not arbitrary “one file per category” classification. Review `rights` on that basis.
- The user wants changes **on docs only** while the worker system is being established. No CI/Actions, Production/Validation writes or unsanctioned test execution.

## Current source and verification

GitHub read-only source/tree/branch comparisons and explicit Git branch creation; no docs-native generator, PHP/test/browser run and no application release. Verify fresh heads before every commit, preserve expected-head fast-forward behavior. Git commit history retains retired/rewritten working files.

At each meaningful milestone update the task table with work completed, current evidence and real blockers; at eventual retirement consolidate lasting knowledge, remove the Docs shared area entry and delete this file together.