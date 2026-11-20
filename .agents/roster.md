# Dev workstream — branch-local coordination

**Next lease token:** 31

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 4 | 29 | Active — replacement chat registered; awaiting next Gallery assignment, prior Stage/Media browser acceptance pending | Gallery Artwork table, title editor, selection/ordering, Stage/Settings and Public Artwork/Gallery; coordinate shared controls with owners | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 19 | Source cleanup committed; isolated migration and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom types and Hero Artworks template; preserve Gallery/Exhibitions, retire Home-only editors | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 16 | Active — Gallery Hero Random anti-repeat and conditional chance implemented; runtime/browser acceptance pending | Gallery Hero Random draw, per-visitor recent image, Stage next chances, eligibility; coordinate Gallery ownership with Dev Worker 4; no tests/deployment | [dev-6.md](workers/dev-6.md) |
| Dev Worker 7 | 18 | Shared dropdown CSS tokens consolidated across native/Filament/pager; browser acceptance pending | General Appearance, shared Admin Selects and Gallery/General Preview | [dev-7.md](workers/dev-7.md) |
| Dev Worker 8 | 30 | Central Clear icon now uses shared custom eraser glyph; browser review pending | Shared Admin Create/Edit dialogs, central dialog geometry, date controls and picker; coordinate shared primitives; no deployment | [dev-8.md](workers/dev-8.md) |


| Dev Worker 9 | 28 | Shared pointer-origin focus state integrated for Admin actions; Shift-only no false focus ring, keyboard navigation preserved; browser review pending | General/Gallery Live Preview focus and interactions, central Admin action focus grammar; coordinate Dev Worker 7 and Gallery ownership | [dev-9.md](workers/dev-9.md) |
| Dev Worker 10 | 26 | Active — rights-processing Validation readiness and completion | Media rights preparation/delivery pipeline, queue-worker integration requirements, current Validation activation evidence and safe rollout handoff; no deployments or artist-data changes without explicit approval | [dev-10.md](workers/dev-10.md) |

| Dev Worker 11 | 27 | Active — single-image native-lazy Gallery source repair prepared; browser acceptance pending | Progressive Public Gallery server batches, native image decode and viewer sequence; coordinate with Dev Worker 4 | [dev-11.md](workers/dev-11.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
