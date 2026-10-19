# Quellcodeinventar: initiale Architekturaufnahme

**Belegstand:** `82879135c0ab18dd88b07663c1e214a4ade0e45b` (ursprünglicher `docs`-/`dev`-HEAD). Erfasst über den vollständigen, nicht abgeschnittenen Git-Tree dieses Commits: **763 Dateien**, davon **26** unter `app/Models` und **73** unter `database/migrations`. Die Zahlen messen Dateibestände, **nicht** Implementierungsvollständigkeit. Keine lokale App, Datenbank, Test-Suite oder CI wurde dafür ausgeführt.

## 1. Fachliche Module und Datenflüsse

Die folgenden neun Ordner existieren unter `app/Domain/`. Verantwortlichkeiten wurden anhand benannter Implementierungen geprüft; die Module sind **PHP-Namensräume, keine getrennten Laufzeitprozesse**.

| Modul | Eingänge / maßgebliche Implementierungen | Daten und abhängige Bereiche |
| --- | --- | --- |
| `Admin` | `AdminAuditService`, `AdminActionReceiptService`, `AdminUndoService`, `AdminActivityOrderingProjector`, `AdminNotifier`, `DashboardFeed` | Audit-Ereignisse, Undo-Belege, Ordering-Projektion und eigene Inbox. Notifications werden **explizit** versendet und nicht aus Eloquent-Updates abgeleitet. |
| `Analytics` | `app/Domain/Analytics/` | Matomo-/Metrikgrenze; Jobs, Retention und tatsächliche Anbieter-Aufrufe noch zu kartieren. |
| `Artwork` | `app/Domain/Artwork/` | Gallery-Zuordnung, Artwork-Lebenszyklus, Mediennutzung; mit Content-/Media-Servicepfaden verschränkt. |
| `Contact` | `app/Domain/Contact/` und `PublicContactController` | Formularannahme, Persistenz und Mailtransport sind getrennte Verantwortlichkeiten. |
| `Content` | `SiteSectionEditorialService`, `BlogEditorialService`, `ExhibitionEditorialService`, `HomeRoutingSettingsService`, `PublicNavigationService` | Site Nodes, Journal, Home, komponentenbasierte Seiten und öffentliche Navigation. |
| `Media` | `MediaIngestService`, `MediaAssetEditorialService`, `MediaReferenceQuery`, `PublicMedia` | Originale, Varianten, referenzierte Daten, öffentlich auslieferbare Inhalte und logische Löschung. |
| `Migration` | `app/Domain/Migration/` | Legacy-Import und Reconciliation, nicht mit aktuellem Runtime-Modell gleichsetzen. |
| `Publication` | `PublicationService`, `PublicationVersionService`, `PublicationSnapshot`, `PublicationSchemaGuard`, `CommittedRead` | Working `public.*`, LIVE `committed.*`, Commit-Linie, Retention und Restore. |
| `Storage` | `app/Domain/Storage/` | Speicherberechnung und Reclamation; nicht identisch mit dem Original-Binary-Storage allein. |

`app/Filament/` ist das Admin-Präsentations-/Interaktionssystem, `app/Http/` enthält Webcontroller und Request-Middleware, `app/Livewire/` eigenständige Livewire-Controls. Diese Bereiche sind **Aufrufer und Adapter**, nicht zusätzliche fachliche Datenquellen.

## 2. Vollständiger Eloquent-Dateikatalog (26)

Die Reihenfolge ist nach fachlicher Nutzung statt alphabetischem Klassenordner gewählt. Jede Klasse liegt in `app/Models/<Name>.php`. Die Einstufung **aktiv/kompatibel** ist keine Aufforderung, Dateien ohne Migrationsprüfung zu löschen.

| Teilbereich | Klassen |
| --- | --- |
| Seiten / Content-Konfiguration | `SiteSection`, `CustomPageSetting`, `HomePresentationSetting`, `JournalSetting`, `PublicContentSetting`, `Redirect` |
| Artwork / Journal | `ArtworkCategory`, `Artwork`, `ArtworkMedia`, `ArtworkMaterialPreset`, `BlogPost`, `Exhibition`, `JournalEntryMedia` |
| Medien | `MediaAsset`, `MediaVariant` |
| Redaktion, Audit, History | `User`, `AuditEvent`, `AdminActionReceipt`, `AdminActivityOrderingEvent`, `AdminActivityOrderingProjection`, `AdminNotification`, `PublicationCheckpoint`, `PublicationCheckpointEvent` |
| Andere Persistenz | `ContactMessage`, `DailyMetric` |
| Legacy-Kompatibilität | `CvEntry` |

