# DOCS_SKILL — lebende Arbeitsakte (`docs`)

> **Erst diese Datei vollständig lesen**, dann [AGENTS.md](AGENTS.md),
> [.agents/skills/documentation/SKILL.md](.agents/skills/documentation/SKILL.md)
> und [docs/architecture/README.md](docs/architecture/README.md).
>
> **Diese Datei beschreibt den gegenwärtigen Arbeitsstand, kein chronologisches
> Chatprotokoll.** Nach jedem wesentlichen Schritt den betroffenen Abschnitt
> **ersetzen oder korrigieren**, statt einen neuen „Fortsetzung“-Block anzuhängen.
> Einzelne historische Commits/Quellen bleiben über Git und die Architekturdokumente
> nachvollziehbar.

## Branches, Schutz und Wiedereinstieg

- Repository: `Wiiii90/moeller-lars`.
- `docs`: **eigener dauerhaft genehmigter Branch** für Architektur, Dokumentation,
  Skills und lokal/read-only nutzbare Dokumentationsgeneratoren. Nur hier committen.
- `dev`: kanonische Quelle der parallelen Anwendung/UI-Änderungen. Neu eingetroffene
  Änderungen nach Pfadprüfung als **echten Zwei-Eltern-Merge** nach `docs` holen.
  Niemals ohne Freigabe `docs` nach `dev`/`main` mergen.
- `testing`: separat genehmigt und von anderem Chat verwaltet; **nicht verändern**.
  Keine neuen Branches/Worktrees, kein Force-Push, keine Rewrites.
- **CI absolut tabu:** nicht auslösen, lesen, monitoren, abbrechen oder Workflows
  ändern. Keine versehentlichen Server-/Produktions-/Preview-DB-Mutationen.
- Jeder neue Chat ermittelt **zuerst** `docs`- und `dev`-HEAD über GitHub,
  vergleicht die Entwicklung und übernimmt nur konfliktgeprüfte Änderungen.
  Der folgende Stand ist eine Momentaufnahme, keine festgeschriebene Basis:

| Zuletzt geprüfte Referenz (14.10.2026) | SHA |
| --- | --- |
| `docs` unmittelbar vor diesem Merge | `b05c28730065381674c16eaec9c5e99348eab7d5` |
| `dev`, für diesen Merge geprüfter Stand | `1ca4014cae997b0b4a40422944b90a0328524f53` |
| Synchronisierung | echter Zwei-Eltern-Merge; neue `docs`-SHA danach frisch abrufen |

**Arbeitstechnik:** Genaues aktuelles `docs`-HEAD vor jedem Remote-Ref-Update
prüfen, atomaren Tree/Commit mit expected SHA verwenden. Bei fremden
Zwischencommits bewusst erneut vergleichen. Lokaler Windows-Pfad
`P:\\moeller-lars` steht dieser Linux-Laufzeit **nicht** als Checkout zur Verfügung.
GitHub-DNS ist hier gesperrt; GitHub-Connector erlaubt Datei-/Tree-Lesezugriff
und kontrollierte `docs`-Commits. Keine angeblich ausgeführten vollen
PHP-/DB-/Browser-Prüfungen ohne tatsächliche Ausführung.

## Bereits tatsächlich erstellt

| Thema | Beleg und aktueller Umfang |
| --- | --- |
| Vollständiger App-PHP-Quellkatalog | [php-source-audit.md](docs/architecture/php-source-audit.md): **248** `app/**/*.php`, **214 Klassen, 27 Traits, 7 Enums**, **900** `App\\...`-Importdeklarationen, davon **717** modulübergreifend; Quellenvergleich mit komplettem Git-Tree |
| Modul-/Klassendiagramme | [20 einzelne Importgraphen](docs/architecture/class-import-diagrams.md) sowie [aggregierter Modulgraph](docs/architecture/generated/app-module-imports.mmd); **nur deklarative Imports**, keine Laufzeitaufrufe |
| Eloquent | [eloquent-relations.md](docs/architecture/eloquent-relations.md): alle **26** Models, **48** explizite Relation-Kanten, auch Self-Relations; **nicht** physisches SQL-ERD |
| PHP-Generatoren | `scripts/generate-architecture-inventory.php`, `generate-architecture-relations.php`, `generate-architecture-eloquent.php`, `generate-architecture-import-diagrams.php` und weitere unter `scripts/`; **isolierte Fixture-Prüfungen**, kein vollständiger nativer Checkout-Lauf |
| Admin UI | [ui-surfaces.md](docs/architecture/ui-surfaces.md): **9** Pages, **26** Concerns, **2** Livewire-Komponenten, **26** interne Trait-Uses, **72** statisch benannte Actions |
| Services und HTTP | [admin-publication-calls.md](docs/architecture/admin-publication-calls.md): **21** quellenverankerte direkte Call-Sites; [runtime-entrypoints.md](docs/architecture/runtime-entrypoints.md): **20** explizite Webrouten plus Middleware/Filament/JS; dynamische Route-Registrierung offen |
| Lifecycle / Activity | [lifecycles.md](docs/architecture/lifecycles.md): **5** Domänenautomaten, **35** geprüfte Quellanker; [Working-Operationen](docs/architecture/publication-working-operations.md): Restore/Revert/Reset und **36** geprüfte Anker |
| Public Inhaltsdaten | [structured-components.md](docs/architecture/structured-components.md): **6** Komponenten, **3** Contact-Kinder, List-Nesting, drei Home-Content-Modi und separates Skip-Home-Routing; JSON-Referenzen ≠ SQL-FKs |
| Datenbankschema | [schema-evolution.md](docs/architecture/schema-evolution.md): konkrete Migrationen/Legacy-Snapshot-Kontrakte, `publication_version_rows` ist SQL-View mit Triggern; [read-only PostgreSQL-Exporter](scripts/export-architecture-postgres.php) vorhanden, **kein echter isolierter DB-Export** |
| Browser-Sequenzen | [Publication](docs/architecture/publication.md) inkl. Admin-Commit, [Exhibition-Map](docs/architecture/exhibition-map.md) inkl. Toggle/Geocoder/Notifications; quellenbasiert, kein aktueller Browserlauf |

