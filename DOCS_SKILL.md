# DOCS_SKILL — lebende Arbeitsakte für den Branch `docs`

> **Pflichtlektüre für jede Fortsetzung der Dokumentationsarbeit an `Wiiii90/moeller-lars`.**
> Dies ist die **fortlaufend gepflegte Arbeitsakte**, kein Ersatz für die technischen Verträge
> in `docs/` und kein historisches Transkript. Bei jeder größeren abgeschlossenen
> Arbeitsphase, jedem relevanten `dev`-Merge, neuen Architekturentscheidungen oder
> neuen Blockern **im selben Arbeitsdurchgang aktualisieren und auf `docs` committen**.
> Nicht erst bei drohendem Chatende.
>
> Letzter inhaltlicher Arbeitsabgleich: **2026-10-13, Europe/Berlin**.
> Git-SHAs unten sind **Momentaufnahmen**, nie ungeprüft als gegenwärtige HEADs verwenden.

## 1. Auftrag und Zuständigkeit

- **Verantwortung:** Dokumentation, Quelleninventur, Architekturmodelle, konsistente
  fachliche Verträge, skillbasierte Agent-Anleitungen und lokale Generatoren.
- **Ziel:** Weniger redundant und veraltet, inhaltlich vollständiger und
  überprüfbarer. Insbesondere ein umfassendes, source-linked Bild des
  tatsächlichen Systems: Code-Typen, Datenbank, JSON, Services, Aufrufpfade,
  Zustandsübergänge, Transaktionen, Event-/UI-Lebenszyklen, Abhängigkeiten.
  Nicht bloß Markdown-Buchstaben zählen und nicht nur Diagramme nach Gefühl zeichnen.
- **Branch `docs`:** dauerhaft und ausdrücklich zugelassen, diesem Workstream
  zugeordnet. **Nur hier committen**. Für gewöhnliche Laufzeit-/UI-Entwicklung
  bleibt `dev` zuständig. Änderungen an Runtime-Code auf `docs` vermeiden;
  vorhandene App-Dateien dürfen allein durch bewusste `dev`-Merges hier landen.
- **Branch `testing`:** eigenständiger, ebenfalls dauerhaft erlaubter Workstream;
  nicht hier committen, nicht umschreiben und seine Testreform nicht vereinnahmen.
  `testing` kann parallel zu `dev` und `docs` fortschreiten.
- **`main`:** kein Dokumentationsintegrationsziel ohne separate Anweisung.
  Keine zusätzlichen Arbeitsbranches, Hotfix-Branches oder Worktrees.
- **CI streng tabu:** keine Runs auslösen, ansehen, überwachen, Log-/Statusabfragen,
  Wiederholungen, Cancel oder Workflow-Änderungen. Lokale reine Quellanalysen sind
  etwas anderes; **niemals** produktive oder dauerhaft genutzte Preview-Datenbank
  als Test-/Schema-Ziel verwenden.
- **Quellenhierarchie:** aktuelle ausdrückliche Nutzerentscheidung > aktueller
  Quellcode und tatsächliche Browserabnahme > gegenwärtige Vertragsdokumente >
  alte Issue-/Migration-/Handoff-Aussagen. Begründete Konflikte sichtbar machen.
- **Übergreifende Agentregeln:** [AGENTS.md](AGENTS.md); [Doku-Index](docs/README.md).

## 2. Wiedereinstieg in einem neuen Chat

1. **Diese gesamte Datei lesen**, danach `AGENTS.md` und
   [docs/architecture/README.md](docs/architecture/README.md). Für Details
   gezielt relevante Artefakte statt das gesamte Repository laden.
2. Remote-HEADs von `docs`, `dev` und bei Bedarf `testing` **jetzt** ermitteln.
   Die unten stehenden SHAs sind nur zuletzt gesehene Stände.
