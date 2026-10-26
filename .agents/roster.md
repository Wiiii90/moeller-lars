# Refactor workstream — branch-local coordination

**Next lease token:** 2

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Refactor Worker 1 | 1 | Active — awaiting user assignment | No application changes assigned | [refactor-1.md](workers/refactor-1.md) |

Only actually activated Refactor workers receive a row and their own `.agents/workers/refactor-N.md` record. This roster belongs exclusively to `refactor`; no copied Docs, Dev or Testing workers. Register or explicitly take over a numbered slot only when assigned, using the [local worker protocol](README.md).
