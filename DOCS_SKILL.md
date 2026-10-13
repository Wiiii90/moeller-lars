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

| Referenz | Stand bei diesem Update (2026-10-13) |
| --- | --- |
| `docs` | `c34407e3bfff0de56596457c965918c4a01d294c` (HEAD vor diesem Handoff-Commit) |
| `dev` | `7294d19ae48a58624fc5f0883e63aee207198edb` |
| `docs` relativ zu `dev` | **33 voraus, 0 zurück** vor diesem Commit (mit den letzten beiden `dev`-Merges) |
| `testing` | Eigenständiger paralleler Branch, nicht verändert |

**Vor Weiterarbeit immer aktuelle Remote-HEADs nachlesen**; diese Werte sind
Momentaufnahmen und können durch andere Arbeitschats überholt sein.

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

## 6g. Fortsetzung 2026-10-13 — `dev`-Merge und Journal-Gallery-Parität

- Neuen `dev`-Stand `a53d59030a03e198fbba3c631d560bdffdf5e6e3` per echtem Merge `13edcfb45e829e7ecb6ffbcb9489fae1b241f813` nach `docs` übernommen. 13 geänderte/neu hinzugefügte Dateien, keine Überschneidung mit den neuen Architekturdateien.
- [Schema-Evolution](docs/architecture/schema-evolution.md) aktualisiert: Migration `2026_10_13_000002_add_blog_gallery_presentation.php` ergänzt `blog_posts.gallery_presentation` in Working und LIVE. Vier Modellwerte: `sequence`, `grid`, `mosaic`, `slideshow`.
- Die 15 getrackten Publication-Tabellen und 26 Eloquent-Modeldateien bleiben unverändert; es ist ein neues Feld, keine neue Model-Relation. Für Browser-/Slideshow-Abläufe folgt separate Quellenanalyse.
- Restore/Revert/Reset-Katalog nach dem Merge erneut gegen seine **36 Quellanker** überprüft; alle vorhanden, alle drei gespeicherten Mermaid-Dateien mit dem JSON-Material identisch.
- **Nächster Schritt:** PHP-Generatoren auf echtem Gesamtcheckout verifizieren; Source-Extractor-/Coverage-Defizite beheben und generierte Relation-/Klassendiagramme aktualisieren. Kein `testing`- oder CI-Zugriff.

## 6h. Weiterer `dev`-Merge (2026-10-13)

- `dev` bei `7294d19ae48a58624fc5f0883e63aee207198edb` synchronisiert mit echtem Zwei-Eltern-Merge `c34407e3bfff0de56596457c965918c4a01d294c`.
- Änderungen: `JournalMediaRenderer`, Journal-Gallery-Blade und Blog-Index/Detail sowie `docs/PUBLIC-IMPLEMENTATION-CONTRACT.md`; kein Code in den neuen Architecture-Scripts verändert. Keine neuen SQL-Migrationen in diesem Merge.
- **Nächster Schritt bleibt:** vollständiger PHP-Generatorlauf und Quellen-Coverage, dann Datenbankschema aus Wegwerf-PostgreSQL.

## 6i. Fortsetzung 2026-10-13 — vollumfängliche App-PHP-Quelle erfasst

- [Quellenkatalog](docs/architecture/generated/app-source-audit.json), [Modulgraph](docs/architecture/generated/app-module-imports.mmd) und [Analyse](docs/architecture/php-source-audit.md) committed.
- `app/**/*.php` über GitHub für Quellstand `ca162bcd5e5bbaf50bc6bdb9c6bc01640269d027` **vollständig gelesen**: 248 Dateien, 214 Klassen, 27 Traits, 7 Enums, keine mehrdeutigen benannten Typen.
- Alle **900** `use App\\...`-Imports auf bestehende App-Typen aufgelöst, davon **717** modulübergreifend. Die 20 Modulgruppen und tatsächlichen Quellenpfade sind enthalten. **Import ≠ Methodenaufruf oder Runtime-DI.**
- Die lokale PHP-8.4-Runtime besitzt **keinen GitHub-DNS-/Netzwerkzugriff** auf das private Repository; ein nativer Full-Checkout-Lauf von `generate-architecture-inventory.php` / `generate-architecture-relations.php` wurde weiterhin **nicht** behauptet. Unabhängiger Regex-Scanner im Remote-Zugriff, nicht identisch mit PHP-Tokenizer.
- **Nächster Schritt:** PHP-Tokenizer-Generatoren anhand kleiner konkreter Quellfälle auf falsche Kanten prüfen und reparieren; danach Quelländerungen aus `dev` synchronisieren und weitere Laufzeitauflösung konkret belegen.

## 6j. Fortsetzung 2026-10-13 — Importgrenzen und Undo-Publish

