# PHP-Quellenaufnahme: native Inventare und historische Modulimporte

## Nativer Checkout-Abschluss (19.10.2026)

**Quellenbasis:** `docs@8c9bd0a0f0fca46b1aa5637049372726d6970c4c`, PHP 8.3.33, vollständiger Worktree `P:\moeller-lars-docs`; keine neueren `dev`-Commits importiert. Inventory und Relations wurden nativ mit `--self-test`, `--write` und anschließend `--check` ausgeführt. [Prüfmatrix und Artefaktentscheidung](completion-criteria.md#technischer-source-only-abschluss-19102026) sind der Ergebnis-Owner.

| Native Messung | Ergebnis |
| --- | ---: |
| App-PHP-Dateien / deklarierte Typen | 249 / 249 |
| Klassen / Traits / Enums / Interfaces | 218 / 24 / 7 / 0 |
| Modeldateien / Migrationsdateien | 26 / 76 |
| Interne Dateiimportdeklarationen (Imports mit Präfix `App\`) | 896 |
| Statische interne Beziehungen / übersprungene Dateien | 675 / 0 |

[php-symbols.json](generated/php-symbols.json) enthält Deklarationen und Dateiimports; [php-relationships.json](generated/php-relationships.json) die Quellenpositionen expliziter Strukturbeziehungen. [module-relationships.mmd](generated/module-relationships.mmd) zeigt deren aggregierte Modulreferenzen. Die 675 Kanten verteilen sich auf **171 constructor_type, 48 eloquent_relation, 7 extends, 423 service_locator und 26 trait_use**. Die interne Importzahl ist aus den vom nativen Tokenizer gelieferten Dateiimports gezählt, keine Runtime-Aufrufzahl und keine neue Cross-Module-Zählung des alten Audits.

Diese Inventare ersetzen das bisher fehlende native Extraktionsergebnis. Sie ersetzen **nicht** den unabhängigen historischen Regex-Audit: Der Relationsgraph enthält andere Kantentypen als ein Importgraph. Gleiches Modell-/Dateizählen beweist weder Framework-Discovery noch dynamische Aufrufkanten, DB-FKs oder vollständige Syntaxabdeckung. Es wurde kein Laravel-/DB-/Browserlauf ausgeführt.

## Historischer vollständiger Importaudit

**Git-Quellstand:** `ca162bcd5e5bbaf50bc6bdb9c6bc01640269d027` auf `docs`. [Maschinenlesbarer Quellkatalog](generated/app-source-audit.json), [Mermaid-Modulgraph](generated/app-module-imports.mmd).

## Vollständigkeitsprüfung

| Quellgröße | Tatsächlicher Wert |
| --- | ---: |
| Dateien unter `app/**/*.php` | **248** |
| Benannte Klassen | **214** |
| Benannte Traits | **27** |
| Benannte Enums | **7** |
| Benannte Interfaces | **0** |
| Interne `use App\\...`-Importdeklarationen | **900** |
| Davon modulübergreifend | **717** |
| Modulgruppen | **20** |

Die 248 Quelldateien wurden einzeln über den authentifizierten GitHub-Zugriff gelesen und mit dem vollständigen rekursiven Git-Tree verglichen. Alle 248 Deklarationen sind eindeutig; jedes der 900 importierten `App\\...`-Symbole löst auf **genau eine** dieser Deklarationen auf. Der Katalog enthält Datei, vollqualifizierten Typ, Deklarationsart und Quellzeile, PHP-Importe, ausgeschriebene öffentliche Methoden sowie separate rohe `::class`-Hinweise.

**Nicht behauptet:** Dass importierte Klassen tatsächlich instanziiert, als Service konsumiert, über Laravel gebunden oder als Methodenaufruf verwendet werden. Der Quellscanner benutzt unabhängige konservative reguläre Ausdrücke und wurde **nicht als der bestehende PHP-Tokenizer-Generator ausgeführt**. Es gibt noch keine Laufzeit- oder Datenbankprüfung.

**Klassenebene:** [Alle 20 Modul-Klassendiagramme](class-import-diagrams.md) geben die Quelltypen und Importkanten einzeln wieder, statt einen einzigen unlesbaren 248-Node-Graphen zu erzeugen.

## Änderungsabgleich gegen aktuellen Git-Tree (14.10.2026)

Der ursprüngliche vollständige Katalog wurde bei `ca162bcd5e5bbaf50bc6bdb9c6bc01640269d027` erstellt. Die beiden **vollständigen rekursiven Git-Trees** dieses Commits und des überprüften `dev`-Commits `1ca4014cae997b0b4a40422944b90a0328524f53` enthalten jeweils **248** Dateien unter `app/**/*.php`, ohne hinzugefügte oder gelöschte Pfade. Der SHA-Vergleich aller 248 PHP-Blobs weist exakt **zwei veränderte Dateien** aus:

| Geänderte Quelle | Gegen das alte Inventar erneut geprüft |
| --- | --- |
| [`JournalWorkspace.php`](../../app/Filament/Pages/JournalWorkspace.php) | Benannte Typdeklaration bleibt `App\\Filament\\Pages\\JournalWorkspace`; alle **20** `use App\\...`-Importe stimmen textgleich mit dem Inventar; **39** öffentliche Methoden haben dieselben Namen |
| [`SecurityHeaders.php`](../../app/Http/Middleware/SecurityHeaders.php) | Benannte Typdeklaration `App\\Http\\Middleware\\SecurityHeaders`, weiterhin **keine** projektinternen Imports; öffentliche Methode `handle()` unverändert |

**Präzise Folgerung:** Da sämtliche übrigen 246 PHP-Blobs unverändert sind und die beiden veränderten Quellen keine Import-/Typänderung haben, bleiben der **248-Typen-Katalog, 900 deklarierten internen Imports und die daraus erzeugten 20 Klassendiagramme als Typ-/Importgraph** für diesen geprüften Stand unverändert richtig. Das ist eine **inkrementelle Git-Blob-/Quelltextprüfung**, keine neue Ausführung des nativen PHP-Generators.

**Nicht erneuert:** Die im [JSON-Audit](generated/app-source-audit.json) bewusst fixierte `source_ref` bleibt der ursprüngliche vollständige Lese-Commit. Insbesondere gespeicherte **Dateibytes, rohe `::class`-Hinweise und andere zeilenbezogene Anker** veränderter Dateien sind nicht automatisch die heutigen Werte. Das JSON wird daher nicht durch einen fälschlich als vollständige Neugenerierung ausgegebenen Teilpatch umetikettiert. Bei späteren `dev`-PHP-Änderungen muss diese Delta-Abdeckung erneut geprüft werden.

## Neuer Source-Delta nach Journal-/Settings-Refactor (14.10.2026)

Ein nachfolgender `dev`-Commit `2cd299354e42f565144b4b13752f6ec8e3192fe1` ändert **sieben App-PHP-Dateien**. Die konkreten alten/neuen `use App\\...`-Deklarationen aller sieben Dateien wurden einzeln verglichen (kein Generator- oder Runtime-Test). Drei Deklarationen änderten sich:

| PHP-Quelle | Präziser Import-Delta |
| --- | --- |
| [`JournalSettingsService`](../../app/Domain/Content/JournalSettingsService.php) | **−1** `App\\Domain\\Admin\\AdminAuditService`; Identity und Template werden jetzt über Klassen **im gleichen Namespace** delegiert, ohne neuen `use App\\...` |
| [`SiteSectionEditorialService`](../../app/Domain/Content/SiteSectionEditorialService.php) | **+1** `App\\Models\\PublicContentSetting` |
| [`CustomPageWorkspaceLifecycle`](../../app/Filament/Pages/Concerns/CustomPageWorkspaceLifecycle.php) | **+1** `App\\Models\\PublicContentSetting` |

Die vier übrigen geänderten PHP-Dateien behalten ihre projektinternen Imports unverändert. Daher hat die aktuelle Quelle gegenüber dem bisher eingecheckten JSON **netto +1 Importdeklaration**: rechnerisch **901 interne Imports statt 900**, davon **718 modulübergreifende statt 717**. Die Anzahl der benannten Typen und PHP-Dateien bleibt unverändert, soweit diese geänderten Dateien und der verifizierte Change-Set reichen.

**Wichtige Generationgrenze:** [`generated/app-source-audit.json`](generated/app-source-audit.json), [Modulimportgraph](generated/app-module-imports.mmd) und [20 Klassendiagramme](class-import-diagrams.md) haben weiterhin **900/717** aus ihrer ursprünglichen `source_ref` und sind auf diesem neuen Stand **für Importkanten nicht mehr vollständig aktuell**. Sie wurden bewusst nicht manuell auf eine angeblich nativ regenerierte Version umetikettiert. Der Delta ist ein überprüfbarer Ergänzungsbeleg; die spätere native Extraktion ist oben separat ausgewiesen und ersetzt diesen historischen Importstand nicht.

## Neuerer Git-Tree-Abgleich: Custom-Page-Child-Cleanup

**Quelle:** Rekursiver Git-Tree `docs@597559f93bc0f5031273291d6a0f12bf644a75e4` nach Integration von `dev@b6fa7a461331e73bb30ea4debaeb26bfeeffdbe9`. Der aktuelle Tree enthält **245** Dateien unter `app/**/*.php`, nicht mehr 248. Die drei tatsächlich entfernten Traits sind:

- `app/Filament/Pages/Concerns/CustomPageWorkspaceChildOrdering.php`
- `app/Filament/Pages/Concerns/CustomPageWorkspaceChildProjection.php`
- `app/Filament/Pages/Concerns/CustomPageWorkspaceListContactActions.php`

`CustomPageWorkspace` bindet aktuell **sieben** interne eigene Traits direkt ein, nicht mehr zehn. `app/Filament/Pages/Concerns/` enthält insgesamt **23** PHP-Dateien statt 26. Das ist ein reproduzierbarer **Pfadzähl-/Source-Delta** gegen den eingecheckten Git-Tree; die drei gelöschten Dateien waren Traits im vorherigen Inventar. Bei sonst gleichbleibenden benannten Typen ergibt sich damit rechnerisch ein Typbestand von **214 Klassen / 24 Traits / 7 Enums** — ausdrücklich **keine erneute unabhängige Vollinventarisierung aller 245 Deklarationen**.

Die früheren **900/717** Importzahlen des generierten Originalkatalogs und der oben ausgewiesene Zwischenstand **901/718** gelten nur für ihre jeweils geprüften historischen Source-Refs. Durch die spätere umfangreiche Filament-/Custom-Page-Refaktorierung wurden ganze Trait-Dateien und Importdeklarationen entfernt sowie weitere PHP-Dateien verändert. Für diesen historischen Delta wurde die **damalige Gesamtsumme nicht neu gezählt**; die native Messung oben ist ein eigener Beleg. Insbesondere darf weder der 900er Graph noch die frühere Rechnung 901/718 als aktuelle Quelle ausgegeben werden. Der oben belegte native Lauf schließt die Extraktionslücke für `docs@8c9bd0a0`. Die ursprünglichen JSON-/Mermaid-Importdateien bleiben unabhängig davon **eingefrorener Vergleichsstand**; ihre historische Cross-Module-Summe wird nicht auf die neue Quelle übertragen.

## Modulübergreifende Imports

| Quellmodul → importiertes Modul | Explizite Deklarationen |
| --- | ---: |
| `Filament` → `Models` | 135 |
| `Filament` → `Domain/Content` | 78 |
| `Domain/Content` → `Models` | 53 |
| `Domain/Admin` → `Models` | 45 |
| `Filament` → `Domain/Admin` | 42 |
| `Domain/Media` → `Models` | 29 |
| `Filament` → `Domain/Artwork` | 29 |
| `Filament` → `Domain/Media` | 29 |
| `Http` → `Models` | 26 |
| `Domain/Artwork` → `Models` | 23 |
| `Http` → `Domain/Content` | 20 |
| `Domain/Migration` → `Models` | 15 |

Der Gesamtgraph [`app-module-imports.mmd`](generated/app-module-imports.mmd) bildet die **20 Module** und ihre importdeklarierten Kanten ab. Die Zahlen sind Referenzanzahlen, keine Service-Aufrufhäufigkeiten.

### Gegenüberstellung der bestehenden Architekturbelege

- [Eloquent-Relationen](eloquent-relations.md): explizite Relation-Methoden mit Ziel-Model, auch Selbstbeziehungen.
- [Admin-/Publication-Aufrufstellen](admin-publication-calls.md): quellverankerte **konkrete Methodenaufrufe** und deren fachliche Aufgaben.
- [UI-Komposition](ui-surfaces.md): tatsächliche `use TraitName;`-Komposition in Filament-Pages und Concerns.
- **Dieser Quellenkatalog:** jedes benannte App-Typobjekt und alle deklarativen projektinternen Imports. Weitere Instanz-/Provider-/DI-Beziehungen bleiben gesondert zu modellieren.

## Gegenseitige Modulimporte und konkrete Aufrufpfade

Die [Importgrenzen-Analyse](import-boundaries.md) beschreibt die stark zusammenhängende Komponente aus acht Modulen und einen konkreten Unterschied zwischen Gallery- und Undo-Publish. Solche Funde werden gegen tatsächliche Methodenaufrufe geprüft, nicht allein aus Importzählungen gefolgert.

## Abgrenzung und weitere Verifikation

Die nativen Generatoren wurden jetzt auf dem vollständigen Windows-Checkout mit PHP 8.3.33 ausgeführt. Tokenizer und unabhängiger historischer Regex-Audit bleiben unterschiedliche Extraktionsmodelle; rohe `::class`-Hinweise aus dem historischen JSON sind keine bewiesenen Kanten. Framework-/Vendor-Typen, JSON-Medienreferenzen, Livewire-Events und PostgreSQL-FKs gehören in ihre eigenen Kataloge.

**Verbleibende Grenze:** gezielte Source-Triage bei relevanten neuen Änderungen; dynamische Service-/Provider-Auflösung und physisches Schema nur in einer separat autorisierten sicheren Runtime-/Wegwerf-DB-Prüfung.
