# Dev — shared coordination

**Normal implementation branch:** `dev`. **Canonical coordination:** this file on `docs`; implementation policy remains in current `dev/AGENTS.md`. The branch head moves continuously: verify a new source head before each task or commit.

**Next lease token:** 1

| Worker | Lease | State | Current owned scope | Individual record |
| --- | --- | --- | --- | --- |
| *(none activated under this prototype)* | | | | |

An activation allocates the **lowest available positive worker number**, creates its individual record and registers its fresh lease in this table in one expected-head commit. Current pre-existing Dev chats are **not** silently mapped onto numbered identities.

For parallel work, claim an explicit feature/file area and check others' claims before shared changes. Overlap requires a negotiated split or serialized updates; refresh the actual `dev` head again before committing. Shared dependencies with [Testing](testing.md), [Docs](docs.md) or proposed [Refactor](refactor.md) belong here with pointers to their own areas, **not** copies of their workers' temporary states.

Allocation, replacement, consolidation, reuse and deletion: [single worker protocol](../README.md).