3. Prüfen, ob neue `dev`-Commits vorliegen. Änderungen und betroffene Pfade
   nachvollziehen; **bewusst und per echtem Merge-Commit** in `docs` übernehmen,
   ohne History-Rewrite, Force-Push oder unbegründeten Konflikt-„Favorit“.
   Ein `dev`-Merge ist eine kontrollierte Synchronisierung, keine Freigabe,
   fremde App-Änderungen auf `docs` eigenständig umzuschreiben.
4. Den letzten erledigten und den nächsten offenen Arbeitsblock unten lesen.
   **Praktisch weiterarbeiten und committen**, nicht die alte Inventuranalyse
   von vorne erzählen oder um dieselbe Freigabe erneut bitten.
5. Nach dem Schritt Status und Abdeckungsstand dieser Datei anpassen:
   konkrete neue Artefakte/Quellen, tatsächlich ausgeführte Checks, offene
   Risiken, aktualisierte `dev`-Basis und nächste Fortsetzung.
6. Falls kein Checkout oder PostgreSQL erreichbar ist: GitHub-Quellen prüfen,
   generatorfähige Scripts und explizite Restlücken erstellen, **nicht**
   behaupten, eine Laufzeit-, PHP- oder DB-Verifikation durchgeführt zu haben.
   GitHub-Connector mit atomarem Tree/Commit und expected-SHA-Ref-Update nutzen.

## 3. Zuletzt verifizierter Branchstand

| Referenz | Stand zum Arbeitscheckpoint 2026-10-13 |
| --- | --- |
| `docs` | `26d33e6bdcd6dd9d4a9b51ec67818c8742285df0` (HEAD vor dieser Aktualisierung) |
| `dev` | `337da15bc7d4fcbae45f0bb3ed38a99fa4a5e4b7` |
| `docs` relativ zu `dev` | **28 Commits voraus, 0 zurück** vor diesem Checkpoint |
| `testing` | eigenständiger Workstream; vom Dokumentationsteam nicht geändert |

**Vor jedem weiteren Schritt Remote-HEADs erneut abrufen.** Diese SHA ist eine
historische Momentaufnahme; spätere Änderungen durch parallele Chats können
jederzeit eintreffen. `dev`-Merges bewahren beide Parent-Commits und lösen
Überschneidungen bewusst; kein Rewrite.

## 4. Bereits implementiert und committed

### Navigation und Quellenaufnahme

- [docs/architecture/README.md](docs/architecture/README.md): Index,
  Belegstandards und ausdrücklich noch offene Abdeckung.
- [docs/architecture/inventory.md](docs/architecture/inventory.md): neun
  `app/Domain`-Modulgruppen, 26 Eloquent-Models, Publication-Umfang,
  Public-Routen und erste UI-/Service-Eintrittspunkte.
- [docs/architecture/eloquent-relations.md](docs/architecture/eloquent-relations.md):
  alle 26 Model-Dateien gelesen; **48 explizite PHP-Relationsmethoden**;
  Self-Relations von `SiteSection` und `PublicationCheckpoint` berücksichtigt.
- [generated/eloquent-relationships.json](docs/architecture/generated/eloquent-relationships.json)
  und [Gesamtgraph](docs/architecture/generated/eloquent-relationships.mmd):
  vollständiges Eloquent-Klassen-Relationsinventar auf Basis dieser 26 Dateien.
  **Nicht** mit physischen FK-Constraints gleichsetzen.
- [docs/architecture/schema-evolution.md](docs/architecture/schema-evolution.md):
  wichtige Migrationen, Working/LIVE, SQL-View statt Tabelle, Trigger/Shared
  History Payload, alter und aktueller Publication-Tabellensatz.
- [docs/architecture/publication.md](docs/architecture/publication.md):
  Working → Commit → LIVE/Versionierung und Public-Read-Kontext mit
  `PublicationPostgresGrammar` statt erfundener globaler `search_path`-Umschaltung.
