# Docs workstream — shared coordination

**Next lease token:** 4

| Worker | Lease | State | Claimed work | Own task record |
| --- | ---: | --- | --- | --- |
| Docs Worker 1 | 3 | active | Documentation structure, canonical ownership, source reconciliation and agent workflows | [docs-1](workers/docs-1.md) |

This roster owns **only registration, lease and claimed scope**. The worker's short [record](workers/docs-1.md) owns actual progress/next actions, and the reusable [documentation method](skills/docs/SKILL.md) owns how to work. Changes of owner, replacement, retirement and sign-out must follow the [single worker protocol](README.md) on this branch.

Mission: maintain an accurate, layered human/agent manual based on verified current `dev` application source. Give each accepted invariant one normative home, keep implementation/operation boundaries clear, and preserve source-pinned historical evidence without making it today's execution report. Do not import provisional tasks from other workstreams.

Documentation publication to `dev` is a **separately authorized, reviewed transfer of permanent files built on the then-current destination head**. Temporary `.agents/roster.md`, `.agents/workers/`, private investigation notes and this branch's root `AGENTS.md` never cross with it.
