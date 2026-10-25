# Docs — coordination on the docs branch

**Next lease token:** 2

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Docs Worker 1 | 1 | active | Project documentation structure, consolidation and docs workflows | [docs-1](../workers/docs-1.md) |

All Docs worker activations, changes of owner, pauses, replacements and retirements must update **this roster on `docs`**. A retiring worker signs out and deletes its own numbered record after transferring any lasting decisions or unfinished tasks to the right owner. Follow the [branch-local worker protocol](../README.md). Other branches own their **own** coordination; there are no remote Dev/Testing/Refactor rosters here.

**Docs mission:** review implementation changes already integrated into `dev` and keep the permanent project documentation clear, complete, useful and nonredundant. Import the actual current `dev` source to Docs with explicit file/history boundaries, then reconcile changed system behavior and accepted decisions into existing documents rather than appending status prose. When Testing or Refactor changes are **returned to `dev`**, inspect the resulting `dev` delta for required updates to test strategy, architecture, behavior and evidence. Don't continuously process branches still being independently edited.

**Current shared documentation debt:** consolidate remaining overlapping Admin/Public contracts, classify historical source inventories and the temporary rights investigation, remove obsolete cross-branch state claims and repair documentation navigation **without losing unique accepted behavior**. See [Docs procedure](../procedures/documentation.md) and [human documentation index](../../docs/README.md). The assigned detailed steps/status belong only to the active worker's record.

**Integration boundary:** `docs` → `dev` exports **reviewed permanent documents**. This coordination file and all individual Docs worker files remain on `docs`; a normal full branch merge is not safe for historical isolation.
