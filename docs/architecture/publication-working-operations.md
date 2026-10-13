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

## 3. Reset: Working → aktueller LIVE-Inhalt

[Sequenzdiagramm](generated/working-operations/reset.mmd)

Reset verlangt den bestätigten Dialog, dann `requireActor()`. Unter Advisory Lock und Schema-Guard vergleicht `pendingCount()` die getrackten Tabellen. **Falls der Diff null ist:** `clearWorkingContext()`, kein neuer Audit-Event, kein Media-Cleanup, Info-Notification `Nothing staged`. **Falls Änderungen vorliegen:** bestehende Working-Medien für Cleanup vormerken, sämtliche Working-Tabellen mit `committed.*` ersetzen, Working-Kontext löschen, ein `publication.stage_reset`-Audit-Event schreiben und nach erfolgreichem DB-Commit `drain()` ausführen. Die UI meldet `Staged changes reset`.

Es werden **keine historischen Audit-Events und keine PublicationCheckpoints gelöscht**. Reset ist kein Restore einer frei gewählten Version, sondern ein Zurücksetzen der aktuellen Working-Kopie auf das **jetzige LIVE**.

## Nachfolgender Commit und History

`PublicationService::commit()` prüft unter Lock erneut den fachlichen Working/LIVE-Diff. Nur bei Diff entstehen ein neuer Checkpoint, Snapshot-Manifest und die Zuordnung ungebundener Audit-Events zur neuen Publication-Generation. Bei Restore wird der neue Checkpoint mit `operation=restore` und `source_publication_checkpoint_id=<wiederhergestellte Version>` angelegt; bei Revert entsprechend `operation=revert` und Source = vorheriger LIVE-Checkpoint. Bei **keinem** verbleibenden Diff löscht `commit()` den Working-Kontext und gibt `null` zurück, ohne neuen Checkpoint.

Die bestehenden [Publication-Versioning-Integrationstests](../../tests/Integration/Publication/PublicationVersioningTest.php) haben Quellfälle zu Reset ohne History-Verlust, Restore-plus-Commit, Revert-plus-Commit und FK-gerechter Medien-Reihenfolge. Diese Testdateien wurden geprüft, aber **nicht in dieser Runde ausgeführt**.

### Qualitätssicherung und Grenzen

Die drei Katalogflows besitzen **konkrete Code-Zeilen als Anker**; der [lokale Generator](../../scripts/generate-architecture-working-operations.php) kontrolliert diese bei `--write` bzw. `--check` und baut `restore.mmd`, `revert.mmd`, `reset.mmd`. Das beweist, dass die Stellen noch existieren, nicht dass jeder Lauf alle Pfade nimmt. Die drei Mermaid-Dateien wurden unmittelbar aus dem Manifest geschrieben. Ein vollständiger lokaler Checkout-Test, konkurrierende Datenbankoperationen, echte Livewire-Klicks und Restore-Fälle mit fehlenden Media-Payloads sind weiterhin offen.