Alle generierten Artefakte haben ihr eigenes Eingabeformat, Quellen-SHA und
Abdeckungsgrenze. Syntaktisch belegte Kanten dürfen nicht als ausgeführte
PHP-Aufrufe, tatsächlich registrierte Laravel-Routen oder Datenbankconstraints
ausgegeben werden. Ein lokaler Fixture-Lauf ist **kein** Full-Checkout-Test.

## Dokumentationskorrekturen und fachliche Befunde

- `README.md`, `docs/ARCHITECTURE.md` und `docs/DATA-MODEL.md` behandeln
  die Home-Templates nun korrekt als **Artwork, Under Construction, Custom**;
  `Skip Home` ist ein eigenständiges Routingflag, nicht der vierte Inhaltstyp.
- `CvEntry` ist Migration-/Legacy-PHP-Modell, keine aktive CV-Tabelle;
  `cv_entries` wurde in Working und LIVE entfernt. CV heute als
  Custom-Page-List-/Rich-Text-Komposition.
- `docs/MIGRATION-PLAN.md`: v0.3-Akzeptanz ist historische Aussage, **v0.4**
  wird noch bearbeitet; Promotion nach `main` nur nach ausdrücklicher Abnahme
  eines **genauen SHAs**. v0.5-Cutover bleibt ein bedingtes Vorhaben.
- Aktuell **15** Publication-Snapshot-Tabellen, historischer Initialzustand
  mit **16** inkl. CV. Die historische Versioning-Migration referenziert
  `PublicationSnapshot::TABLES` dynamisch – ein noch zu beurteilender
  Vertragskonflikt, **nicht** ungeprüft zu reparieren.
- [Importgrenzen](docs/architecture/import-boundaries.md): acht Module in
  gemeinsamer Importkomponente. Direkte Imports ≠ Runtime-Zyklus.
- **Konkrete Produktabweichung:** Gallery nutzt
  `ArtworkPublicationService::publish()`, Undo über
  `AdminLifecycleRegistry` aber `ArtworkEditorialService::publish()`.
  Letzterer verlangt einen Asset-ALT und Thumbnail auch für Fälle, in denen
  Gallery ALT-Override/Video zulässt. Quellenbelegt, **nicht runtimegetestet**;
  künftige kanonische Readiness-/Undo-Prüfung nötig, keine Runtime-Änderung
  auf `docs`.
- Restore/Revert/Reset arbeiten zunächst nur auf **Working**. Revert liest
  LIVE-/Parent-Checkpoint vor dem Advisory Lock; mögliche Konkurrenzprüfung
  offen. Historische Custom-Page-Blöcke werden beim Restore kanonisiert.
- `blog_posts.gallery_presentation` wurde auf `dev` in Working und LIVE
  ergänzt, Werte `sequence/grid/mosaic/slideshow`.

## Task-spezifische Anleitungen

- [Documentation](.agents/skills/documentation/SKILL.md) – Normen und Methode.
- [Admin UI](.agents/skills/admin-ui/SKILL.md) – verweist auf `ui-skills.md`,
  Tabellen-, Controls- und Dialog-Owners.
- [Public UI](.agents/skills/public-ui/SKILL.md) – Routing, Navigation, Home,
  Journal, Medien und public acceptance.