- [docs/architecture/exhibition-map.md](docs/architecture/exhibition-map.md):
  Filament-Toggle → gemountete Edit-Action → Domain/Geocoding → DB/Audit →
  explizite Notification; Fehlschläge rekonstruieren den gespeicherten
  Map-Toggle-Wert. Parallelentwicklung auf `dev` bereits abgeglichen.
- [docs/architecture/lifecycles.md](docs/architecture/lifecycles.md),
  [lifecycles.json](docs/architecture/lifecycles.json) und
  [fünf Mermaid-Dateien](docs/architecture/generated/lifecycles):
  **35 quellverankerte Übergänge** für Blog, Exhibition, Artwork, SiteSection,
  MediaAsset. Zustände, Berechnungen und indirekte Mutationen getrennt.

### Generatoren — ausführbar im lokalen Checkout, nicht Teil der Anwendung

| Skript | Output | Abdeckungs-/Ausführungsstand |
| --- | --- | --- |
| [generate-architecture-inventory.php](scripts/generate-architecture-inventory.php) | `generated/php-symbols.json` | Token-basierte PHP-Typen/Imports/Migrationspfade; vollständiger Checkout-Lauf **offen** |
| [generate-architecture-relations.php](scripts/generate-architecture-relations.php) | `generated/php-relationships.json`, `module-relationships.mmd` | `extends`, `implements`, Traits, Konstruktoren, Eloquent, explizite Service-Locator-Kanten; vollständiger Lauf **offen** |
| [generate-architecture-eloquent.php](scripts/generate-architecture-eloquent.php) | `generated/eloquent-relationships.{json,mmd}` | Output aus 26 gelesenen Modelquellen eingecheckt und miteinander abgeglichen; **lokaler Generatorlauf offen** |
| [generate-architecture-lifecycles.php](scripts/generate-architecture-lifecycles.php) | `generated/lifecycles/*.mmd` | 35 Kanten-Quellanker remote kontrolliert; alle fünf Diagramme mit Manifest abgeglichen; **lokaler PHP-Check offen** |
| [export-architecture-postgres.php](scripts/export-architecture-postgres.php) | `generated/postgresql-catalog.json`, `postgresql-fk-graph.mmd` | Nur read-only PostgreSQL-Katalog, **noch keine sichere migrierte Wegwerf-DB / kein tatsächlicher Export** |

Alle Generatoren müssen die Grenze zwischen *syntaktisch belegter Kante* und
*laufzeitverifizierter Beziehung* offenlegen. Der Eloquent-Generator ist
speziell auf direkt deklarierte Relation-Methoden beschränkt; JSON-Felder,
Morphing, externe Typen und SQL-FKs benötigen getrennte Inventare.

## 5. Tatsächlich geprüfte Architektur-Fakten und offene Widersprüche

- `HomeTemplate::options()` hat **drei Inhaltsmodi**; `SkipHome` bleibt nur
  Legacy-Enumwert. Routing ist eigenständiger Zustand in
  `HomeRoutingSettingsService`. Alte `README.md`/`ARCHITECTURE.md`/
  `DATA-MODEL.md` enthalten teils noch den vierten „Content-Modus“.
- `CvEntry` existiert als PHP-Altmodell; Migration
  `2026_09_16_235901_finalize_legacy_cv_retirement.php` entfernt aktive
  `cv_entries`-Tabellen in Working und LIVE.
- Aktuelles `PublicationSnapshot::TABLES`: **15 Tabellen**, historischer
  Start des committed-Schemas: **16** einschließlich `cv_entries`. Historische
  Migration nicht eigenmächtig an heutigen Runtime-Konstanten ausrichten.
- `publication_version_rows` wurde zu einer **SQL-View** mit
  INSTEAD-OF-Triggern; echte Daten liegen in
  `publication_version_row_manifests` und `history_payloads`. History und
  Admin-Undo teilen denselben Payloadstore.