**Nicht aus Modellklassen ableitbar:** technische SQL-Tabellen, Views, Partial Indexes, CHECK Constraints, beide PostgreSQL-Schemata und JSON-Strukturen. Insbesondere `history_payloads`, `publication_version_row_manifests` und der logische `publication_version_rows`-Zugriff gehören zur Architekturanalyse, ohne dass dafür jeweils eine gleichnamige Eloquent-Klasse existieren muss.

**Legacy-Grenze:** `CvEntry` ist nach der Migration `database/migrations/2026_09_16_235901_finalize_legacy_cv_retirement.php` kein aktives `cv_entries`-Runtime-Persistenzmodell. Das verbleibende PHP-Modell ist nicht mit einem aktuellen Tabellenvertrag gleichzusetzen. CV/Vita ist als strukturiertes Custom-Page-Content-Modell zu untersuchen.

## 3. Persistenz und Publication

`PublicationSnapshot::TABLES` ist der **geschlossene fachliche Commit-Umfang**, nicht die Liste aller Datenbanktabellen:

```text
artwork_categories    artworks            artwork_media
media_assets          media_variants      site_sections
custom_page_settings  journal_settings    journal_entry_media
home_presentation_settings               exhibitions
exhibition_media      blog_posts          public_content_settings
redirects
```

**Quellen:** `app/Domain/Publication/PublicationSnapshot.php`, `PublicationService.php`, `PublicationVersionService.php`, `PublicationSchemaGuard.php`.

- Die 15 Tabellen werden zwischen `public` (Working) und `committed` (LIVE) verglichen. `created_at` und `updated_at` sind beim fachlichen Vergleich ausgenommen, anders als fachliche Zeitstempel.
- `RESTORE_TABLES` gibt eine eigene, FK-bewusste Restore-Reihenfolge vor.
- `exhibition_media` bleibt auch dann Teil dieser technischen Snapshots, wenn die aktuelle Journal-Mediennutzung von `journal_entry_media` getragen wird. Nicht ohne Schema-/History-Plan herauslösen.
- `PublicationService::commit` nutzt Transaktion und PostgreSQL Advisory Lock; ältere Versionen sind nicht schon deshalb restorable, weil Commit-Metadaten existieren.
- Audit-/Notification-/Undo-/History-Tabellen bilden eigenständige Zustände außerhalb des Publication-Diff. Eine nicht vorhandene Modellklasse beweist keine nicht vorhandene Tabelle.

Für eine korrekte ERD müssen sämtliche Migrationen in einer **wegwerfbaren** PostgreSQL-Umgebung aufgebaut und per `pg_catalog`/`information_schema` inklusive Foreign Keys, Indizes, Views und Constrainttypen inspiziert werden. Das geschieht nicht auf einer dauerhaft genutzten DB und wurde **noch nicht** durchgeführt.

## 4. HTTP- und Browser-Eingänge

Die konkreten Public-Routen werden in `routes/web.php` registriert:

| Eingang | Controller/Middleware | Kontext |
| --- | --- | --- |
| `GET /` | `PublicHomeController::show` | Home-Inhalt oder gültiges Skip-Home-Routing |
| `GET /{section}` | `PublicSiteSectionController::show`, Home-Gate | öffentliche Site Node |
| `GET /{section}/{slug}` | `PublicSiteSectionController::journalEntry`, Home-Gate | aktiver Journal-Eintrag |
| `GET /artworks/{slug}` | `PublicArtworkController::show`, Home-Gate | Artwork Detail |
| `POST /contact` | `PublicContactController::submit`, Home-Gate und Throttle | Kontaktformular; kein öffentlicher GET |
| `GET /media/original/{mediaAsset}`, `GET /media/variant/{mediaVariant}` | `PublicMediaController` | eigene PublicMedia-Policy; Varianten-Middleware |
| `/preview/*` | `ProtectArtistPreview`, Public- und AdminMedia-Controller | explizit geschützter Vorschaupfad |
| `/admin/media-preview/*`, `/admin/media-download/*` | `AdminMediaController` | separate Admin-Medienoberfläche |
| `GET /sitemap.xml`, `GET /robots.txt` | `PublicSeoController` | SEO |

