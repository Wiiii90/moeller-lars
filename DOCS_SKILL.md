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
| `docs`, zuletzt vor dieser Quellenbereinigung geprüft | `e6a280f0ea05e666151b38c2e10cf272ead90a8f` |
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

## Letzte abgeschlossene Einheit und unmittelbare Fortsetzung

**Aktuell geprüfter Source-Stand (14.10.2026):** `dev` `1ca4014cae997b0b4a40422944b90a0328524f53` ist in `docs` enthalten. Letztes geprüftes `docs`-HEAD **vor dieser Dokumentationsbereinigung:** `e6a280f0ea05e666151b38c2e10cf272ead90a8f`. Beim Wiedereinstieg beide HEADs **frisch abrufen und vergleichen**; nie diesen Stand als fixen Branch-Zeiger verwenden. `testing`, `main`, CI und persistente Daten bleiben unangetastet.

**PHP-Inventare und verifizierte Abdeckung:** Git-Tree `ca162bcd5` (ursprünglicher Vollkatalog) gegen `1ca4014ca` verglichen: weiterhin exakt **248** `app/**/*.php`-Pfade, 214 Klassen, 27 Traits, 7 Enums. Nur `JournalWorkspace.php` und `SecurityHeaders.php` änderten ihre PHP-Blobs; deren benannte Typen und **20 bzw. 0** projektinterne Imports sowie öffentliche Methodennamen blieben unverändert. Deshalb sind die **900 deklarativen Projektimporte / 717 modulübergreifenden Kanten / 20 Diagramme** auf dem geprüften Stand weiterhin strukturell gültig. `generated/app-source-audit.json` behält korrekterweise seine ältere `source_ref`: Bytes/andere rohe Quellanker sind nicht durch einen angeblichen Full-Generatorlauf nachgezogen. [Prüfung](docs/architecture/php-source-audit.md).

**Modelle:** Alle **26** aktuellen Model-PHP-Quellen gegen die **48** expliziten Relation-Ziele im JSON geprüft. Zwei veraltete `BlogPost`-Zeilenanker wurden korrigiert (`siteSection: 30→37`, `mediaUsages: 35→42`), ohne Typ/Ziel/Kantenanzahl/Graph zu verändern. [Belege](docs/architecture/eloquent-relations.md). Eloquent-/Importrelationen sind keine SQL-FKs.

**Dokumentationsgeneratoren:** `generate-architecture-inventory.php` behandelt Namespace-Scope statt scheinbarer Closure-/Trait-`use`-Imports; `generate-architecture-relations.php` erkennt globale `app()/resolve()`-Helper ohne Methoden-/Static-False-Positives, hat den `$mode`-CLI-Selbsttest-Reihenfolgefehler behoben und tokenisiert Konstruktorattribute/-Defaults/Union-/Intersection-Parameter; `generate-architecture-eloquent.php` verwendet einen engen Return-Type/Methoden-/Literal-Tokenizer statt eines formatabhängigen Regex; `check-architecture-routes.php` erfasst die **20** expliziten `routes/web.php`-Routen tokenbasiert ohne Kommentare/String-False-Positives. Für diese Parser liegen mehrere **tatsächlich ausgeführte lokale PHP-8.4-Fixtures** vor, für Eloquent außerdem synthetische `--self-test`/`--write`/`--check` mit **3 Models/3 Relationen**. **Nicht behaupten:** Native gesamte Checkout-Läufe der eingecheckten Skripte, Regeneration des 248er Quellkatalogs, echtes `artisan route:list` oder physische DB-Prüfung. [Generator-/Abdeckungsindex](docs/architecture/README.md).

**Runtime-/Domain-Architektur:** [Admin Notification Transport](docs/architecture/admin-notification-transport.md) trennt Mutation, Append-only Audit, request-lokale Activity-Präsentation, `AdminNotifier`, user-scoped Mailbox und Livewire-/Session-Header. Wiederholung einer stabilen `source_id` verhindert nicht automatisch ein neues Header-Event; bei ausgeschöpftem gepinntem Retention-Limit könnte eine neue ungepinnte Notification sofort gelöscht werden. **Quellenhypothesen, nicht runtimegetestet.** [Public-SPA-Lebenszyklen](docs/architecture/public-spa-lifecycle.md) enthalten nun Journal-Slideshow, lazy Exhibition-Map mit Async-Disposition, Home-Hero, Artwork-Viewer mit AbortController, Matomo und native Folio-Pseudoelement-/Shadow-Animation. Der aktuelle Folio-Code ignoriert `prefers-reduced-motion: reduce` bewusst für diese kleine Dekoration; **Accessibility-/Browserabnahme offen**.

**Normative Admin-/Public-Verträge:** Blog Date/Status und sieben Actions wechseln zusammen bei **72rem realer Tabellenbreite**; Wide bleibt das **stabile** 7-Track-Action-Grid (State-Slot 6rem, sichtbares `Cancel`, zugänglich `Cancel schedule`, Restore in Archive-Slot 6), keine vorübergehende content-sized Flex-Version. Exhibition Date/Status faltet bei 64rem; einzeilige Range/Vernissage. [Owner](docs/ADMIN-TABLE-CONTRACT.md). Pages Settings/Add/Edit sind im [Dialogvertrag](docs/ADMIN-DIALOG-CONTRACT.md), Home Skip-/Destination-Zeilenaktionen im Tabellenvertrag, Metrics enthält nur Metric-Prioritäten. Public motion dokumentiert ausschließlich die ursprünglichen `#content::before/after`-Kanten und drei vorhandene Shadow-Ebenen, keine überlagerten Linien/Debug-Modi. Keiner dieser neu gezogenen Vertragsstände wurde durch diesen Dokumentationszweig im Browser abgenommen.

