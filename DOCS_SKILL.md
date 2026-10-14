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
| letzter bestätigter Zwei-Eltern-`docs`-Merge | `3a0bcc846d4425fba5a16c376b33b8b68471f421` |
| darin aufgenommener `dev`-Stand | `470f2daa900f2d68db9c7e5fe2b227db20e44bc7` |
| Status | nach 14 Dev-Commits weitere drei aufgenommen: Child-Projektion im Custom-Page-Admin, dauerhafte Public-Rights-Ausführungsakte und Public-CSS-Cleanup; Docs-Source-Audit fortgesetzt, keine CI/DB/Browserläufe |

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
| PHP-Quellkatalog | [php-source-audit.md](docs/architecture/php-source-audit.md): **eingefroren** 248 Typdateien / 214 Klassen / 27 Traits / 7 Enums / 900 Imports (717 cross-module); **aktueller Tree 245 PHP-Dateien** nach drei entfernten Custom-Page-Traits, aktuelle Importgesamtsumme **unbekannt**, Generator/Graph nicht regeneriert |
| Modul-/Klassendiagramme | [20 einzelne Importgraphen](docs/architecture/class-import-diagrams.md) sowie [aggregierter Modulgraph](docs/architecture/generated/app-module-imports.mmd); **nur deklarative Imports**, keine Laufzeitaufrufe |
| Eloquent | [eloquent-relations.md](docs/architecture/eloquent-relations.md): alle **26** Models, **48** explizite Relation-Kanten, auch Self-Relations; **nicht** physisches SQL-ERD |
| PHP-Generatoren | `scripts/generate-architecture-inventory.php`, `generate-architecture-relations.php`, `generate-architecture-eloquent.php`, `generate-architecture-import-diagrams.php` und weitere unter `scripts/`; **isolierte Fixture-Prüfungen**, kein vollständiger nativer Checkout-Lauf |
| Admin UI | [ui-surfaces.md](docs/architecture/ui-surfaces.md): **eingefroren** 9 Pages / 26 Concerns / 2 Livewire / 26 Trait-Kanten / 72 literal Actions; aktueller Tree hat **23 Concerns**, Custom Page **sieben** direkte Traits; der UI-Generator ist **nicht** neu gelaufen |
| Services und HTTP | [admin-publication-calls.md](docs/architecture/admin-publication-calls.md): **21** quellenverankerte direkte Call-Sites; [runtime-entrypoints.md](docs/architecture/runtime-entrypoints.md): **20** explizite Webrouten plus Middleware/Filament/JS; dynamische Route-Registrierung offen |
| Lifecycle / Activity | [lifecycles.md](docs/architecture/lifecycles.md): **5** Domänenautomaten, **35** geprüfte Quellanker; [Working-Operationen](docs/architecture/publication-working-operations.md): Restore/Revert/Reset und **36** geprüfte Anker |
| Public Inhaltsdaten | [structured-components.md](docs/architecture/structured-components.md): sechs Persistenztypen; Contact-Heading und drei JSON-Kinder, List-`context` mit Legacy-Projektion; **älterer JSON-Typkatalog eingefroren**, Home/UI separat |
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

