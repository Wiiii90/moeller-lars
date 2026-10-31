# Dev workstream — branch-local coordination

**Next lease token:** 7

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 5 | Active — Übernahme durch neuen Chat; Soft-Paper-Stilversuch in Browserabnahme | Public Exhibitions style, Admin-Dialog-Autosave/Schließen; Medien-Folgeaufgaben | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 6 | Active — investigating reported JPEG upload failure | Canonical media ingest / derivative failure, bounded read-only diagnosis; no Admin dialog or Public ownership | [dev-2.md](workers/dev-2.md) |
| Dev Worker 3 | 4 | Active — inherited Rich-Text changes; awaiting local browser acceptance | Shared Admin Rich-Text editor link/color popovers and visual consistency; avoid unrelated editor changes | [dev-3.md](workers/dev-3.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
