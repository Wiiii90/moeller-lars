# Refactor workstream — branch-local coordination

**Next lease token:** 1

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |

Only actually activated Refactor workers receive a row and their own `.agents/workers/refactor-N.md` record. This empty roster belongs exclusively to `refactor`; no copied Docs, Dev or Testing workers. Register or explicitly take over a numbered slot only when assigned, using the [local worker protocol](README.md).
