# Dev workstream — branch-local coordination

**Next lease token:** 4

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 3 | Active — takeover after context limit; Validation backup transfer and restore proof pending | Public media rights, thumbnail cutover, Storage upload and local preview readiness | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 2 | Active — Gallery artwork page vertical fit under investigation | Public Gallery artwork sizing, caption layout and responsive menu-width interplay | [dev-2.md](workers/dev-2.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