- Die großen Bestandsdokumente `AGENTS.md` und `ui-skills.md` sind noch
  **nicht radikal gekürzt**. Nur abgesichert konsolidieren, keine akzeptierten
  Browserregeln verlieren und keine neuen UI-Regeln aus unakzeptierten
  Designentwürfen erfinden.

## Offene Arbeit in Reihenfolge

1. **Dokumentations-Duplikate und Owner prüfen:** `ui-skills.md` gegen
   `docs/ADMIN-TABLE-CONTRACT.md`, `ADMIN-CONTROL-CONTRACT.md`,
   `ADMIN-DIALOG-CONTRACT.md` sowie den aktuellen Public-Vertrag;
   identischen Inhalt am kanonischen Owner lassen, Unterschiede dokumentieren
   und nur belegbar löschen. Skill-Router werden bei Änderungen mitgezogen.
2. **Native PHP-Generatoren** auf realem Checkout ausführen, wenn möglich;
   bis dahin PHP-Tokenizer-Randfälle/Fehler in isolierten Prüfungen validieren,
   echte vollständige Remote-Quellenaufnahme gegen native Generatorsemantik
   abgrenzen. Source-Report/Graphs aktualisieren, wenn `dev` fortschreitet.
3. **Strukturierte Runtime-Instanzen:** Livewire-/Filament-Action-Dispatch,
   JS-Menü/Slideshow/Public-Shell-Lebenszyklen und die noch fehlenden
   Publikations-/Media-/Notification-Negativpfade nur mit Belegen erweitern.
4. **Datenbank:** ausschließlich auf einer vollständig migrierten, expliziten
   Wegwerf-PostgreSQL `pg_catalog`, FKs/Views/Trigger/Indizes erheben;
   nie die persistenten Preview-/Prod-Datenbanken zurücksetzen.
5. **Dokumentationsbereinigung:** `AGENTS.md`, `ui-skills.md`, Altverträge,
   Browser-Checklisten und Migrationsevidenz erst nach Quell-/Linkprüfung
   reduzieren, ohne Domain-Invarianten oder historische Restore-Kompatibilität
   zu verlieren. Eine aktuelle Norm pro Fachinhalt.

## Letzte abgeschlossene Einheit und unmittelbare Fortsetzung

**Branch-Checkpoint (14.10.2026):** Drei neue `dev`-Commits bis `1ca4014cae997b0b4a40422944b90a0328524f53` nach Pfadprüfung als echten Zwei-Eltern-Merge nach `docs` übernommen. Weitere `dev`-Fortschritte vor jedem Ref-Update frisch vergleichen. Keine Änderungen an `dev`, `testing`, `main`, CI oder persistenten Daten.

**Constructor-Relationen:** Der Relationsgenerator trennt Konstruktorparameter nun nur an **top-level Commas** und ignoriert PHP-Attribute sowie verschachtelte Default-Werte als Typkandidaten. Union-/Intersection-Hints vor dem echten Parameter-`$variable` bleiben erhalten. Vier lokale PHP-8.4-Fixtures erfolgreich, `php -l` der isolierten Fixture-Datei bestanden. Das aktualisierte Remote-Gesamtskript und sein voller Graph sind **noch nicht nativ ausgeführt**; eine veränderte Kantenanzahl wird nicht ohne Checkout behauptet.

**PHP-Generatorfehler behoben:** `generate-architecture-relations.php` hatte einen tatsächlich sichtbaren CLI-Bug: `$mode` wurde erst **nach** dem neuen `--self-test`-Zweig initialisiert. Der Modus wird jetzt unmittelbar am Skriptbeginn validiert; der Fixture-Zweig liegt vor dem Lesen von `php-symbols.json`. Die Unterscheidung von globalen `app()/resolve()`-Helpern und Methoden-/Static-Calls bleibt erhalten. Verifikation: Quellprüfung des reparierten Skripts, **nicht** ein vollständiger nativer Generatorskriptlauf.

**Eloquent-Generator Fixture-Pipeline tatsächlich ausgeführt:** Unter PHP 8.4 wurden zusätzlich zu den fünf Parser-Fixtures auch `--self-test`, `--write` und `--check` auf einer **explizit synthetischen lokalen Fixture-Repo-Struktur** ausgeführt; alle erfolgreich. Die generierten Dateien enthielten genau **3 Models und 3 erwartete Relationen** und bestanden den Read-only-Check. Die lokale Skriptkopie hatte den bereits vorgesehenen JSON-Feldaufbau (model/method/kind/target/path/line), war aber kein vollständiger Checkout der Anwendung; keine neue Behauptung über den unverifiziert gebliebenen 26-Model-/48-Kanten-Graphen.

