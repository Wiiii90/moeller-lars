# Dev workstream — branch-local coordination

**Next lease token:** 45

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 42 | Active — replacement chat continues Journal (Blog and Exhibitions); exhibition settings simplification and current Stage/browser acceptance in progress | Journal Stage, organizer/venue directories, private preview and per-Exhibition presentation; coordinate shared controls and template ownership with Dev Workers 4/5/8 | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 41 | Active — Custom Page Stage New/Layout/Settings: canonical New, visual sortable nested Layout and inline Gallery-style Settings; table remains flat, browser acceptance pending | Custom Page model/editor/Public template composition and user-assigned Pages Stage; coordinate Pages domain with Dev-5 and shared Journal/controls with owners | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 36 | Active — separated Hero source Gallery row from all expanded candidate rows; source review complete, runtime/browser acceptance pending | Hero Artworks Stage/Public Preview and explicitly requested Hero source-list row ownership correction; coordinate other Hero source actions with Dev Worker 6 | [dev-3.md](workers/dev-3.md) |
| Dev Worker 4 | 38 | Active — original holder explicitly reinstated by user; Gallery source changes retained for verification | Gallery/Artwork and Public video presentation; coordinate shared owner changes | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 33 | Active — replacement chat continues the Pages type/template cutover; source-integrated, runtime and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom Page types and Hero Artworks template; Pages Settings, nested Page hierarchy and Heading controls | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 44 | Active — Hero Artwork Edit source path reconciled; browser acceptance pending | Hero Artworks Edit, source overlays, Stage/Public consumers, direct media reference integration; coordinate with Dev Worker 3/4 | [dev-6.md](workers/dev-6.md) |

| Dev Worker 7 | 37 | Active — General Legal tabbed Rich Text and screenshot corrections committed; browser acceptance pending | General Admin Legal editor, optional shared tab Help, central Rich Text frame and direct public legal/rights consumers; coordinate shared primitives with other Dev workers | [dev-7.md](workers/dev-7.md) |
| Dev Worker 8 | 40 | Active — corrected mounted Action dirty tracking and opt-in amber Create readiness source-integrated; browser acceptance pending | Restore X at top-right only in affected form dialogs; scope shared dialog action chrome and direct consumers, coordinate overlapping areas with existing owners | [dev-8.md](workers/dev-8.md) |
| Dev Worker 10 | 26 | Active — rights-processing Validation readiness and completion | Media rights preparation/delivery pipeline, queue-worker integration requirements, current Validation activation evidence and safe rollout handoff; no deployments or artist-data changes without explicit approval | [dev-10.md](workers/dev-10.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
