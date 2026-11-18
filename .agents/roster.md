# Dev workstream — branch-local coordination

**Next lease token:** 19

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 9 | Active — replaced archived chat; local migration verified, qualification pending | Exhibition date precision/migration, address, map and autosave; Public Exhibitions baseline | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 6 | Active — current artist-media integrity remediation | Canonical media references/deletion safety, Storage reclaim/preview lifecycle, upload completion and zombie prevention; no Gallery/Artwork UI, tests, deploy | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 10 | Active — dashboard preview findings | Dashboard checks settings popover consistency, Findings clear-button state, Activity clock positioning | [dev-3.md](workers/dev-3.md) |
| Dev Worker 4 | 17 | Active — Expanded Artwork viewport and content-track fixes; browser acceptance pending | Gallery/Artwork presentation, Stage, shared General/Gallery Live Preview; no unrelated General appearance or media lifecycle | [dev-4.md](workers/dev-4.md) |
| Dev Worker 5 | 11 | Active — Pages type/template refactor; schema and data integrity first | Unify Home identity with Gallery/Journal/Custom types, extract Hero Artworks as Gallery template, remove Skip Home and redundant Home editors; preserve Gallery/Exhibitions data | [dev-5.md](workers/dev-5.md) |

| Dev Worker 6 | 16 | Active — targeted source corrections committed; local Preview/browser acceptance outstanding | Home Hero: Dummy Galleries, candidate ranking/selection, Stage/Settings, public resolver, shared Create dialog; no tests/deployment | [dev-6.md](workers/dev-6.md) |
| Dev Worker 7 | 18 | Active — replaced chat; General Backing seam source review, browser acceptance pending | Appearance Global/Title/Menu/Page/Legal, preview geometry/persistence, Regional Formats hints | [dev-7.md](workers/dev-7.md) |
| Dev Worker 8 | 15 | Active — Admin scrollbar stability and dialog legacy cleanup | Shared dialog/popover visuals; native root, Sidebar, modal and Stage scrolling without visible rails; Public behavior retained, artist options deferred | [dev-8.md](workers/dev-8.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
