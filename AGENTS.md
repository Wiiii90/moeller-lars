# Testing worker instructions

This is the **testing** branch, not an ordinary Dev checkout. The user has final authority over architecture, scope, freeze, verification and integration. Test workers curate one behavior-based suite for the **actual** application source on `dev`; do not change application behavior or general product documents here to make tests pass.

## Incremental preparation

- Record the exact last accepted/integrated `dev` source revision, read the **entire cumulative change** to the current Dev source, and inspect current code, dependencies, migrations, shared owners and affected behaviors. Do not rely on the latest commit or workers' summaries alone.
- Regularly reconcile production source from `dev` into this branch in a concurrency-safe, reviewed way while preserving Testing-owned test modifications/deletions. Other workers may keep developing on `dev`; preparatory source parity is provisional, not an executed acceptance test.
- **Before adding cases**, reconsider the test architecture for all affected layers, fixtures and frameworks. Consolidate/rename/delete obsolete tests and duplicate assertions; do not stack one regression test per bug, mirror implementation CSS/source spellings, or introduce a parallel runner simply for convenience.
- Test ownership is exclusive to this branch until approved integration. Dev workers implement application changes and maintain active task records; they do **not** write tests. Docs workers maintain general documentation separately.
- Existing [test design and isolation policy](docs/TESTING.md) and [testing commands/ownership](tests/README.md) are authoritative. Read relevant current `dev` source and canonical contracts for expected behavior, not the obsolete root UI/test skill files.

## Final gate (only on user instruction)

1. The user **freezes Dev**; record its exact commit and verify this branch's **non-test source tree is equivalent** to that frozen source, with explicit review of any expected Testing-only support files. If Dev changes, re-reconcile and repeat this gate.
2. Only at this final pre-integration stage, use **user-authorized Codex/native execution** of relevant/full test suites in a positively verified, independent disposable database and file environment. Never run destructive tests on Preview, curated Validation or Production. Do not interact with Continuous Integration (CI) workflows without separate authorization.
3. Correct failing/obsolete test implementations here; when the application is genuinely wrong, return a precise finding to Dev rather than bending assertions. Reconcile corrected Dev source, rerun affected verification and report actual pass/fail/skipped evidence.
4. After user approval, transfer only the reviewed tests and permitted support files into the exact current Dev tree. Do not merge branch histories/worker data by default. Test acceptance does **not** authorize promotion to `main`.

## Shared rules

Before introducing any file/module/fixture/framework, reconsider whether the existing structure should be consolidated, renamed or deleted. **No pre-release compatibility aliases, parallel implementations or duplicate documentation stubs** merely to avoid cleaning callers. Persisted database, migration and historical payload compatibility is protected where truly needed.

If requirements conflict with prior architecture, explain the conflict and ask the user to decide; never silently invent a workaround. Keep current code and user acceptance distinct from source-reviewed or authored tests. Work only on the branch actually assigned, inspect and reconcile HEAD before writes, use non-forced expected-head updates, and respect concurrent worker ownership.

Do not publish private server providers, plans, costs, access information or credentials in public documentation. The legacy unmaintained ADR folder has been removed.
