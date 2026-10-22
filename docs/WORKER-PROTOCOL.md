# Worker startup, regression preservation and living-document ownership

This is the **operating index** for new workers and chat continuations. It does not replace the binding root `AGENTS.md`, the currently accepted Admin UI contract `ui-skills.md`, project-specific Product contracts, or the test/environment isolation rules. Read source at the **current** branch HEAD, not at the illustrative source SHA `dev@74e629274ac2`.

## Quick worker startup

A normal new task can be stated as:

> Du bist devworker 1. Arbeite direkt auf dev. Aufgabe: [konkrete Änderung]. Betroffene Oberfläche: [Bereich]. Halte alle bisher akzeptierten gemeinsamen Verhaltensweisen ein; prüfe Quell-Owner und Auswirkungen.

For a read-only review, say `devreviewer` plus the specific scope. For a documentation-only task, say `docsworker`; it writes exclusively to the existing `docs` branch. **Worker numbers are coordination labels, not branches, remote agents, scheduled tasks or permission to create worktrees.** A worker without a concrete task should first inspect the repository's current work instructions and then ask only for an actually missing assignment, rather than guessing an implementation.

For an implementation worker:
1. Read current `AGENTS.md`, current `dev` HEAD and the exact requested files. Consult `ui-skills.md` for Admin/visual tasks; `followup-skill.md` for resumed chats; `TESTING_SKILL.md` and `tests/README.md` only when test work is needed; normative `docs/README.md` and the [architecture composition index](architecture/cross-feature-compositions.md) for touched cross-cutting owners. Do not re-read the entire repository or previous chat transcript.
2. Identify **one primary change owner**, impacted consumers, current accepted vs rejected behaviors, and any runtime/data risks. Use the [cross-feature mapping](architecture/cross-feature-compositions.md#4-regression-risk-seams-and-evidence). New project-wide behavior belongs at the canonical service/shared primitive, not in one page-local workaround.
3. Before changing a shared owner, list concrete affected consumers. If the requested scope does not include a behavior those consumers depend on, **preserve it**. On a scope conflict, report the dependency and stop before including an unrelated cross-feature rewrite. In particular, *reduced code* is not proof of equivalent UI behavior.
4. Implement one coherent slice; compare the net diff to the request, inspect overlapping changes by other workers, remove newly obsolete code only after a reference check, and keep all existing successful behaviors. Source review, authored tests, actual test execution, browser verification and explicit user acceptance are **distinct evidence**.
5. Run only the narrowest safe, relevant checks allowed for that environment. Database/destructive tests require an independently verified disposable PostgreSQL instance. Do not execute, inspect or operate Continuous Integration (CI) under the standing prohibition. The artist's cutover-preparation **Validation** content is never a test database.
6. Immediately before pushing, fetch the actual `dev` HEAD, compare any intervening changes and reconcile overlap. Commit only the agreed paths directly to `dev` using fast-forward and an expected-HEAD guard. Do not create worker branches, reset, force-push, overwrite other chats or touch `main`. Report changed paths, exact commit, actual checks, browser acceptance status and remaining issues.

**Minimal durable handoff for a full chat:** active branch and latest **verified** commit, original user goal and accepted result, one source owner plus consumers, changed files, unfinished risk or failed check, and the next safe executable step. Use `followup-skill.md` as the canonical detailed handoff method. Do not create an ever-growing temporary journal, repeated worker prompts or a new root `WORKER_1_SKILL.md` for each chat.

## Non-regression gate before changing a shared owner

This is the practical application of the mandatory **Cross-feature acceptance preservation gate** in current `dev/AGENTS.md`:

- **Contract:** find which existing source/contract defines the intended interaction. A current accepted browser behavior may not be rewritten by a source-only refactor, a newer doc paragraph or a fragile test.
- **Blast radius:** enumerate all direct and indirect consumers (same component, CSS selector, sorter, field schema, modal lifecycle, media selection, Publication/Working read and frontend JavaScript entrypoint).
- **Before/after:** state the prior accepted behavior as well as the proposed change **for each consumer**. Reject changes that make an unrelated surface worse, even if the one requested page looks correct.
- **Proof:** use tests of lasting domain invariants and, when the change affects gestures/layout/popovers, **real browser review** on the integrated candidate. If browser review is unavailable, mark the result unverified; a unit test cannot certify a drag target or dialog click-away surface.
- **Diff/cleanup:** confirm no unrelated shared owner changed; identify source/tests/styles rendered dead by this particular change and remove only those after locating references. Do not weaken old assertions or rename an unaccepted implementation into the contract.
- **Stop:** on a known previously solved issue resurfacing (Pages root/child placement, General active-tab sizing, Beside title, full-width divider, popup dismissal, published primary media, no-op notifications), consult its **one actual contract and shared implementation owner**, rather than another local CSS/JavaScript patch. Refer to [the impact matrix](architecture/cross-feature-compositions.md#4-regression-risk-seams-and-evidence).

No written rule can absolutely prevent regressions. The enforceable defense is **shared owner + cross-consumer impact review + targeted behavioral verification + artist acceptance**. Avoid brittle tests that assert exact decorative control placement, incidental CSS class names or repair-iteration details.

## Living files: one owner per purpose

| File or directory | Status and responsibility | Organization decision before docs -> dev integration |
| --- | --- | --- |
| `AGENTS.md` on **dev** | Automatic root execution/policy entry, concurrency, hard prohibitions and acceptance preservation | **Keep current dev as canonical.** Incorporate only links to new architecture/worker docs, never replace with older docs-branch AGENTS wholesale. |
| `ui-skills.md` on **dev** | Canonical current Admin UI grammar/accepted interaction patterns | **Keep current dev**, reconcile conflicts against user-accepted behavior and actual shared primitives; docs copy is older. |
| `followup-skill.md` on **dev** | Short continuation/handoff method for exhausted chats | **Keep current dev**, link this worker index; older docs compatibility entry is not a second active worker policy. |
| `TESTING_SKILL.md` on **dev**; `tests/README.md` | One test system, safe isolated runners, current testing handoff | Keep on dev; there is no separate testing branch or need to clone an old testing worktree. |
| `DOCS_SKILL.md` on **docs** | **Temporary living Docs source-audit checkpoint**, SHA/evidence and next batched task | Keep separate while docs-only work continues; do not treat this checkpoint as a product rule or an execution instruction for dev workers. Review consolidation/retirement only after doc integration. |
| `PUBLIC_RIGHTS_WORKSTREAM.md` on **dev** | Still-active, incomplete Rights/Privacy implementation checklist | **Keep dev copy and its open gates**, not the stale docs copy. Retire only when actual rights tests/acceptance and normative docs are confirmed; absence of new writes is not closure. |
| `.agents/skills/{admin-ui,public-ui,documentation,handoff}/SKILL.md` on **docs** | Task-specific reference instructions, not a second `AGENTS.md` | Merge by role and verify links only during an explicitly authorized integration. `dev` currently has no equivalent skill tree; do not silently make historical docs-only claims live development policy. |
| `docs/architecture/**` and other `docs/*.md` on **docs** | Normative contracts, source-versioned architecture and actual verification evidence | These are the substantive docs integration target, with new `dev` Rich Text/Localization/Exhibition contracts reconciled by ownership before merging. |

**Stable vs living:** Product contracts and architecture compositions should survive worker turnover. A living checkpoint contains **current unresolved state and next safe step only**; completed incident narration and obsolete command transcripts belong in Git history. Do not add an indefinitely growing `WORKER_N` file, duplicate test specification or second copy of a shared contract.

## Later controlled docs -> dev integration — not executed by this document

The user has expressed intent to bring the documentation into `dev` **later**. This page is preparation, **not authorization to merge now**. When explicitly requested:

1. Freeze a coherent docs source snapshot and compare current `docs` and `dev` trees/commits at the time of integration. Work on **existing branches only**, under the direct-dev concurrency lease rules. Do not force-push, reset, create branches, run CI or deploy.
2. Treat `dev/AGENTS.md`, `dev/ui-skills.md`, `dev/followup-skill.md`, `dev/TESTING_SKILL.md` and `dev/PUBLIC_RIGHTS_WORKSTREAM.md` as the **current active work rules**, not conflicting disposable duplicates. Diff/resolve changed normative `docs/*.md` files by owner, rather than blindly copying files or using one merge strategy for all.
3. Carry the completed architecture/contract/index documents onto `dev`; preserve older source SHAs and native generator evidence as **historical snapshots**. New `dev` product files/migrations/tests must not be overwritten with the older code tree embedded in the docs branch.
4. For artifacts that derive from actual application code, a fresh complete-checkout generation/validation is a **separate, rare, expressly approved Codex batch**, not part of routine source-documentation commits. It must use the actual checkout SHA and must never use Validation data.
5. Review root/skill references, product-contract ownership and regression gates after conflict resolution; then and only then make a normal controlled fast-forward commit on `dev`. Independent user/browser/release acceptance remains separately required.

Until then: **docs writes exclusively to `docs`; dev implementation and the only tests live on `dev`**. New Docs workers normally use the GitHub connection. `P:\moeller-lars-docs` is reserved for infrequent authorized Codex bulk checks; never instruct the user to run local Docs commands after every edit.