**Migrationsevolution:** [Schema-Verlauf](docs/architecture/schema-evolution.md) unterscheidet eingefrorene 16 Tabellen am 29.08. (inkl. `cv_entries`), die Version-/Hashmigration 15.09. mit **veränderlichem aktuellem** `PublicationSnapshot::TABLES` von 15 Tabellen und spätere CV-Retirement-Konversion/Löschung am 16.09. CV fehlt somit beim damaligen Backfill/Hashinput trotz noch vorhandener Tabelle. **Quellbelegter Self-Containment-Verstoß, kein nachgewiesener `migrate:fresh`-Fehler.** Historische Migration nicht auf `docs` reparieren.

**Publication-Revert-Concurrency:** [Working-Operations](docs/architecture/publication-working-operations.md#statische-race-sequenz-revert-ziel-vor-dem-lock) enthält nun ein konkret quellgestütztes H/P→H2-Interleaving: Revert lädt aktuelles LIVE H und Parent P vor dem Advisory Lock; paralleles `PublicationService::commit()` kann H2 erzeugen; danach staged Revert unter Lock weiterhin P mit Source H und ohne HEAD-Revalidation. Bei späterem Commit kann die Parent-Linie zu H2 gehören, die Revert-Provenienz aber H nennen. **Nur statisches Race-Fenster**, kein ausgeführter Paralleltest; konzeptionell LIVE-/Parent-Auswahl oder erwartete HEAD-ID unter derselben Sperre erneut prüfen. Keine Runtime-Änderung auf `docs`.

**Strukturierte Content-Daten:** [`structured-components.md`](docs/architecture/structured-components.md#tatsächliche-reihenfolge-kandidat-normalisierung-und-öffentliche-readiness) enthält jetzt die tatsächliche Reihenfolge: CustomPageSetting-`saving` normalisiert **vor** `validateBlocks(false)`; Home-`validateComponents()` prüft den ungespeicherten Kandidaten über `assertEditableComponents()`; `assertReadyForPublic()` erzwingt zusätzlich Public-Media-Guards für veröffentlichte Komponenten/Kinder. Quellbelegte Unterschiede für ungültige Raw-Lists, Divider-Fallback, Contact-Deduplizierung, `published`-Boolean und ALT; noch kein Import-/DB-/Runtime-Test. Risiko von stiller Normalisierung bleibt bei direktem Model-Save getrennt von der UI-Mutation zu prüfen.

**Relationships-Generator: aktuelle Abdeckungsgrenze:** `generated/app-source-audit.json` listet 248 PHP-Quellen und 900 interne `App\...`-Imports; **0** davon enthalten gruppierte PHP-`use`-Deklarationen oder `as`-Aliase. Die zwei seit der Aufnahme veränderten PHP-Blobs haben diese Imports nicht geändert. Damit sind entsprechende Alias-Randfälle für den aktuell geprüften Produktionsquellstand nicht aktiv belegt. `generate-architecture-relations.php` benötigt als eigenen, gegenwärtig **nicht eingecheckten** Input `generated/php-symbols.json`, den zuvor nur `generate-architecture-inventory.php --write` im realen Checkout erzeugt. Alte `app-source-audit.json`-Daten nicht als native Tokenizer-Ausgabe ausgeben. Details im [Architekturindex](docs/architecture/README.md).

**Komponenten-Tabellenvertrag konsolidiert (14.10.):** Die ausführlichen Home-/Custom-Page-Regeln für Parent/Child-Achsen, permanenten Component-Select, Kind/Content-Trennung, Sortier-/Selection-/Edit-Semantik, Add Row und fünf Actions sind jetzt unter `docs/ADMIN-TABLE-CONTRACT.md#component-sequences` gesammelt. `ui-skills.md` §10/§11 verweist darauf und auf den getrennten Dialog-/JSON-Owner. **Wichtiger Vertragswiderspruch behoben:** Die alte feste „genau eine Notification pro Home-Aktion“-Behauptung widersprach `AdminNotifier`/Notification-Vertrag (eine Notification pro konsumiertem validem Activity-Kontext, möglicherweise mehrere). Jetzt allein die kanonische Kardinalitätsregel. Beide tatsächlichen Blade-Hierarchien quellengeprüft; **keine Browser-/Livewire-Tests** hierfür ausgeführt.

**Unmittelbare nächste Arbeit:** (1) übrig gebliebene UI-Duplikate gegen aktuelle Blade-/CSS-Owner konservativ bereinigen, (2) echte source-belegte Runtime-/Media-Negativpfade prüfen, (3) `dev` nach Pfadkontrolle korrekt mergen. Native PHP-Generator-Checks und `pg_catalog` erfordern weiterhin einen sicheren vollständigen Checkout beziehungsweise eine ausdrücklich disposable vollständig migrierte PostgreSQL-Instanz. Volle PHP-Generator-Checks und `pg_catalog` erst bei sicherem vollständigem Checkout und ausdrücklich disposable vollständig migrierter PostgreSQL-Instanz. Keine CI, kein `testing`, kein Browser-/Runtime-Erfolg aus statischem Code ableiten.