**Eloquent-Generator verbessert:** `generate-architecture-eloquent.php` verwendet statt eines formatabhängigen Regex einen eng begrenzten PHP-Tokenizer: direkte öffentliche, parameterlose Relation-Methoden, passende Return-Type/Methoden-Kombination, eindeutiges Literal-Target `Model::class` oder `self::class`. Kommentare, Zeilenumbrüche, direkte Methodenchains, falsche Rückgabetypen und dynamische Targets werden kontrolliert behandelt. Fünf isolierte Positiv-/Negativ-Fixtures wurden im lokalen PHP 8.4 mit der entsprechenden Parserfunktion tatsächlich ausgeführt und bestanden; `php -l` des lokalen vollständigen Generatorentwurfs bestand ebenfalls. **Keine native Ausführung der hier hochgeladenen exakten Remote-Datei** und keine vollständige 26-Model-Regeneration behauptet; die ursprünglichen 48 Kanten bleiben statisch belegter Altstand.

**Admin Journal:** Der jüngste `dev`-Stand **nimmt das kurzlebige Flex-Rail zurück** und verwendet wieder ein stabiles siebenachsiges Grid. Die Publication-State-Achse nutzt jetzt zentral 6rem statt der ehemaligen 9rem Cancel-schedule-Achse; die sichtbare Beschriftung lautet `Cancel`, der zugängliche Name bleibt `Cancel schedule`. Damit bleibt jede Icon-Achse von der Label-Länge unabhängig und die ursprüngliche Lücke entfällt. Bei **72rem Tabellenbreite** bleibt die gemeinsame Status-Faltung/Umstellung auf sieben feste Icon-Slots. Restore bleibt in Archive-Slot 6 und nicht in Publish-Slot 4. Der [Tabellenvertrag](docs/ADMIN-TABLE-CONTRACT.md) ist kanonisch aktualisiert, `ui-skills.md` bleibt knapper Verweis. Source-/Testdateien **nur gelesen**, nicht im Browser geprüft.

**Public SPA:** Die jüngsten `dev`-Änderungen ergänzen Browser-/JS-Testquellen für die kleine native Pseudoelement-/Schattenanimation auch unter reduziertem Bewegungssignal. Verhalten und bewusstes Ignorieren von `prefers-reduced-motion` waren bereits im Public-Vertrag dokumentiert. Die neuen Tests sind **nicht hier ausgeführt**; die visuelle und Accessibility-Abnahme bleibt offen.

**Admin-Notification-Transport quellenbasiert geschlossen:** Der neue [Transport-Graph](docs/architecture/admin-notification-transport.md) trennt Admin-Actions → Audit-Context → `AdminNotifier` → user-scoped `firstOrCreate` → Livewire/Event oder Session → persistiertes Header-JS → Dashboard-Mailbox/Retention. **Kein automatischer Transport allein durch Audit oder DB-Write.** `firstOrCreate` dedupliziert nur den Inbox-Datensatz, nicht den pro Invocation neu erzeugten Header-Payload. Retention-Grenzfall: Bei mindestens so vielen gepinnten Notifications wie dem konfigurierten Limit kann eine gerade erzeugte ungepinnte Notification unmittelbar herausfallen. Der Fall ist source-abgeleitet, nicht runtimegeprüft; Testing-/Browser-Akzeptanz bleibt offen. Die separate Contact-E-Mail-Pipeline wurde nicht als AdminNotifier-Mail-Versand ausgegeben.

**Route-Checker gehärtet:** `scripts/check-architecture-routes.php` ignoriert jetzt kommentierte und als String geschriebene `Route::get/post(...)`-Beispiele durch echten PHP-Tokenvergleich. Die Quelle `routes/web.php` enthält weiterhin genau **20** explizite GET-/POST-Aufrufe; die neue Prüfung liest deren Positionen tokenbasiert und prüft weiterhin Controller/Closure und den manifestierten Preview-Pfad. Mit `--self-test` gibt es positive/negative Quell-Fixtures ohne Laravel oder DB. Ein äquivalenter Parser wurde im lokalen PHP 8.4 via `php -l` und Fixture-Lauf geprüft; **keine native Ausführung der hier geänderten exakten Remote-Datei** oder aller echten 20 Routen mit dem neuen Checker behauptet.

**Nächster konkreter Schritt:** Verbleibende Namespace-/Class-Scope-Fälle der Relationsgenerierung gegen reale Quellen prüfen und weitere dauerhafte Vertragsduplikate beseitigen. Danach native `--self-test` der gesamten eingecheckten Generatoren und schließlich `--write`/`--check` nur in einem vollständigen sicheren Checkout ausführen. Parallel weitere belastbare Dokumentationsduplikate reduzieren und `dev` bewusst mergen.

**Offen:** Vollständige native PHP-Generator-Checks, isolierte vollständig migrierte PostgreSQL-`pg_catalog`-Prüfung, Notification-Transport-Runtimeabnahme und Browser-/Produktabnahme. Weder CI noch `testing` für diese Arbeit verwenden.
