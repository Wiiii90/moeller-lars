# Reliable continuation between chats

**Entry:** the user may write only "Du bist Devworker 1, arbeite." The root agent entrypoint resolves the role through [registry](WORKERS.md), loads the [current state](workers/devworker-1.md), and resumes the recorded actionable task. There is no need for a user-authored copy/paste handoff **after the role's first assignment has been persisted**.

A new chat cannot automatically retrieve uncommitted reasoning, invisible actions, an unsaved browser session or the entire prior conversation merely from the worker number. **Persistent continuity requires the outgoing worker to write the actual state into the repository.** Do this proactively on the active branch, subject to scope/commit permissions, and avoid assumptions about context size.

## Bounded state contract (replace, never append)

Each worker file contains: **role and status; branch and last verified commit; one active objective; owning files and dependent consumers; user-approved/rejected decisions; current code and last test/browser evidence; current blocker; exactly one next executable action**. Include links to durable contracts instead of duplicating them. Update before the context runs long and alongside coherent code/docs commits. Remove completed or obsolete steps instead of preserving a commit diary.

Before a new turn acts:
1. Fetch current branch HEAD; read the role's state, current source owner, and only linked contracts.
2. If head advanced, compare overlap and reconcile previous state before editing; no blind trust in a stale commit.
3. Resume the next concrete action and targeted acceptance checks. Do not ask for rules already in `AGENTS.md` or repeat accepted decisions.
4. If a missing state means a crucial assignment is unknowable, ask **that one missing question**. Never fabricate the old chat's work.
5. At completion, mark the existing state **done**, retain an exact accepted evidence link and remove open-task instructions. On reassignment replace the same role state rather than stacking versions.

## What not to store

Do not put secrets, user data, entire chat logs, verbose console output, CI results, temporary branch histories, massive checklist appendices, guesses labeled as accepted, or unrelated future improvements into active state. Keep those out of Product contracts. Normative architecture is [Docs index](../README.md); durable interaction acceptance is in its owning contract, source/versioned evidence in [architecture](../architecture/README.md), not in the worker state.

An integrated Docs→dev transfer is required before a fresh **dev-branch** coding agent is guaranteed to see this automatic entrypoint. Do not claim that this Docs-only file already configures the existing `dev` root.
