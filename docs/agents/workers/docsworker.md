# Docsworker — current state

- **Status:** maintaining the documentation-only `docs` branch; never edit `dev`.
- **Objective:** source-align architecture with new `dev` commits, consolidate normative owners and maintain a flexible agent-workspace system so role names load relevant living context without auto-executing prior assignments or forcing a handoff prompt.
- **Source snapshot being reviewed:** `dev@35163591efb98eb51770a9c3061dda260fe25aba` (25 October 2026), 25 commits after prior `dev@74e629274a`; check fresh HEAD on resumption.
- **Current accepted decisions:** user wants normal dialogue or an explicit task after a role greeting; **no automatic backlog execution, no handoff documents**, no old root skill filenames, bounded in-place subject/state updates, infrequent Codex batches only, real system interactions instead of exclusively class diagrams. New Docs source audit is not a rerun of native generators/tests.
- **Canonical docs:** [documentation procedure](../DOCUMENTATION.md), [worker registry](../WORKERS.md), [architecture owner index](../../README.md), [technical evidence](../../architecture/completion-criteria.md).
- **Open integration:** current `dev/AGENTS.md`, `ui-skills.md`, `followup-skill.md`, `TESTING_SKILL.md` and Rights temporary handoff must not be overwritten indiscriminately. User expressed future intent, not an instruction to modify `dev` now. Documentation integration also must make the new agent entrypoint visible to direct-dev chats.
- **Safety:** no CI, no Validation, no local Docs worktree unless rare authorized Codex batch, no release.
- **Open questions / possible next subjects:** when the user wants it, plan the separately authorized docs→dev integration (protecting newer dev instructions and existing state) and decide whether a dedicated `refactor` branch has enough scope to justify its maintenance and merge cost. Otherwise discuss or review docs as requested; **no automatic next executable action**.
- **Evidence:** source and remote relative-link review; no new native PHP or browser tests for this batch.

Keep this subject/state file current by replacing stale paragraphs, not by appending a session log. It may contain open discussion and questions without a task to execute.
