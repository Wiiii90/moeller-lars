# Activity → Working: Restore, Revert und Reset

**Quellenbasis:** `app/Filament/Pages/Activity.php`, `app/Filament/Support/AdminPublicationHistory.php`, `app/Domain/Publication/PublicationVersionService.php` und `app/Domain/Publication/PublicationService.php` bei `dev` = `337da15bc7d4fcbae45f0bb3ed38a99fa4a5e4b7`. [JSON-Verzeichnis](publication-working-operations.json) belegt konkrete Quellstellen; die [drei erzeugten Diagramme](generated/working-operations/) sind daraus reproduzierbar. **Statische Quellprüfung, kein durchgeführter Browser- oder Datenbanktest.**

Diese Operationen sind **nicht** identisch mit einem individuellen Activity-Undo und **nicht** identisch mit Commit. Sie ersetzen Working-Tabellen; der öffentlich gelesene `committed`-Zustand bleibt zunächst unverändert. Die Activity-UI verwendet Filament-Confirmation-Actions (je eine Einzel-/Selection-Variante für Restore und Revert und eine Reset-Action).

## UI-Guards und eindeutige Zuständigkeiten

| Operation | UI-Auswahl und Vorprüfung | Domain-Methoden |
| --- | --- | --- |
| Restore | `AdminPublicationHistory::project`: `can_restore = ! live && snapshot_available && schema_hash == current_schema_hash`. Genau eine Selection bzw. Detail-Action, dann erneute Prüfung. | `PublicationVersionService::stageVersion(checkpoint, actor)` |
| Revert | `can_revert = live && parent snapshot_available && parent.schema_hash == current_schema_hash`. Nur aktueller LIVE-Eintrag und restorable Parent; Auswahl wird erneut geprüft. | `PublicationVersionService::stageRevertOfCurrent(actor)` |
| Reset | Bestätigungsdialog „Reset changes?“, keine Checkpoint-Auswahl. | `PublicationVersionService::resetStagedChanges(actor)` |

Die **UI-Eligibility** ist keine Sicherheitsgrenze: `PublicationVersionService::assertRestorable` und `PublicationSchemaGuard::assertParity` prüfen vor der Datenmutation erneut. Für Restore wird die gewählte `PublicationCheckpoint`-ID im Action-Handler geladen; ungültige ID führt zu 404, fehlende Snapshot-Verfügbarkeit oder Schemaabweichung zu ValidationException und Warn-Notification.

## Serialisierungsgrenze: Wer bestimmt welches Ziel unter Lock?

**Lock-Source-Trace erneut geprüft bei `dev@11f7e101` (`PublicationService`, `PublicationVersionService`, `PublicationSnapshot`); ohne DB-Ausführung.** Alle vier Domain-Operationen besitzen einen `DB::transaction`-Aufruf und erwerben `pg_advisory_xact_lock(PublicationSnapshot::LOCK_KEY)`; daraus folgt **nur eine gemeinsame Serialisierung der teilnehmenden Operationen**, nicht automatisch eine unveränderliche Bestätigung aus einer früheren UI-Projektion.

| Domain-Operation | Vor dem Shared-Lock | Unter Shared-Lock | Nach SQL-Commit |
| --- | --- | --- | --- |
| `PublicationService::commit` | Eingabe-Längenprüfung; UI-/Preflight-Pending kann veraltet sein | `assertParity()`; zusätzlich `LOCK TABLE public.<tracked> IN SHARE MODE`; **PendingSummary erneut**; bei null Diff nur Kontext löschen, sonst Parent/Historie bestimmen, LIVE-Tabellen ersetzen, Checkpoint/Events/Receipts verknüpfen | `mediaCleanup->drain()` **nur mit neuem Checkpoint** |
| `stageVersion(checkpoint)` / Restore | Ausgewählte Checkpoint-ID aus Admin-Request; ein altes UI-`can_restore` ist nicht autoritativ | `assertParity()`, `assertRestorable(checkpoint)`, Diff, Working-Replace, Restore-Context, Audit; **kein früher Null-Diff-No-op** | `drain()`, dann geänderten Wert + Checkpoint zurückmelden |
| `resetStagedChanges` | Confirmation/Actor | `assertParity()`, aktuelle `pendingCount()`; Null-Diff löscht nur Working-Context; andernfalls Working aus LIVE ersetzen, Context löschen, Audit | `drain()` **nur bei geänderten Rows** |
| `stageRevertOfCurrent` | **`currentLiveCheckpoint()` und `live->parent()` werden vor BEGIN/Lock gelesen**, fehlende Werte sofort abgelehnt | `assertParity()`, **nur** `assertRestorable(parent)`; keine erneute Prüfung, ob der zuvor gelesene LIVE noch aktuell ist; Working-Replace, Context-Source = gelesener LIVE, Audit | `drain()`; Rückgabe von refreshed Parent/LIVE (Refresh ist keine atomare Neuauswahl unter Lock) |

