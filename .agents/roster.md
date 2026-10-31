# Dev workstream — branch-local coordination

**Next lease token:** 5

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 3 | Active — Exhibition-Textachse mit/ohne Cover vereinheitlicht; neues Template offen | Public Exhibitions, Admin-Dialog-Autosave/Schließen; Medien-Folgeaufgaben | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 2 | Active — Gallery ALT and shared field appearance committed; browser acceptance pending | Gallery Artwork editor controls, Rich Text and Storage picker field styling; prior Admin action consistency | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 4 | Active — inherited Rich-Text changes; awaiting local browser acceptance | Shared Admin Rich-Text editor link/color popovers and visual consistency; avoid unrelated editor changes | [dev-3.md](workers/dev-3.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
