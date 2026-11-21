# Dev workstream — branch-local coordination

**Next lease token:** 33

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 4 | 31 | Active — Cyclic arrows and connection lines removed centrally; source committed, browser verification pending | Gallery Artwork table, title editor, selection/ordering, Stage/Settings and Public Artwork/Gallery; coordinate shared controls with owners | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 19 | Source cleanup committed; isolated migration and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom types and Hero Artworks template; preserve Gallery/Exhibitions, retire Home-only editors | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 32 | Active — replacement chat registered; prior Hero/Random/Dummy Gallery/Filters source changes awaiting browser and user acceptance; awaiting a specific new task | Gallery → Hero Artworks and directly affected shared controls; coordinate broader Gallery changes with Dev Worker 4 | [dev-6.md](workers/dev-6.md) |
| Dev Worker 8 | 30 | Fixed repeated Create Clear: nested Filament action now unmounts normally without closing parent; source verified, browser acceptance pending | Shared Admin dialogs, common empty-state/pager/filter controls; no deployment | [dev-8.md](workers/dev-8.md) |


| Dev Worker 9 | 28 | Shared Gallery View Options Alpine initialization syntax fixed; Gallery Auto-focus flow reviewed with previewability/staging constraints, browser acceptance pending | Shared Live Preview navigation and Gallery Preview integration; coordinate Dev Worker 4 Gallery ownership and Dev Worker 7 controls | [dev-9.md](workers/dev-9.md) |
| Dev Worker 10 | 26 | Active — rights-processing Validation readiness and completion | Media rights preparation/delivery pipeline, queue-worker integration requirements, current Validation activation evidence and safe rollout handoff; no deployments or artist-data changes without explicit approval | [dev-10.md](workers/dev-10.md) |

| Dev Worker 11 | 27 | Active — Gallery caption pre-decode width collapse fixed in source; browser acceptance pending | Public Gallery server batches, media/caption sizing and viewer sequence; coordinate with Dev Worker 4 | [dev-11.md](workers/dev-11.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
