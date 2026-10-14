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
| letzter bestätigter Zwei-Eltern-`docs`-Merge | pending merge of `dev@1a6cbe45` |
| darin aufgenommener `dev`-Stand | `1a6cbe45cfa81df0ba2d224bbed2eb9ffc377528` |
| Status | Gallery-/Journal-Livewire-Testquellen und Dokumentationsänderungen konfliktgeprüft; neue Heads erneut prüfen |

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

**Markdown-Verweisprüfung (14.10.2026):** Aktueller **unabhängiger GitHub-Tree-Quellscan** über 63 Markdown-Dateien und 609 einfache Inline-Linkvorkommen (581 relative lokale Ziele) auf `docs@f7bdc77d6`: **kein fehlender relativer Pfad**. 25 Ziel-Fragment-Verwendungen (17 verschiedene Paare) gegen vorhandene Überschriften geprüft: **alle vorhanden**. URI-Beispiel `media:123` ist ausdrücklich kein relativer Dateipfad. Neuer reiner PHP-Linkprüfer `scripts/check-documentation-links.php` mit `--check|--summary|--self-test` erstellt. Sein **identischer Git-Blob** wurde unter lokalem PHP 8.4 mit `php -l`, `--self-test` und einem synthetischen positiven sowie absichtlich negativen Checkout-Fixture tatsächlich ausgeführt; der neue Checker wurde **noch nicht** auf einem vollständigen realen Checkout ausgeführt. Komplexe/mehrzeilige Markdown-Destinations und HTML-/MDX-Links außerhalb des Scanner-Modells; dazu siehe [docs/README.md](docs/README.md#local-documentation-link-verification).

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

### Quell- und Generatorstand

- **Zuletzt vollständig aufgenommener Originalbestand:** 248 App-PHP-Dateien,
  214 Klassen, 27 Traits, 7 Enums, 900 deklarierte interne Imports,
  davon 717 modulübergreifend, und 20 einzelne Importdiagramme.
  Der Journal-/Settings-Refactor ergab in einer **statischen Delta-Prüfung**
  **901 interne / 718 modulübergreifende Imports**. Die eingefrorenen
  JSON-/Mermaid-Graphen wurden **noch nicht vollständig regeneriert**;
  ihre ursprüngliche `source_ref` nicht fälschlich auf den neuen Commit
  heben. [Import-Audit](docs/architecture/php-source-audit.md).
- **Models/Relationen:** 26 Eloquent-Modelldateien und 48 explizite
  Relationsmethoden abgeglichen; kein aus PostgreSQL abgeleitetes ERD.
  **HTTP:** 20 explizite Laravel-Routen; Filament-/Livewire-Registrierung
  bleibt dynamisch. **Admin-Quelle:** 9 Pages, 26 Concerns, 2 Livewire-
  Komponenten; 26 interne Trait-Uses, 72 wörtlich benannte Actions.
- **Generatoren und Laufgrenzen:** Die vorhandenen
  `scripts/generate-architecture-*.php` und
  `scripts/check-documentation-links.php` besitzen tatsächlich lokal
  ausgeführte, isolierte PHP-8.4-Parser-/Write-/Check-Fixtures.
  Kein kompletter nativer PHP-Generatorlauf über alle echten Dateien.
  `generate-architecture-relations.php` benötigt zuerst
  `generated/php-symbols.json` aus dem Inventory-Generator
  (noch nicht eingecheckt). Ein echtes `artisan route:list`, Filament,
  PostgreSQL-Schemaexport, Laravel-/Livewire-Featurelauf oder Playwright
  wurde hier **nicht ausgeführt**. Vollständiges `pg_catalog` nur mit
  ausdrücklich disposable, vollständig migrierter Testdatenbank.
- **Markdown-Verweise:** Authentifizierter unabhängiger GitHub-Tree-
  Quellscan auf `docs@f7bdc77d6`: **63** Markdown-Dateien,
  **609** einfache Inline-Linkvorkommen, davon **581** relative lokale
  Ziele; **25** Abschnittsanker-Verwendungen, **17** eindeutige
  Ziel/Fragment-Paare; **0** fehlende relative Ziele oder geprüfte
  Anker. Kein vollständiger CommonMark-Parser-/nativer PHP-Checkout-
  Lauf. [Dokumentationsindex](docs/README.md#local-documentation-link-verification).

### Kanonische Dokumentations-Owner und bereits bereinigte Normen

- [Tabellen](docs/ADMIN-TABLE-CONTRACT.md) besitzen Home-/Custom-Page-
  Component-Hierarchie, permanente Component-Selects und Parent-/Child-
  Achsen sowie Blog-/Exhibition-Splitfold, Toolbar- und Selection-
  Geometrie. Die **Dashboard-Feed-Ausnahme** (Selected-Trigger ohne
  internen Counter, separates Count-`span` innerhalb des terminalen
  Selection-Bereichs) ist jetzt am selben kanonischen Selection-Owner
  mit dem Dashboard-Blade abgeglichen. `ui-skills.md` leitet auf
  diese Normen weiter, statt eigene Achsen-/Counter-Regeln zu führen.
- [Dialoge](docs/ADMIN-DIALOG-CONTRACT.md) besitzen Edit-/Create-/Command-
  Persistenz, Page-Heading-Autosave, Pages Settings und Journal-/
  Gallery-/Custom-Settings-Konsistenz. Gallery Artwork Create/Edit teilen
  nun den fachlichen `ArtworkEditDialog::schema()`-Owner; Gallery-Edit-
  Header-Moves/Publication/Detach und Materials-Repeater-Commit sind
  nach Quellvergleich im Dialogvertrag nachgeführt. Gleiches gilt
  für Blog-/Exhibition-Edit-Header-Rollen und responsive `md`-Formen
  aus dem anschließenden `dev@f9afd7aa`-Stand. Neue `dev@1a6cbe45`-Featuretests prüfen Gallery-Materials-
  Commit erst nach explicit Action sowie Gallery-/Journal-Create-/Edit-
  Mounts. Tests wurden auf `docs` **nicht ausgeführt**; sie ersetzen
  weder tatsächliche Header-Action-Mutationen noch Browser-Akzeptanz. Die `dev`-Änderungen wurden
  inhaltlich mit den zusätzlichen Pages-Regeln zusammengeführt.
- [Benachrichtigungen](docs/ADMIN-NOTIFICATION-CONTRACT.md) definieren
  die Meldungskardinalität anhand tatsächlich konsumierter Activity-
  Präsentationen, **nicht** pauschal „eine Home-Notification pro Aktion“;
  Mailbox-Idempotenz (`firstOrCreate`) ist nicht dasselbe wie Header-
  UUID-/Transport-Idempotenz.
- [Public](docs/PUBLIC-IMPLEMENTATION-CONTRACT.md): Home besitzt
  **Hero Artwork, Under Construction, Custom**; Skip Home bleibt ein
  separates Routingflag. Menü-/Content-Folio nutzt nur vorhandene
  Pseudoelemente/Schatten und native Livewire-Dokumentnavigation,
  keine zusätzlichen Overlay-Knoten oder JS-Timer. In
  [General](app/Filament/Pages/General.php) erlaubt Page Width jetzt
  Einzelpixel (`step(1)`) innerhalb unveränderter Min-/Max-Grenzen.
- CV ist heute Teil strukturierter Custom-Page-Komponenten; die
  historische `cv_entries`-Tabelle ist nicht aktiv. Historisch
  **16** Publication-Tabellen einschließlich CV, aktuell **15**.
  v0.4 ist noch nicht nach `main` abgenommen; v0.5-Cutover ist
  weiterhin bedingt, kein vollzogener Produktionswechsel.
  Altvertrag und Migrationsbelege nicht durch aktuelle Policies ersetzen.

### Offene konkret belegte Produkt-/Testpfade

1. **Pages → Edit: getrennte Titel und Navigationslabels.**
   `SitePages::sectionLabel()` gibt beim Form-Fill vorrangig
   `navigation_label` als `name` zurück;
   `ManagesSitePageEditDialog::updateDialogIdentity()` speichert
   dies auch bei vermeintlichem No-op als `title`, bei nicht-Gallery
   über `SiteSectionIdentityService`. Journal, Custom Page und
   Navigation Node sind betroffen; Gallery synchronisiert über
   `GalleryEditorialService` ebenfalls einen Label-Wert in die
   kanonische Namens-/Titelschicht. Journal-Listing-Titel können
   unbeabsichtigt mitfolgen. Konvertierung und mehrfaches Autosave
   sind zusätzliche Negativfälle. [Quellpfad/Testmatrix](docs/architecture/ui-surfaces.md).
   **Kein ausgeführter Livewire-Nachweis**.
2. **Home/Custom Page: gleichartige Komponenten nach Reorder.**
   Ein fehlender `CustomPageWorkspaceTargetHelpers::componentAt()`-
   Zugriff wurde inzwischen auf `dev` wiederhergestellt, inklusive
   Test für die dritte Textkomponente nach einem Divider.
   Beide Home-/Custom-Page-Editoren prüfen jedoch als Stale-Target nur
   **absoluten Index + Persistenztyp**. Vertauscht ein zweiter Kontext
   zwei `text`-Blöcke, kann ein weiterhin offener Editor bei gleicher
   Typprüfung den falschen Block überschreiben. Home-Heading und
   Home-Rich-Text persistieren beide als `type=text`.
   Konkret geforderter Zweifenster-Reorder-/Save-Konflikttest und
   stabile Identity-/Revision-Guard-Prüfung in
   [UI-Surfaces](docs/architecture/ui-surfaces.md).
3. **Home-List-Media:** `MediaReferenceQuery::mediaIdsForHome()`,
   `MediaReferenceCatalog::contentReferenceRowsByMediaId()` und
   `MediaAssetEditorialService::removeHomeReferences()` erfassen
   Home-List-`media_asset_id` und Medien aus `items[].body` nicht
   vollständig; die Custom-Page-Pfade tun dies bereits.
   Referenzlisten/Storage/Detach müssen separat von LIVE-/History-
   Schutz bei physischem Reclaim bewertet werden.
   Zudem Fehler nach schon committeter logischer Löschung und
   Retry-/Queue-Übergänge untersuchen.
   [Media-/Cleanup-Audit](docs/architecture/media-reference-and-cleanup.md).
4. **Publication/Audit/Migration:** Gallery-/Undo-Publish-Readiness
   unterscheidet sich bei ALT-Override, Video und Thumbnail;
   ein kanonischer Guard mit Negativtests fehlt.
   Revert liest LIVE und Parent vor dem Advisory Lock (potenzielles
   Revert-/Commit-Interleaving). Historische Versioning-Migration
   referenziert das aktuelle `PublicationSnapshot::TABLES`
   dynamisch, obwohl historische CV-Tabelle abweicht.
   Weitere Quelle: [Importgrenzen](docs/architecture/import-boundaries.md),
   [Publication-Operationen](docs/architecture/publication-working-operations.md),
   [Schemaevolution](docs/architecture/schema-evolution.md).
5. **Public-Folio/SPA-Lifecycle:** Im jüngsten `dev@298bf74bce837385b8a7261b145d2b9fd887ed7c`
   besitzt **jeder** Public-Inhalt (Home, Gallery, Artwork, Blog,
   Exhibitions, Custom, CV) dieselbe `#content > .public-content-reveal`-
   Hülle. Der frühere Artwork-only-700-ms-Höhenübergang ist entfernt.
   Beim Livewire-Swap misst `capturePublicFolio()` die vorherige
   Innenhöhe; `animatePageHeight()` nutzt **980 ms** bei Differenz
   mindestens 7 px und clippt ausschließlich die innere Hülle.
   **Jeder** tatsächliche Swap erhält einen `pulsePaper()`-Impuls,
   selbst bei gleicher Höhe: Content-Ecken/Schatten **1.480 ms**,
   Menü-Ecken/Schatten **980 ms**. Erst wenn die endlichen
   Animationen fertig sind und das DOM-Ziel noch dasselbe ist,
   startet `startAmbient()` erneut: Content-Ecken
   **19,8/23,6 s**, Menü-Ecken **21,9/25,8 s**,
   Content-/Menü-Schatten **24,7/28,1 s**, ohne JS-Timer.
   Echte Papierlagen, keine zusätzlichen DOM-Dekorationen;
   einheitliche Source in [Public-SPA](docs/architecture/public-spa-lifecycle.md)
   und [Public-Vertrag](docs/PUBLIC-IMPLEMENTATION-CONTRACT.md).
   Aktualisierter Browser-Test enthält außerdem einen zweiten
   unterbrochenen SPA-Swap: tatsächliche Corner-/Shadow-Zwischen-
   koordinaten und Content-Höhe werden als neue WAAPI-Startwerte
   geprüft, ohne Timer. **Nur Testquelle gelesen**, nicht ausgeführt.
   Aktuelle Unit-/Browser-Testdateien wurden nur **gelesen**;
   kein Runtime-/Browser-Lauf. Unendliche WAAPI-Handles weiterhin
   ohne explizite `cancel()`-Disposer: mögliche Retention **nur
   als Browser-Testlücke**, nicht als bestätigter Leak.
6. **Weitere Quellgrenzen:** `AdminNotifier`/Mailbox-Pin-Limits,
   Restore-/Revert-/Reset-Working-vs-LIVE-/History-Guards,
   Medienvarianten mit Byte-/Hash-identischer Regenerierung,
   Migrationskompatibilität und dynamische Action-Mount-Lebensdauer.
   Diese Punkte nicht als Browser-/DB-bestätigte Defekte behandeln.

### Weiterarbeiten

1. Neuesten `docs`-/`dev`-HEAD **neu abfragen**, Pfadüberschneidungen
   prüfen und nur sichere Änderungen als **echten Zwei-Eltern-Merge**
   nach `docs` übernehmen. Stand oben ist nur Referenz.
2. Die offenen Identity-/Index+Typ-/Media-/Publication-Negativpfade
   mit zusätzlicher Quell- und Testabdeckung präzisieren; keine
   Anwendungscode-Reparaturen auf `docs`.
3. Normative Doppelungen zwischen `ui-skills.md`, Admin-Verträgen
   und dem Public-Vertrag **am vorhandenen Owner** konsolidieren.
4. Native PHP-Generatorläufe und den vollständigen Graphenabgleich erst
   auf einem vollständigen Checkout durchführen. `pg_catalog` nur
   auf einer **disposable**, vollständig migrierten DB.
5. Nach jeder abgeschlossenen Einheit `docs` committen/pushen,
   diese Arbeitsakte **inhaltlich aktualisieren statt fortschreiben**;
   niemals CI, fremde Branches oder persistente Daten anfassen.
