# Dev workstream — branch-local coordination

**Next lease token:** 9

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 5 | Active — partial-date source cutover candidate, local database/browser verification pending | Exhibition date precision/migration, address, map and autosave; Public Exhibitions baseline | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 6 | Active — current artist-media integrity remediation | Canonical media references/deletion safety, Storage reclaim/preview lifecycle, upload completion and zombie prevention; no Gallery/Artwork UI, tests, deploy | [dev-2.md](workers/dev-2.md) |
| Dev Worker 4 | 8 | Active — replacement chat; checkpoint 10 read-only preflight passed, checkpoint 9 pending | Gallery/Artwork and historical publication projection; no destructive restore or Validation changes | [dev-4.md](workers/dev-4.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
