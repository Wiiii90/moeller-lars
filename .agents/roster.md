# Dev workstream — branch-local coordination

**Next lease token:** 28

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 9 | Active — replaced archived chat; local migration verified, qualification pending | Exhibition date precision/migration, address, map and autosave; Public Exhibitions baseline | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 24 | Source implemented — Settings, General regional formats and primary Admin displays; migration/browser acceptance pending | Account/security, personal Admin regional Settings and Public General date/number/time/time-zone display; shared header/dialog owners unchanged | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 25 | Active — Dashboard assignment registered; implementation not started | Admin Dashboard workspace, dashboard-specific content and actions; coordinate shared Admin owners | [dev-3.md](workers/dev-3.md) |
| Dev Worker 4 | 23 | Stage form compacted and staged Preview error source fixed; runtime/browser pending | Gallery Artwork table, title editor, selection/ordering, Stage/Settings and Public Artwork/Gallery; coordinate shared controls with owners | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 19 | Source cleanup committed; isolated migration and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom types and Hero Artworks template; preserve Gallery/Exhibitions, retire Home-only editors | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 16 | Active — targeted source corrections committed; local Preview/browser acceptance outstanding | Home Hero: Dummy Galleries, candidate ranking/selection, Stage/Settings, public resolver, shared Create dialog; no tests/deployment | [dev-6.md](workers/dev-6.md) |
| Dev Worker 7 | 18 | Source-corrected — shared device selector restored to native Admin Select; browser acceptance pending | General Appearance and shared Gallery/General Preview; Gallery Preview focus consumer | [dev-7.md](workers/dev-7.md) |
| Dev Worker 8 | 15 | Active — Admin dialogs vertically centered inside measured frame; drag-only header and full-width separation preserved; browser review pending | Shared Admin dialog geometry/chrome, bounded drag without resize, paginated Media picker; no deployment | [dev-8.md](workers/dev-8.md) |

| Dev Worker 9 | 21 | Active — submenu hover countdown committed; browser acceptance pending | Public cyclic inline navigation including timed underline and child Back; shared General/Pages remain with current owners | [dev-9.md](workers/dev-9.md) |

| Dev Worker 10 | 26 | Active — rights-processing Validation readiness and completion | Media rights preparation/delivery pipeline, queue-worker integration requirements, current Validation activation evidence and safe rollout handoff; no deployments or artist-data changes without explicit approval | [dev-10.md](workers/dev-10.md) |

| Dev Worker 11 | 27 | Active — initial Public keyboard issue user-accepted; ready for further issues | Bounded public-page layout, rendering and interactions consistent with CMS settings/publication; coordinate with existing feature owners | [dev-11.md](workers/dev-11.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
