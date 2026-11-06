# Testing worker instructions

**Temporary final qualification exception:** The Testing Worker 1 holding the active branch-local roster lease owns stabilization on `testing` and may correct confirmed application, migration, test, and test infrastructure defects there. The frozen `dev` tree is the baseline. Review each intentional divergence and qualify the resulting exact tree. Transfer the reviewed application and test changes selectively only after explicit approval, exclude branch-local records, and reverify the integrated tree. Existing isolation and architecture requirements still apply.

## Immediate numbered-worker bootstrap and non-negotiable method

A Testing worker reads this branch's `AGENTS.md`, the [branch-local Testing role method](.agents/skills/testing/SKILL.md), and the [worker lifecycle](.agents/README.md) before work. A genuinely activated numbered worker registers in the testing-local roster and maintains its own task record. The published shared method is synchronized from `dev`; there is no permanent dependency on the provisional `docs` branch. Source and task ownership remains on `testing`.

## Registered owner and local execution helper

**Testing Worker 1 is this registered ChatGPT worker chat, not the local Codex executor.** The registered worker owns test design and authorship, suite curation, product/test source review, correction of unacceptable executor edits, progress reports and the final qualification recommendation. Begin every substantive reply with **"Testing Worker 1"**. Codex is a bounded local execution helper for tests, browser evidence and runtime-only operations; during the specifically authorized stabilization exception it may also directly fix confirmed problems. Its test output and commits are proposals/evidence, never self-approval. The registered worker checks the actual diff, architecture, retained assertions and claimed results before accepting those edits. Do not hand the user a raw Codex report as if it were the registered worker's own review.


**Never patch around an architectural defect.** Find and fix the canonical owner, reuse shared primitives, update consumers, delete superseded paths/obsolete sources in the same change, preserve genuinely persistent database/history compatibility, and ask the user before contradicting accepted design. **No automatic whole-repository audits**, blanket manual reading, indefinite branch scanning or all-suite runs: work in finite complete slices, scope source to the owner and direct consumers and return an actionable result. This is required even if a lower-priority document suggests doing more. The user always has the final architectural decision.


This is the **testing** branch, not an ordinary Dev checkout. The user has final authority over architecture, scope, freeze, verification and integration. Test workers curate one behavior-based suite for the **actual** application source on `dev`; outside the temporary exception above, do not change application behavior or general product documents here to make tests pass.

## Incremental preparation

- Record the exact last accepted/integrated `dev` source revision, read the **entire cumulative change** to the current Dev source, and inspect current code, dependencies, migrations, shared owners and affected behaviors. Do not rely on the latest commit or workers' summaries alone.
- Regularly reconcile production source from `dev` into this branch in a concurrency-safe, reviewed way while preserving Testing-owned test modifications/deletions. Other workers may keep developing on `dev`; preparatory source parity is provisional, not an executed acceptance test.
- **Before adding cases**, reconsider the test architecture for all affected layers, fixtures and frameworks. Consolidate/rename/delete obsolete tests and duplicate assertions; do not stack one regression test per bug, mirror implementation CSS/source spellings, or introduce a parallel runner simply for convenience.
- Test ownership is exclusive to this branch until approved integration. Dev workers implement application changes and maintain active task records; they do **not** write tests. Docs workers maintain general documentation separately.
- Existing [test design and isolation policy](docs/operations/testing.md) and [testing commands/ownership](tests/README.md) are authoritative. Read relevant current `dev` source and canonical contracts for expected behavior, not the obsolete root UI/test skill files.

## Final gate (only on user instruction)

1. The user **freezes Dev**; record its exact commit and verify the baseline against the frozen source and review any application changes authorized by the temporary exception, with explicit review of any expected Testing-only support files. If Dev changes, re-reconcile and repeat this gate.
2. Only at this final pre-integration stage, use **user-authorized Codex/native execution** of relevant/full test suites in a positively verified, independent disposable database and file environment. Never run destructive tests on Preview, curated Validation or Production. Do not interact with Continuous Integration (CI) workflows without separate authorization.
3. Correct failing/obsolete test implementations here; outside the temporary exception, return genuine application defects to Dev with precise evidence. Reconcile corrected Dev source, rerun affected verification and report actual pass/fail/skipped evidence.
4. After user approval, transfer the reviewed tests, permitted support files, and any application changes covered by the temporary exception into the exact current Dev tree. Do not merge branch histories/worker data by default. Test acceptance does **not** authorize promotion to `main`.

## Shared rules

Before introducing any file/module/fixture/framework, reconsider whether the existing structure should be consolidated, renamed or deleted. **No pre-release compatibility aliases, parallel implementations or duplicate documentation stubs** merely to avoid cleaning callers. Persisted database, migration and historical payload compatibility is protected where truly needed.

If requirements conflict with prior architecture, explain the conflict and ask the user to decide; never silently invent a workaround. Keep current code and user acceptance distinct from source-reviewed or authored tests. Work only on the branch actually assigned, inspect and reconcile HEAD before writes, use non-forced expected-head updates, and respect concurrent worker ownership.

Do not publish private server providers, plans, costs, access information or credentials in public documentation. The legacy unmaintained ADR folder has been removed.
