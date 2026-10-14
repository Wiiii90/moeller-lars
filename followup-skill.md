# Follow-up chat handoff — compatibility entrypoint

The maintained, authoritative process is [.agents/skills/handoff/SKILL.md](.agents/skills/handoff/SKILL.md). This root file retains the original entrypoint for older links and requests such as “Folgeprompt nach followup-skill.md”. **Do not recreate the superseded temporary worker-branch/reconciliation model or infer that all tasks must run on `dev`.**

## Current branch responsibilities

- Ordinary application work: **`dev`**, with fresh-HEAD reconciliation, direct commits and no side branches/worktrees.
- Architecture, documentation and source-only docs generators: **`docs`**, merging new `dev` commits after overlap review. Resume from [DOCS_SKILL.md](DOCS_SKILL.md).
- Test refactoring: **`testing`**, separate owner. Do not change this branch from another workstream.
- `main` release/deployment: only under separate explicit authorization.
- **Never touch CI** in the current workstreams. Do not inspect its status/logs or trigger runs.

## Temporary PUBLIC RIGHTS handoff invariant

For a `dev` implementation continuation on rights, privacy, media protection
or SEO, first read root [`PUBLIC_RIGHTS_WORKSTREAM.md`](PUBLIC_RIGHTS_WORKSTREAM.md).
It records the active slice, real implementation SHA, executed versus unexecuted
tests, accepted browser results and blockers; the durable architecture is
[`docs/PUBLIC-RIGHTS-AND-PRIVACY.md`](docs/PUBLIC-RIGHTS-AND-PRIVACY.md).
Before a handoff, update that temporary file as part of the **implementation**
commit. Do not mark plans complete by implication. On full completion the root
temporary workstream and this instruction are retired. The `docs` workstream
is independent and resumes from [`DOCS_SKILL.md`](DOCS_SKILL.md).

## What a continuation must preserve

First verify the current Git branch/HEAD and existing sources. A fresh ChatGPT environment may not have the user's Windows checkout `P:\moeller-lars`; use the available authenticated repository tools before declaring source access unavailable. Do not invent browser runs, container details, migrations or test results.

A practical continuation names: its **specific task and owning branch**, exact last verified SHA and concurrency rules, accepted/rejected user feedback, applicable existing domain/UI contracts, changed files and commits, unverified defects, restrictions, next executable action, and which checks were **actually** run. Include local preview/container specifics only when already verified and needed for the task. Distinguish source-read, syntactic-check, test-executed, browser-reviewed and user-accepted states.

When the user requests a complete copy/paste continuation prompt, deliver **one complete fenced code block**. If two distinct follow-up chats are explicitly requested, deliver **two complete self-contained prompts**, each with its own ownership and boundaries. Do not require a local `git pull` as a prerequisite when the next task can proceed through repository access.

For browser-heavy work, collect the user's full feedback on the current slice before implementing fixes; do not treat a technically running preview as product acceptance. Document unresolved risks and known broken intermediate states rather than claiming completion from a worker report.

For additional detail and examples use the [Handoff skill](.agents/skills/handoff/SKILL.md), [AGENTS.md](AGENTS.md), and the relevant [Admin UI](.agents/skills/admin-ui/SKILL.md), [Public UI](.agents/skills/public-ui/SKILL.md) or [Documentation](.agents/skills/documentation/SKILL.md) skill. This file is no longer an independent branch/workflow policy.
