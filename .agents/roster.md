# Dev workstream — branch-local coordination

**Next lease token:** 9

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 5 | Active — local Exhibition schema migration verified; date-data/browser/restore qualification pending | Exhibition date precision/migration, address, map and autosave; Public Exhibitions baseline | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 6 | Active — current artist-media integrity remediation | Canonical media references/deletion safety, Storage reclaim/preview lifecycle, upload completion and zombie prevention; no Gallery/Artwork UI, tests, deploy | [dev-2.md](workers/dev-2.md) |
| Dev Worker 4 | 8 | Active — Gallery dimensions full-width source updated; browser confirmation pending, historical 9/10 read-only checks passed | Gallery/Artwork, Materials, public Artwork fit, bounded publication projection; Validation not yet deployed | [dev-4.md](workers/dev-4.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
