# Dev workstream — branch-local coordination

**Next lease token:** 4

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 3 | Active — Dialogschließen sicherheitsorientiert geprüft; Browserabnahme offen | Admin-Dialog-Autosave/Schließen; Medien-Folgeaufgaben | [dev-1.md](workers/dev-1.md) |
| Dev Worker 2 | 2 | Active — Admin action availability consistency | Admin action state/toolbar and row/bulk availability across editorial tables; prior Gallery visual acceptance pending | [dev-2.md](workers/dev-2.md) |

Only activated Dev workers receive a row and their own `.agents/workers/dev-N.md` record. This roster belongs exclusively to `dev` and must never receive Docs, Testing or Refactor worker entries. Follow the [shared worker protocol](README.md) for atomic registration, takeover and lease updates.
