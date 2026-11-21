# Dev workstream — branch-local coordination

**Next lease token:** 37

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 34 | Active — common Journal Stage/preview for Blog and Exhibitions source-integrated; runtime/browser acceptance pending | Journal workspace and Blog/Exhibitions Stage, preview and selection focus; coordinate Page/Journal shared ownership with Dev Worker 5 and Gallery stage primitives with Dev Worker 4 | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 35 | Active — Custom Pages assignment; awaiting concrete feature work | Custom Page editorial workspace and Public Custom Page content; coordinate Pages hierarchy/type/navigation with Dev Worker 5 and shared Stage/preview with Dev Worker 1 | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 36 | Active — Hero Stage live page preview and simulator integration source-prepared; browser acceptance pending | Hero Artworks Stage and protected Public preview simulation; coordinate Pages/template ownership with Dev Worker 5 and source/editor ownership with Dev Worker 6 | [dev-3.md](workers/dev-3.md) |
| Dev Worker 4 | 31 | Active — consolidated duplicated Gallery/Dashboard/Stage tab persistence into single Admin preference owner; source ready for browser acceptance | Gallery Artwork table, title editor, selection/ordering, Stage/Settings and Public Artwork/Gallery; coordinate shared controls with owners | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 33 | Active — replacement chat continues the Pages type/template cutover; source-integrated, runtime and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom Page types and Hero Artworks template; Pages Settings, nested Page hierarchy and Heading controls | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 32 | Active — Hero-only regular Gallery Edit dialog, shared source-member table and corrected action/Stage rails source-implemented; runtime/browser acceptance pending | Hero Artworks source actions/Stage and named shared table action variant; coordinate Gallery/editor overlap with Dev Worker 4 | [dev-6.md](workers/dev-6.md) |

| Dev Worker 10 | 26 | Active — rights-processing Validation readiness and completion | Media rights preparation/delivery pipeline, queue-worker integration requirements, current Validation activation evidence and safe rollout handoff; no deployments or artist-data changes without explicit approval | [dev-10.md](workers/dev-10.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
