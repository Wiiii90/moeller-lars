# Worker and work-area registry

Worker names are **flexible conversation/work areas**, not intrinsically Git branches, numbered agents or jobs to execute. This file points to ongoing subject knowledge when it exists; it does not grant write permissions. Do not add a row for every chat or completed commit.

| Example spoken name | Area of interest | Living record | State |
| --- | --- | --- | --- |
| Dev Worker 1 / Developer Worker 1 | Current application development conversations | [Worker 1](workers/worker-1.md) | Available for a task **or a discussion**; no active assignment is reliably recorded here yet. |
| Docs Worker | Documentation and architecture | [Docs workspace](workers/docsworker.md) | Existing living documentation context; inspect its open questions before acting. |
| Testing Worker | Test design, executed verification and safe environments | [Testing procedure](TESTING.md) | Role can be used for a discussion or testing task; no separate worker state or branch assumption until actually needed. |
| Refactor Worker | Systematic architecture/code cleanup | [Architecture compositions](../architecture/cross-feature-compositions.md) | Possible role; `refactor` branch **not yet created or authorized**. Establish scope first. |

A user may name any additional worker; add a small state file **only when ongoing relevant context exists**. Worker numbers need not be global and never imply a branch. A workspace may switch between discussion, implementation and verification; the current message takes precedence over any older pending idea.

When a task requires edits, identify its owner, actual authorized branch, overlapping workers, and accepted behavior. All important product-level decisions are written once in [normative docs](../README.md); living records hold only current subjects, options, blockers and pointers to evidence.
