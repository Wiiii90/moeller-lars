# Dev workstream — branch-local coordination

**Next lease token:** 1

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
