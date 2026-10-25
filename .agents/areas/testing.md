# Testing — shared worker coordination

**Working branch:** `testing` is active, curating the eventual common regression suite. The earlier test iteration was merged into `dev`, but curation continued on `testing`. Do not call it a historical/inactive branch. Current verified reference: `testing@b7eee549b86ac35eaa6a064ac78ef226182c170e` (25 October 2026).

**Next new worker number:** 2.

| Slot | Epoch | State | Scope / claim | Worker record |
| --- | ---: | --- | --- | --- |
| Testing Worker 1 | 1 | reserved | Existing Testing conversation is active outside this not-yet-integrated workflow; exact in-chat task not persisted here | [testing-1](../workers/testing-1.md) |

**Shared coordination:** When the current Testing Worker adopts this structure, update its actual scope, test ownership, latest verified branch references and completed checks here. Do not infer today's task or test success from a merge commit. Additional Testing Workers must claim disjoint test files or explicitly serialize edits. Compare both current `testing` and `dev` refs and flag overlaps before integration.

Stable safety/test methods: [Testing agent guidance](../procedures/testing.md) and [canonical test strategy](../../docs/TESTING.md). Coordination and replacement lifecycle: [agent entry](../README.md). No separate invented test architecture and no unattended merge to `dev`.
