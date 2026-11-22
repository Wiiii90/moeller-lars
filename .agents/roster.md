# Dev workstream — branch-local coordination

**Next lease token:** 41

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 34 | Active — Blog/Exhibition Stage editor, queued drafts, Journal display defaults, ID-based Blog URLs and organizer presets source-integrated; migrations/runtime/browser acceptance pending | Journal workspace and Blog/Exhibitions Stage, preview and selection focus; coordinate Page/Journal shared ownership with Dev Worker 5 and Gallery stage primitives with Dev Worker 4 | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 39 | Active — Custom Page Layout and embedded Gallery/Hero/Journal source components plus Pages Stage integrated; standalone local ownership, flow and browser acceptance pending | Custom Page content editor, local template composition, Public renderer and user-assigned Pages Stage; coordinate Pages domain with Dev Worker 5 and Gallery/Journal sources with their workers | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 36 | Active — separated Hero source Gallery row from all expanded candidate rows; source review complete, runtime/browser acceptance pending | Hero Artworks Stage/Public Preview and explicitly requested Hero source-list row ownership correction; coordinate other Hero source actions with Dev Worker 6 | [dev-3.md](workers/dev-3.md) |
| Dev Worker 4 | 38 | Active — Gallery Public media/caption width mismatch corrected in canonical CSS; broader Gallery regression review pending | Gallery Artwork table, Stage/Settings and Public Artwork/Gallery presentation; coordinate shared owner changes | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 33 | Active — replacement chat continues the Pages type/template cutover; source-integrated, runtime and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom Page types and Hero Artworks template; Pages Settings, nested Page hierarchy and Heading controls | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 32 | Active — Hero-only per-source Artwork overrides implemented across dialog, settings, Resolver, Stage/Public media and authorization; source verified, browser acceptance pending | Hero Artworks edit, source actions, Stage and Public Hero overlay consumers; coordinate with Dev Worker 3/4 | [dev-6.md](workers/dev-6.md) |

| Dev Worker 7 | 37 | Active — General Legal tabbed Rich Text and screenshot corrections committed; browser acceptance pending | General Admin Legal editor, optional shared tab Help, central Rich Text frame and direct public legal/rights consumers; coordinate shared primitives with other Dev workers | [dev-7.md](workers/dev-7.md) |
| Dev Worker 8 | 40 | Active — shared dialog X/Submit position and semantic colors source-integrated; browser acceptance pending | Restore X at top-right only in affected form dialogs; scope shared dialog action chrome and direct consumers, coordinate overlapping areas with existing owners | [dev-8.md](workers/dev-8.md) |
| Dev Worker 10 | 26 | Active — rights-processing Validation readiness and completion | Media rights preparation/delivery pipeline, queue-worker integration requirements, current Validation activation evidence and safe rollout handoff; no deployments or artist-data changes without explicit approval | [dev-10.md](workers/dev-10.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
