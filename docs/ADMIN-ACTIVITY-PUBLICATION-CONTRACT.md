# Admin Activity and publication contract

## Scope

Activity and Publication are related but deliberately different systems.

- **Activity** answers what administrative change happened, when and by whom.
- **Working state** is the current editable state in `public.*`.
- **LIVE state** is the current published snapshot in `committed.*`.
- **Commit history** answers which complete website versions were published and how they relate.

Notification/inbox state is not part of this contract. See [ADMIN-NOTIFICATION-CONTRACT.md](ADMIN-NOTIFICATION-CONTRACT.md).

## Activity

`audit_events` is the append-only forensic source for successful administrative mutations. It is not a hidden second history: the Activity workspace is the user-visible projection of this evidence before and after publication.

The publication boundary defines where an Activity is shown:

- audit events that are not yet linked to a Commit form the current **Activity** view;
- when a Commit succeeds, every still-uncommitted audit event is linked to that Commit;
- those events then disappear from the current Activity list and remain inspectable through that Commit's details;
- only current, uncommitted Activities may expose individual Undo;
- committed Activities never expose individual Undo; historical reversal happens only through whole-Commit Restore/Revert.

### Ordering Activity grouping

Ordering can generate many persisted mutations during one editing sequence. To avoid hundreds of rows for routine reordering, ordering events are structurally grouped by actor, action and stable ordering scope.

The grouping is state-continuous and publication-bounded, never timer-, debounce- or burst-based:

- the projection stores the canonical starting and latest ordering sequences plus the number of raw reorder operations;
- the latest event in the projection represents that whole ordering Activity in the current Activity view and later inside Commit details;
- publication freezes the projection because its representative event becomes part of the Commit;
- a different scope/action, a different actor, a discontinuous state or a publication boundary starts another projection;
- returning to the original order does **not** erase the Activity. The persisted reorder operations happened and remain part of the forensic history;
- there is no special sequence rule for `A→B→A`, `A→B→C→A` or any other path;
- an ordering Undo receipt exists only when applying the projection inverse would currently change state safely. A grouped identity Activity remains visible but naturally has nothing to Undo.

The projection never rewrites or deletes `audit_events`; it only controls how multiple raw ordering mutations are represented as one visible Activity.

Activity uses the shared `AdminActivityFeed` read model. Its visible projection has four explicit semantic fields before the ordinary actor/time/publication context:

- **Area** — the admin domain in which the change happened;
- **Type** — the stable action family such as Ordering, Edited, Media or Publication;
- **Change** — the concise canonical action sentence, for example `Ordered artworks in Galleries`;
- **Details** — target/value/order specifics derived from the recorded mutation evidence, for example which two artworks swapped positions.

The table keeps Details out of the Change cell so scanning stays stable:

```text
Change | Who | When | Target | Area | Type | Publication | Actions
```

Details remain available in the Activity viewer and Commit details. Immediate header feedback may compose the same semantics as `Change: Details`; it must not invent a second wording system.

`Details` is the stable first row action. When `AdminActionReceiptService` exposes a currently valid actor-scoped receipt for an uncommitted Activity, `Undo` appears immediately after Details. A Commit is a hard Undo boundary: once an Activity belongs to a Commit, that Activity is inspectable through the Commit but is no longer individually reversible.

Activity and Commits are two views of the same forensic workspace. Both retain the applicable Search/Area/Type/Date/Time filter context and use bounded pagination with the shared page-size choices `25`, `50` and `100`.

Both table views implement the shared trailing Selection contract: row checkboxes occupy the far-right rail, the visible select-all checkbox sits directly above them, and the Selection trigger's count badge shares that horizontal axis. The Activity multi-action menu supports Details for exactly one selected current Activity, Undo only for selected current Activities that still expose safe receipts, and Clear selection. The Commits menu supports Details for exactly one selected Commit plus Restore or Revert only when exactly one selected Commit is semantically eligible for that operation.

### Date and time filters

Calendar and clock are not decorative secondary controls.