Die Tabelle ist ein **Quellinventar von `routes/web.php`**, kein `route:list`-Beleg: Filament- und Framework-Routen müssen später zur tatsächlichen registrierten Routermenge ergänzt werden.

Der öffentliche GET/HEAD-Zustand entsteht in `bootstrap/app.php` → `UseCommittedPublicState` → `CommittedRead` → `PublicationReadContext` → `PublicationPostgresGrammar`. Ausnahmen sind u. a. `admin/*`, `preview/*`, `livewire/*` und `up`. Weitere Abgrenzung: [Publication-Ablauf](publication.md).

Browserseitige Entry-Dateien aus `resources/js` (12 Dateien; noch keine Behauptung über geladenen Asset-Graph):

```text
admin-action-titles.js      admin-dialog-geometry.js
admin-notifications.js      admin-password-tools.js
admin-storage-viz.js        admin-viz.js
app.js                      artwork-viewer.js
home-hero.js                journal-presentation.js
matomo.js                   public-navigation.js
```

Künftige Instanz-/Zustandsmodelle müssen auch `public-navigation.js` (vollständige Menüelementfenster mit zyklischer Navigation), `artwork-viewer.js`, Filament Edit-Actions und `admin-notifications.js` einbeziehen; keine davon ist durch ein Eloquent-ERD erklärt.

## 5. Erste quellengeprüfte Beziehungsketten

```text
public GET/HEAD
  → UseCommittedPublicState
  → CommittedRead [Repeatable Read]
  → PublicationReadContext
  → PublicationPostgresGrammar [tracked tables → committed.*]

admin Exhibition Edit
  → JournalEntryEditorSchema
  → AdminControl [Toggle change bridge]
  → AdminDialog [callMountedAction]
  → JournalWorkspace::editExhibitionAction
  → ExhibitionEditorialService::update [validation, lock, geocoding]
  → AdminAuditService::record [nur echte Änderungen]
  → AdminNotifier::notification [expliziter Aufruf durch Workspace]

admin Commit
  → PublicationService::commit [Lock, Schema-Guard, Diff]
  → committed.* ersetzen
  → PublicationVersionService::capture [Snapshot, Hash, History]
  → publication_checkpoint_events / Undo-Grenze
  → PublicationMediaCleanupService::drain [nach Commit]
```

Die konkreten Verzweigungen und Belegstellen stehen in [Publication](publication.md) und [Exhibition Map](exhibition-map.md).

## 6. Bereits nachgewiesene Dokumentationsabweichungen

| Aussage in Altdokumenten | Tatsächlicher Gegenbeleg | Bearbeitung |
| --- | --- | --- |
| Skip Home als vierter aktiver Home-Inhaltsmodus | `HomeTemplate::options()` bietet Artwork, Under Construction, Custom; Skip Home ist aktuelle Routingkonfiguration in `HomeRoutingSettingsService` | `README.md`, `ARCHITECTURE.md`, `DATA-MODEL.md` korrigieren |
| `CvEntry` als aktive CV-Persistenz | retire-Migration und aktueller Custom-Page-Aufbau | historisches Modell getrennt dokumentieren |
| „Referenzierte Medien können nicht gelöscht werden“ als pauschale Aussage | `MediaAssetEditorialService::delete` kann referenzierte Einträge bewusst lösen und Auswirkungen auditieren | Lösch-/Reclamation-Vertrag präzisieren |
| Pro `AdminNotifier::notification` stets nur ein Datensatz | Bei mehreren aufgezeichneten Activity-Meldungen ruft die Implementierung `recordAndSurface` mehrfach auf | Kardinalität korrekt und mit Ausnahmebedingungen dokumentieren |

## Reproduzierbarer Typindex

Das zugehörige **generierende Programm** steht unter `scripts/generate-architecture-inventory.php` und nutzt `token_get_all` statt eines regulären Ausdrucks über Kommentare und Zeichenketten. Ergebnis nach lokalem `--write`: `docs/architecture/generated/php-symbols.json`, mit konkreten FQCNs, Typkategorien, Zeilen, Dateipfaden, syntaktischen Imports und einer vollständigen PHP-Migrationsdateiliste. Ein vorhandenes Ergebnis lässt sich über `--check` vergleichen.