- Die historische Migration
  `2026_09_15_000001_add_restorable_publication_versions.php` importiert
  `PublicationSnapshot::TABLES` beim Backfill – möglicherweise konträr zum
  Projektinvariant „Migrationen historisch self-contained“. **Dokumentieren,
  nicht auf `docs` den Anwendungscode ungeprüft verändern.**
- `MediaAssetEditorialService::delete()` kann Referenzen lösen, Artworks auf
  Draft setzen und Gallery-Verknüpfungen trennen. Logische Löschung und
  physischer GC sind strikt getrennt. Alte `MEDIA.md`-Aussagen prüfen.
- Die Gallery-Actions nutzen `ArtworkPublicationService`, während
  `ArtworkEditorialService` weitere Publikationsmethoden hat. Vollständige
  Aufrufstellenanalyse nötig, bevor „kanonisch“ oder „ungenutzt“ behauptet wird.
- Blog `scheduled` plus Zeitbedingung wird öffentlich sichtbar, **ohne**
  automatische Änderung der gespeicherten State-Spalte. Exhibition
  `upcoming/current/past` ist berechnet, unabhängig von
  `draft/published/archived`.
- Ursprüngliche DB-CHECKs lassen teils mehr Werte zu als die untersuchten
  aktuellen Service-Transitionen (`hidden`/`archived`/`quarantined` usw.).
  **Endschema erst nach echtem PostgreSQL-Katalogexport behaupten.**
- Browser- und Testabnahme: bisher nur Code-/Testquellen eingesehen.
  Die neuen Docs selbst sind **nicht** durch Testlauf/Browserreview bestätigt.

## 6. Offene Aufgaben, in sinnvollen Etappen

### Nächster konkreter Block: Generatoren validieren

- PHP-Generatoren gegen realen Checkout ausführen oder mit einer tatsächlich
  verfügbaren geeigneten Laufzeit überprüfen; Script-Syntax und
  Abdeckungsabweichungen vor weiteren Vollständigkeitsbehauptungen beheben.
- `php-symbols.json` und `php-relationships.json` samt Moduldiagramm
  erzeugen/einchecken, sofern verifiziert. Prüfen, ob mehrdeutige
  Dateien, dynamische Abhängigkeiten und nicht erfasste Klassen transparent
  als Lücken ausgewiesen sind.
- Den vorhandenen vollständigen Eloquent-Relationsgraph mit lokalem
  `generate-architecture-eloquent.php --check` abgleichen.

### Danach: vollständige Systemmodelle

- **DB:** Wegwerf-PostgreSQL nach sämtlichen aktuellen Migrationen aufbauen;
  physisches Schema inklusive Views, FK-Targets, Indizes, Constraints,
  Triggern, `public`/`committed` auslesen. **Niemals** Preview-/Prod-DB
  `migrate:fresh`/`reset` aussetzen.
- **PHP/Framework:** statische Typ-/Servicekanten durch Laravel-Provider,
  Filament-Actions, Livewire-Events, Routen/Middleware, Jobs und konkrete
  Aufrufer erweitern. Syntaktischer Import ≠ Laufzeitaufruf.
- **Komponenten:** Custom-Page-/Home-JSON, Rich Text, Media-Verweise und
  Validation/Normalization als separate Datenschemata inventarisieren.
- **Lebenszyklen:** Activity/Audit, Undo-Receipts, PublicationCheckpoint,
  Restore/Revert, MediaVariant, Home-Routing/Templates, Journal und
  Browser-/Dialog-/Navigation-Instanzen ergänzen, mit Guards/No-ops/Fehlern.
- **Diagramme:** Source → maschinenlesbares Manifest → deterministische
  Mermaid/Graphviz-Ansichten. Quellpfade/Version, unerkannte Kanten und
  Prüfstatus explizit angeben. Kein erfundenes „vollständiges UML“.
