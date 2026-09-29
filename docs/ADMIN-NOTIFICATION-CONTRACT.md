# Admin notification contract

## Scope

This document defines the durable contract for administrative feedback, persistent notifications and their relationship to Activity and Publication.

The admin has four separate concepts. They must not be collapsed into one another:

| Concept | Purpose | Persistence |
| --- | --- | --- |
| Header feedback | Immediate feedback for the action the current user just performed | Ephemeral |
| Notification | Information that deserves later attention in the admin inbox/feed | Persistent, retention-managed |
| Activity | Immutable factual history of successful administrative changes | Append-only |
| Publication | Working/live/version state and publication operations | Domain state/history |

A successful edit may create header feedback and an Activity event without creating a persistent notification. A publication or processing failure may create both header feedback and a persistent notification without inventing an Activity event for a change that did not succeed.

## Central notification authority

Administrative feedback and persistent notifications originate from structured server-side application state through `AdminNotifier`.

The notifier owns three explicit delivery intents:

- **feedback** — immediate ephemeral feedback for the project-owned sticky-header header feedback surface only;
- **inbox** — persistent `AdminNotification` only;
- **both** — immediate header feedback plus a persistent `AdminNotification`.

Immediate feedback does **not** use Filament notification objects as transport. During a Livewire request, `AdminNotifier::feedback()` dispatches the project-owned `admin-header-feedback` event directly from the active component. Outside a Livewire request it places a bounded message in the session queue for the next admin render.

Persistent notifications are written directly to `AdminNotification`. They do not depend on browser markup, a rendered framework notification, DOM inspection or the header feedback being visible.

Application code must not construct or send framework notifications for normal admin feedback. Filament resource hooks that require a nullable notification return type may be overridden only to return `null` after project feedback has been emitted.

## What belongs in each channel

Ticker-feedback examples include successful save/reorder/upload operations, mark read/unread confirmations, successful Undo, successful staged-state reset, successful version restore and successful publication.

Persistent notification examples include publication/preflight failures, background-job failures, media-processing failures, incomplete cleanup, meaningful storage-capacity warnings and other system conditions that remain relevant after the initiating request ends.

Contact messages are already durable first-class `DashboardFeed` entries. Do not create a second `AdminNotification` for the same incoming contact merely to make it persistent; create an `AdminNotification` only for a separate condition that is not already represented by the contact record itself.

Use **both** when a condition deserves immediate interruption and later retrieval, for example a publication failure or media-processing failure encountered by the current user.

Do not use the inbox as a duplicate Activity feed. Routine successful editorial mutations already have durable Activity history.

## Persistence model

`AdminNotification` is the persistent inbox/history record. It remains user-scoped and retention-managed.

Every persistent notification has an origin-generated `source_id`. The database uniqueness contract `(user_id, source_id)` provides idempotency. `source_id` identifies the source event or condition rather than an ephemeral header-feedback message.

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

Success feedback that depends on a database mutation must not be emitted as durable truth before that mutation is committed. No-op saves must emit neither mutation Activity nor success header feedback.

When a transaction can still roll back, success feedback or persistent notifications must be dispatched only after successful commit or from a point where the domain service has established success. A failed operation must not leave behind a persistent notification claiming that it succeeded.

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

## Header feedback runtime

Transient feedback is rendered only in the project-owned ticker inside the persistent sticky admin header.

The runtime path is:

```text
Admin mutation
  -> AdminNotifier::feedback()
  -> project-owned Livewire event or bounded session queue
  -> admin-header-feedback
```

The header feedback surface is static and keeps stable topbar geometry. The newest feedback text is shown in place. Each further feedback event increments the fixed `+N` counter while replacing the visible text with the newest message; identical messages still count as additional feedback. The surface does not scroll, animate, auto-play a queue, dismiss itself or move neighboring header controls. On narrow screens the body text may be suppressed before header controls become inaccessible.

Persistent-notification details use the shared admin dialog/viewer primitives. Context actions such as `Open record`, `Open activity`, `Review staged changes` or `Mark unread` appear only when they are semantically available.

Notification status/severity is factual state, not decorative styling. Do not add page-local dialog, badge or action systems when shared admin primitives already own those roles.

## Forbidden parallel feedback paths

The project must not maintain a second transient-feedback renderer alongside the header feedback surface.

Forbidden paths include:

- constructing or sending Filament notification objects for normal admin feedback;
- rendering stacked floating framework notification cards;
- intercepting framework notification markup and translating it afterward;
- observing the DOM to discover administrative events;
- keeping a compatibility bridge that can produce duplicate header feedback + framework feedback.

The ticker event/session channel and `AdminNotification` persistence are the two intentional notification mechanisms.

## Verification

Durable coverage should prove at least:

- header feedback does not create `AdminNotification` rows;
- inbox delivery works without a browser session;
- `both` persists and sends immediate header feedback;
- `(user_id, source_id)` remains idempotent;
- notification reads/mutations remain user-scoped;
- retention and pin protection remain correct;
- structured action/context projection remains bounded and safe;
- framework notification construction is absent from normal admin feedback;
- the DOM/markup capture path is absent;
- dashboard notification filtering reflects the current feed sources.

Tests should protect these semantics rather than framework-private presentation classes.
