# Org — current project state

This is the compact live organizational state for the single `org` chat on `dev`. It is not a transcript. Replace superseded facts instead of appending history.

## Current milestone — v0.4.0

**Development state:** artist-feedback work is still active. The user has **not** declared a Dev freeze and no final v0.4 source SHA exists yet.

Open v0.4 closing gates:
- **#198 — functional/failure-state validation:** bounded closeout only. v0.4 does not require a permanently bug-free application; at freeze the core feature set must work without a known high-severity ordinary-use blocker.
- **#199 — verification suite/release checks:** final Testing reconciliation and executed isolated qualification happen only after the user declares the exact Dev freeze.
- **#214 — release notes and milestone closeout:** pre-freeze themes are prepared; final date, Git range, evidence, Dashboard entries and milestone closure wait for the exact freeze.

Accepted foundational v0.4 Issues already closed by user decision:
- #177 Activity / Undo / Notification foundation;
- #197 public frontend foundation;
- #207 Admin controls/tables consistency foundation;
- #208 shared responsive foundation.

Do not reopen those merely because later artist-driven changes expose a concrete regression. Track/fix the concrete owner instead.

## Next milestone — v0.5.0

Current open v0.5 gates:
- #31 protected Biography/Exhibitions/profile reconciliation;
- #34 exact-candidate public/editorial regression comparison;
- #38 authoritative database + original-media backup/restore proof;
- #39 exact v0.5 Production-candidate GO/NO-GO;
- #57 Analytics against real aggregate data;
- #59 Storage capacity/quota in target runtime;
- #122 real Contact mail-delivery acceptance;
- #209 artist-approved Validation cutover dataset.

v0.5 is already partially exercised in practice because protected Validation is used for real artist editorial work, but v0.4 should still receive a clean historical close before the v0.5 release-candidate gates are treated as the active milestone.

## Validation

Protected Validation is an active pre-release/editorial environment whose database and canonical originals must be preserved across ordinary preview updates.

For a requested new Dev preview, resolve the exact fresh `dev` SHA and use the established two-stage release contract:
1. `scripts/validation-preview.ps1 <exact-sha>` builds/publishes the exact preview image;
2. the platform Validation update consumes that same exact SHA.

Do not record private host/access details here. No current deployed Validation SHA is independently confirmed in Org state; verify before claiming one.

## Main / repository-history gate

Before the next approved promotion to `main`, remove the historical public ADR infrastructure material from reachable Git history. The user decision is made: the old ADR content should be removed from history, not merely deleted from the current tree.

Do **not** interrupt ordinary active worker development for this now. Perform the coordinated history rewrite only at the later pre-main write-freeze, covering all relevant branches/tags/refs and follow-up clone/cache reconciliation. The current release contract owns the mandatory reminder.

The shared protocol now also forbids concrete private operational topology/access details in the public application repository so this class of exposure is not repeated.

## Cross-workstream / live-file hygiene

Org does not duplicate active worker assignments; inspect the owning branch roster when needed.

Current audit (2026-11-03):
- **Dev Worker 1:** knowledge promotion is good (Exhibitions/design-process lessons reached durable Docs/Dev method), but the live file is still an oversized chronology with obsolete lease/design states. It needs real compaction before retirement/sign-out.
- **Dev Worker 2:** strong durable-method promotion into the Dev skill and clear owner boundaries, but the live file still appends many dated follow-up sections and implementation commits. Compact to current invariants, current implementation state, grouped Testing handoff and remaining gates.
- **Dev Worker 3:** substantially improved. The live file is now reasonably compact and current, but still contains some stale screenshot/rejected-state evidence that conflicts with later accepted popover/caption state; one further cleanup pass is appropriate.
- **Dev Worker 4:** healthy for a complex active assignment. It is longer than ideal but mostly contains current product semantics, browser backlog, Testing handoff and cross-worker deferred ownership rather than pure chronology. Continue replacing duplicated implementation bullets as acceptance advances.
- **Testing Worker 1:** structurally sound but stale/chronological; multiple old reconciliation batches and obsolete Dev baselines should be collapsed before its next substantive reconciliation.
- **Docs Worker 1:** largest remaining token sink. Its record preserves extensive October publication history that already belongs in Git/durable docs. Before the next Docs work it needs aggressive promotion-aware compaction to current reconciled baseline, unpublished durable changes, verification limits and next transfer gate.
- **Refactor Worker 1:** healthy and compact; no substantive assignment yet.

Knowledge-promotion evidence is real: recent Dev work repeatedly amended the Dev skill/shared protocol, and Docs has reconciled accepted Admin/Public/Rich-Text/release behavior into canonical documents. The promotion-before-deletion rule is now explicit on `dev`.

**Pending workflow synchronization:** the exact new Shared Protocol compaction/promotion gate is currently present on `dev` only. Testing/Docs/Refactor already have role-specific durable-feedback rules, so this is not an immediate knowledge-loss hole, but their branch-local `.agents/README.md` snapshots must receive the reviewed shared update before relying on the new compaction contract there. Do not assume `dev` publication automatically propagated.

## Immediate organizational objective

Continue artist-feedback development without inventing a freeze. Maintain Issue/milestone hygiene while work moves. When the user declares the v0.4 Dev freeze, drive the bounded #198 → #199 → #214 closeout sequence, then prepare the separately authorized history-cleanup/main-promotion gate.