- **Dokumentationsneuschnitt:** Nach dem inhaltlichen Nachweis alte 34
  Markdown-Dateien abschnittsweise zu einer klaren Eigentümerstruktur
  konsolidieren; `AGENTS.md` drastisch verschlanken; `ui-skills.md` fachlich in
  Admin/Public trennen, später eine echte Documentation-Skill unter
  `.agents/skills/documentation/SKILL.md`. Root-`DOCS_SKILL.md` bleibt
  unabhängig davon die **lebende Arbeitsakte**.
- **Koordination:** `testing` kann bestehende `docs/TESTING.md` und Tests
  parallel ändern. Vor Änderungen daran `testing`-/`dev`-Entwicklung prüfen;
  Testpolicy nicht überschreiben oder Tests heimlich auf `docs` refactoren.

## 6a. Fortsetzung 2026-10-13 — Admin/Publication-Call-Sites

- [Admin-/Publication-Aufrufkatalog](docs/architecture/admin-publication-calls.md) mit **21** direkten Methodenaufrufstellen, **14** Klassen und **5** PHP-Quellen.
- Machine-readable `docs/architecture/admin-publication-calls.json`, generiertes `docs/architecture/generated/admin-publication-calls.mmd`.
- Lokal mit PHP 8.4: `generate-architecture-call-graph.php` in einem isolierten Quellfixture syntaktisch geprüft, `--write` und `--check` ausgeführt, absichtlich veraltete Zeilenangabe wird zuverlässig abgelehnt. **Nicht** als Full-Checkout-Verifikation ausgeben.
- Alle echten 21 Source-Anker anhand der GitHub-Dateien und Zeilen geprüft.
- Neue Erkenntnis: Admin-Audit bereitet Receipts/Activity vor, aber Notification-Versand ist ein separater expliziter Schritt; Undo besitzt separate Ordering-/Snapshot-/Media-/State-Inversen.
- **Nächster Schritt:** Generator-Abdeckung und Korrektheit auf vollständigem Checkout prüfen bzw. PHP-Typgraph-Lücken (u. a. dynamische Resolver und skipped declarations) methodisch sichtbar machen. Parallel die verbleibenden Domain- und Filament-Einstiegspunkte inventarisieren.

## 6b. Fortsetzung 2026-10-13 — JSON-Komponentenarchitektur

- [Strukturierte Komponenten](docs/architecture/structured-components.md) und [maschinenlesbarer Katalog](docs/architecture/structured-components.json) erfasst.
- Aus PHP Konstanten/Normalisierern verifiziert: **6** persistierte Component-Typen, **3** Contact-Child-Typen, **4** Divider-Varianten und individuelle `published`-Flags. `heading` und `rich_text` des Home-Editors serialisieren beide zu `text`.
- Custom Page und Home teilen den bestehenden `CustomPageSetting`-Validator. JSON-Media-IDs sind **keine automatisch gesicherten SQL-FKs**.
- `dev`-Änderung an Public-Exhibitions über echten Merge `0ebeea0fe1031d0912f9e0d7827115d052509465` übernommen.
- **Zusätzlich lokal geprüft:** vorhandener `generate-architecture-eloquent.php` mit PHP 8.4 in isoliertem Modellfixture: Syntax, `--write`, `--check` sowie erwarteter Fail bei Quelländerung. Realer Voll-Checkout-/DB-Check weiterhin offen.
- **Nächster Schritt:** Vollständige PHP-Symbol-/Service-Generatorabdeckung und dynamische Fehlstellen nachprüfbar erfassen; anschließend Routen/Livewire-/Filament-Einstiegspunkte.

## 6c. Fortsetzung 2026-10-13 — Runtime-Einstiegspunkte