**Status:** Generator ist implementiert und mit einem minimalen Beispiel geprüft. Das erzeugte Inventar aus dem vollständigen Projekt ist noch auszuführen und zu überprüfen; es wäre falsch, bereits generierte Vollständigkeit zu behaupten.

## Statische Beziehungskanten

`scripts/generate-architecture-relations.php` liest die Token-Struktur zu den mit dem ersten Generator erfassten Dateien und verzeichnet nachweisbare Vererbungs-/Interface-/Trait-, Konstruktor-, Eloquent-Relation- und explizite Service-Locator-Beziehungen. Die Verweise werden nur auf bekannte Projekt-Typen normalisiert. Syntaktische Imports gelten **nicht** automatisch als Laufzeitabhängigkeit. Multitypdateien und dynamische Ziele werden nicht stillschweigend erfunden, sondern als Abdeckungslücke behandelt.

Die generierte Modul-Mermaid-Grafik zählt solche Kanten, **nicht** echte Aufrufhäufigkeiten. Das Datenbankschema muss unabhängig davon aus einem sauber migrierten PostgreSQL-Katalog abgeleitet werden.

## Fachliche Lebenszyklen

Die [fünf quellverankerten Zustandsmodelle](lifecycles.md) enthalten 35 kuratierte Übergänge und unterscheiden persistierte Zustände von zeitabhängigen Public-Prädikaten. Die maschinenlesbare Eingabe ist [lifecycles.json](lifecycles.json), der Generator liegt unter `scripts/generate-architecture-lifecycles.php`. Das ist ein erster Ausschnitt.

## Schemahistorie und physische Datenbank

Die [Schema-Evolution](schema-evolution.md) erfasst nachgeprüfte Migrations-Etappen und verwechselt `publication_version_rows` nicht mit einer aktuellen physischen Tabelle; seit dem Shared-History-Payload-Refactor ist es eine SQL-View mit Triggern. Ein tatsächlicher `pg_catalog`-Export bleibt offen.

## Überprüfte Service-Aufrufstellen

[21 direkte Methodenaufrufe](admin-publication-calls.md) in der Admin-/Publication-Achse sind mit Klasse, Methode, Dateipfad, exakter Zeile und Textanker modelliert. Die zugehörige [JSON-Quelle](admin-publication-calls.json) und das [Mermaid-Diagramm](generated/admin-publication-calls.mmd) sind ein überprüfter Querschnitt, **kein vollständiger Laufzeitaufrufgraph**.

## Vollständige Anwendungs-PHP-Klassen und Imports

Der [Quellenkatalog](php-source-audit.md) trennt nun das native Ergebnis auf `docs@8c9bd0a0` (**249 Typen: 218 Klassen, 24 Traits, 7 Enums; 896 interne Imports; 675 statische Beziehungen**) vom historischen 248/900-Importaudit. Die [Symbole](generated/php-symbols.json), [Beziehungen](generated/php-relationships.json) und [Modulbeziehungen](generated/module-relationships.mmd) sind nativ erzeugt und geprüft; der alte [Importmodulgraph](generated/app-module-imports.mmd) bleibt historische Referenz.

## 7. Nacharbeit, ohne Scheingenauigkeit

- Der native Symbol-/Strukturgraph ist erzeugt und geprüft; vollständige dynamische Verwendungsauflösung bleibt außerhalb seiner dokumentierten Syntaxgrenzen.
- Tatsächliches DB-Schema aus einer isoliert migrierten PostgreSQL-Datenbank, einschließlich `public`/`committed`, Constraints und SQL-Views.
- Eloquent-Relationen, Polymorphie, JSON-Strukturen, Domain-Enums und sämtliche Zustandsübergänge.
- Filament-Resource-/Action-Register, Livewire-Events, Blade-/JS-Komponentengraph und externe Aufrufe.
- Abdeckungsprüfung: jede erkannte Einheit einem Architekturmodell oder ausdrücklich begründeten Ausschluss zuordnen.

Diese Lücken bleiben offen markiert, bis der jeweilige Extraktor und seine Ergebnisse geprüft sind. **Ein inventarisierter Dateiname ist noch kein geprüftes Laufzeitverhalten.**
