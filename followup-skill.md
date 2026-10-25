# Chat continuation and work handoff

**Default for a registered worker:** the branch-local [worker protocol](.agents/README.md), current [area roster](.agents/areas/docs.md) where applicable and **that worker's actual task record** are the handoff. Update the record with decisions, evidence, open scope and next action **before** the chat ends. A new chat must not infer assignments from its name or recreate a completed task. A same-number replacement requires the explicit takeover and new lease defined in the protocol. Do not publish these temporary records as permanent project documentation.

**Fallback for a task without a registered worker:** use one concise continuation prompt only when necessary. It conveys concrete facts, not an entire chat transcript or another management workflow.

## Verified context to transfer

Include only what materially changes the next action:

1. **Goal and scope:** accepted user decision, exact owned components/files, protected neighbors, exclusions and permissions.
2. **Repository:** working branch, checked commit SHA, uncommitted/stashed state if actually observed, known parallel editing scope. Never assume an old local checkout equals remote HEAD.
3. **Current implementation and responsibility:** the real service/Blade/JavaScript owners and authoritative [product contracts](docs/README.md); no parallel storage, rendering, notification or publication system.
4. **Candidate and verification:** source inspected; focused tests **actually executed** and safely isolated; browser build/URL and review **actually confirmed**; product acceptance; release/deployment only if separately authorized. Never promote authored assertions or a previously green build into a current result.
5. **Open actions:** one ordered queue of concrete still-reproducible defects, next safe step, blockers and stop conditions. Remove resolved, rejected and superseded items.

Use precise states: **SOURCE-REVIEWED**, **TEST-EXECUTED**, **BROWSER-REVIEWED**, **USER-ACCEPTED**, **RELEASE-QUALIFIED**. Each has different evidence. No-op/validation/rollback and durable audit/Notification matter independently of presentation.

## Continuation procedure

Read the actual task record (or compact manual handoff) and current branch rules first. Revalidate HEAD and overlapping edits; read the relevant implementation and **one** canonical owner rather than every long manual. Confirm the user's most recent accepted requirement; when the browser candidate is older than source, do not claim the newer code was seen. Modify the canonical owner, make a focused safe check, and update the same task record plus any changed durable contract.

For an explicit **manual copy/paste prompt**, keep all required details in one block: mission, SHA, owned files, actual symptom, accepted outcome, known root cause (only if proven), verification so far, next check and branch/commit guard. Do not add unsupported promises of runtime tests, CI status, deployment or production changes.

## Protected boundaries

Normal application work follows the destination branch's own [AGENTS.md](AGENTS.md), with concurrency-safe non-forced commits. Never treat a handoff as new authorization for release, server access, protected Validation/Production, CI interaction, destructive database resets or an extra branch. Keep browser Preview data and production media safe. Use disposable verified test environments for destructive DB/file tests.

The handoff is successful when the next worker can **act on the next verified step without asking again about settled constraints**; it is not measured by document length.
