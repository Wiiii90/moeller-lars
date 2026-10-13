# Publication: Working, LIVE und öffentlicher Lesezugriff

**Quellbelege:** `PublicationSnapshot.php`, `PublicationService.php`, `PublicationVersionService.php`, `PublicationReadContext.php`, `CommittedRead.php`, `PublicationPostgresGrammar.php`, `UseCommittedPublicState.php`, `bootstrap/app.php` (Beleg-HEAD siehe [Inventar](inventory.md)). Vertragsregeln verbleiben vorläufig in [ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md](../ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md).

## Physische und logische Zustände

- **Working:** `public.<tracked_table>` — editorielle Änderungen.
- **LIVE:** `committed.<tracked_table>` — nur ausdrücklich committed Änderungen.
- **Commit-History:** `publication_checkpoints`, logische Version-Row-Schnittstelle, Manifeste und Shared Payloads.
- **Activity/Audit:** `audit_events` plus Zuordnung `publication_checkpoint_events`; keine zweite Content-Quelle.
- **Recovery:** Undo-Receipts und restorable Versionen mit eigenen Aufbewahrungsbedingungen.

Die Konstante `PublicationSnapshot::TABLES` enthält genau 15 fachliche Tabellen. Gleiche Tabellennamen in Working und LIVE bedeuten **nicht**, dass ein öffentlicher Request einfach die PostgreSQL-`search_path`-Variable umschaltet.

## Öffentlicher GET-/HEAD-Ablauf

```mermaid
sequenceDiagram
    participant Browser
    participant Middleware as UseCommittedPublicState
    participant Scope as CommittedRead
    participant Context as PublicationReadContext
    participant Grammar as PublicationPostgresGrammar
    participant DB as PostgreSQL

    Browser->>Middleware: GET/HEAD public URL
    alt admin, preview, livewire oder up
        Middleware-->>Browser: Normaler Requestpfad
    else öffentlicher Lesezugriff
        Middleware->>Scope: run(callback)
        Scope->>Context: runCommitted(callback)
        Scope->>DB: transaction; REPEATABLE READ bei Level 1
        Note over Context,Grammar: Scoped committedDepth > 0
        Middleware->>Grammar: Eloquent-Query erzeugen
        Grammar->>Grammar: Trackbare unqualifizierte Tabelle?
        alt Ja
            Grammar->>DB: SELECT FROM committed.<table>
        else Nein
            Grammar->>DB: Unveränderte Tabellenreferenz
        end
        DB-->>Browser: Read-only HTML/Response
        Scope->>Context: committedDepth im finally zurücksetzen
    end
```

**Genauigkeit:** Das Diagramm vereinfacht die tatsächliche Aufrufverschachtelung und stellt die Grammatik als eigenständige Phase dar; sie wird bei Query-Compilation über die PostgreSQL-Connection verwendet. Die Read-Kontext-Depth läuft über ein `try/finally` wieder zurück. `wrapTable` qualifiziert nur unqualifizierte Namen aus `PublicationSnapshot::TABLES` und respektiert explizite Schemanamen und Aliasnamen. `CommittedRead` setzt bei der ersten Transaktionsebene `REPEATABLE READ`. Das ist eine wesentliche Architekturgrenze gegenüber einer pauschalen `search_path`-Erklärung.

## Explicit Commit

```mermaid
sequenceDiagram
    participant Admin
    participant Service as PublicationService
    participant DB as PostgreSQL
    participant Versions as PublicationVersionService
    participant Cleanup as PublicationMediaCleanupService

    Admin->>Service: commit(actor, message)
    Service->>DB: BEGIN
    Service->>DB: pg_advisory_xact_lock
    Service->>DB: assert schema parity; lock Working tables
    Service->>DB: Working-vs-LIVE row diff
    alt Keine fachlichen Änderungen
        Service->>Versions: clearWorkingContext()
        DB-->>Service: COMMIT (kein neuer Checkpoint)
        Service-->>Admin: null
    else Fachliche Änderungen vorhanden
        Service->>DB: uncommitted Audit-IDs und Parent bestimmen
        Service->>DB: TRUNCATE committed.<tracked tables>
        Service->>DB: INSERT SELECT * FROM public.<tracked tables>
        Service->>DB: neuen publication_checkpoint anlegen
        Service->>Versions: capture(checkpoint, parent, operation, source)
        Versions->>DB: Snapshot und History manifestieren
        Service->>DB: Audit-Events an Checkpoint binden
        Service->>DB: betroffene Undo-Receipts löschen
        Service->>Versions: clearWorkingContext()
        DB-->>Service: COMMIT
        Service->>Cleanup: drain()
        Service-->>Admin: PublicationCheckpoint
    end
```

**Sicherheitsgrenzen und Negativpfade:**

1. Transaction + Advisory Lock schützen Commit/Restore/Reset vor konkurrierenden inkonsistenten Snapshots. Ein Schema-Paritätsfehler verhindert die Operation.
2. Kein fachlicher Diff bedeutet **keinen neuen Commit**. Ein bloßer `updated_at`-Unterschied ist nicht publikationsrelevant.
3. Der Commit schließt noch nicht zugeordnete Audit-Ereignisse zur aktuellen Generation; einzelne Undo-Receipts für diese Events werden entfernt.
4. **Restore** lädt einen kompatiblen historischen Zustand in **Working**, nicht unmittelbar nach LIVE. **Revert** ist auf den Parent des aktuellen LIVE-Commits beschränkt und wird ebenfalls zunächst nur staged.
5. History-Metadaten können vorhanden sein, obwohl kein kompatibler Restore-Payload mehr verfügbar ist (`snapshot_available`, `schema_hash`).
6. `PublicationMediaCleanupService::drain` läuft nach erfolgreichem Commit; Medienreferenzen aus einem geschützten historischen Snapshot dürfen nicht durch vorzeitiges Löschen physischer Originale entwertet werden.

### Noch ausdrücklich nicht untersucht

- vollständiges SQL-Schema der History-/Undo-Tabellen und sämtliche FK-/Indexgrenzen;
- Transaktions- und Konkurrenzszenarien durch ausgeführte Tests;
- alle Filament-/Livewire-UI-Einstiegspunkte zu Commit, Restore, Revert und Reset;
- externe Binary-Storage-Fehler und Recovery bei fehlgeschlagenem Cleanup.

Diese Punkte werden nicht durch das obige Diagramm als bereits verifiziert ausgegeben.
