# Testing — shared coordination

**Working branch:** `testing` is active and separately curated toward a later controlled integration into `dev`. The previous integration of a Testing iteration did **not** retire this branch. Before work, verify current `testing` and `dev` heads and protect the actual Testing worker's existing changes.

**Next lease token:** 1

| Worker | Lease | State | Current owned scope | Individual record |
| --- | --- | --- | --- | --- |
| *(none activated under this prototype)* | | | | |

The existing Testing chat is running **outside the new numbered-worker system**; it has not yet been assigned a numbered identity. Do not invent its private task state or reserve a number for it. After the user activates a Testing Worker, allocate the lowest free positive ID and read that worker's real conversation/task where available.

Maintain the canonical, consolidated tests on `testing` while newer `dev` changes are reconciled; remove redundant transient tests, preserve real behavioral coverage and verify any execution only in approved disposable environments. Parallel workers register disjoint files or serialize shared edits. Long-term rules: [Testing procedure](../procedures/testing.md), [test strategy](../../docs/TESTING.md).

Worker allocation and retirement: [single protocol](../README.md).
