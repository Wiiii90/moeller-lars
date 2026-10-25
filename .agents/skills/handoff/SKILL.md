---
name: handoff
description: Produce concrete, copyable handoff prompts or implementation summaries that respect the current dev/docs/testing branch ownership and no-CI policy.
---

# Task handoff and continuation

## Gather real state before writing

1. Verify the exact `dev` / `docs` / `testing` HEAD only for branches relevant to the task. Do not assume the last known SHA remains current. `main` is not an ordinary implementation target.
2. Name the **authorized working branch** and affected files/owners. `docs` owns documentation and source-only generators, `testing` is separately owned, and regular application changes go directly to `dev`. **No worker/feature branches, worktrees, force pushes or CI interaction.**
3. Identify user-accepted requirements, source-verified facts and evidence that is still missing. Distinguish inspected tests from tests actually executed, source reasoning from browser-tested results, and Draft/Working changes from a LIVE publication commit.
4. Check the current canonical docs: [AGENTS](../../../AGENTS.md), [docs index](../../../docs/README.md) and the relevant [Admin](../admin-ui/SKILL.md), [Public](../public-ui/SKILL.md) or [Documentation](../documentation/SKILL.md) owner. For the docs branch **read [Docsworker state](../../../docs/agents/workers/docsworker.md) first** to resume without re-auditing.

## Write a prompt only when one is needed

When the user requests a copy/paste follow-up or a scoped task handoff, produce **one complete fenced Markdown block** for the entire prompt, normally short enough to act on without repeated restatement. Include:

- repository, branch, most recently verified SHA and safe re-check/reconciliation instructions;
- the concrete task and its objective, allowed source boundaries and explicit non-goals;
- relevant user decisions and current canonical service/UI/Publication invariants;
- confirmed browser findings versus unverified hypotheses, with source links;
- the next implementation step, deterministic acceptance criteria and safety stop conditions;
- what was already changed/committed, exact output/check status and unresolved issues.

Do not turn a prompt into a second agent policy: link the current owners, avoid repeating long source histories, do not silently delegate coding to another branch and never include CI instructions while forbidden. A status change or a completed test must be backed by an actual result.

## Complete chat-to-chat continuity

If the user requests a **full continuation prompt** (not merely a scoped code handoff), add any facts materially needed to resume the work:

- an exact Git SHA and branch; the next assistant must refresh HEAD before writing; record any parallel chat's file ownership and in-progress conflicting changes;
- whether the current candidate was source-reviewed, built, locally tested, browser-reviewed, **accepted by the user** or release-qualified — these are separate states;
- verified local URL/preview container and already-applied migrations **only when** the next step requires them; never assume the current assistant's workspace is mounted there;
- the next **specific** review slice, unresolved browser findings, concrete prior mistakes and stop conditions, without duplicating durable architecture manuals;
- what the next chat can do with repository access alone versus what truly needs a local PHP/PostgreSQL/browser environment;
- when the user asks for two follow-up chats, provide **two individually complete** prompts, including explicit file/branch ownership. The prompts must not rely on each other.

Do not create, merge, monitor or read CI as part of composing a prompt. Do not turn historical temporary-worker, side-branch or PR advice into a new operating model. All routine application work stays on `dev`; the only existing long-lived exceptions are `docs` and `testing`.

### Source and command quality

Prefer one coherent fenced Markdown block for a user-copyable continuation prompt. Shell commands should be directly runnable, bounded and defensive. For PowerShell obey [AGENTS.md](../../../AGENTS.md): `$HOME` and `$Args` are reserved, verify expected HEAD and diff before mutation, stop on failed steps, and avoid verbose unreviewed scripts. Only provide build/migration commands whose behavior and environment are actually verified. Never turn `P:\moeller-lars` into a mandatory precondition of a new chat.

Browser acceptance follows user feedback: collect one complete review slice before edits, do not equate the worker's account with a browser pass and do not repeatedly build during feedback collection. Use the relevant Admin/Public UI source contracts rather than repeating obsolete pixel/HTML-class assertions.

## Implementation return / next chat

A completion report identifies the commit SHA, actual changed files, source behavior, checks **actually** run, conflicts resolved, what remains untested and any out-of-scope finding. Visual work must name the accepted source reference and shared presentation primitives reused; no worker or assistant may declare a candidate browser-approved without user evidence.

For `docs`, update [Docsworker state](../../../docs/agents/workers/docsworker.md) by **replacing the last checkpoint and next actions**, not appending an infinite chronological diary. Keep branch history in Git and normative contracts at one documented owner. The new chat continues the next actionable task directly.
