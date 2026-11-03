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

Current hygiene audit: several active records have accumulated too much chronology and should be compacted by their **owning worker on its next substantive write**, preserving only current scope/state/evidence/gates:
- Dev Worker 1;
- Dev Worker 2;
- Dev Worker 3;
- Docs Worker 1.

Testing Worker 1 is smaller but should also replace superseded reconciliation history rather than continue appending dated batches. Dev Worker 4 and Refactor Worker 1 are currently compact.

Org must re-audit this periodically without copying worker histories into this file. Compaction is accepted only when reusable lessons have already reached the appropriate shared protocol/role skill/canonical documentation owner, or the worker retains one concise pending-durable-consolidation gate.

Current promotion audit: the mechanism exists and is being used (recent Dev skill/protocol changes and Docs-branch canonical document reconciliations provide concrete evidence), but the shared compaction rule previously did not explicitly forbid deleting an unpromoted reusable lesson. That gap is now closed by the promotion-before-deletion gate in `.agents/README.md`.

## Immediate organizational objective

Continue artist-feedback development without inventing a freeze. Maintain Issue/milestone hygiene while work moves. When the user declares the v0.4 Dev freeze, drive the bounded #198 → #199 → #214 closeout sequence, then prepare the separately authorized history-cleanup/main-promotion gate.
