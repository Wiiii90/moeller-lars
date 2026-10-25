# Dev — shared worker coordination

**Working code branch:** `dev` (already active). **Coordination source:** this file on `docs`. Do not interpret the work-area label as permission to write a branch. For implementation follow the current `dev/AGENTS.md`.

**Next new worker number:** 2. Number 1 is reserved by the previously created placeholder; assign new numbers monotonically and never reuse a retired identity.

| Slot | Epoch | State | Scope / claim | Worker record |
| --- | ---: | --- | --- | --- |
| Dev Worker 1 | 1 | available | No recoverable task or exclusive file ownership assigned | [dev-1](../workers/dev-1.md) |

**Shared coordination:** Each active worker adds a brief owned feature/file scope here before touching overlapping code, then refreshes it as the work changes. Distinct Dev Workers may work concurrently on `dev`; compare current `dev` HEAD and the other slots' claims before editing and before committing. An overlap requires explicit division of responsibility or serialized editing, not competing edits. Any cross-area dependency with Testing, Docs or future Refactor is summarized here with a link, not a copy of their task notes.

**Current cross-area boundary:** [Testing area](testing.md) curates tests on a different active branch; [Docs area](docs.md) maintains documentation. These have independent commits and must be integrated deliberately. Current verified `dev` reference: `35163591efb98eb51770a9c3061dda260fe25aba` (25 October 2026), not a pinned future working HEAD.

Remove completed scopes and retired slots rather than appending a task diary. Lifecycle, replacement epochs and atomic coordination rules are owned by [agent entry](../README.md).
