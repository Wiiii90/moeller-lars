# Dev workstream — branch-local coordination

**Next lease token:** 34

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 4 | 31 | Active — session-restorable Gallery Staged queues and safe new-versus-reused Discard implemented in source; runtime acceptance pending | Gallery Artwork table, title editor, selection/ordering, Stage/Settings and Public Artwork/Gallery; coordinate shared controls with owners | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 33 | Active — replacement chat continues the Pages type/template cutover; source-integrated, runtime and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom Page types and Hero Artworks template; Pages Settings, nested Page hierarchy and Heading controls | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 32 | Active — regular Gallery Edit misrouting and obsolete Artwork callback corrected in source; Dummy Gallery Date read-only; browser acceptance pending | Hero source Gallery/Dummy Gallery actions, shared media selection and directly affected controls; broader Gallery owned by Dev Worker 4 | [dev-6.md](workers/dev-6.md) |

| Dev Worker 9 | 28 | Shared Gallery View Options Alpine initialization syntax fixed; Gallery Auto-focus flow reviewed with previewability/staging constraints, browser acceptance pending | Shared Live Preview navigation and Gallery Preview integration; coordinate Dev Worker 4 Gallery ownership and Dev Worker 7 controls | [dev-9.md](workers/dev-9.md) |
| Dev Worker 10 | 26 | Active — rights-processing Validation readiness and completion | Media rights preparation/delivery pipeline, queue-worker integration requirements, current Validation activation evidence and safe rollout handoff; no deployments or artist-data changes without explicit approval | [dev-10.md](workers/dev-10.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
