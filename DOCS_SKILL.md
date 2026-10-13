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
| `docs` vor letzter Dokumentationsphase | `0c0be8a4eba37ef688923a6b9816c7216597c951` |
| `dev` | `5b1d02d013452b7980d08786c1f5339bd36fdbfc` |
| Differenz zum damaligen `dev` | neu abgeglichen; tatsächlichen Vergleich immer frisch abrufen |

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
| Services und HTTP | [admin-publication-calls.md](docs/architecture/admin-publication-calls.md): **21** quellenverankerte direkte Call-Sites; [runtime-entrypoints.md](docs/architecture/runtime-entrypoints.md): **19** explizite Webrouten plus Middleware/Filament/JS; dynamische Route-Registrierung offen |
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

**Stand 13.10.2026:** `docs` vor diesem Commit `af4a45ec77dba447e133479e01537b8306919cae`,
`dev` zuletzt `919eec2c088ccd7aca804ab5108a6a8378a1028e`.
Remote-HEADs vor weiterer Arbeit erneut prüfen.

**Hauptdokumente korrigiert:** `docs/ARCHITECTURE.md` und
`docs/DATA-MODEL.md` wieder mit [MEDIA.md](docs/MEDIA.md) und dem
tatsächlichen Code abgeglichen: keine aktive CV-Direct-Image-/Body-
Datenstruktur und kein eigener `CV List`-Komponententyp; `list`/
`text` bleiben kanonisch. Medienreferenzen führen beim autorisierten
Löschen zu Domain-gesteuerter Usage-Entfernung statt zu einem
pauschalen Verbot. Rich Text kann sichere externe HTTP(S)-Bilder
verlinken, ohne sie als `MediaAsset` zu verwalten.
`ARCHITECTURE.md` unterscheidet Activity-bedingte mehrfache
Notifications und behauptet weder ausgeführte Livewire-Tests noch
einen automatisch autorisierten `main`-PR/CI-Gate.

**Bereits geprüft:** 61 Markdown-Dateien / 450 erkannte relative Links
ohne fehlende Dateiziele (keine Fragment-/externe URL-Prüfung).
`ADMIN-ACTIVITY-PUBLICATION-CONTRACT` beschreibt Reset-Audit nur
bei echtem Working/LIVE-Diff. Keine Runtime-/Browser-/CI-Tests gelaufen.

**Unmittelbar weiter:** [MIGRATION-INVARIANTS.md](docs/MIGRATION-INVARIANTS.md)
gegen die konkrete historische Migration prüfen, die noch eine
veränderliche `PublicationSnapshot::TABLES`-Konstante importiert;
quellbelegte Abweichung getrennt von der normativen Zielregel
dokumentieren. Danach Anker-Verweise/weitere echte Widersprüche.

**Offen:** Full-Checkout-PHP, Wegwerf-PostgreSQL-`pg_catalog`, E2E
und Notification-Retry. `testing`/CI unberührt.
