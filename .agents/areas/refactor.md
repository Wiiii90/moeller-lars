# Refactor — shared coordination

**Working Git branch:** `refactor`, created 25 October 2026 **from exactly `dev@0200407133ce4696817271d2141fceaca82f8aff`**. This was the contemporaneous `dev` head. Subsequent commits on `dev` are **not** automatically present on `refactor`; check both HEADs before every reconciliation or source claim.

**Next lease token:** 1

| Worker | Lease | State | Claimed scope | Individual record |
| --- | --- | --- | --- | --- |
| *(none activated yet)* | | | | |

Opening the branch did not create a Refactor Worker. When the user assigns one, create the lowest free `refactor-N` identity with a new lease token, and sign in here and in its own task file in the same guarded commit.

**Mission:** behavior-preserving structural changes only: dead-code removal after proof, consolidation of duplicate implementations, simplification of unnecessarily complex dependencies, cleanup of obsolete paths and fragile architectures. Protect existing visible interaction, publication, Undo, media/historical retention and authorization semantics. Identify real framework-discovered entrypoints and consumers; absence of direct import alone is not evidence of dead code. Coordinate shared file ownership with [Dev](dev.md); [Testing](testing.md) reviews behavior regression and [Docs](docs.md) tracks durable design/owner changes.

**Branch model:** periodically bring new `dev` commits into `refactor`, resolve conflicts and revalidate affected behavior; integrate small coherent improvements *back to* `dev` only when separately reviewed/authorized. Do not overwrite ongoing feature work from older refactor files or merge unreviewed behavior changes. No extra branch/worktree created merely by a worker name. The [Refactor skill](../skills/refactoring/SKILL.md) describes the procedure. Sign-out/deletion follows [worker protocol](../README.md).