# Refactor — proposed coordination area

**Git branch:** `refactor` does not yet exist. If the user authorizes its creation, base the new branch on the **then-current, verified `dev` head**, not on `docs`, `testing` or a stale remembered source commit. No code branch is created by opening a Refactor Worker.

**Next lease token:** 1

| Worker | Lease | State | Current owned scope | Individual record |
| --- | --- | --- | --- | --- |
| *(none activated)* | | | | |

**Intended purpose:** parallel behavior-preserving cleanup, simplification, dead-code removal, obsolete paths and structural weaknesses. No user-visible feature redesign, unrelated data migrations, destructive history cleanup or broad changes to accepted interactions. Before a removal, establish actual callers, framework-discovered entrypoints, runtime/feature gates, compatibility/undo/publication consumers and evidence that the code is unreachable or obsolete. Review tests and source together.

**Synchronization:** `refactor` does not automatically receive new `dev` commits. Periodically reconcile incoming `dev` changes with reviewed conflicts; integrate coherent, separately verified refactor changes back to `dev` only after explicit agreement. Parallel Dev/Refactor workers must coordinate shared file ownership and avoid treating passing source-level checks as browser acceptance.

Other areas: [Dev](dev.md), [Testing](testing.md), [Docs](docs.md). Worker lifecycle: [single protocol](../README.md).
