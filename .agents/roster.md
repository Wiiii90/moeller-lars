# Dev workstream — branch-local coordination

**Next lease token:** 23

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 9 | Active — replaced archived chat; local migration verified, qualification pending | Exhibition date precision/migration, address, map and autosave; Public Exhibitions baseline | [dev-1.md](workers/dev-1.md) |
| Dev Worker 4 | 22 | Active — Gallery List/Dense ranked table roles, selection and inline-title reuse implemented in source; browser acceptance pending | Exhibitions position/drag, Storage-style selection, seven-action central rail, responsive data columns, no middots | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 19 | Source cleanup committed; isolated migration and browser acceptance pending | Protected Home identity, shared Gallery/Journal/Custom types and Hero Artworks template; preserve Gallery/Exhibitions, retire Home-only editors | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 16 | Active — targeted source corrections committed; local Preview/browser acceptance outstanding | Home Hero: Dummy Galleries, candidate ranking/selection, Stage/Settings, public resolver, shared Create dialog; no tests/deployment | [dev-6.md](workers/dev-6.md) |
| Dev Worker 7 | 18 | Source-updated — Menu Automatic retired, three explicit choices; corrective migration/browser acceptance pending | Appearance Global/Title/Menu/Page/Legal, preview geometry/persistence, Regional Formats hints | [dev-7.md](workers/dev-7.md) |
| Dev Worker 8 | 15 | Active — central Admin dialog drag/resize source implemented; browser acceptance pending | Shared modal geometry, draggable header, resize grip, viewport constraints, native Filament lifecycle; Media Select remains paginated | [dev-8.md](workers/dev-8.md) |

| Dev Worker 9 | 21 | Active — submenu hover countdown committed; browser acceptance pending | Public cyclic inline navigation including timed underline and child Back; shared General/Pages remain with current owners | [dev-9.md](workers/dev-9.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
