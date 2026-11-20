# Dev workstream — branch-local coordination

**Next lease token:** 28

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 9 | Active — replaced archived chat; local migration verified, qualification pending | Exhibition date precision/migration, address, map and autosave; Public Exhibitions baseline | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 24 | Settings autosave and regional labels corrected in source; migration/browser acceptance pending | Account/security and per-user Settings; independent Public regional formats in General; shared dialog owner reused | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 25 | Source corrected — group actions and detailed Notification viewer; runtime/browser acceptance pending | Dashboard group actions, mixed read state, confirmation checkbox, canonical Audit-linked before/after display; maintain Activity grouping | [dev-3.md](workers/dev-3.md) |
| Dev Worker 4 | 23 | Shared Gallery/Storage live upload queue, stage auto-focus, circular tabs and Submit/All actions; browser acceptance pending | Gallery Artwork table, title editor, selection/ordering, Stage/Settings and Public Artwork/Gallery; coordinate shared controls with owners | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 19 | Source cleanup committed; isolated migration and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom types and Hero Artworks template; preserve Gallery/Exhibitions, retire Home-only editors | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 16 | Active — targeted source corrections committed; local Preview/browser acceptance outstanding | Home Hero: Dummy Galleries, candidate ranking/selection, Stage/Settings, public resolver, shared Create dialog; no tests/deployment | [dev-6.md](workers/dev-6.md) |
| Dev Worker 7 | 18 | Source-corrected — stable Preview device picker width and canonical optgroup styling; browser acceptance pending | General Appearance and shared Gallery/General Preview; Gallery Preview focus consumer | [dev-7.md](workers/dev-7.md) |
| Dev Worker 8 | 15 | Active — fixed prepaint partial-date popover position; shared date/time control value row and calendar icon standardized; browser review pending | Shared Admin dialogs, date field geometry and media picker; no deployment | [dev-8.md](workers/dev-8.md) |

| Dev Worker 9 | 21 | Active — accepted Public hover navigation consolidated; browser recheck pending | Public cyclic inline navigation, countdown and Back; shared General/Pages remain with existing owners | [dev-9.md](workers/dev-9.md) |

| Dev Worker 10 | 26 | Active — rights-processing Validation readiness and completion | Media rights preparation/delivery pipeline, queue-worker integration requirements, current Validation activation evidence and safe rollout handoff; no deployments or artist-data changes without explicit approval | [dev-10.md](workers/dev-10.md) |

| Dev Worker 11 | 27 | Active — initial Public keyboard issue user-accepted; ready for further issues | Bounded public-page layout, rendering and interactions consistent with CMS settings/publication; coordinate with existing feature owners | [dev-11.md](workers/dev-11.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
