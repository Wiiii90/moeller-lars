# Docs — shared coordination

**Working branch:** `docs`. Existing Docs worktree is for rare, agreed local/Codex batches; remote documentation/source reviews otherwise. This is a **shared worker roster and conflict coordination**, not a second job diary.

**Next lease token:** 2

| Worker | Lease | State | Current claimed scope | Individual record |
| --- | --- | --- | --- | --- |
| Docs Worker 1 | 1 | **active** | Documentation system/information architecture, source alignment, worker procedures | [docs-1](../workers/docs-1.md) |

When work is paused or completed, update the status/claim here. **On retirement the exiting worker must consolidate durable findings, delete its own worker file and remove its row here in one commit**; free worker numbers may later be reused with a fresh lease. Before any edit to shared docs, another Docs worker checks this table for overlap and obtains a distinct ownership scope.

**Source alignment:** `docs@a9ac270a` imported application/tests/runtime from `dev@02004071` in an actual two-parent merge (source now fully aligned at that snapshot, documentation semantics still being reconciled). The user expects continued frequent review and real `dev` integration, not simply reading a stale branch.

**Area responsibilities:** continually compare current `dev` source with `docs` and review current `testing` when appropriate; maintain accurate, usable, **cohesively designed** technical architecture; keep one owner per stable decision; eliminate misleading historical/temporary claims and redundant contracts after careful source/reference review. New facts should be integrated into the relevant existing document, not dropped as date-stamped fragments. [Documentation method](../procedures/documentation.md) owns the stable method, [Docs index](../../docs/README.md) identifies current content.

**Sources:** Current `dev` is the implementation baseline imported into `docs`; current `testing` is the independently curated verification/test-architecture evidence. Docs keeps both in view and consolidates accepted changes into the owning system/feature/test documents. A Testing-only code diff is not automatically a product change and is not merged into Docs without ownership review.

**Current cross-area dependencies:** active application development on [Dev](dev.md); active curated test cleanup on [Testing](testing.md); newly created [Refactor](refactor.md) from current `dev` at branch creation. The [rights workstream evidence](../topics/rights.md) remains temporary, pending classification against the [durable rights contract](../../docs/PUBLIC-RIGHTS-AND-PRIVACY.md). Do not copy other workers' task states into this shared file; use references and only the relevant external dependency.

Worker registration, takeover, consolidation and retirement: [single protocol](../README.md).