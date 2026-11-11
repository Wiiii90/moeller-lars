# Dev workstream — branch-local coordination

**Next lease token:** 14

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 9 | Active — replaced archived chat; local migration verified, qualification pending | Exhibition date precision/migration, address, map and autosave; Public Exhibitions baseline | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 6 | Active — current artist-media integrity remediation | Canonical media references/deletion safety, Storage reclaim/preview lifecycle, upload completion and zombie prevention; no Gallery/Artwork UI, tests, deploy | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 10 | Active — dashboard preview findings | Dashboard checks settings popover consistency, Findings clear-button state, Activity clock positioning | [dev-3.md](workers/dev-3.md) |
| Dev Worker 4 | 8 | Active — Gallery Settings canonical two-column source update; browser review pending | Gallery/Artwork, Materials, public Artwork fit, publication projection | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 11 | Active — Pages preview findings | Pages settings semantics/consistency; failed page-type switch must restore the persisted selection | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 12 | Active — Home preview improvements | Pages → Home: Hero Artwork candidate management/rotation/stage; Under Construction and Custom settings/editor parity; checks on video and month rotation; no tests/deployment | [dev-6.md](workers/dev-6.md) |
| Dev Worker 7 | 13 | Active — General Appearance preview corrections | Appearance Global/Title/Menu/Page/Legal, preview geometry/persistence, Regional Formats hints | [dev-7.md](workers/dev-7.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