- [Runtime-Einstiegspunkte](docs/architecture/runtime-entrypoints.md) und [Routenindex](docs/architecture/runtime-entrypoints.json): 19 explizite Web-Routendeklarationen mit aufgelösten Preview-Pfaden und Quellzeilen, keine behauptete vollständige Runtime-Route-Liste.
- `bootstrap/app.php` globale/Web-Middleware; Filament-Panel-/Auth-Discovery; unterschiedliche Admin-/Public-Media-Policies; scoped Admin-Mutationsbuffer und `X-Publication-Pending` dokumentiert.
- Public JS Einstieg in `layouts/app.blade.php` und fünf tatsächlich importierte Module verifiziert.
- **Nächster Schritt:** PHP-Symbol-/Beziehungs-Generatoren mit vollständigem Checkout ausführen und nicht erfasste Typen/Runtime-Einstiege offenlegen. Anschließend UI-/Livewire-Signalfluss detailliert erfassen.

## 6d. Fortsetzung 2026-10-13 — Filament und Livewire

- [Filament-/Livewire-Komposition](docs/architecture/ui-surfaces.md) erfasst: **9** Filament-Page-Klassen, **26** Concern-Dateien, **2** eigenständige Livewire-Controls; Slugs und Abgrenzung zur Filament-Route-Discovery sind quellengeprüft.
- Neuer lokaler `scripts/generate-architecture-ui-surfaces.php`: extrahiert Page/Concern/Livewire-Klassen, direkte Trait-Nutzungen, öffentliche Methoden und literal definierte Actions zu JSON/Mermaid.
- PHP 8.4 **tatsächlich** geprüft: Syntax, `--write`, `--check`, Fixture mit Page/Trait/Livewire und negativer Stale-Action-Fall. Die 37 Originaldateien wurden jetzt zusätzlich über den GitHub-Connector erfasst und der [JSON-Gesamtkatalog](docs/architecture/generated/ui-surfaces.json) samt [Mermaid-Komposition](docs/architecture/generated/ui-composition.mmd) eingecheckt: **26** interne Trait-Kanten (22 direkt, 4 verschachtelt) und **72** statisch benannte Action-Fundstellen. Der native PHP-Full-Checkout-Generatorlauf ist noch offen.
- **Generatorfehler korrigiert:** Typkörper-Suche fixiert gegen nachfolgendes `::class`; Concern-zu-Concern-Traitbeziehungen werden jetzt mitgezählt. Im lokalen PHP-8.4-Fixture inklusive Nested-Trait nachgeprüft.
- **Nächster Schritt:** vollständigen PHP-Checkout für Generatorläufe herstellen bzw. statischen Scope konservativ ausweiten; verschachtelte Trait-Nutzung und konkrete Filament-/Livewire-Event-Callbacks untersuchen.

## 6e. Fortsetzung 2026-10-13 — Commit-UI bis zum Domain-Service

- [Publication-Architektur](docs/architecture/publication.md) um echten Admin-Commit-Einstieg ergänzt: Filament-Navigation → Alpine `publication-commit` → Livewire-Control → `PublicationService::commit` → expliziten `AdminNotifier`.
- Quellgenau dokumentiert: No-op ohne Working-Diff, Fehler-Notification und rethrow, Erfolg nur bei vorhandenem Checkpoint; `AdminNotifier` kann Activity-Nachrichten konsumieren.
- Livewire-Ereignis `publication-state-changed` und optionaler HTTP-Header `X-Publication-Pending` sind **zwei** Inputpfade für denselben Navigationszustand. Der Header erscheint nur bei `X-Livewire` plus dirty Mutation-Buffer.
- **Nicht ausgeführt:** Browser-Klickfluss, gleichzeitige Requests oder vollständiger Laravel-Runtime-Check. Als nächstes Restore/Revert/Reset-Einstiege und Filament-Actions mit den entsprechenden Admin-Service-Verzweigungen abgleichen.

## 6f. Fortsetzung 2026-10-13 — Restore/Revert/Reset vollständig als Quellabläufe

