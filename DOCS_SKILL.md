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

| Zuletzt geprüfte Referenz (13.10.2026) | SHA |
| --- | --- |
| `docs` unmittelbar vor diesem Merge | `45adb4b164f403484deb9499ee59a48e05811966` |
| `dev`, für diesen Merge geprüfter Stand | `8fb60db6a1a427ce6f905e5d8a65454c7d39ec45` |
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

**Branch-Checkpoint (13.10.2026):** Den `dev`-Stand `8fb60db6a1a427ce6f905e5d8a65454c7d39ec45` (sechs Commits seit dem letzten übernommenen Stand `2ced9565`) nach Pfadprüfung als echten Zwei-Eltern-Merge in `docs` übernommen. `docs`-HEAD nach dem Merge frisch prüfen; künftig eingehende `dev`-Commits nicht blind überschreiben. `testing`, `main` und CI unberührt.

**Journal:** Kanonischer `ADMIN-TABLE-CONTRACT.md` an Quellstand angepasst. Blog Status faltet bei **74rem** tatsächlicher Tabellenbreite in Date; die sieben Actions behalten Labels bis **72rem**, danach gemeinsamer icon-only Rail- und Spaltenwechsel. Exhibition Status/Date faltet bei **64rem**; Date-Range und `Vernissage: dd.mm.yyyy, HH:mm` bleiben getrennte einzelne Zeilen, die Vernissage kann ellipsieren. Blog-Excerpt verwendet vorhandenen Text oder einen auf **140 Zeichen** begrenzten, normalisierten Body-Preview. Gemeinsamer `admin-table__identity--ellipsis-meta`-Modifier für einzeilige Überschrift und zweite Zeile. Die obsolete 78rem-/Zwei-Zeilen-Vernissage-Dokumentation und doppelte responsive Journal-Breakpoints entfernt. Umsetzung in PHP/Blade/CSS quellengeprüft, **kein neuer Browserlauf**.

**Public-SPA:** Loopback-only `?folio-motion=debug`, persistiert pro Tab, mit überzeichneter **1800ms**-Diagnoseanimation und `?folio-motion=off` statisch verifiziert und im Public-Vertrag plus `public-spa-lifecycle.md` dokumentiert. Normaler Reduced-Motion-Vertrag bleibt bestehen. Aktualisierte JS-/Testquellen **nur gelesen**, nicht ausgeführt; keine Produktabnahme aus dem Debugmodus abgeleitet.

**Duplikatabbau:** Vier am Ende von `ui-skills.md` fehlplatzierte Metrics-/Stage-Bullets gelöscht, nachdem die Regeln bereits in `ADMIN-RESPONSIVE-METRICS.md` und `ADMIN-RESPONSIVE-STAGES.md` verortet wurden. Keine Anwendungscode-Korrektur auf `docs`.

**Nächster Schritt:** Weitere thematische Duplikate mit realen Code-Owners abgleichen und PHP-Tokenizer/Generatoren an isolierten Syntax-Fixtures validieren, bis ein sicherer vollständiger Checkout zur Verfügung steht. Eingehende `dev`-Änderungen mit neuem Compare prüfen.

**Offen:** native Full-Checkout-PHP-Generatorprüfungen, isoliertes vollständig migriertes PostgreSQL-`pg_catalog`, Notification-Transport und Browser-Produktabnahme. Weder `testing` noch CI für diese Arbeit verwenden.
