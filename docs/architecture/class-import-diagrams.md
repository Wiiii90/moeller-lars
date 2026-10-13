# Klassen-Importdiagramme nach Modulen

Die **20** [Mermaid-Detaildiagramme](generated/class-imports/) umfassen insgesamt alle **248** unter `app/` deklarierten Klassen, Traits und Enums als Ausgangsobjekte sowie die **900** intern aufgelösten PHP-Importdeklarationen. Ein Modul zeigt alle eigenen Typen und zusätzlich die durch `use App\\...` importierten Typen anderer Module.

**Eindeutige Semantik:** Ein Pfeil `A -->|import| B` bedeutet nur, dass die Quell-PHP-Datei A einen Import von B **deklariert**. Er beweist nicht, dass A B instanziiert, eine Methode aufruft, die Container-Bindung auflöst oder die Klassendatei zur Laufzeit lädt. [Der Gesamtmodulgraph](generated/app-module-imports.mmd) fasst dieselben Kanten zusammen; [Call-Sites](admin-publication-calls.md) und [UI-Traits](ui-surfaces.md) besitzen separate, stärkere Evidenz.

**Aktualitätsprüfung (14.10.2026):** Der [inkrementelle 248-Dateien-Git-Blob-Abgleich](php-source-audit.md#änderungsabgleich-gegen-aktuellen-git-tree-14102026) bestätigt, dass seit der ursprünglichen Quellaufnahme nur zwei PHP-Dateien verändert wurden und deren benannte Typen/Projektimports unverändert sind. Die **900 Typ-Importkanten** und 20 Klassengraphen bleiben daher für den geprüften `dev`-Stand strukturell passend. Andere auditierte Roh-Quellanker bleiben an die ursprüngliche `source_ref` gebunden; keine nativen Generatorläufe werden behauptet.

## Modulansichten

| Modul | Eigene Typen | App-Imports | Diagramm |
| --- | ---: | ---: | --- |
| `Database` | 1 | 2 | [Mermaid](generated/class-imports/database.mmd) |
| `Domain/Admin` | 21 | 55 | [Mermaid](generated/class-imports/domain-admin.mmd) |
| `Domain/Analytics` | 8 | 8 | [Mermaid](generated/class-imports/domain-analytics.mmd) |
| `Domain/Artwork` | 10 | 42 | [Mermaid](generated/class-imports/domain-artwork.mmd) |
| `Domain/Contact` | 1 | 1 | [Mermaid](generated/class-imports/domain-contact.mmd) |
| `Domain/Content` | 40 | 81 | [Mermaid](generated/class-imports/domain-content.mmd) |
| `Domain/Media` | 10 | 48 | [Mermaid](generated/class-imports/domain-media.mmd) |
| `Domain/Migration` | 7 | 22 | [Mermaid](generated/class-imports/domain-migration.mmd) |
| `Domain/Publication` | 7 | 11 | [Mermaid](generated/class-imports/domain-publication.mmd) |
| `Domain/Storage` | 2 | 7 | [Mermaid](generated/class-imports/domain-storage.mmd) |
| `Filament` | 88 | 515 | [Mermaid](generated/class-imports/filament.mmd) |
| `Http` | 16 | 63 | [Mermaid](generated/class-imports/http.mmd) |
| `Jobs` | 1 | 1 | [Mermaid](generated/class-imports/jobs.mmd) |
| `Livewire` | 2 | 7 | [Mermaid](generated/class-imports/livewire.mmd) |
| `Mail` | 1 | 0 | [Mermaid](generated/class-imports/mail.mmd) |
| `Models` | 26 | 11 | [Mermaid](generated/class-imports/models.mmd) |
| `Notifications` | 1 | 0 | [Mermaid](generated/class-imports/notifications.mmd) |
| `Providers` | 2 | 24 | [Mermaid](generated/class-imports/providers.mmd) |
| `Routing` | 1 | 2 | [Mermaid](generated/class-imports/routing.mmd) |
| `Support` | 3 | 0 | [Mermaid](generated/class-imports/support.mmd) |

## Reproduzierbarkeit

Das manuelle lokale Script [`scripts/generate-architecture-import-diagrams.php`](../../scripts/generate-architecture-import-diagrams.php) liest das eingecheckte [`app-source-audit.json`](generated/app-source-audit.json). Es ermittelt aus dessen Quellpfaden genau die 20 Module, erzeugt deterministisch pro Modul eine Liste eigener Klassen plus referenzierter Importziele und überprüft im `--check`-Modus auch unerwartete überzählige Diagramme.

```sh
php scripts/generate-architecture-import-diagrams.php --write
php scripts/generate-architecture-import-diagrams.php --check
```

**Verifikation:** Die Berechnungslogik wurde in einer isolierten lokalen PHP-8.4-Miniprojektstruktur mit zwei Typen und einer Modulgrenze tatsächlich mit `php -l`, `--write`, `--check` und einem absichtlich veralteten Diagramm durchlaufen. Die **20 eingecheckten echten Diagramme** wurden unmittelbar aus dem vollständigen GitHub-Quellenkatalog erstellt und auf Summen (248 Typen, 900 Kanten) geprüft. Eine **native Ausführung dieses Scripts auf dem realen vollständigen Checkout** wurde nicht durchgeführt, weil das private Repository lokal nicht geklont werden kann. Insbesondere sind alle Diagramme bewusst eine Darstellung des eingefrorenen Source-Audit-Standes, nicht der aktuellen, dynamisch bootstrappenden Laravel-Anwendung.

Der konkrete [Importgrenzen-Befund](import-boundaries.md) zeigt zudem, dass selbst eine geschlossene Modul-Importkomponente noch kein belegter Laufzeitzyklus ist.
