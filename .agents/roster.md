# Dev workstream — branch-local coordination

**Next lease token:** 5

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 3 | Active — Soft-Paper-Stilversuch nach Ablehnung von Night Gallery; Browserabnahme offen | Public Exhibitions style, Admin-Dialog-Autosave/Schließen; Medien-Folgeaufgaben | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 2 | Active — Page metadata ownership consolidated; migration and browser acceptance pending | Pages-owned Meta Description and public slug; Gallery title/settings retained; previous Gallery UI acceptance | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 4 | Active — inherited Rich-Text changes; awaiting local browser acceptance | Shared Admin Rich-Text editor link/color popovers and visual consistency; avoid unrelated editor changes | [dev-3.md](workers/dev-3.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
