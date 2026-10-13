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
| `docs`, zuletzt vor diesem Update geprüft | `afd2369f862518e116c4f965969adb7f758a842b` |
| `dev`, für diesen Merge geprüfter Stand | `1ca4014cae997b0b4a40422944b90a0328524f53` |
| Status | `dev` bis zur genannten SHA bereits in `docs` enthalten; spätere Commits neu vergleichen |

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
- **Produktabweichung (Quelle, nicht runtimegetestet):** Gallery-Publish über `ArtworkPublicationService` akzeptiert ALT-Override und unterstützte Videos ohne Bild-Thumbnail; Undo-Re-Publish über `AdminLifecycleRegistry` benutzt den strengeren `ArtworkEditorialService` mit Asset-ALT/Thumbnail-Pflicht. [Testmatrix](docs/architecture/import-boundaries.md#negativfallmatrix-für-eine-kanonische-publish-readiness) einschließlich möglicher umgekehrter MIME-Guard-Lücke und Validation-/Receipt-Fragen ist erfasst. Kein Application-Fix auf `docs`; später eine einzige kanonische Domain-Readiness abnehmen.
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

**Markdown-Verweisprüfung (14.10.2026):** 62 Markdowndateien und 553 erfasste Markdown-Linkvorkommen per GitHub-`docs`-Tree `e33cc5b1` gegen reale Pfade geprüft: **keine fehlenden relativen Ziele**. Zusätzlich 19 Absatzanker-Verwendungen (14 eindeutige Ziel/Fragment-Paare) gegen tatsächliche Überschriften geprüft: **alle vorhanden**. URI-Beispiel `media:123` ist ausdrücklich kein relativer Dateipfad. Neuer reiner PHP-Linkprüfer `scripts/check-documentation-links.php` mit `--check|--summary|--self-test` erstellt. Sein **identischer Git-Blob** wurde unter lokalem PHP 8.4 mit `php -l`, `--self-test` und einem synthetischen positiven sowie absichtlich negativen Checkout-Fixture tatsächlich ausgeführt; der neue Checker wurde **noch nicht** auf einem vollständigen realen Checkout ausgeführt. Komplexe/mehrzeilige Markdown-Destinations und HTML-/MDX-Links außerhalb des Scanner-Modells; dazu siehe [docs/README.md](docs/README.md#local-documentation-link-verification).

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

## Aktueller Prüfstand und Wiedereinstieg (14.10.2026)

**Branch-Checkpoint:** Letztes bestätigtes `docs`-HEAD vor diesem reinen Dokumentationsupdate: `afd2369f862518e116c4f965969adb7f758a842b`; darin ist `dev` bis `bd8e7fd328b56c25e638be01b5be4b93b3b87bf1` über den echten Zwei-Eltern-Merge `facca0f28d3fab73487d1116f11544f3db1e869d` übernommen. Vor jeder weiteren Änderung beide Heads neu abfragen, die konkreten Pfade vergleichen und neue `dev`-Commits nur kontrolliert **als echten Zwei-Eltern-Merge** nach `docs` holen. `dev`, `main`, `testing`, CI und persistente Daten nicht verändern.

**Quellinventar:** Der ursprüngliche vollständige Katalog [php-source-audit.md](docs/architecture/php-source-audit.md) hat **248 `app/**/*.php`-Dateien / 214 Klassen / 27 Traits / 7 Enums / 900 interne Imports / 717 Cross-Module-Kanten / 20 Importdiagramme**. Der vollständige Blobvergleich bis zum obigen `dev`-Stand ergibt nur zwei geänderte PHP-Blobs (`JournalWorkspace.php`, `SecurityHeaders.php`), beide ohne neue/entfernte projektinterne Imports oder Typen. Das JSON behält bewusst die ursprüngliche `source_ref`: Dateibytes/rohe Zeilendaten sind **nicht** als vollregeneriert auszugeben. Alle **26 Models / 48 Relation-Methoden** wurden mit der aktuellen Quelle erneut auf Typ/Ziel/Zeile abgeglichen; zwei BlogPost-Zeilennummern wurden nachgezogen. Eloquent- und Importkanten sind keine physischen FKs.

**Dokumentationsgeneratoren:** PHP-Tokenizer/Relation-Parser erfassen Namespace-/Closure-Scope, globale `app()/resolve()` statt Methoden/Static-Calls, Konstruktor-Union/Intersection/Attribute und direkt typisierte Eloquent-Methoden. Routenprüfer trennt die **20** expliziten Routen von Kommentaren/Strings. Parser haben **lokale PHP-8.4-Fixtures**, Eloquent zudem einen ausgeführten **synthetischen 3-Model-/3-Relationen-`--write/--check`-Lauf**, aber keinen echten vollständigen Application-Checkout. `generate-architecture-relations.php` erwartet `generated/php-symbols.json`, derzeit **nicht eingecheckt**; dafür muss im echten Checkout zuvor `generate-architecture-inventory.php --write` laufen. Der unabhängige Importkatalog ersetzt die native Eingabe nicht. Von den 900 internen Imports enthalten **0** gruppierte `use`-Deklarationen oder `as`-Aliase, sodass dafür im geprüften Quellstand kein aktiver Produktionsfall vorliegt.

**Markdown-Verweise:** Vollständiger GitHub-`docs`-Tree-Audit über **62 Markdowndateien und 553 Markdown-Linkvorkommen**, kein fehlender relativer Zielpfad. **19** Abschnittsanker-Verwendungen (14 unterschiedliche Ziel-/Fragment-Paare) stimmen mit den vorhandenen Überschriften überein. Neuer [lokaler Linkprüfer](scripts/check-documentation-links.php) prüft Pfade und einfache Überschriftenanker mit `--check|--summary|--self-test`. **Identischer Blob-SHA** unter PHP 8.4 lokal syntaktisch geprüft, Selbsttest und positive/negative Fixture-Repositorys ausgeführt. **Kein** voller Checkout-Run dieses Skripts behauptet; komplexes CommonMark/HTML/MDX liegt außerhalb seines dokumentierten Parsermodells.

**Normative UI-Owner:** [ADMIN-TABLE-CONTRACT](docs/ADMIN-TABLE-CONTRACT.md) besitzt Blog-/Exhibition-Splitfold, stabile siebenachsige Blog-Actions und nun die gesamten Home-/Custom-Page-Component-Hierarchien (permanenter Component-Select, gemeinsame Parent-/Child-Achsen, Edit, Selection, Add Row). `ui-skills.md` verweist auf diese Details statt sie erneut festzuschreiben; [ADMIN-DIALOG-CONTRACT](docs/ADMIN-DIALOG-CONTRACT.md) besitzt Pages-Settings und Dialogpersistenz, [ADMIN-RESPONSIVE-METRICS](docs/ADMIN-RESPONSIVE-METRICS.md) nur Metrics. Die frühere Aussage „genau eine Home-Erfolgsnotification pro Aktion“ wurde entfernt: [Notification-Vertrag](docs/ADMIN-NOTIFICATION-CONTRACT.md) und `AdminNotifier` bestimmen die Meldungszahl anhand der tatsächlich konsumierten validen Activity-Präsentationen, potenziell mehr als eine.

**Media Reference/Cleanup (Quelle, 14.10.2026):** [Architekturbeleg](docs/architecture/media-reference-and-cleanup.md) ordnet Storage-Löschung (Publication-Advisory-Lock, Canonical-Detach, logische Löschung), physische Dateilöschung (Working/LIVE/History-Guards, Queue) und `Free storage` (geschützter LIVE-/Working-Source-Snapshot, Undo-Receipts, Variante nur nach reproduzierbarem SHA/Byte-/Dimension-Check) getrennt ein. **Konkrete Lücke:** Home-`list.media_asset_id` und `list.items[].body` werden trotz gültiger Home-List-Speicherung/Rendering nicht von `MediaReferenceQuery::mediaIdsForHome()`, `MediaReferenceCatalog::contentReferenceRowsByMediaId()` oder `MediaAssetEditorialService::removeHomeReferences()` berücksichtigt; Custom Page besitzt beide Pfade. Zusätzlich unmittelbarer `deleteNow()`-Fehlerpfad ohne Queue-Nachtrag und boolescher No-op bei erneutem `delete(deleted)`. Alles statische Testfälle für `dev`/Testing, keine Runtime-Reproduktion und keine Application-Änderung hier.

**Quelle statt Runtime-Erfolg:** [Admin Notifications](docs/architecture/admin-notification-transport.md) dokumentiert `firstOrCreate` nur für Mailbox-Idempotenz, nicht Header-Payload-UUID; ausgeschöpftes Pin-Kontingent kann neue ungepinnte Records verdrängen. [Artwork-Publish/Undo](docs/architecture/import-boundaries.md) besitzt eine Positiv-/Negativmatrix für ALT-Override/Video/Thumbnail-Readiness, da Gallery- und Undo-Publish-Services unterschiedliche Guards haben. [Publication-Revert](docs/architecture/publication-working-operations.md) dokumentiert das vorgelagerte H/P-Lesen vor dem Advisory Lock und das mögliche H2-Race. [Migrationsevolution](docs/architecture/schema-evolution.md) trennt historische 16 Tabellen (inkl. CV) vom aktuellen dynamisch verwendeten 15er History-Backfill. [Structured Components](docs/architecture/structured-components.md) trennt direktes Model-`saving` (Normalisierung vor Validation) vom Home-Edit- und öffentlichen Readiness-Guard. [Public SPA](docs/architecture/public-spa-lifecycle.md) dokumentiert Viewer-/Map-/Slideshow-/Matomo-Disposer und Folio-Pseudoelement-Animation samt offener Reduced-Motion-Abnahme. Diese potenziellen Fehler sind **quellenbasiert**, nicht durch Runtime-, DB- oder Browser-Tests bestätigt.

**Public-Folio-Dev-Merge (14.10.2026):** Nach drei neuen `dev`-Commits (`95ecdd9`, `eef9910`, `bd8e7fd`) animiert die native WAAPI die vorhandenen Content- und Menü-Pseudoelemente nun **kontinuierlich auch beim ersten Seitenaufbau**. Content links/rechts 16/19,4 Sekunden, Menü 17,4/20,8 Sekunden, zwei hintere Content-Schatten 18,2 Sekunden. Menükonturen West/Outer-East angepasst; keine zusätzlichen DOM-Kanten/Timer. `app.js` initialisiert ohne bisherigen `pendingVisit`-Guard. Sowohl JS- als auch Browser-Testquellen sind inzwischen an die exportierte Dauerschleifen-API angepasst. [Public-SPA-Architektur](docs/architecture/public-spa-lifecycle.md) beschreibt die neue Source-Sequenz. **Keine hier ausgeführten JS-/Browser-Tests und keine visuelle Freigabe.**

**Nächste Arbeit:** Weiterhin kontradiktorische UI-Vertragsduplikate nur nach tatsächlichem Blade/CSS-/Domain-Quellenabgleich reduzieren; weitere konkrete Media-/Publication-Negativpfade aufzeichnen. Native Full-Checkout-Generatorläufe und `pg_catalog` erst bei sicherem vollständigem Checkout bzw. ausdrücklich wegwerfbarer vollständig migrierter PostgreSQL-Instanz. Keine Runtime-Fixes auf `docs`, kein CI und keine Testbehauptungen aus bloßen Quelltexten.
