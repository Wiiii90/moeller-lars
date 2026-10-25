# Active worker registry

This is an index, **not** the worker's progress diary. Update an existing row when assigning/reassigning/completing a role. Never append an entry for every new chat or commit. Roles must be unique across concurrent workers. The linked state file carries the actionable assignment.

| Spoken role | Owning branch | State file | Assignment |
| --- | --- | --- | --- |
| Devworker 1 / Dev worker 1 / Worker 1 | `dev` | [devworker-1](workers/devworker-1.md) | **Uninitialized** — no reliably recoverable active task is recorded yet. First assignment must be supplied once; subsequent checkpoints replace the file. |
| Docsworker / Documentation worker | `docs` | [docsworker](workers/docsworker.md) | Current source-based architecture and workflow consolidation; details in state file |

New named workers receive a **single** short state file only when assigned. Do not create 20 empty placeholders or separate worktrees. An unregistered role with a known assignment can be registered at the first authorized work commit; without an assignment, request it once.

The agent's runtime/chat identity is not stored here. The record is not a substitute for permission to modify a branch or a database.
