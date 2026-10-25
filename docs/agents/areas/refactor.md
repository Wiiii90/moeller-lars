# Refactor — proposed worker area

**Branch:** `refactor` has not been created or authorized. This coordination area supports discussion/planning only until the user expressly approves the branch and concrete integration boundaries.

**Next new worker number:** 1.

No worker slots have been allocated. On first explicit Refactor Worker activation, allocate `refactor-1` (epoch 1), create its bounded record, update the next number and this table atomically on `docs`. This does not itself create a Git branch or authorize code changes.

If refactoring touches shared Admin/Public/Publication/Media owners, coordinate file claims with [Dev](dev.md), [Testing](testing.md) and [Docs](docs.md). Preserve existing accepted behavior across all consumers. The [cross-feature architecture](../../architecture/cross-feature-compositions.md) and the actual owning contract remain authoritative.

Worker and cleanup rules: [agent entry](../README.md).
