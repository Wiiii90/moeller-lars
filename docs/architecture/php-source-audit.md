# Vollständige PHP-Quellenaufnahme: Anwendungs-Typen und Modulimporte

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

Die lokale Containerumgebung hat PHP 8.4, kann das private GitHub-Repository aber mangels GitHub-DNS/Netzwerkzugriff nicht direkt klonen. Ein **vollständiger nativer Durchlauf** von `generate-architecture-inventory.php` und `generate-architecture-relations.php` wurde **nicht durchgeführt**; deren PHP-Tokenizer-Semantik kann von der hier verwendeten unabhängigen Regex-Quellenaufnahme abweichen. Auch `::class`-Hinweise werden deshalb lediglich roh gesammelt und **nicht** als bewiesene Kanten interpretiert. Framework-/Vendor-Typen, JSON-Medienreferenzen, Livewire-Events und PostgreSQL-FKs werden in ihren eigenen Katalogen behandelt.

**Nächste Arbeit:** Differenz- und Randfallprüfung des PHP-Tokenizers, Abdeckung veränderter `dev`-Dateien, kanonische Service- und Provider-Aufrufstellen. Danach das echte physische Datenbankschema ausschließlich auf einer migrierten Wegwerf-Datenbank prüfen.
