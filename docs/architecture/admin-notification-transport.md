# Admin-Notifications: Transport, Grenzen und Negativpfade

**Quellstand:** `docs` bei `736b9656b406ae1802c71f884c534eb8dae6af82` (Anwendungscode aus `dev` `62abc92ffc8621b00adb50b93d198f59bc36dcd5`). Alle Befunde sind **statische Codeprüfung**, keine ausgeführten Livewire-/DB-/Browser-Tests. Die normative Produktregel bleibt ausschließlich im [Notification-Vertrag](../ADMIN-NOTIFICATION-CONTRACT.md); dieser Architekturbeleg beschreibt ihren Quellpfad und bekannte Grenzen.

## Eingänge und Speichergrenzen

| Ebene | Verantwortlicher Quellcode | Zustand / Bedeutung |
| --- | --- | --- |
| Editorial Mutation | konkrete Filament Action oder Livewire Callback | Muss den erfolgreichen **tatsächlichen** Domain-Change erkennen und explizit `AdminNotifier::notification()` rufen; die Domain-Speicherung oder ein Audit-Event genügt nicht |
| Audit/Presentation | [`AdminAuditService::record()`](../../app/Domain/Admin/AdminAuditService.php), [`AdminActivityNotificationContext`](../../app/Domain/Admin/AdminActivityNotificationContext.php) | Audit bleibt append-only; die request-lokale Presentation wird nach validem Audit-ID gesammelt, maximal neun Einträge, pro ID ersetzt |
| Fanout / Persistence | [`AdminNotifier`](../../app/Domain/Admin/AdminNotifier.php) | `success` konsumiert vorhandene, durch `AuditEvent::whereKey` bestätigte Change-/Details-Präsentationen; ein Aufruf kann 0 oder mehrere fachliche Kontexte, aber bei leerem Context einen eigenen Nachrichteneintrag erzeugen |
| Dauerhafte Inbox | [`AdminNotification`](../../app/Models/AdminNotification.php) | `firstOrCreate` nach `(user_id, source_id)`, Metadaten und Read-State; Created-Callback ruft Retention; kein SMTP-/E-Mail-Versand in diesem Pfad |
| Live-Transport | `AdminNotifier::surfaceNotification()` | Nur wenn aktueller authentifizierter Browser-Akteur der Empfänger ist: Livewire-Component-Event `admin-header-notification`, sonst bei Sessionverfügbarkeit begrenzte Pending-Liste |
| Browser-Presentation | [Admin-Panel `BODY_END`-Hook](../../app/Providers/Filament/AdminPanelProvider.php), [persistiertes Header-Partial](../../resources/views/filament/partials/admin-header-notification.blade.php), [`admin-notifications.js`](../../resources/js/admin-notifications.js) | UI-Queue mit max. acht wartenden IDs, Seen-Liste max. 48, Countdown und Dismiss. Kein zusätzlicher Inbox-Write, keine Löschung durch Header-Dismiss |
| Mailbox-Lesen | [`DashboardFeed`](../../app/Domain/Admin/DashboardFeed.php), [`DashboardFeedPins`](../../app/Domain/Admin/DashboardFeedPins.php), [`DashboardNotificationRetention`](../../app/Domain/Admin/DashboardNotificationRetention.php) | Filter nach `user_id`; Pin, Read/Unread, Delete und Retention sind Inbox-Operationen ohne neue Notification |

## Nachvollziehbare Sequenz

```mermaid
sequenceDiagram
    participant UI as Filament/Livewire Action
    participant Domain as Editorial Domain Service
    participant Audit as AdminAuditService
    participant Context as Request Activity Context
    participant Notifier as AdminNotifier
    participant DB as AdminNotification / DB
    participant Shell as Livewire Event or Session
    participant Header as Persisted Header JS

    UI->>Domain: Validate / mutate
    alt Actual successful change with Activity
        Domain->>Audit: record successful change
        Audit->>Context: push(audit id, Change/Details)
        UI->>Notifier: notification(status=success)
        Notifier->>Context: consumeRecorded()
        Context->>DB: Confirm Audit IDs still exist
        loop Each verified Activity presentation
            Notifier->>DB: firstOrCreate(user_id, activity:id)
            DB->>DB: Created callback may prune old inbox rows
            Notifier->>Shell: dispatch or bounded session pending (same actor)
            Shell->>Header: admin-header-notification or initial JSON
        end
    else Success without recorded Activity
        UI->>Notifier: notification(status=success)
        Notifier->>DB: firstOrCreate(user_id, source_id)
        Notifier->>Shell: surface to current recipient
    else No-op, invalid, or rolled back mutation
        UI-->>UI: No success notification call allowed
    else Explicit failure/warning message
        UI->>Notifier: notification(status=warning/danger)
        Notifier->>Context: discard pending Activity presentation
        Notifier->>DB: firstOrCreate if recipient exists
        Notifier->>Shell: surface if matching actor/session
    end
```

