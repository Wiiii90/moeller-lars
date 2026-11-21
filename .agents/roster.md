# Dev workstream — branch-local coordination

**Next lease token:** 35

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 34 | Active — common Journal Stage/preview for Blog and Exhibitions source-integrated; runtime/browser acceptance pending | Journal workspace and Blog/Exhibitions Stage, preview and selection focus; coordinate Page/Journal shared ownership with Dev Worker 5 and Gallery stage primitives with Dev Worker 4 | [dev-1.md](workers/dev-1.md) |
| Dev Worker 4 | 31 | Active — Gallery Editor tabs and Gallery/Storage View persist consistently; Dashboard compact Overview restore source-ready, browser acceptance pending | Gallery Artwork table, title editor, selection/ordering, Stage/Settings and Public Artwork/Gallery; coordinate shared controls with owners | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 33 | Active — replacement chat continues the Pages type/template cutover; source-integrated, runtime and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom Page types and Hero Artworks template; Pages Settings, nested Page hierarchy and Heading controls | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 32 | Active — Hero-only regular Gallery Edit dialog, shared source-member table and corrected action/Stage rails source-implemented; runtime/browser acceptance pending | Hero Artworks source actions/Stage and named shared table action variant; coordinate Gallery/editor overlap with Dev Worker 4 | [dev-6.md](workers/dev-6.md) |

| Dev Worker 10 | 26 | Active — rights-processing Validation readiness and completion | Media rights preparation/delivery pipeline, queue-worker integration requirements, current Validation activation evidence and safe rollout handoff; no deployments or artist-data changes without explicit approval | [dev-10.md](workers/dev-10.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
