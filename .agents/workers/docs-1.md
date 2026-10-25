# Docs Worker 1 — active

**Slot:** `docs-1` · lease **2** · [shared roster](../areas/docs.md). Replacement of previous context was completed by guarded lease 1 → 2. The current user explicitly assigned continuation.

## Mission

Make project documentation and agent procedures **fast to navigate, coherent, current and source-verifiable**, and remove superseded files, alias bridges and stale archives instead of retaining them for compatibility. Source-versioned older material survives in Git history. Work exclusively on `docs`; transfer into another branch requires separate authorization.

## Verified baseline and completed work

- Application baseline imported from `dev@0200407133ce4696817271d2141fceaca82f8aff`; at the last direct check, `docs` was not behind `dev`. **Recheck both heads before new writes**.
- Architecture overview and index reorganized; overlapping Admin/Public/Responsive rules consolidated. General Appearance active-tab-only height reconciled against actual `dev` CSS; public SPA, navigation, title and folio composition separated.
- Root `ui-skills.md` and `followup-skill.md` removed rather than retained as compatibility aliases; accepted material remains in current canonical docs and numbered worker handoff after checking the feature-specific rules against Admin UI, Dialog, Table, Responsive, Notification, Media and Browser contracts.
- `docs/architecture/completion-criteria.md` replaced with a concise ten-boundary evidence map; original dated audit dump removed from the active tree after retaining current owner map; original prose remains in Git history.
- Rights/consent text aligned with the artist's **deferred** consent-UI decision. Current source implementation, legal review and authorized tracking activation remain distinct checks.
- Canonical human/agent fast path added to `docs/README.md`, browser brief/source-review gates to `docs/ADMIN-BROWSER-WORKFLOW.md`; no application change or runtime/product acceptance claimed.

- User explicitly required deletion rather than alias retention. `docs` removed the three obsolete root skills, the new dated audit dump and two copied temporary rights handoffs; references were updated. `dev` independently received the same root-file deletions, direct canonical Admin UI/Responsive contracts, and effective **Dev-no-test-authorship** policy at `dev@12c0efe4e7a331045d00bbe69f10c26ad9853bca` without touching application code or test source.
- Repository-wide remote Markdown **path** scans covered all 79 files on `docs` and 45 on `dev`; stale relative paths found after deletion were corrected in the affected documents. This does not claim the native PHP heading validator was executed.

- User-approved work model now documented in branch-local worker protocol, Docs method, testing method and canonical test strategy: owner-final authority; cumulative diffs; parallel preparation; freeze/source parity; final Codex checks before reviewed transfers. No large-scale semantic currency audit until this workflow/ADR cleanup concludes.
- Unmaintained ADR directory (two files) deleted on Docs and Dev, together with public navigation links, due to private infrastructure/cost disclosure and lack of real ADR governance. Git history may still expose the prior contents; **deleting tracked files is not history sanitization**.

## Still open / next actions

1. Review remaining Admin/Public/Media/Rights prose **only when a concrete rule has competing owners**. A six-gram comparison of all 26 Admin UI sections against Table/Dialog/Control/Responsive/Browser found predominantly unique material; no destructive mass deletion is justified. Preserve accepted requirements.
2. Remote inventory checked all 85 Markdown files for link destinations and found 14 stale test paths; historical file references were pinned to their verified original commits, current dialog E2E paths updated. After the 14 repairs, 11 touched manuals were rechecked against GitHub's exact branch tree (394 local destinations, zero missing). The repository-wide path scan covered 85 Markdown files in batches; the full native PHP link/**heading** checker has **not** been run here.
3. Review concrete source changes against current owners without adding dated audit dumps, alias manuals or duplicate task files. Do not label authored tests executed without evidence.
4. Root skills and obsolete audit/rights snapshots deleted; full remote path scan of all 79 remaining Markdown files found one stale source-snapshot link and corrected it. Final native PHP path+heading validation still awaits an isolated checkout. Review only remaining current owner contradictions; no new compatibility aliases. Never merge this worker-bearing branch history into `dev`.

**Evidence:** connected GitHub source checks and guarded commits; no native PHP/Pest, browser/Validation, CI, deployment, production or full documentation checker executed here. Keep this task record short and replace outdated progress at each milestone.