## Fallunterscheidung und tatsächlich verbleibende Risiken

- **Datenbankspeicherung vs. Erfolgsmeldung:** Weder `AdminAuditService::record()` noch `AdminNotification::firstOrCreate()` beweisen, dass eine konkrete UI-Action bei No-op/Fehlschlag korrekt unterdrückt. Der Change-Guard liegt **beim Aufrufer**. Es gibt im Notifier selbst keinen generischen Changed-Result-Check.
- **Erfolg mit mehreren Audit-Ereignissen:** `AdminActivityNotificationContext::push()` dedupliziert gleiche Audit-IDs in der Request-Liste und schneidet die Liste auf neun Einträge ab. `consumeRecorded()` filtert auf zum Abfragezeitpunkt vorhandene Audit-IDs und nichtleere Change-Texte. Der Success-Notifier erzeugt pro geliefertem Kontext eine Nachricht; es ist nicht pauschal genau eine pro Action.
- **Idempotenz ist nur auf die Mailbox beschränkt:** `firstOrCreate()` kann einen vorhandenen Datensatz zurückgeben; der danach gebildete Header-Payload besitzt **jedes Mal eine neue UUID**. Die JS-Seen-Liste unterdrückt Wiederholung **derselben Payload-UUID**, nicht eine Wiederholung von `source_id`. Mehrfache Header-Anzeige bei wiederholtem stabilen Source ist ein quellenbelegter, **nicht runtimegetesteter** Risikofall.
- **Empfänger-/Session-Grenze:** Explizit anderer User erhält keinen unmittelbaren Header im aktuellen Browser. Ohne gültigen Adminempfänger ist keine dauerhafte Inbox möglich; ohne Session und außerhalb des Livewire-Components gibt es auch keine Pending-Header-Übergabe. Eine Benachrichtigung kann damit einen validen API-Aufruf darstellen, ohne sichtbar zugestellt zu werden.
- **Retention-Grenzfall:** `AdminNotification::created()` startet `pruneFor()`. Bei bereits `limit` gepinnten Notifications ergibt sich `keepUnpinned = 0`; damit kann selbst die gerade gespeicherte ungepinnte Nachricht sofort wieder aus der Inbox entfernt werden. Das ist eine statisch ableitbare Bedingung, noch kein beobachteter Produktfehler. Die Response-/Header-Nachricht kann in diesem Fall eine bereits entfernte `notification_id` enthalten. Dies benötigt einen gezielten Fixture-/Runtime-Test im getrennten Testing-Workstream, keine ungetestete Korrektur auf `docs`.
- **Browser-Dismiss vs. Mailbox:** Header-Dismiss beendet nur den sichtbaren Präsentations-Lebenszyklus, während Read/Unread/Delete über den `DashboardFeed` auf einen nach `user_id` eingeschränkten Record gehen. Header-`@persist` und bounded `sessionStorage` der Navigation sind unterschiedliche Zustände; die Notification-Queue ist ausschließlich JS-intern.
- **E-Mail ist ein getrenntes Produkt:** Der Admin-Notification-Pfad enthält keinen Mail-Transport. Website-Contact versendet über die Contact-/Mailer-Pipeline und projiziert Kontaktnachrichten gegebenenfalls in den DashboardFeed. Das ist **nicht** die semantische Zustellung von `AdminNotifier`.

## Noch auszuführende Nachweise (nicht CI)

Auf einer ausdrücklich isolierten Testdatenbank und einer echten Filament-/Livewire-Instanz: je eine Erfolgsmutation mit einem und mit mehreren Audit-Kontexten; No-op und Validation-Fehler; zurückgerollte Mutation; Wiederholung mit gleicher `source_id` und doppeltem Header-Versuch; fremder User und fehlende Session; vollständiger Retention-Pin-Grenzfall; Livewire-Navigation mit Header-Queue; Dismiss und getrennte Read-/Delete-Änderung. Erwartete Message-Anzahlen werden aus den tatsächlich erzeugten Events abgeleitet, nicht aus der bloßen Anzahl der `notification()`-Aufrufe.

**Abdeckungsgrenze:** Keine Behauptung über tatsächliche Event-Zustellung, Livewire-Queue, Browser-Ankerposition, PostgreSQL-Constraints oder ausgelieferte E-Mails allein aus diesen Quellen.
