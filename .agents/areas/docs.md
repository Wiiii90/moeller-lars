# Docs — shared coordination

**Working branch:** `docs`. The existing Docs worktree may be used for separately agreed local/Codex batches. Routine review and documentation reconciliation can use remote GitHub source. This coordination file persists without requiring an activated numbered worker.

**Next lease token:** 1

| Worker | Lease | State | Current owned scope | Individual record |
| --- | --- | --- | --- | --- |
| *(none activated under this prototype)* | | | | |

**Area-wide responsibilities:** Docs is the **review and consolidation arm** of the project: identify documentation-worthy changes from current `dev` (and current `testing` when relevant), keep architecture and user-accepted behavior accurately documented, identify contradictions and duplicated owners, and continuously retire stale or purely temporary explanatory material. Prefer **one short, navigable design/architecture entry** with deep reference documents over a flat accumulation of contracts and post-hoc source audits.

**Current area-wide issue (not an individual worker's task):** audit the information architecture of `docs/`, in particular `ARCHITECTURE.md` versus `architecture/`, the large Admin/Public specifications, temporary evidence and generated diagrams. Classify each document as normative, design/architecture, operational procedure, historical evidence or removable duplicate **before** moving/deleting content. Keep unique source-backed detail until it is safely consolidated. [Documentation procedure](../procedures/documentation.md) defines the review method; [documentation index](../../docs/README.md) lists existing canonical owners.

**Cross-area context:** implementation on [Dev](dev.md), curated tests on [Testing](testing.md), and a possible future [Refactor](refactor.md) lane. The rights/media topic is retained as [temporary evidence](../topics/rights.md); verified enduring rules belong to [rights and privacy](../../docs/PUBLIC-RIGHTS-AND-PRIVACY.md). This prototype is not yet integrated into direct Dev or Testing chats.

Worker allocation and retirement: [single protocol](../README.md).