- [Importgrenzen](docs/architecture/import-boundaries.md) dokumentieren acht gemeinsam importverbundene Module, mit direkten beidseitigen Kanten. Das ist keine automatische Diagnose eines Runtime-Zyklus.
- **Neu verifiziert:** Gallery-Actions publizieren über `ArtworkPublicationService`; `AdminLifecycleRegistry` verwendet für Undo-Inversen `ArtworkEditorialService::publish`. Die Guards unterscheiden sich bei ALT-Override und Video/Thumbnail. Konkrete Verhaltens-Testhypothese; **kein** ausgeführter End-to-End-Test.
- Nur Docs auf `docs`; kein Runtime-Code und kein `testing`/CI.
- **Nächster Schritt:** native PHP-Tokenizer-Generatoren an kritischen Syntax-Randfällen prüfen und korrigieren, Ergebnisse mit dem vollständigen Source-Audit vergleichen.

## 6k. Fortsetzung 2026-10-13 — Vollständige Modul-Klassendiagramme

- [20 Moduldiagramme](docs/architecture/class-import-diagrams.md) für alle 248 Quelltypen und 900 intern auflösbaren Importkanten eingecheckt. Die Subgraphen sind nach Modul-Owner aufgeteilt; sie zeigen auch fremde Importziele.
- [PHP-Generator](scripts/generate-architecture-import-diagrams.php) verarbeitet das versionierte `app-source-audit.json`, hat `--write`/`--check` und erkennt überzählige Diagramme.
- Isolierter PHP-8.4-Fixture **tatsächlich geprüft**: Syntax, Write, Check, absichtlich veraltetes Diagramm wird abgelehnt. Die realen 20 Diagramme wurden aus denselben Audit-Daten generiert und Summen verifiziert; **kein** nativer Voll-Checkout-PHP-Lauf.
- Importkanten sind keine Aufruf-/Instanz-Beweise; Runtime- und DB-Generatoren bleiben getrennt.

## 6l. Fortsetzung 2026-10-13 — veraltete Hauptdokumente korrigiert

- `README.md`, `docs/ARCHITECTURE.md`, `docs/DATA-MODEL.md`: nur **drei** aktuelle Home-Inhaltstemplates; Skip Home ist eine separate Routingentscheidung, kein vierter Home-Inhaltsmodus.
- `docs/DATA-MODEL.md`: `CvEntry` ausdrücklich als Legacy-/Migrationsmodell klassifiziert, `cv_entries` ist aus Working/LIVE entfernt; CV heute in Custom-Page-Komponenten.
- `docs/MIGRATION-PLAN.md`: historische v0.3-Akzeptanz wird nicht mehr als Abnahme der noch laufenden v0.4-Arbeit oder automatische Promotion nach `main` verstanden; v0.5-Cutover bleibt bedingt geplanter Schritt.
- **Nächster Schritt:** Dokumentations-Skill als eigene knapp gehaltene Arbeitsanleitung etablieren; die großen `AGENTS.md` und `ui-skills.md` nur abschnittsweise und belegbar entflechten, ohne akzeptierte UI-Contracts zu verlieren.

## 6m. Fortsetzung 2026-10-13 — Dokumentations-Skill etabliert

- Neue [`.agents/skills/documentation/SKILL.md`](.agents/skills/documentation/SKILL.md) enthält die dauerhafte Methode. `DOCS_SKILL.md` bleibt ausschließlich die **lebende Arbeitsakte** und wird weiter nach jedem Abschnitt aktualisiert.
- Die Skill trennt Quellenprüfung, statische Diagramme, echten Generator-/DB-/Browserlauf und fordert je normativem Vertrag genau einen Owner. Controlled `dev`-Merges, `docs`-Ownership und absolutes CI-Verbot sind klar dokumentiert.
- `AGENTS.md` und `docs/README.md` verlinken den Skill, ohne die große vorhandene UI-Grammatik vorzeitig zu löschen.
- Neuer `dev`-Stand `bee4297b50aec3e07b219125fbc79a854211d0b8` (Journal-Admin-Tabelle und `ui-skills.md`) per Zwei-Eltern-Merge `e002980db2b508de0e2985541b54c56c070e958a` übernommen.
- **Nächster Schritt:** `ui-skills.md` und die Public-/Admin-Verträge nach echten Owners und überschneidenden Bereichen inventarisieren; danach sicher in kürzere getrennte Skills überführen, ohne akzeptierte Browserregeln zu verlieren.

## 6n. Fortsetzung 2026-10-13 — Admin/Public Skills als getrennte Einstiegspunkte

- [Admin UI Skill](.agents/skills/admin-ui/SKILL.md) und [Public UI Skill](.agents/skills/public-ui/SKILL.md) erstellt. Kurze Routings auf **verschiedene** bestehende Owner-Verträge/Code, keine 67KB-Kopie von `ui-skills.md`.
- `AGENTS.md` und `docs/README.md` verlinken beide; `ui-skills.md` bleibt bis zur kontrollierten Content-Migration intakt, einschließlich der auf `dev` neu eingetroffenen Journal-/Tabellenregeln.
- **Nächster Schritt:** segmentweise identifizieren, welche `ui-skills.md`-Teile im Admin Table/Dialog/Control Vertrag schon doppelt liegen, wie Public-Bestand durch den zuletzt geänderten Public-Vertrag abgedeckt wird, und welche alte Regel mit direktem Code-/Browserbeleg gestrichen werden kann.

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