- Clicking a calendar day applies the actual Activity/Commit date filter for the active view.
- Clicking an hour applies that hour in addition to the selected date.
- Date/Time are reflected in the normal filter toolbar and can be cleared there.
- The Activity timeline counts the same logical current Activities shown by the table, including grouped ordering Activities rather than raw reorder-event volume.
- Calendar density retains year context so selecting one day does not collapse the visualization itself.

The top visualization and publication stage is shared context for the active Activity/Commits view. Switching views must not create a second Activity page or a parallel publication surface.

## Commits view

The current Commit-history table exposes:

```text
Commit | Who | When | Summary | Publication | Actions
```

`Details` is the stable first action. A compatible historical snapshot may then expose `Restore`; only the current LIVE commit may expose `Revert` when its parent is a compatible retained snapshot. Those contextual actions follow Details rather than shifting it between rows.

Commit history is permanent factual publication history. Working-state controls live in the shared publication stage above the table rather than as a second independent history system.

## Publication source of truth

The application intentionally has two current database states:

```text
public.*       = working/admin state
committed.*    = current LIVE state
```

`PublicationSnapshot::TABLES` is the closed current tracked-table set. Publication delta is the row-level semantic difference between those two states, ignoring framework-only `created_at`/`updated_at` differences.

`PublicationService::pendingSummary()` is the source of truth for **Pending changes**, and `PublicationService::hasPendingChanges()` derives from that same Working-vs-LIVE comparison. Audit-event count is forensic context and must never be used as publication truth.

