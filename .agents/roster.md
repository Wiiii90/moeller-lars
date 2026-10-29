# Dev workstream — branch-local coordination

**Next lease token:** 2

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 1 | Active — critical local artist JPEG upload pixel-limit fix; Validation paused | Public media rights, thumbnail cutover, Storage upload and local preview readiness | [dev-1.md](workers/dev-1.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
