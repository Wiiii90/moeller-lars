# Testing — shared coordination

**Working branch:** `testing` is active and separately curated toward a later controlled integration into `dev`. The previous integration of a Testing iteration did **not** retire this branch. Before work, verify current `testing` and `dev` heads and protect the actual Testing worker's existing changes.

**Next lease token:** 1

| Worker | Lease | State | Current owned scope | Individual record |
| --- | --- | --- | --- | --- |
| *(none activated under this prototype)* | | | | |

**Cross-area source gap observed 25 October 2026:** `testing@b7eee549b86ac35eaa6a064ac78ef226182c170e` was 12 commits behind `dev@0200407133ce4696817271d2141fceaca82f8aff`, while 21 independently curated Testing commits were ahead. The active Testing worker must reconcile the newer `dev` changes into its maintained tests without losing existing work; this Docs review did **not** merge into or execute tests on `testing`. Refresh heads before action. This is a shared dependency, not the private task state of a numbered Testing Worker.

The existing Testing chat is running **outside the new numbered-worker system**; it has not yet been assigned a numbered identity. Do not invent its private task state or reserve a number for it. After the user activates a Testing Worker, allocate the lowest free positive ID and read that worker's real conversation/task where available.

Maintain the canonical, consolidated tests on `testing` while newer `dev` changes are reconciled; remove redundant transient tests, preserve real behavioral coverage and verify any execution only in approved disposable environments. Parallel workers register disjoint files or serialize shared edits. Long-term rules: [Testing procedure](../procedures/testing.md), [test strategy](../../docs/TESTING.md).

Worker allocation and retirement: [single protocol](../README.md).
