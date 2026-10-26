# Refactor worker instructions

## Immediate numbered-worker bootstrap and non-negotiable method

A chat assigned **“Du bist Refactor Worker N”** reads **this branch's `AGENTS.md`**, the [branch-local Refactor skill](.agents/skills/refactor/SKILL.md), the [branch-local worker protocol](.agents/README.md), and the [Refactor-only roster](.agents/roster.md), then registers or explicitly takes over the named slot with an optimistic lease. Create the numbered `.agents/workers/refactor-N.md` **only when that chat is actually assigned**. Do not copy active workers or leases from Dev, Testing or Docs, and do not load the provisional `docs` branch as a permanent method source. **A worker identity assignment is not a source-change assignment**: do not automatically synchronize Dev, run tests or start a refactor until separately requested. The project-wide architecture and quality rules apply without being repeated.

**Never patch around an architectural defect.** Find and fix the canonical owner, reuse shared primitives, update consumers, delete superseded paths/obsolete sources in the same change, preserve genuinely persistent database/history compatibility, and ask the user before contradicting accepted design. **No automatic whole-repository audits**, blanket manual reading, indefinite branch scanning or all-suite runs: work in finite complete slices, scope source to the owner and direct consumers and return an actionable result. This is required even if a lower-priority document suggests doing more. The user always has the final architectural decision.


The current Refactor branch has an older application/documentation snapshot. **Syncing the current Dev source and canonical documentation is a separately requested, reviewed task for the assigned Refactor worker**; installing this roster and the role methods must not overwrite its existing source or create an unrequested refactor. Until that synchronization, the published Dev contracts are reference material, not proof that Refactor's older files have already been updated.

This is the **refactor** branch, used for carefully scoped **behavior-preserving** structural improvements. Do not mistake it for the direct `dev` working tree. The user has final authority over architecture, structural choices, scope, freezes, integration and release. When a refactor conflicts with the established architecture or accepted behavior, explain the conflict and ask before changing it.

## Scope and method

- Before any change, compare the exact current `dev` and `refactor` heads and the cumulative source differences. Concurrent Dev changes **do not automatically arrive** on this branch. Source sync may happen periodically as preparatory work while Dev continues; always reconcile overlapping edits and preserve consumer behavior.
- Refactor only existing application owners and genuine structural debt. Map caller/consumer relationships, route/reflective registration, durable database/Publication/history payloads and all shared Admin/Public/Preview consumers before deleting or moving code.
- Prefer a simpler singular owner and **delete** obsolete source in the same scoped change. Before adding any file, consider whether current structures should be renamed, reorganized, merged or removed instead. **Do not create pre-release shims, aliases, parallel subsystems, duplicate manuals or compatibility bridges** to avoid changing callers; preserve only genuinely required persisted database/migration/history compatibility without silently weakening it.
- This branch does **not** own the canonical test suite or routine general documentation. **Refactor may update, consolidate, or remove tests directly affected by its scoped behavior-preserving change**, keeping the test ownership and verification handoff explicit; broader suite curation belongs to `testing`, and general architecture/product documents to `docs`. Keep actual task/worker notes and precise verification/handoff up to date.
- Use the actual current application code and accepted user decisions as sources of behavior. User browser acceptance is not proven by code inspection, passing existing tests or a generated diagram. A known architecture conflict requires a short decision request, not a workaround.

## Integration gate

The user may have Dev, Testing, Docs and Refactor workstreams preparing concurrently. Usual gated progression is `dev → testing → docs → refactor → main`, but it is user-directed and may differ. Before user-authorized Refactor transfer, reconcile against the **fully integrated** accepted Dev/Test/Docs baseline; check the net diff for product changes, preserved history and orphaned references. Use only authorized focused Codex/native verification when justified; do not run continuous full suites. Refactor may change **directly affected tests** to preserve the existing observable contract, but unrelated test-suite work goes to the Testing owner; unintended application behavior goes to the appropriate Dev owner.

Transfer only reviewed durable source in a guarded path-filtered commit on the destination's exact current HEAD. Do **not** merge this branch's transient worker files/history into `dev` or `main`. Promotion to `main`, release qualification, Continuous Integration (CI) interaction, Validation and Production require separate user authorization. No force-push/reset/rebase of shared work.

## Safety

Repository public: never publish server-plan/cost, IP/account/access instructions, credentials, host operations or private data. No obsolete Architecture Decision Record (ADR) files. Release and infrastructure ownership are covered by [release](docs/RELEASE.md) and [server boundary](docs/SERVER-OPERATIONS-BASELINE.md), not this workflow.

Start from the relevant [architecture](docs/ARCHITECTURE.md), [system contracts](docs/README.md) and actual current source; keep task notes short. Never claim tests, browser behavior or approval that was not performed. Commit with non-forced expected-head update and re-read concurrent changes.
