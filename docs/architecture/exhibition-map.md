# Exhibition Map: Edit-Autosave, Geocoding und Fehlergrenzen

**Quellen:** `app/Filament/Support/JournalEntryEditorSchema.php`, `app/Filament/Support/Controls/AdminControl.php`, `app/Filament/Support/Dialogs/AdminDialog.php`, `app/Filament/Pages/JournalWorkspace.php`, `app/Domain/Content/ExhibitionMapAddress.php`, `ExhibitionEditorialService.php`, `ExhibitionGeocodingService.php`. **Tests als vorhandener Quellcode gelesen, nicht ausgeführt:** `tests/Feature/Admin/Journal/ExhibitionMapAutosaveTest.php`, `tests/Architecture/Admin/AdminExhibitionMapAutosaveTest.php`.

## Autoritätsgrenzen

| Verantwortung | Tatsächlicher Owner |
| --- | --- |
| Map-Toggle deaktiviert, solange keine identifizierbare Ortsangabe vorliegt | `JournalEntryEditorSchema::mapSection()` |
| Eine definierte Adress-Mindestbedingung | `ExhibitionMapAddress::isSufficient()`: Country und (City oder Postal Code) |
| Filament-Switch erzeugt ein echtes `change`-Signal für den bestehenden Edit-Pfad | `AdminControl::decorateToggle()` |
| Edit bleibt geöffnet; native Formvalidierung und Action-Lifecycle bleiben bei Filament | `AdminDialog::edit()` mit `wire:change="callMountedAction"` und `after()->halt()` |
| DB-Persistenz, Lock, Geocoder-Entscheidung und Domain-Audit | `ExhibitionEditorialService::update()` |
| Ein einzelnes Koordinaten-Update des geöffneten Editors | `JournalWorkspace::editExhibitionAction()` → `Schema::partialRawState` |
| Notification erst bei Änderung | expliziter `AdminNotifier::notification()`-Aufruf im `JournalWorkspace` |

Der Toggle darf bei bereits aktiver Map auch mit einer später unvollständigen Adresse **ausgeschaltet** werden. Bei erstmaliger Aktivierung muss der Ort identifizierbar sein. Die UI-Vorprüfung ist keine Sicherheitsgrenze: dieselbe Policy wird im Domain-Service geprüft.

## Sequenz mit Abzweigungen

```mermaid
sequenceDiagram
    participant UI as Editor / Toggle
    participant Action as Filament / JournalWorkspace
    participant Domain as ExhibitionEditorialService
    participant Geo as ExhibitionGeocodingService
    participant DB as PostgreSQL
    participant Notifier as AdminNotifier

    UI->>Action: change → callMountedAction()
    Action->>Domain: update(exhibition, changed form data)
    Domain->>DB: lockForUpdate()
    Domain->>Domain: normalize fields; compare location signature
    opt Map aktiv UND (Ort geändert ODER Koordinaten fehlen)
        Domain->>Domain: ExhibitionMapAddress::isSufficient()
        alt Fehlende Country/City/Postal Code
            Domain-->>Action: ValidationException
        else Adresse ausreichend
            Domain->>Geo: locate(address)
            alt Anbieter nicht verfügbar / kein Treffer
                Geo-->>Domain: Error / null
                Domain-->>Action: ValidationException
            else Koordinaten vorhanden
                Geo-->>Domain: latitude, longitude
            end
        end
    end
    alt Validierung fehlgeschlagen
        Note over Domain,DB: DB-Transaktion wird zurückgerollt
        Action-->>UI: Validierungsfeedback; Dialog bleibt
    else Domain-Update erfolgreich
        Domain->>DB: save falls isDirty; Audit falls Row/Media geändert
        Domain-->>Action: fresh Exhibition
        Action->>UI: partialRawState latitude, longitude, geocoded_at
        opt Fachlicher Änderungsvergleich zeigt Diff
            Action->>Notifier: success Notification
        end
    end
```

Die UI setzt die **Preview-Koordinaten** auf leer, wenn der Benutzer ein Ortsfeld per Blur aktualisiert. Erst das Domain-Ergebnis befüllt die Hidden-State-Werte wieder, ohne andere fokussierte Formfelder neu zu füllen. Eine fehlgeschlagene Geocoding-Anfrage darf keinen erfundenen gültigen Map-Zustand speichern.

## Wichtige Unterscheidungen

- `gallery_enabled` und `map_enabled` sind zwei unabhängige Präsentationsentscheidungen.
- `latitude`, `longitude` und `geocoded_at` werden vom Domain-Service verantwortet; die Hidden-Felder sind nicht die Source of Truth.
- Der öffentliche Map-Renderer (`ExhibitionMapPresentation`) ist von der Admin-Vorschau zu trennen; eine iframe-Editorvorschau beweist noch keine öffentliche Publikationsfreigabe.
- Ein gespeichertes `map_enabled=true` allein beweist ohne gültige Koordinaten keine Publish-Readiness.

## Abgleich mit bestehenden Testfällen

Die vorhandenen Testdateien enthalten Prüfungen zu Toggle on/off ohne Dialogschluss, einmaligem Re-Geocoding bei Adressänderung, unvollständiger Adresse und fehlgeschlagenem Geocoding. **Das ist nachgewiesene Testabdeckung im Quellcode, kein bestandener Testrun.** Die spätere Branch-Zuständigkeit für `testing` bleibt von dieser Dokumentation getrennt.
