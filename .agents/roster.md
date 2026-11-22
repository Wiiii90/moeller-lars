# Dev workstream — branch-local coordination

**Next lease token:** 42

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 34 | Active — Blog/Exhibition Stage editor, queued drafts, Journal display defaults, ID-based Blog URLs and organizer presets source-integrated; migrations/runtime/browser acceptance pending | Journal workspace and Blog/Exhibitions Stage, preview and selection focus; coordinate Page/Journal shared ownership with Dev Worker 5 and Gallery stage primitives with Dev Worker 4 | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 41 | Active — replacement chat took over Custom Pages and Pages Stage; prior detailed handoff retained; central control parity, draft actions, visual Layout/Navigation and template integration remain incomplete and not browser-accepted | Custom Page model/editor/Public template composition and user-assigned Pages Stage; coordinate Pages domain with Dev-5 and shared Journal/controls with owners | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 36 | Active — separated Hero source Gallery row from all expanded candidate rows; source review complete, runtime/browser acceptance pending | Hero Artworks Stage/Public Preview and explicitly requested Hero source-list row ownership correction; coordinate other Hero source actions with Dev Worker 6 | [dev-3.md](workers/dev-3.md) |
| Dev Worker 4 | 38 | Active — Public native video Download action hidden through existing shared media initialization; Gallery visual regressions and browser acceptance pending | Gallery/Artwork and Public video presentation; coordinate shared owner changes | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 33 | Active — replacement chat continues the Pages type/template cutover; source-integrated, runtime and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom Page types and Hero Artworks template; Pages Settings, nested Page hierarchy and Heading controls | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 32 | Active — Hero Artwork Edit reduced to central Activate Override panel for metadata; rejected media/ALT/custom/source/duplicate visibility controls removed; browser acceptance pending | Hero Artworks Edit, source overlays, Stage/Public consumers, direct media reference integration; coordinate with Dev Worker 3/4 | [dev-6.md](workers/dev-6.md) |

| Dev Worker 7 | 37 | Active — General Legal tabbed Rich Text and screenshot corrections committed; browser acceptance pending | General Admin Legal editor, optional shared tab Help, central Rich Text frame and direct public legal/rights consumers; coordinate shared primitives with other Dev workers | [dev-7.md](workers/dev-7.md) |
| Dev Worker 8 | 40 | Active — draft-aware X danger styling and enabled-only submit presentation source-integrated; browser acceptance pending | Restore X at top-right only in affected form dialogs; scope shared dialog action chrome and direct consumers, coordinate overlapping areas with existing owners | [dev-8.md](workers/dev-8.md) |
| Dev Worker 10 | 26 | Active — rights-processing Validation readiness and completion | Media rights preparation/delivery pipeline, queue-worker integration requirements, current Validation activation evidence and safe rollout handoff; no deployments or artist-data changes without explicit approval | [dev-10.md](workers/dev-10.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