Historical migrations must not import `PublicationSnapshot::TABLES` or other mutable runtime publication constants to define their own past behavior. A migration freezes the publication tables/entity types that existed when that migration was introduced; later changes to the current publication contract are applied by later forward migrations. **Current compliance is not complete:** a known preexisting use of the mutable constant in the September 15 restorable-versions migration is documented under [migration invariants](MIGRATION-INVARIANTS.md#historical-migration-self-containment). This is a source-verified outstanding issue, not a reason to rewrite historical migrations without a safe reconstruction.

`blog_settings` is not legacy-source evidence and is not part of the runtime Publication source of truth. It was an early application-owned Blog-settings table whose canonical values were normalized into `site_sections` / `journal_settings`; a forward migration retires it after the obsolete foreign-key coupling is removed. Historical migrations may still mention it solely because they reconstruct the application's actual schema evolution. It must never return as a Publication fallback or tracked source.

## Preflight

`PublicationService::preflight()` reports only rules the current publication path genuinely enforces. Today this includes:

- whether any tracked delta exists;
- `public`/`committed` schema parity.

Do not invent UI-only readiness rules that the Commit path itself does not understand. Domain write services remain responsible for their own mutation invariants before state reaches publication.

## Commit identity and lineage

Every new publication Commit is a permanent `publication_checkpoints` record with:

- a full 64-hex SHA-256 `hash`;
- a full `snapshot_hash` of the complete tracked publication state;
- the publication `schema_hash` used by the snapshot;
- parent commit relation;
- optional source commit relation for restore/revert operations;
- actor, message, row-delta count and publication time;
- operation kind (`commit`, `restore`, `revert`, or initial legacy state).

The UI normally displays the first 10 hex characters while retaining the full hash as identity/integrity metadata.

Commit hashes are application-level logical publication identities. They are not Git repository commit SHAs.

## Full restorable snapshots

Every new Commit still exposes a logically complete row snapshot through the transparent `publication_version_rows` interface, but those rows are not stored as repeated JSONB copies. `publication_version_row_manifests` stores each Commit/table/row identity and references immutable content-addressed payloads in `history_payloads`. Undo snapshot receipts reference the same `history_payloads` store through `snapshot_payload_id`, so identical JSON payloads shared by Undo and Publication are physically stored once.

This project intentionally favors logically full snapshots over reconstructing historical state from Activity deltas. Content-addressed payload sharing is an implementation/storage optimization beneath that deterministic restore contract. Media binaries are **not** duplicated per version; snapshots reference canonical MediaAsset identities/storage keys.

Historical checkpoint metadata that predates this snapshot system remains visible in Commit history. Such legacy checkpoints are explicitly `metadata only`; the UI must not offer Restore when a real compatible snapshot does not exist.

A retained snapshot is restorable only while its stored `schema_hash` matches the current publication schema. Schema-incompatible history remains inspectable rather than being restored through unsafe best-effort coercion.

## Commit permanence

Commit metadata is permanent factual history.

- PostgreSQL rejects deletion of `publication_checkpoints` at the database boundary.
- Reverting/restoring creates later working state and, once explicitly committed, a new Commit. Existing commits remain in the chain.
- The explicit Storage action `Free storage` may relinquish **restore payloads**, not Commit metadata. Released checkpoints remain visible as history with `snapshot_available=false` and cannot be offered as restorable versions.
- The current LIVE checkpoint and the checkpoint referenced by `publication_working_context.source_publication_checkpoint_id` are protected from that reclaim action.

Undo receipts are intentionally bounded recovery data rather than permanent history: actor-scoped receipts expire after 365 days, retain at most 5,000 receipts per user and are capped at 256 MiB logical payload budget per user. `Free storage` may clear them immediately. Committing an Activity closes individual Undo for that Activity; the append-only audit evidence remains available through the Commit's forensic details.

Shared `history_payloads` are garbage-collected only after neither a Publication manifest nor an Undo receipt references them. This lets recovery roots be released without duplicating or prematurely deleting payload data still required elsewhere.

## Working changes

The shared publication stage exposes Working-vs-LIVE state above both Activity and Commits. It shows:

- true pending row count;
- grouped affected publication areas/entities;
- current preflight status;
- current LIVE version context;
- `Review changes` when staged state exists;
- `Reset` for the `Reset staged changes` operation when staged state exists;
- `Commit` when preflight allows publication.

The logical operation name remains **Reset staged changes** even where the compact action label is `Reset`.

### Reset staged changes

`Reset staged changes` means exactly:

```text
committed.* -> public.*
```

The replacement is transactional under the publication advisory lock and schema guard. It discards current staged content state while preserving Activity and Commit history. **Only when a Working/LIVE diff exists** does it replace Working, record a new `publication.stage_reset` Activity and drain abandoned-media cleanup. When the diff is zero, `resetStagedChanges()` merely clears the Working provenance context and returns `0`, **without** a new Activity event or cleanup. Reset never deletes earlier Activity/Commit evidence.

Reset is not implemented by replaying individual Undo receipts.

## Restore this version

`Restore this version` applies a retained historical snapshot to **Working** only:

```text
LIVE/committed = current HEAD
Working/public = selected historical snapshot
```

The live website does not change. The resulting current-vs-working delta is reviewed through the normal publication surface and requires an explicit later Commit.

The later Commit gets operation `restore`, retains the selected source Commit and has the previous LIVE Commit as its parent.

Restore replaces all current staged work. The confirmation copy must say so explicitly.

## Revert commit

`Revert commit` is intentionally available only for the current LIVE Commit and only when its parent is a compatible retained snapshot.

It stages that parent snapshot as Working state. It does not delete the current Commit and it does not publish automatically.

Given:

```text
A -> B -> C (LIVE)
```

staging a revert of C loads B into Working. After review and explicit Commit the history becomes:

```text
A -> B -> C -> D
              D = revert of C, content equivalent to B
```

This is safer than pretending an arbitrary old Commit can be inversed across unknown later edits. Older historical states use `Restore this version` instead.

The later Commit gets operation `revert`, retains the reverted LIVE Commit as its source and keeps normal parent lineage.

## Three reversal levels

The admin therefore has three deliberately separate reversal mechanisms:

```text
One supported editorial action
Activity row -> Undo

All unpublished work
Publication stage -> Reset staged changes

Published state
Commits -> Restore this version / Revert current commit -> review -> Commit
```

They must not be collapsed into one generic Undo button.

## Media retention

Publication snapshots refer to canonical media rather than copying binaries; the **checkpoint history/recovery root** must remain protected while restorable, and `Free storage` must not erase permanent Commit metadata or the current LIVE/Working restoration source.

The **single media reference, logical deletion, physical cleanup and queued-retry contract** is [MEDIA.md](MEDIA.md#deletion-and-cleanup), backed by its [one source/coverage matrix](architecture/media-reference-and-cleanup.md#fachlicher-abschluss-reader-symmetrie-zustandsfilter-und-schutzwurzeln). Publication does not define a second media walker or independent physical-file deletion policy.

### Optional rights revisions and cleanup outbox (source dev@434975e1bd09)

The existing Publication lock/transaction protects selection of immutable Rights artifacts for Working, checkpoints and Committed reads through `MediaRightsPublicationSelections`. Preparation and public delivery remain independently opt-in and require the associated migration; an audit of edited rights text does not imply that its file revision has already been prepared or published. Under logical media deletion the same transaction must durably enqueue physical cleanup; `PublicationMediaCleanupService` rechecks references and retains pending jobs where historical checkpoints prevent deletion. The [Media contract](MEDIA.md#deletion-and-cleanup) remains the sole file-retention and deletion policy; this paragraph defines only Publication ownership. No database/concurrency run was performed.

## Activity integration for publication controls

Reset (**when there are staged changes**), Restore and Revert staging operations record normal immutable Activity events in the `Publication` area:

- `publication.stage_reset`;
- `publication.version_restored`;
- `publication.commit_revert_staged`.

Those events target the concrete publication Commit when possible and link to the Commits view. A restore/revert staging event becomes part of the next Commit generation when it leaves Working different from LIVE.

## Transactions and locks

Commit, Reset, Restore and Revert use the shared publication advisory lock. Replacement of tracked Working/LIVE tables happens transactionally. Commit must re-evaluate the Working/LIVE difference under that lock; it also takes explicit `SHARE` table locks on tracked Working tables. The **Revert current commit** contract additionally requires that the **specific LIVE head confirmed in the UI** and its parent be read or revalidated **under that same serialization boundary** before Working is replaced. A successful `can_revert` recheck alone is insufficient: the selected checkpoint ID must remain the expected target at the Domain mutation boundary. The present `Activity::performRevertCurrentCommit()` forwards no confirmed checkpoint ID; `PublicationVersionService::stageRevertOfCurrent()` reads LIVE/Parent before the lock and checks only parent restorability after it. These are **two separate source-confirmed target/lock gaps**, not accepted correct behavior. Staging is global Working state; the first successful later Commit consumes the stored context, so a separate stale-source *after* such a Commit is **not** inferred without another specific source path. See the [canonical source/interleaving closure](architecture/publication-working-operations.md#fachlicher-abschluss-bestätigtes-revert-ziel-locks-und-zweiphasiger-commit).

Unsafe partial restore is not acceptable. If schema validation, FK constraints or snapshot hydration fail, the transaction rolls back rather than using `CASCADE`, silently skipping rows or publishing partial state.

## UI terminology

Use these labels consistently:

- `Activity`
- `Commits`
- `Staged`
- `Committed`
- `Pending changes`
- `Working changes`
- `Current live version` / `LIVE`
- `Review changes`
- `Reset staged changes` (compact action may read `Reset`)
- `Restore this version`
- `Revert commit`
- `Commit`

Avoid `flush`, `checkpoint` as artist-facing primary terminology, `staged for next publish`, or permanently disabled Undo actions.

## Verification

Durable tests should prove at least:

- Commit creates a 64-hex SHA-256 identity and complete snapshot rows;
- current LIVE state equals the committed snapshot after Commit;
- Reset restores Working exactly to LIVE while preserving Activity;
- Restore changes Working but not LIVE until Commit;
- a restore Commit records parent and source lineage;
- Revert stages only the currently confirmed LIVE parent, rejects a concurrent head change detected under the publication lock without replacing Working, and publishes later as a new Commit;
- Commit history cannot be deleted at the database boundary;
- media referenced by any retained version cannot be physically deleted;
- current Activity contains only uncommitted forensic Activities, while Commit details retain their committed Activities;
- ordering grouping stays visible even when the final order equals the starting order;
- date/hour filtering works in both Activity and Commits views beyond the default Activity window;
- Activity and Commit pagination retain filter/view context and support the shared 25/50/100 page-size choices;
- Activity aggregate query cost remains bounded;
- historical publication migrations do not depend on mutable runtime snapshot constants.