Die `SHARE`-Tabellensperren bei Commit und die für das Ersetzen nötigen `TRUNCATE`-Sperren sind **zusätzliche** PostgreSQL-Datenbankmechanismen; die Domaincode-Stellen beweisen keine ausgeführte Mehrsession-Serialisierungsprobe. Fehler beim nachgelagerten **Datei-Cleanup** liegen außerhalb der bereits committeten Working-/LIVE-Transaktion. Die konkrete hypothetische H→H2-Pfaddifferenz bleibt ausschließlich im [Revert-Race-Diagramm](#statische-race-sequenz-revert-ziel-vor-dem-lock), nicht in einem zweiten generischen Publication-Diagramm. Die **normative** Forderung, den Revert-Head unter derselben Serialisierungsgrenze zu revalidieren, steht im [Publication-Vertrag](../ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md#transactions-and-locks). Zwei unabhängige PostgreSQL-Sessions sind weiterhin erforderlich, um tatsächliche Konflikt-/Rollback-Semantik zu belegen.

## 1. Restore: historischer Snapshot → Working

[Sequenzdiagramm](generated/working-operations/restore.mmd) · Quellen: [`Activity`](../../app/Filament/Pages/Activity.php), [`PublicationVersionService`](../../app/Domain/Publication/PublicationVersionService.php)

1. `Activity` prüft ausgewählten Commit per `AdminPublicationHistory`, lädt `PublicationCheckpoint` und verlangt einen Admin-Actor.
2. In einer **Transaktion mit Advisory Lock** folgen Schema-Parität, Verfügbarkeit und Hash-Kompatibilität des Snapshots sowie ein Vergleich zum gegenwärtigen Working.
3. Noch aus dem bisherigen Working benötigte Medien werden für Cleanup berücksichtigt; die 15 getrackten Working-Tabellen werden geleert und in `PublicationSnapshot::RESTORE_TABLES`-Reihenfolge aus `publication_version_rows` geladen.
4. Historische `custom_page_settings.blocks` werden im Restore durch `HistoricalCustomPageBlockCanonicalizer` an die heutige Komponentenform angepasst. Ein Restore ist folglich **nicht zwingend byteidentisch** mit den ursprünglichen JSON-Payloads.
5. `publication_working_context` erhält `operation=restore` und `source_publication_checkpoint_id=<Ziel-ID>`; `publication.version_restored` wird auditiert. Nach Transaktions-Commit läuft `PublicationMediaCleanupService::drain()`.
6. Nach erfolgreichem Rückgabewert meldet `Activity` eine **explizite** Success-Notification und zeigt die erneut berechnete Pending-Zahl. LIVE und frühere Checkpoints wurden noch nicht publiziert/verändert.

**No-op-Unterscheidung:** `stageVersion` besitzt *keinen* frühen Rücksprung bei `changed=0`. Es setzt auch dann Working-Kontext und auditiert den Restore-Vorgang. Die Änderungssumme ist der Vergleich gegen den **vorherigen Working-Zustand**, nicht automatisch die Diff-Zahl gegen LIVE nach der Operation.

## 2. Revert: aktuelles LIVE → Parent → Working

[Sequenzdiagramm](generated/working-operations/revert.mmd)

`AdminPublicationHistory` bietet Revert nur für den aktuellen LIVE-Checkpoint mit kompatiblem Parent-Snapshot. `stageRevertOfCurrent` ermittelt **selbst** den neuesten LIVE-Checkpoint und seinen Parent, prüft beide auf Existenz, öffnet dann eine Transaktion mit demselben Advisory Lock und kontrolliert Schema/Parent. Vorhandene Working-Änderungen werden durch den historischen Parent-Inhalt ersetzt. Der Kontext vermerkt `operation=revert` und **die ID des zu revertierenden LIVE-Commits**, nicht die ID des Parent; das Audit-Event heißt `publication.commit_revert_staged`.

Die UI meldet danach `Commit revert staged`. Erst ein späterer `PublicationService::commit` veröffentlicht den Parent-Inhalt als **neuen** Commit mit `operation=revert`, Parent = bisheriger LIVE und Source = bisheriger LIVE. Die historischen Checkpoints bleiben bestehen.

**Offene konkurrierende Änderungsgrenze:** Die Ermittlung von `currentLiveCheckpoint()` und `parent()` erfolgt im Code **vor** der Transaktion mit Advisory Lock. Eine mögliche Race-Bedingung mit parallelem Commit muss gesondert unter realen DB-Transaktionen geprüft werden; die bloße Existenz des Locks beweist keine vollständige Serialisierung dieses vorgelagerten Reads. Keine eigenmächtige Runtime-Reparatur auf dem Dokumentationsbranch.

### Statische Race-Sequenz: Revert-Ziel vor dem Lock

```mermaid
sequenceDiagram
    participant R as Request R: Revert current
    participant C as Request C: Publish new commit
    participant DB as PostgreSQL / Publication

    R->>DB: currentLiveCheckpoint() -> H
    R->>DB: H.parent() -> P
    Note over R,DB: Noch keine Revert-Transaktion und kein Advisory Lock
    C->>DB: BEGIN; pg_advisory_xact_lock
    C->>DB: Working -> committed; checkpoint H2 (parent H)
    C->>DB: COMMIT; Lock frei
    R->>DB: BEGIN; pg_advisory_xact_lock
    R->>DB: assertParity(); assertRestorable(P)
    R->>DB: Replace Working from P
    R->>DB: Set working_context(revert, source = H)
    R->>DB: COMMIT; Lock frei
```

In diesem zulässigen **hypothetischen Interleaving** wird nach dem neu erstellten LIVE **H2** weiterhin der ursprünglich gelesene historische Parent **P** ins Working gestellt; die Revert-Metadaten beziehen sich jedoch auf **H**. [`stageRevertOfCurrent()`](../../app/Domain/Publication/PublicationVersionService.php) prüft innerhalb des Locks `assertRestorable(P)`, berechnet einen Pending-Diff und ändert Working, prüft aber **nicht erneut**, ob `currentLiveCheckpoint() === H`. Der Checkpoint-Reihenfolgeanker im späteren [`PublicationService::commit()`](../../app/Domain/Publication/PublicationService.php) ist unabhängig davon **unter Lock** und kann H2 als Parent der neu veröffentlichten Version verwenden, während der gespeicherte Revert-Source noch H ist.

Das ist eine mögliche **Auseinanderentwicklung von Nutzerintention, gewähltem Working-Snapshot und Revert-Provenienz**, kein Beleg für eine bereits durchgeführte Doppelveröffentlichung, verlorene Daten oder fehlgeschlagene Transaktion. Eine spätere kanonische Korrektur müsste unter der **gleichen** Serialisierungsgrenze sowohl LIVE und Parent bestimmen beziehungsweise die ursprünglich vom Nutzer bestätigte aktuelle HEAD-ID erneut gegen LIVE prüfen, bevor Working überschrieben und der Revert-Kontext gespeichert wird. Sie darf den bestehenden History-/Receipt-Vertrag nicht umgehen.

**Reproduzierbarer Testauftrag für isolierte PostgreSQL-Instanz:** mit zwei unabhängigen DB-Sessions/Revert-vor-Lock- und Commit-Interleaving H→H2 erzwingen, den Revert-Guard und seine erwartete Validation/Retry-Semantik prüfen, danach Working-Hash, `publication_working_context`, Parent/Source des nächsten Commits, Audit und User-Notification kontrollieren. Keine Änderung am Produktions- oder Preview-Schema für diesen Nachweis.



## Fachlicher Abschluss: bestätigtes Revert-Ziel, Locks und zweiphasiger Commit

**Quellbasis:** `dev@11f7e101` — `Activity::performRevertSelectedCommit()`, `Activity::performRevertCurrentCommit()`, `AdminPublicationHistory::checkpoint()/project()`, `PublicationVersionService::stageRevertOfCurrent()/resetStagedChanges()/stageVersion()`, `PublicationService::commit()`. **Testquellen, eigenständiger Stand:** `testing@14af6804`, `tests/Integration/Publication/PublicationRestoreTest.php`, `PublicationCommitWorkflowTest.php` und `PublicationVersionHistoryTest.php`; Vergleichsquelle `dev@11f7e101`, `tests/Integration/Publication/PublicationVersioningTest.php`. Die auf `testing` vorhandenen Fälle sind **Assertions im Repository**, keine durch diesen Workstream ausgeführten Tests und kein Nachweis der Kompatibilität mit `dev`.

| Zeitfenster / Fall | Tatsächlicher Source-Guard und Zustand | Fachliche Grenze / nötiger Negativfall |
| --- | --- | --- |
| Veraltete Commits-Auswahl **vor** der erneuten UI-Prüfung | `Activity` holt den selektierten Checkpoint per `AdminPublicationHistory::checkpoint(id)` neu; `can_revert` verlangt LIVE und kompatiblen Parent. | Ein inzwischen nicht mehr aktuelles H wird bei dieser erneuten Abfrage zurückgewiesen. Das ist **nur ein UI-Recheck**, kein Lock und keine dauerhafte Bindung der Bestätigung. |
| Neuer H2 **zwischen** erfolgreichem UI-Recheck und Domain-Zielauswahl | `performRevertCurrentCommit()` reicht **keine bestätigte H-ID** an `stageRevertOfCurrent(actor)` weiter. Der Service wählt seinerseits `currentLiveCheckpoint()`/Parent neu. | Bei einem inzwischen aktuellen H2 kann die Domain den **anderen** Head H2 statt des bestätigten H stagen. Die Anforderung „Revert genau des bestätigten aktuellen Commits“ braucht eine unter Lock verglichene erwartete H-ID; allein „den jeweils neuesten Head unter Lock wählen“ schützt nicht die Nutzerintention. |
| Neuer H2 **zwischen** Domain-Read H/Parent P und Advisory Lock | Der Service schließt H/P in die Closure ein und prüft nach `pg_advisory_xact_lock` ausschließlich `assertRestorable(P)`. | Der bestehende [H→H2-Interleaving-Beleg](#statische-race-sequenz-revert-ziel-vor-dem-lock) zeigt eine mögliche Staging-/Provenienzabweichung. Es gibt weder einen zweiten LIVE-Recheck noch einen Vergleich H mit einem erwarteten ID-Token. |
| Revert bereits gestaged; **ein anderer Commit-Aufruf** folgt vor dem eigenen | Working und `publication_working_context` sind **global**, nicht actor-/Session-gebunden. Der erste erfolgreiche `PublicationService::commit()` nimmt unter Lock die dann aktuellen Working-Daten, Parent und Source auf und **löscht** den Working-Kontext. Ein späterer Net-zero-Commit erzeugt keinen zweiten Checkpoint. | Hier ist **kein eigenständiger fortlebender stale Revert-Context nach einem erfolgreichen Commit** aus dem Normalpfad belegt. Offen ist stattdessen die tatsächliche Mehrnutzer-/Review-Akzeptanz, wenn ein anderer Actor das bereits gestagte Working publiziert. Dies nicht mit dem vor-lockigen H→H2-Fehler vermengen. |
| `stageVersion` oder `stageRevertOfCurrent` mit `changed=0` gegenüber bisherigem Working | Beide Methoden schreiben trotzdem Kontext und Audit, rufen nach Commit Cleanup auf und die UI zeigt Staging-Erfolg. `changed` vergleicht Working mit dem *gewählten Snapshot*, **nicht** zwingend Working mit LIVE. | `PendingSummary` kann danach 0 **oder** größer 0 sein. Ein späterer `commit()` mit 0 LIVE-Diff löscht nur den Kontext, ohne neuen Checkpoint; das bereits erzeugte Stage-Audit wird dadurch nicht automatisch gelöscht. Getrennter No-op-/Audit-/Notifier-Test erforderlich. |
| Schema-, Snapshot- oder FK-Fehler während Working-Replacement | `assertParity()`, `assertRestorable()` und SQL-Replace liegen in der Transaktion. `drain()` folgt **erst nach erfolgreichem** DB-Commit; Cleanup-Fehler dürfen nicht mit zurückgerollter Datenmutation gleichgesetzt werden. | Zwei-Sessions-Test muss Working-Hash, Kontext, Checkpoint-/Audit-/Notification-Kardinalität, DB-Rollback und Cleanup-Recovery separat prüfen. |

**Authored Coverage und Grenze:** `testing/PublicationRestoreTest.php` enthält die sequentiellen Reset-, Restore-plus-Commit- und Revert-plus-Commit-Assertions einschließlich Parent/Source und Medien-FK-Reihenfolge. `testing/PublicationCommitWorkflowTest.php` prüft Pending/Recheck, HTTP-LIVE-vs-Preview, Commit-Idempotenz und einzelne Livewire-Notifier-Assertions. `testing/PublicationVersionHistoryTest.php` prüft Hash/Snapshot und DB-Deletion-Guard. `dev/PublicationVersioningTest.php` enthält ebenfalls sequentielle Restore-/Revert- und Retention-Fälle. **Keine dieser gelesenen Assertions** erzwingt den zweifachen Interleaving-Abstand UI→Domain beziehungsweise Domain-Read→Lock, die Mehractor-Staging-Übernahme oder den Zero-Diff-Staging-Feedbackfall.

**Endlicher Dokumentationsbefund:** Besitz und Serialisierungsgrenzen sind quellenbasiert erklärt; die zwei **Zielidentitäts-/Head-Race-Fenster** bleiben Implementierungs-/Laufzeitarbeit auf `dev`/`testing`. Für einen späteren isolierten Test: bestätigte H-ID im UI festhalten; per zweiter PostgreSQL-Session H2 (a) vor der Domain-Zielauswahl und (b) zwischen Domain-Read und Lock publizieren; dabei niemals Working überschreiben, wenn der bestätigte H nicht mehr LIVE ist; erwartete Validation/Warnung statt Erfolg, unveränderte Working-Daten, Kontext, Audit/Receipt/Notifier und kein neuer Revert-Checkpoint. Danach den getrennten mehractorigen Staging-/Commit- und `changed=0`-Fall prüfen. Der **normative Owner** bleibt [Admin Activity/Publication – Transactions and locks](../ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md#transactions-and-locks); hier stehen nur Quellbelege, Interleavings und Testgrenzen.

## 3. Reset: Working → aktueller LIVE-Inhalt

[Sequenzdiagramm](generated/working-operations/reset.mmd)

Reset verlangt den bestätigten Dialog, dann `requireActor()`. Unter Advisory Lock und Schema-Guard vergleicht `pendingCount()` die getrackten Tabellen. **Falls der Diff null ist:** `clearWorkingContext()`, kein neuer Audit-Event, kein Media-Cleanup, Info-Notification `Nothing staged`. **Falls Änderungen vorliegen:** bestehende Working-Medien für Cleanup vormerken, sämtliche Working-Tabellen mit `committed.*` ersetzen, Working-Kontext löschen, ein `publication.stage_reset`-Audit-Event schreiben und nach erfolgreichem DB-Commit `drain()` ausführen. Die UI meldet `Staged changes reset`.

Es werden **keine historischen Audit-Events und keine PublicationCheckpoints gelöscht**. Reset ist kein Restore einer frei gewählten Version, sondern ein Zurücksetzen der aktuellen Working-Kopie auf das **jetzige LIVE**.

## Nachfolgender Commit und History

`PublicationService::commit()` prüft unter Lock erneut den fachlichen Working/LIVE-Diff. Nur bei Diff entstehen ein neuer Checkpoint, Snapshot-Manifest und die Zuordnung ungebundener Audit-Events zur neuen Publication-Generation. Bei Restore wird der neue Checkpoint mit `operation=restore` und `source_publication_checkpoint_id=<wiederhergestellte Version>` angelegt; bei Revert entsprechend `operation=revert` und Source = vorheriger LIVE-Checkpoint. Bei **keinem** verbleibenden Diff löscht `commit()` den Working-Kontext und gibt `null` zurück, ohne neuen Checkpoint.

Die bestehenden [Publication-Versioning-Integrationstests](../../tests/Integration/Publication/PublicationVersioningTest.php) haben Quellfälle zu Reset ohne History-Verlust, Restore-plus-Commit, Revert-plus-Commit und FK-gerechter Medien-Reihenfolge. Diese Testdateien wurden geprüft, aber **nicht in dieser Runde ausgeführt**.

### Qualitätssicherung und Grenzen

Die drei Katalogflows besitzen **konkrete Code-Zeilen als Anker**; der [lokale Generator](../../scripts/generate-architecture-working-operations.php) kontrolliert diese bei `--write` bzw. `--check` und baut `restore.mmd`, `revert.mmd`, `reset.mmd`. Das beweist, dass die Stellen noch existieren, nicht dass jeder Lauf alle Pfade nimmt. Die drei Mermaid-Dateien wurden unmittelbar aus dem Manifest geschrieben. Ein vollständiger lokaler Checkout-Test, konkurrierende Datenbankoperationen, echte Livewire-Klicks und Restore-Fälle mit fehlenden Media-Payloads sind weiterhin offen.
