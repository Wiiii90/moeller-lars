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
| `docs` unmittelbar vor diesem Merge | `8b945406d9ed6df969860e519c031bc202ef631a` |
| `dev`, für diesen Merge geprüfter Stand | `1cf6aa5cccf82c54820f26225b4e66f6723523ac` |
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

**Branch-Checkpoint (14.10.2026):** Fünf neue `dev`-Commits bis `1cf6aa5cccf82c54820f26225b4e66f6723523ac` nach Pfadprüfung als echten Zwei-Eltern-Merge nach `docs` übernommen. Vor jeder weiteren Übernahme neue HEADs lesen und eventuelle Überschneidungen prüfen. `dev` bleibt Application-Quelle; `testing`, `main`, CI, Deployment und persistente Daten bleiben unberührt.

**PHP-Quellinventur:** `scripts/generate-architecture-inventory.php` erkennt Dateiimports nun ausschließlich auf Namespace-Ebene. Closure-`use ($vars)`, Trait-Uses sowie anonyme Klassen sind keine Dateiimports; geklammerte Namespaces und String-Interpolation werden korrekt abgegrenzt. Read-only `--self-test` mit vier Fixture-Fällen ergänzt, ohne Laravel/Composer/DB/echten Checkout nutzbar. Eine äquivalente isolierte PHP-8.4-Parserfunktion wurde mit vier erfolgreichen Fixture-Fällen und `php -l` geprüft. **Der vollständige native Lauf der gerade geänderten Remote-Generator-Datei ist noch nicht durchgeführt.** Ebenso bleiben `--write`/`--check` auf einem vollständigen Checkout und die Neugenerierung der 248-Dateien-Quelle offen. Ein Fixture-Lauf ist kein realer Inventarlauf.

**PHP-Relationships:** `scripts/generate-architecture-relations.php` ignoriert bei Service-Locator-Kanten jetzt korrekt Methoden- und statische Aufrufe wie `$obj->resolve(Foo::class)` oder `Container::resolve(Foo::class)` sowie Funktionsdeklarationen; echte globale `app()/resolve()`-Helper einschließlich `\\app()/\\resolve()` werden erkannt. Die vorherige Prüfung des unmittelbaren Nachbartokens war durch Kommentare/Whitespace und fremde Caller fehleranfällig. Neuer read-only `--self-test` mit **5** Syntaxfällen, ohne PHP-Inventardatei ausführbar. Äquivalentes Token-Prädikat unter lokalem PHP 8.4 mit fünf PASS-Fällen und `php -l` der Fixture-Datei geprüft; **das eingecheckte vollständige Generatorskript und sein realer Relationengraph wurden hier nicht ausgeführt**. Siehe `docs/architecture/README.md`.

**Admin-Journal:** Die neue Quelle koppelt Blog-Status-Faltung und Action-Label-Komprimierung **gemeinsam bei 72rem tatsächlicher Tabellenbreite**; vorherige 74/72-Stufung ist obsolet. Separate Wide-Date-/Status-Spalten: 7rem und 6rem. Restore belegt als archived-post-Aktion ausschließlich den festen Archive-Slot **6**, nicht Publish-Slot **4**; inapplicable Publish/Schedule deaktiviert. Keine zusätzliche Actions-Spalte. Kanonischer Owner `docs/ADMIN-TABLE-CONTRACT.md`; Exhibition bleibt bei 64rem. Quellgeprüft, nicht browserabgenommen.

**Public-SPA:** Das jüngste `dev` erweitert die Bewegung der ursprünglichen `#content::before/::after`-Konturen um die **bestehenden drei** Papier-Schattenebenen, keine neuen DOM-Knoten. Vier `top/bottom`-Werte plus aktueller `boxShadow` werden vor Livewire-Swap erfasst. Bei Zielwahl bleiben die originale seitliche Position und `.55rem`-Returns unverändert, die zwei hinteren Schattenebenen bewegen sich nur vertikal wenige Pixel, die vorderste bleibt fest. Native Pseudo-/Shadow-Animationen dauern **1150ms**. **`prefers-reduced-motion: reduce` wird im neuen Quellcode für diese Dekoration ausdrücklich nicht mehr als Stop-Kriterium verwendet**; dokumentierte, noch nicht browserabgenommene Barrierefreiheitsabweichung. Der Public-Vertrag und `public-spa-lifecycle.md` bilden diesen Stand ab. JS-/Testquellen nur gelesen; keine Tests oder Browserläufe behaupten.

**Nächster Schritt:** `generate-architecture-eloquent.php` auf explizite Relationserkennung sowie `generate-architecture-relations.php` auf verbleibende Alias-/Constructor-Scope-Randfälle prüfen, reproduzierbare Quell-Fixtures ausbauen. Danach die Architektur-Quellenlisten gegen echten sicheren Checkout validieren und restliche Vertragsduplikate gezielt reduzieren.

**Offen:** vollständige native PHP-Generator-Checks, isoliert vollständig migriertes PostgreSQL-`pg_catalog`, Notification-Transport sowie Browser-/Produktabnahme. Nicht CI oder `testing`.
