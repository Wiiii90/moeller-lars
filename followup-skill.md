# Follow-up chat handoff skill

This file defines a compact, lossless continuation handoff for `Wiiii90/moeller-lars`. It transfers current facts and remaining work, not a history of closed chats, abandoned branches or every implementation attempt.

## Active execution mode

- Work **directly on `dev`**, usually in `P:\moeller-lars` when locally available. Other chats may also modify `dev`; inspect the current HEAD and reconcile overlapping changes before writing. Never create development branches/worktrees, force/reset/rebase or rewrite other chats' work.
- No CI/Actions access, logs, status inspection, manual workflow runs, release, server or deployment during ordinary development. `main` promotion and release qualification are separately authorized.
- Keep the current shared Domain, publication, media, Rich Text and admin UI owners. Never introduce duplicate runtime paths, timers or symptom-level workarounds.
- A test-only environment must be positively isolated from the persistent browser Preview DB, media and filesystem. Tests that were merely authored or reviewed are not "passed".
- For current work on public media/privacy/SEO, read the active `PUBLIC_RIGHTS_WORKSTREAM.md` and current durable rights contracts before modifications. That temporary handoff is deleted only when its complete workstream ends.

## Source of truth

1. Exact current `dev` SHA and production source.
2. Current user's browser screenshots/feedback on the same candidate.
3. Open product issues/unfinished acceptance.
4. Durable `AGENTS.md`, `ui-skills.md`, `docs/*` and focused test contracts.
5. Actual checks performed with results; never infer success from a worker statement or from older commits.

A newer accepted browser result overrides stale repair-era descriptions. Do not silently resurrect old branch modes, old test suites or abandoned implementations.

## Before creating a follow-up

- Establish latest local and remote `dev` commit as appropriate and report uncommitted changes or stashes **without modifying them**.
- Check which edits were source-reviewed, tested, built, browser-reviewed and user-accepted.
- Identify concurrent chats' scope ownership, shared files and any unmerged or not-yet-verified changes.
- Capture the concrete current preview/Validation URL and commit identity **only when actually verified**. Do not assume the browser is showing the most recent commit.
- Name remaining errors or blockers by actual behavior and evidence, not guesswork. Avoid copying secrets, cookies, tokens or full logs.
- Update any active scoped living handoff before transfer. Do not append obsolete historical incidents to living documents.

## Required content of a continuation prompt

A single copyable prompt should cover:

1. **Mission and boundaries:** what the next chat owns and explicitly must not touch; direct `dev`, no CI/server/release, shared-contract rules.
2. **Repository state:** exact latest verified `dev` SHA, local path if known, working-tree/stash precautions, other chats' concurrent scope.
3. **Current application behavior:** relevant public/admin design, latest accepted user requirements, exact affected owner files and invariants.
4. **Runtime/browser state:** current Preview command and URL, whether rebuilt after the latest source change, current browser acceptance and errors.
5. **Actual verification:** exact focused checks run vs unexecuted, test database isolation, any outstanding risks.
6. **Remaining queue:** user-prioritized defects and next actionable step; do not present completed or rejected proposals as open work.
7. **Handoff output:** changed files, commit SHA, diff intent, checks truly performed, source-vs-browser acceptance state, remaining blockers.

Preserve the distinctions `SOURCE-REVIEWED`, `TEST-RUN`, `BROWSER-VERIFIED` and `USER-ACCEPTED`. A passing Playwright or PHP test is not proof of the user's browser presentation. One successful page does not authorize UI changes to neighboring areas.

## Implementation and review process

Work from the current symptom inward. Read only the responsible source and applicable shared contract rather than re-auditing the entire repository. Reconcile any newly advanced `dev` before committing. Verify the actual net diff against requirements and reject unrelated edits, duplicate CSS/persistence owners and untested claims.

For copy/paste prompts, put the entire actionable prompt in **one fenced block**, with source SHA, allowed scope, non-goals, exact target state, root cause when established, checks, stop conditions, and commit/push rules. Do not imply a role must operate on its own Git branch. A separate audit can remain read-only; an implementation session may change files only within its explicitly owned scope.

For browser tasks, use the accepted shared reference, keep a single canonical implementation, and rebuild the local Preview once per coherent batch. Browser acceptance belongs to the actual integrated candidate. If a user rejects the markup or behavior, passing tests do not make it accepted. After acceptance, remove safely proven obsolete markup, CSS, hooks and duplicated tests for that scoped change.

## Handoff quality

Keep the prompt concise enough for a new chat to act immediately, and include enough exact paths, constraints and user decisions to avoid re-asking settled questions. Do not include massive command histories, unrelated issue archives, old SHA chronologies, abandoned worktrees, obsolete testing-branch workflows, fabricated test evidence or full CI logs. Do not block a source-only task merely because the user's local Preview isn't accessible from a connected agent.