**Markdown-Verweisprüfung (14.10.2026):** Aktueller **unabhängiger GitHub-Tree-Quellscan** über 63 Markdown-Dateien und 643 einfache Inline-Linkvorkommen (615 relative lokale Ziele) auf `docs@1a6955f1`: **kein fehlender relativer Pfad**. 33 Ziel-Fragment-Verwendungen (21 verschiedene Paare) gegen vorhandene Überschriften geprüft: **alle vorhanden**. URI-Beispiel `media:123` ist ausdrücklich kein relativer Dateipfad. Neuer reiner PHP-Linkprüfer `scripts/check-documentation-links.php` mit `--check|--summary|--self-test` erstellt. Sein **identischer Git-Blob** wurde unter lokalem PHP 8.4 mit `php -l`, `--self-test` und einem synthetischen positiven sowie absichtlich negativen Checkout-Fixture tatsächlich ausgeführt; der neue Checker wurde **noch nicht** auf einem vollständigen realen Checkout ausgeführt. Komplexe/mehrzeilige Markdown-Destinations und HTML-/MDX-Links außerhalb des Scanner-Modells; dazu siehe [docs/README.md](docs/README.md#local-documentation-link-verification).

## Abschlussstrategie — verbindliche Reihenfolge (14.10.2026)

**Kostendisziplin/User-Entscheidung:** Zunächst die **gesamte fachliche Architekturarbeit, Quellprüfung und Dokumentationskonsolidierung ohne Codex** mit den bereits verfügbaren Mitteln fertigstellen. Keine kleinteiligen lokalen Codex-Aufträge, keine Generator-Experimentierwellen, keinerlei CI-Ersatz. Der Nutzer bietet lokalen Codex **erst nach Abschluss dieser inhaltlichen Arbeit** für eine bewusst geplante **gebündelte** Generierung/Prüfung und abschließende Artefaktordnung an. Das ist ein späterer optionaler Schritt, **nicht** die aktuelle Arbeitsweise und keine Begründung, inhaltliche Lücken zu vertagen. Neue `dev`-Änderungen regelmäßig konfliktgeprüft mergen, aber nicht als Grund verwenden, nie ein abgeschlossenes Dokumentationspaket zu erreichen.

**Definition eines endlichen Abschlusses:** Nach benannten Architekturflächen muss jede Quelle einen Verantwortungs-/Datenfluss, relevante Guards, Fehl-/No-op-/Rollback-Pfade und Quellanker haben. Doppelte normative Verträge sind zu konsolidieren; Mermaid gehört nur dauerhaft hinein, wenn es die fachliche Struktur erklärt, nicht allein die Kantenmenge aufbläht. Statische Evidenz, tatsächliche Laufzeitbelege und unprüfbare Grenzen bleiben getrennt. Erst dann ein **einmaliges vorbereitetes** lokales Generator-/Verifikationspaket und geprüfte Entscheidung je Artefakt: dauerhafter Source-Output, nur lokal generierter Vollkatalog oder entbehrliches Zwischenprodukt. Abschluss bedeutet konkrete Kriterien und Grenzen, nicht endloses Nachziehen jeder neuen `dev`-Änderung.

### Jetzt: fachliche Arbeitsreihenfolge ohne Codex

1. **Lifecycle-/Transaktionsketten:** Admin-Actions bis persistiertem Domainzustand, Audit, Notification, Reopen; Page-/Component-Identity, Home-/Custom-Edit und parallel reordern. Jede kritische Kante explizit quellenverankern, negative Flows markieren.
2. **Publication/History/Media:** Working/LIVE, Publish-Readiness, Undo/Restore/Revert/Reset, Advisory-Lock-Grenzen, Storage-Ref- und physische Cleanup-Grenzen; bestehende Belege vervollständigen, nicht kopieren.
3. **Public/Navigation/CSP:** Routen, Navigationsfenster und Home-Sonderfall, Livewire-Swap/Disposer, deterministische Folio-Pose, Gallery-Puffer, öffentliche Media/SEO/Analytics und strikte CSP. Aktuelle Quelle über alte Animationsnummern stellen.
4. **Persistenz-/Schema-/Legacy-Grenzen:** Models und JSON-Content, deklarative vs physische Beziehungen, migrationshistorische Beweiskraft, Cutover; PostgreSQL-Katalog erst auf ausdrücklich isolierter Wegwerf-DB.
5. **Endredaktion:** einen normativen Owner je Verhalten behalten, tote/duplizierte Diagramme und Verträge gezielt entfernen/vereinfachen, Inhalts-/Linkkonsistenz und Qualitäts-/Coverage-Matrix. Status nachvollziehbar abschließen, nicht bloß inventarisieren.

### Später, erst bei fachlicher Vollständigkeit

Lokale Codex-/native PHP-Generatorläufe, isolierter PostgreSQL-Export und reproduzierbare `--write/--check`-Abnahme sind **zurückgestellt**. Bis dahin vorhandene eingefrorene JSON-/Mermaid-Artefakte als historischen Stand kennzeichnen. Es werden **keine** lokalen Commands an den Nutzer ausgelagert, solange die oben genannte fachliche Schreib-/Analysearbeit offen ist.

## Explizite fachliche Abschlussmatrix (14.10.2026)

[**`docs/architecture/completion-criteria.md`**](docs/architecture/completion-criteria.md) ist die verbindliche fachliche Abdeckungs- und Qualitätsmatrix dieses Workstreams: zehn Architekturflächen, jeweils bereits source-geprüfte Flüsse, noch fehlende inhaltliche Arbeit und **separat** später ausführbare technische Verifikation. Der geprüfte Bestand `docs@d88bd8f4` umfasst **19 Architektur-Markdown-Dateien**, **26 eingebettete Mermaid-Blöcke**, **32 eigenständige `.mmd`-Diagramme**, davon allein **20 Klassenimport-Graphen**. Die Mengen belegen keine fachliche End-to-end-Abdeckung. Jedes dauerhaft aufbewahrte Diagramm braucht eine erkennbar beantwortete Architekturfrage; rohe generierte Vollinventare können nach späterer Prüfung lokal bleiben.

**Verbindliche Kostenregel:** Solange auch nur eine fachliche Zeile der Abschlussmatrix unvollständig ist: **keinerlei Codex-Aufträge, keine CI-Nutzung, keine Generatorwellen**, keine lokalen Experimente zulasten des Nutzers. Erst **nach** vollständiger Dokumentationsanalyse, source-geprüften Abläufen und normativer Konsolidierung wird **ein** vorher vorbereiteter lokaler Verifikations-/Generator- und Artefaktordnungsauftrag geplant. Bestehende Source-Defekte werden als reproduzierbare offene Anwendungsthemen festgehalten; ihr Fix auf `dev` ist keine Voraussetzung, die Dokumentation selbst endlich abzuschließen.

## Aktueller Prüfstand und Wiedereinstieg (14.10.2026)

### Quell- und Generatorstand

- **Zuletzt vollständig aufgenommener *historischer* Originalbestand:** 248 App-PHP-Dateien,
  214 Klassen, 27 Traits, 7 Enums, 900 deklarierte interne Imports,
  davon 717 modulübergreifend, und 20 einzelne Importdiagramme.
  Der Journal-/Settings-Refactor ergab in einer **statischen Delta-Prüfung**
  **901 interne / 718 modulübergreifende Imports** *nur zum damaligen Zwischenstand*. Der aktuelle Tree enthält nach drei gelöschten Traits **245 PHP-Dateien**; die Importgesamtsumme wurde noch nicht vollständig neu gezählt. Die eingefrorenen
  JSON-/Mermaid-Graphen wurden **noch nicht vollständig regeneriert**;
  ihre ursprüngliche `source_ref` nicht fälschlich auf den neuen Commit
  heben. [Import-Audit](docs/architecture/php-source-audit.md).
- **Models/Relationen:** 26 Eloquent-Modelldateien und 48 explizite
  Relationsmethoden abgeglichen; kein aus PostgreSQL abgeleitetes ERD.
  **HTTP:** 20 explizite Laravel-Routen; Filament-/Livewire-Registrierung
  bleibt dynamisch. **Eingefrorene Admin-Quelle:** 9 Pages, 26 Concerns, 2 Livewire-
  Komponenten; 26 Trait-Kanten/72 literal `Action::make`-
  Vorkommen **nur im eingefrorenen `337da15b`-Quellkatalog**.
  Die jüngsten Journal-/Gallery-Header-Rollen sind nicht als
  vollständig neu gezählter Live-Katalog zu deklarieren.
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
  Quellscan auf `docs@1a6955f1`: **63** Markdown-Dateien,
  **643** einfache Inline-Linkvorkommen, davon **615** relative lokale
  Ziele; **33** Abschnittsanker-Verwendungen, **21** eindeutige
  Ziel/Fragment-Paare; **0** fehlende relative Ziele oder geprüfte
  Anker. Kein vollständiger CommonMark-Parser-/nativer PHP-Checkout-
  Lauf. [Dokumentationsindex](docs/README.md#local-documentation-link-verification).

### Kanonische Dokumentations-Owner und bereits bereinigte Normen

- **Pages Heading vs Site Identity (aktueller `dev@470f2daa`):** Die neue `PageHeadingFields`-Komponente ist über Pages Add, Edit und Home integriert. Autosave für Content-Heading ist quellengeprüft (Domain-Owner mit Row-Lock und No-op); neue Featuretests beschreiben die Fälle, wurden hier nicht ausgeführt. **Alte Titel-/Navigationslabel-Fehlzuordnung bleibt bestehen**: `editPageAction` nimmt `sectionLabel()` als `name`, `SiteSectionIdentityService::update` synchronisiert bei fehlendem vierten Labelparameter beide Werte. Neu betroffen als statischer Fall auch **Home** bei eigenem Label; [UI-Surfaces](docs/architecture/ui-surfaces.md#pages--edit-titel-navigation-label-und-no-op-statischer-negativpfad) enthält Regressionen.
- **Zwei bewusst getrennte lebende Zustandsdateien:** Root [`PUBLIC_RIGHTS_WORKSTREAM.md`](PUBLIC_RIGHTS_WORKSTREAM.md) steuert die **parallele Produktimplementierung auf `dev`** und wird ausschließlich dort im gleichen Commit wie Codeänderungen fortgeschrieben; unser [`DOCS_SKILL.md`](DOCS_SKILL.md) steuert die **Architekturdokumentation auf `docs`**. Bei Dev-Änderungen die Rechteakte als Source importieren, aber `docs`-Audits nicht als erfüllte Produkt-Slices kennzeichnen. Temporäre Produktdatei erst beim tatsächlichen G0–G10-Endabschluss entfernen; Codex erst nach fachlicher Vollständigkeit dieses Docs-Workstreams.
- **Structured-Content-Source erneuert:** [structured-components.md](docs/architecture/structured-components.md) beschreibt jetzt `image.children[]` als **eine** geordnete Prose-Ebene bei dekorativen Images, die ein Repeater im Parent-Image-Dialog editiert. Unterstützte Child-Typen/Verbote, Model-Normalisierung, Repeater-UUID→JSON-Order, native Float-Include, separate Top-Level-Contact-Form und transaktionales **Adopt existing prose** (Source entfernen, Child anhängen, Index neu mounten) sind verankert. **Index+Typ-Stale-Target** bleibt offen. Public/Storage-Media-Reader sind noch nicht rekursiv; JSON-Typkatalog weiterhin historisch eingefroren, nicht regeneriert.
- **Public Security/Rights/Privacy neu als End-to-end-Sourcepfad:** [public-security-privacy-boundaries.md](docs/architecture/public-security-privacy-boundaries.md) trennt LIVE-GET-/HEAD, Admin-Preview, Public/Private-Medien, Contact-POST, Nonce/CSP, TDM/robots, Matomo und Datenschutzerfassung. Zwei neue Quellenlücken: **HTTP(S)-Rich-Text-Bilder werden validiert, aber Public-CSP blockiert unbekannte Bild-Hosts**; **dekorative Image-Children mit kanonischem eingebettetem Media werden vom nichtrekursiven Working-/PublicMedia-Reader übersehen**, außerdem top-level List-Bilder im PublicMedia-Guard. Source-belegt, keine ausgeführten Negativtests. [Public-Rechte-/Privacy-Plan](docs/PUBLIC-RIGHTS-AND-PRIVACY.md) ist **teilimplementierter Produktplan**, kein vollzogener Schutz/Consent; keine falschen Download- oder Matomo-Compliance-Versprechen.
- **Activity/Undo als eigenen Source-Flow dokumentiert:** [activity-undo-lifecycle.md](docs/architecture/activity-undo-lifecycle.md) unterscheidet Audit-/Receipt-/Ordering-Projektionen, individuelle transaktionale Snapshot-/Media-/Lifecycle-/Ordering-Inversen, Actor-/TTL-/Commit-Guards, fehlende neue Undo-Receipts bei Inversen und das **nicht atomare** Selection-Undo über einzelne Receipts. Kein Livewire/DB-Test ausgeführt.
- **Abdeckungsmaßstab statt Quantität:** [Abschlussmatrix](docs/architecture/completion-criteria.md) koordiniert jetzt alle Quellketten und noch offenen fachlichen Zeilen; 20 eingefrorene Importdiagramme ausdrücklich nicht als 20 fachlich abgeschlossene Workflow-Diagramme zählen.

- **Journal-Template-Lifecycle:** `lifecycles.md` beschreibt jetzt `JournalTemplate::Blog/Exhibitions` als **reine Präsentationswahl**, nicht als Datenmigration: `updateJournalTemplate` sperrt SiteSection, schützt No-op, auditiert echte Änderungen, behält beide Eintragsbestände; `JournalSettingsService` kapselt Identität/Redirect + Template in äußerer Transaktion. Öffentliche Blog-Detailroute existiert nur unter aktivem Blog-Template; inaktive Daten bleiben erhalten. Journal-Settings-Testdefinitionen gelesen; keine Ausführung. Offene parallele Livewire-Stale-Targets als Negativfall.
- **Medienlöschung fachlich vertieft:** [`media-reference-and-cleanup.md`](docs/architecture/media-reference-and-cleanup.md) beschreibt nun die echte `MediaAssetEditorialService::delete`-Kette als **DB-Commit → getrennte physische Bereinigung** samt Guard-Tabelle für LIVE, Versionrows, Working-State/-Refs; unterscheidet direkte `deleteNow`-Fehler (noch **keine automatische Queue-Nachbuchung**) vom wiederholbaren `drain`. Auch `delete() === true` bei bereits gelöschtem Working-Asset/fehlendem erneutem Audit sowie Home-List-Coverage als separater Sicherheitspfad ausdrücklich abgegrenzt. Statische Quelle, kein DB-/Storage-Test.
- **Fachliche Zustandsachsen:** [`lifecycles.md`](docs/architecture/lifecycles.md) ergänzt gegenüber dem **eingefrorenen Fünferkatalog** zwei aktuelle Modelle: Home-Inhalt/Skip-/Menüflags als **orthogonale** Persistenz-/Routingzustände, und Publication-Working/LIVE/History mit konkreten Restore-/Revert-/Reset-/Commit-Guards. Explizit getrennt: Context-Staging mit 0-Diff-Audit, Commit mit 0-Diff ohne Checkpoint, Media-Drain nach Transaktionsende und vor-lock Revert-HEAD-Race. Manuell quellengeprüft, **nicht** aus Generator, DB- oder Runtime-Test bewiesen.
- **Quellgetragene Mutationsketten:** [`editorial-mutation-paths.md`](docs/architecture/editorial-mutation-paths.md) trennt für **Home-Show-in-Navigation** und **Custom-Page-Image-Wechsel** Filament-Event, Domain-Lock, Guard, Persistenz, Audit, No-op, Projektion und Notification. Enthält Home-Menü/Skip/Publication-Wahrheitstabelle, Browser-/Livewire-Negativfälle und den **nicht gelösten Index+Typ-Reorder-Race**. Quellstand `f69e0e7e`, kein Test ausgeführt. Weitere Domänen- und Recovery-Owner auf dieser Granularität nachziehen; **keinen Codex/Generator vor inhaltlichem Abschluss**.
- **Neue Home/Public- und Admin-Semantik (`dev@f69e0e7e`):** Home besitzt `show_in_navigation` als eigenen Bool-Guard neben Publication und `skip_home`, Default in der Public-UI aus; das ist **kein** globaler Sichtbarkeits-Schalter für andere Seitentypen. Custom-Page-Image Select besitzt nun `live()->afterStateUpdated()` in der Edit-Action und notifiziert nur auf geändertes Domain-Ergebnis; die Rolle ist vom bisherigen Native-Change für andere Felder getrennt. Quellenbewertung und Negativefälle unter `docs/architecture/` vervollständigen. Testquellen gelesen, nicht ausgeführt.
- **Image-Größe (`dev@b6fa7a46`):** Custom Page bietet `image_scale_percent` 25–200 %, Default 100 % als implizites **nicht gespeichertes** Feld. Das Public-Layout multipliziert seine bestehenden Maximalbreiten, ohne Bildzoom oder neue Media-Auslieferung. Home kann den JSON-Wert lesen, sein Editor hat nicht automatisch dieselbe Kontrolle. Siehe [Content-Audit](docs/architecture/structured-components.md#image-size-optionales-persistenzfeld); Feature-Testdatei nur gelesen.
- **Aktueller Tree-Delta (14.10.2026):** Insgesamt 245 `app/**/*.php` statt eingefrorenen 248; `app/Filament/Pages/Concerns/` enthält 23 Dateien statt 26. `CustomPageWorkspace` bindet sieben statt zehn eigene Traits ein. [UI-Audit](docs/architecture/ui-surfaces.md) und [PHP-Audit](docs/architecture/php-source-audit.md) begrenzen die Ableitung auf Source/Tree und erhalten den alten Generatorstand ausdrücklich.
- **Komponentenstand bis `dev@3c1bc75f`:** Custom Page hat die alten Child-Ordering-/Projection-/Action-Traits entfernt. Contact-Heading sowie Toggles und bedingte Felder leben in einem Parent-Edit-Owner; verdeckte Social-/Formwerte bleiben beim Speichern erhalten. Der geteilte Home-/Custom-List-Editor nutzt `Entry / Context / Published / Details`; alte Date-/Meta-/Location-/URL-Felder werden über die Model-Compatibility-Projektion abgebildet. [Content-Audit](docs/architecture/structured-components.md) und [Dialogvertrag](docs/ADMIN-DIALOG-CONTRACT.md) dokumentieren diese Grenze; `structured-components.json` bleibt ausdrücklich ein älterer eingefrorener Generatorstand. Keine Tests dieses Workstreams ausgeführt.

- **Root-Rich-Text-Regel abgeglichen:** `AGENTS.md` erlaubt jetzt ausdrücklich die zentral validierte externe HTTP(S)-Image-Option neben kanonischem Storage-`media:<id>`. `AdminRichText`, `SafeRichTextRenderer` und `SafeLinkPolicy` zeigen diesen tatsächlich existierenden Pfad; unvalidierte/externe unsichere Schemes und zweite Editor-Pipelines bleiben ausgeschlossen.

- [Tabellen](docs/ADMIN-TABLE-CONTRACT.md) besitzen die gemeinsam
  ausgerichteten Parent-Spalten, Component-Selbstbearbeitung, Actions,
  Selection und Journal-Splitfold. **Custom Page** ist jetzt flach:
  List und Contact haben je eine Parent-Zeile, nur Parent-Selection.
  **Home** nutzt denselben Tabellenrahmen, behält aber eigene
  Contact-Kindzeilen und Kind-Selection. Die Dashboard-Feed-Ausnahme
  bleibt allein beim Tabellen-Selection-Owner. `ui-skills.md`
  verweist auf diese Norm, statt eine zweite einzuführen.
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
  weder tatsächliche Header-Action-Mutationen noch Browser-Akzeptanz.
  Zusätzlich wurde der neue `dev@7a5bf344`-
  [Architekturtest](tests/Architecture/Admin/AdminEditorialDialogArchitectureTest.php)
  zu einem zentralen Gallery-/Journal-Dialogowner übernommen;
  er ist ein **gelesener deklarativer Architekturtest**, kein
  in diesem Workstream ausgeführter PHP-/Livewire-Test. Die `dev`-Änderungen wurden
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
   Home-List-`media_asset_id` und historische Medien aus `items[].body`
   nicht vollständig; die Custom-Page-Pfade tun dies bereits.
   **Wichtig nach dem neuen List-Editor:** Embedded-Image-Insertion
   ist dort deaktiviert, aber der Rich-Text-Datenpfad und die
   Modellvalidierung akzeptieren bestehende `media:<id>` weiterhin.
   Eine neue UI-Option eliminiert historische Media-Refs nicht.
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
5. **Public-Folio/SPA, Home Hero, Gallery-Puffer (Quellstand `dev@f69e0e7e`):** URL-stabile **sechs** Ecken/Endpunkte in `layouts/app.blade.php` aus dem Pfad-Hash; sechs Meta-Daten und nonce-autorisierte Root-Variablen, bei Livewire-Swaps mit `applyPublicLayoutFromMeta()` erneuert. Content/Menu-Papierbasis `[-3,4]`, Seitenimpuls **620 ms** und innere Höhe **480 ms**; Ambient-Ecken 98/113 s bzw. 117/133 s, Content-Shadow 129 s, Menu-Sheet 145 s. Home Hero weiterhin nur Content, nicht Menü; `activePaperPulses` und `activeHeightAnimations` trennen veraltete Completion-Handler. `public-spa-lifecycle.md` beschreibt den aktuellen Source-Flow und WAAPI-Retention-Prüfbedarf. **Abweichung:** Gallery-Blade lädt zwei Bilder eager (nur erstes high), neuester Public-Vertrag spricht von einem eager Bild; nicht als erledigt behandeln. **CSP-Image:** statt Inline-Style-Attribut nonce-autorisierter `@stack('public-component-styles')`. Weder Browser- noch Node-/Livewire-Test ausgeführt.
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