- [Working-Operationen](docs/architecture/publication-working-operations.md), [JSON-Quellanker](docs/architecture/publication-working-operations.json) und drei [Mermaid-Diagramme](docs/architecture/generated/working-operations/) hinzugefügt.
- `Activity`-UI, `AdminPublicationHistory`-Eligibility, `PublicationVersionService`-Transaktionen/Audit/Media-Cleanup und nachfolgender `PublicationService::commit` abgeglichen.
- Guards: Restore nur nicht-LIVE und restorable; Revert nur LIVE mit restorable Parent; Reset Working→LIVE. Alle drei lassen LIVE bis zum expliziten Commit unangetastet.
- Nuancen: Restore auditiert auch ohne vorherigen Working-Diff; Reset ohne Diff löscht nur Working-Kontext. Restore kanonisiert ältere CustomPage-JSON-Blöcke; Revert liest LIVE/Parent vor dem Advisory Lock (Concurrency-Test offen).
- Integrationstests für Restore/Revert/Reset **als Quellen gelesen, nicht ausgeführt**. Neues `scripts/generate-architecture-working-operations.php` prüft Quellzeilen und reproduziert drei Mermaid-Dateien; nativer Checkout-Lauf offen.
- **Nächster Schritt:** bestehende PHP-Symbol- und Relationsgeneratoren tatsächlich auf vollständigem Source-Checkout ausführen und auf fehlende bzw. falsche Kanten prüfen; danach physische PostgreSQL-Schemaextraktion ausschließlich in einer isolierten Wegwerf-DB.

## 7. Arbeits- und Updateprotokoll

Bei **jedem** bedeutenden Commit/`dev`-Merge diese knappe Checkliste abarbeiten:

1. Bevor geschrieben wird: `docs`-HEAD erneut lesen, diff gegen Startstand
   kontrollieren; bei HEAD-Wechsel reconciliieren, expected-SHA-Update und
   **kein Force-Push**.
2. Was wurde **tatsächlich** erstellt/geändert? Dateipfade und
   aussagekräftiger Commit oder Git-Stand (nicht nur Absicht).
3. Welche Quellbehauptungen wurden **gelesen**, welche Programme **ausgeführt**,
   welche Ergebnisse **nicht** ausgeführt/geprüft?
4. Abdeckungszahlen/Modelle sowie veraltete Aussagen in `docs/architecture/`
   und hier nachziehen, ohne den gesamten Verlauf als Chronik aufzublähen.
5. Was genau ist der **nächste implementierbare Schritt**? Einen solchen Schritt
   notieren, nicht „weiter analysieren“.
6. Keine CI-Interaktion. `dev`-Merges dokumentieren; `testing` unberührt.

### Checkpoint 2026-10-13 — Start dieser fortlaufenden Datei

- Architekturordner und erste Sequenzen liegen auf `docs`.
- **26 Modelle / 48 Eloquent-Relationen** wurden mit Quellenstellen erfasst.
- **5 Domänenautomaten / 35 Transitionen** wurden erstellt; vorhandene
  Quellanker und Mermaid-Dateien gegen den JSON-Katalog abgeglichen.
- Generatoren für PHP, statische Beziehungen, Eloquent, Lifecycle und
  read-only PostgreSQL existieren. **Vollständige lokale Durchläufe und
  Datenbankschemaexport bleiben offen**.
- `dev` wurde zuletzt mit echtem Merge
  `dac95a381419f9bb6af95d3fe2f836ea4d5dc9a0` übernommen.
- Arbeitsregel für beide dauerhaften Sonderbranches steht bereits in
  `AGENTS.md`. Diese Datei wird zusätzlich im AGENTS-Einstieg verlinkt.
- **Unmittelbar nächster Schritt (aktualisiert):** die vorhandenen PHP-Symbol-, Service- und UI-Generatoren auf einem echten vollständigen Checkout ausführen, Outputs und falsche Kanten korrigieren; danach den isolierten PostgreSQL-Schema-Katalog erstellen.

**Fortsetzungsregel:** Lies diese Arbeitsakte, verifiziere neue Branch-HEADs
und setze den unmittelbaren nächsten Schritt um. Keine erneute
grundsätzliche Architektur-Diskussion, solange sich die Anforderungen
nicht ändern.
