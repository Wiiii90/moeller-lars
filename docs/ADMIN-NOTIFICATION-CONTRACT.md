# Admin notification contract

## Scope

This document defines the durable contract for administrative feedback, persistent notifications and their relationship to Activity and Publication.

The admin has four separate concepts. They must not be collapsed into one another:

| Concept | Purpose | Persistence |
| --- | --- | --- |
| Toast | Immediate feedback for the action the current user just performed | Ephemeral |
| Notification | Information that deserves later attention in the admin inbox/feed | Persistent, retention-managed |
| Activity | Immutable factual history of successful administrative changes | Append-only |
| Publication | Working/live/version state and publication operations | Domain state/history |

A successful edit may create a toast and an Activity event without creating a persistent notification. A publication or processing failure may create both a toast and a persistent notification without inventing an Activity event for a change that did not succeed.

## Central notification authority

Persistent admin notifications originate from structured server-side application state through one central notifier/service. Application code must not infer persistent notifications from rendered HTML, Filament CSS classes, browser DOM mutations or client-side toast markup.

The central notifier owns the three explicit delivery intents:

- **toast** — immediate Filament feedback only;
- **inbox** — persistent `AdminNotification` only;
- **both** — immediate feedback plus persistent notification.

Callers choose the semantic delivery intent. `Toast = Notification` is not a system invariant.

The notifier is the normal application boundary around Filament notification construction. New admin code should not spread direct `Filament\Notifications\Notification::make()` calls when the same result belongs to the central feedback contract.

## What belongs in each channel

Toast-only examples include successful save/reorder/upload operations, mark read/unread confirmations, a successful undo, successful staged-state reset, successful version restore and successful publication.

Persistent notification examples include new contact messages that require attention, publication/preflight failures, background-job failures, media-processing failures, incomplete cleanup, meaningful storage-capacity warnings and other system conditions that remain relevant after the initiating request ends.

Use **both** when a condition deserves immediate interruption and later retrieval, for example a publication failure or media-processing failure encountered by the current user.

Do not use the inbox as a duplicate Activity feed. Routine successful editorial mutations already have durable Activity history.

## Persistence model

`AdminNotification` is the persistent inbox/history record. It remains user-scoped and retention-managed.

Every persistent notification has an origin-generated `source_id`. The database uniqueness contract `(user_id, source_id)` provides idempotency. `source_id` must identify the source event or condition rather than a rendered toast instance.

Examples:

```text
contact-message:184
publication-failed:attempt-481
media-processing:asset-72:job-938
storage-capacity:critical:2026-09-14
```

Structured context may include bounded fields such as:

- semantic type/severity;
- target action URL and label;
- related entity type/id;
- related audit event id;
- related publication/checkpoint/version id;
- small controlled metadata needed to render the notification.

Do not turn `AdminNotification` into a second audit log or an unbounded payload store. Secrets, credentials, private request dumps and arbitrary HTML do not belong in notification metadata.

## User isolation

Persistent notifications are owned by one admin user unless a future explicit broadcast facility says otherwise.

All reads, searches, read/unread mutations, deletes, pins and retention operations must remain scoped by `user_id`. Never accept a notification id from the browser and mutate it without also constraining it to the authenticated actor.

Server-side jobs may create notifications without a browser being open, but they must explicitly resolve the intended recipient(s).

## Retention and deletion

Notification retention is independent from Activity retention.

- `AdminNotification` records may be marked read/unread, deleted and pruned according to the existing dashboard-notification retention policy.
- Pinned notifications remain protected according to the existing retention contract.
- Activity/audit events are append-only and are not deleted merely because a related notification is deleted or pruned.
- Publication versions/checkpoints are governed by their own retention/versioning contract.

A notification may link to Activity or Publication, but those references do not transfer notification deletion semantics to the referenced records.

## Transaction semantics

Success feedback that depends on a database mutation must not be emitted as durable truth before that mutation is committed.

When a transaction can still roll back, success toasts/persistent notifications must be dispatched only after successful commit or from a point where the domain service has established success. A failed operation must not leave behind a persistent notification claiming that it succeeded.

Failure notifications may be emitted from the failure path when their underlying failure fact is itself valid to retain.

## Activity relationship

Activity answers **what successful administrative change happened**. Notifications answer **what still deserves the user's attention**.

Where useful, a persistent notification may carry a nullable relation/reference to the related audit event and offer `Open activity` or equivalent context. The relationship is navigational/contextual only:

- deleting a notification never deletes Activity;
- marking a notification read never changes Activity;
- undoing an Activity event is a new domain mutation and audit event, not a notification mutation.

## Publication relationship

Publication success normally needs only immediate feedback because the durable publication/checkpoint/version state and Activity history already record the operation.

Publication failures, blockers or degraded cleanup may deserve persistent notification. Such notifications should link to the canonical publication review/preflight surface rather than duplicating its entire state in notification text.

Publication restore/revert/reset operations continue through canonical domain services and Activity. Notifications do not become a rollback mechanism.

## Dashboard/feed integration

The existing `DashboardFeed` remains the canonical mixed dashboard inbox/feed projection. Notifications are one feed source alongside the other supported entry types; they do not create a second dashboard architecture.

Notification-specific persistence remains in `AdminNotification`, while common feed behavior such as search, filtering, pagination, read/unread handling and pinning continues through the dashboard feed contract.

## UI details

Persistent-notification details use the shared admin dialog/viewer primitives. Context actions such as `Open record`, `Open activity`, `Review staged changes` or `Mark unread` appear only when they are semantically available.

Notification status/severity is factual state, not decorative styling. Do not add page-local dialog, badge or action systems when shared admin primitives already own those roles.

## Forbidden legacy capture path

The following architecture is explicitly not part of the durable system:

```text
Filament toast
  -> render browser DOM
  -> MutationObserver
  -> parse notification markup/classes
  -> Livewire recorder
  -> AdminNotification
```

After callers have moved to the central notifier, remove the recorder component, render-hook partial, DOM observer, markup parsers and recorder-specific fallback code. Do not retain DOM capture as a compatibility fallback because parallel capture paths create duplicate and semantically ambiguous notifications.

## Verification

Durable coverage should prove at least:

- toast-only delivery does not create `AdminNotification` rows;
- inbox delivery works without a browser session rendering a toast;
- both delivery persists and sends immediate feedback;
- `(user_id, source_id)` remains idempotent;
- notification reads/mutations remain user-scoped;
- retention and pin protection remain correct;
- structured action/context projection remains bounded and safe;
- the DOM/MutationObserver recorder is absent;
- dashboard notification filtering reflects the current feed sources.

Tests should protect these semantics rather than Filament's private DOM/class names.
