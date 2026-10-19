# Klassen-Importdiagramme nach Modulen

Die **20** [Mermaid-Detaildiagramme](generated/class-imports/) umfassen insgesamt alle **248** unter `app/` deklarierten Klassen, Traits und Enums als Ausgangsobjekte sowie die **900** intern aufgelösten PHP-Importdeklarationen. Ein Modul zeigt alle eigenen Typen und zusätzlich die durch `use App\\...` importierten Typen anderer Module.

**Eindeutige Semantik:** Ein Pfeil `A -->|import| B` bedeutet nur, dass die Quell-PHP-Datei A einen Import von B **deklariert**. Er beweist nicht, dass A B instanziiert, eine Methode aufruft, die Container-Bindung auflöst oder die Klassendatei zur Laufzeit lädt. [Der Gesamtmodulgraph](generated/app-module-imports.mmd) fasst dieselben Kanten zusammen; [Call-Sites](admin-publication-calls.md) und [UI-Traits](ui-surfaces.md) besitzen separate, stärkere Evidenz.

**Historische Standgrenze:** Die 20 Diagramme geben weiterhin `app-source-audit.json@ca162bcd5e5bbaf50bc6bdb9c6bc01640269d027` (248 Typen, 900 Imports / 717 modulübergreifend) wieder. Frühere geprüfte Importdeltas sind im [PHP-Audit](php-source-audit.md) als historische Zwischenstände beschrieben. Die native Extraktion auf `docs@8c9bd0a0` zählt 249 Typen und 896 interne Imports; sie ersetzt weder den historischen Input noch dessen Diagramme. Es gibt keine aktuelle 901/718-Vollsumme.

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

**Verifikation am 19.10.2026:** Der native `--check` auf dem vollständigen `docs@8c9bd0a0`-Worktree besteht mit PHP 8.3.33: 248 historische Quelldateien, 900 Importkanten, genau 20 Diagramme; keine Ausgaben geändert. Die [20 Modulansichten oben](#modulansichten) verlinken jede Datei und geben ihr den Nutzen eines historischen Importnachschlagewerks. Entscheidung **B – versioniertes Referenzinventar**: beibehalten, keine Löschung. Der historische aggregierte Modulgraph wurde zusätzlich lesend gegen dasselbe JSON geprüft (20 Module, 71 Cross-Module-Paare, 717 Referenzen). Diese Reproduzierbarkeit bestätigt ausschließlich den eingefrorenen Input, keine aktuellen Laravel-Aufrufe.

Der konkrete [Importgrenzen-Befund](import-boundaries.md) zeigt zudem, dass selbst eine geschlossene Modul-Importkomponente noch kein belegter Laufzeitzyklus ist.
