# Architektur: Quelleninventar und Modelle

Diese Sektion entsteht auf dem dauerhaft erlaubten Branch `docs`. Der ebenfalls dauerhaft erlaubte Branch `testing` ist ein getrennter Arbeitsbereich; Änderungen und Tests dort sind nicht Aufgabe dieses Dokumentationszweigs. Laufende Anwendungscodeänderungen bleiben auf `dev`. Neue `dev`-Commits werden bewusst und ohne History-Rewrite in `docs` übernommen. Keine CI-Interaktion.

## Einstieg

1. [Bestandsaufnahme](inventory.md) — nachprüfbarer Quellcodekatalog mit Klassifikation, Abhängigkeiten und bekannten Abdeckungslücken.
2. [Eloquent-Relationen](eloquent-relations.md) — 26 Modeldateien und 48 Relationen, jetzt zusätzlich als [generierter JSON-Graph](generated/eloquent-relationships.json) und [Mermaid-Gesamtgraph](generated/eloquent-relationships.mmd).
3. [Fachliche Lebenszyklen](lifecycles.md) — fünf getrennte Zustandsmodelle mit 35 überprüfbaren Quellbelegen.
4. [Admin-/Publication-Aufrufstellen](admin-publication-calls.md) — 21 belegte direkte Methodenaufrufe über 14 Klassen, mit separatem JSON/Diagramm und Source-Anchor-Check.
5. [Publication und öffentlicher Lesezugriff](publication.md) — erster durchgehender Daten- und Ablaufgraph.
6. [Restore, Revert und Reset](publication-working-operations.md) — Activity UI bis zum Working-Snapshot, mit drei getrennten Ablaufdiagrammen, Guards und No-ops.
7. [Strukturierte Komponenten](structured-components.md) — die sechs persistierten JSON-Typen, Contact-/List-Kinder, Medienreferenzen und Home-Konfiguration.
8. [Runtime-Einstiegspunkte](runtime-entrypoints.md) — 19 explizite HTTP-Routen, Middleware, Filament-Discovery und Public-JS-Initialisierung.
9. [Filament-/Livewire-Komposition](ui-surfaces.md) — 37 Quelleinheiten, 26 interne Traits und 72 literal Actions in [JSON](generated/ui-surfaces.json) und [Mermaid](generated/ui-composition.mmd).
10. [PostgreSQL-Schemaentwicklung](schema-evolution.md) — Up-Migrationsbelege, Table/View-Unterschiede, historische Zustände und ein konkreter Migrationskonflikt.
11. [Exhibition Map und Edit-Autosave](exhibition-map.md) — UI–Domain–Geocoder–Persistenz-Kette samt Fehlpfaden.

Diese Dokumente sind **beschreibende Architekturbelege**, keine zweite normative Quelle neben den bestehenden Fachverträgen. Maßgebliche Anforderungen bleiben vorläufig [ARCHITECTURE](../ARCHITECTURE.md), [DATA-MODEL](../DATA-MODEL.md), [Publication](../ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md), [Dialog](../ADMIN-DIALOG-CONTRACT.md) und [Public](../PUBLIC-IMPLEMENTATION-CONTRACT.md). Widersprüche werden explizit markiert und beim Neuschnitt auf genau einen Vertrags-Eigentümer reduziert.

## Quellen- und Abdeckungsregel

- **Quellgeprüft**: konkrete Klasse, Methode, Konstante oder Route im bezeichneten Git-Commit gelesen; nur diese konkrete Aussage gilt als geprüft.
- **Statisch inventarisiert**: aus Repositorypfaden oder Quelltext extrahiert; keine Aussage über dynamische Framework-Registrierung.
- **Noch offen**: dynamische Auflösung, vollständige Datenbankschema-/FK-Prüfung oder tatsächliches Browserverhalten noch nicht verifiziert.
- **Laufzeitverifiziert**: erst nach ausdrücklich ausgeführtem, sicherem Test oder Browsercheck; derzeit hier **keine** solche Behauptung.

Dokumentierte Quellbelege beziehen sich auf den Ausgangsstand `82879135c0ab18dd88b07663c1e214a4ade0e45b`. Diese SHA ist **Belegstand, kein festzuhaltender Arbeits-HEAD**. Beim Einholen von `dev` werden betroffene Belege und Diagramme neu geprüft. Wo in den folgenden Dokumenten eine Klassenreferenz steht, verweist sie auf einen Repositorypfad; generierte Kataloge dürfen niemals vorgeben, die gesamte Laufzeitdynamik allein aus Imports abzuleiten.

## Wiederholbare PHP-Quellinventur

Der erste lokale Generator liegt in [`scripts/generate-architecture-inventory.php`](../../scripts/generate-architecture-inventory.php). Er benötigt lediglich PHP und bootet weder Laravel noch Datenbank oder CI:

```sh
php scripts/generate-architecture-inventory.php --write
php scripts/generate-architecture-inventory.php --check
```

Er inventarisiert via PHP-Tokenizer alle deklarierten Typen in `app/**/*.php` einschließlich Fundstelle und die syntaktischen Datei-`use`-Imports; außerdem erfasst er Domain-Moduldateien und Migrationspfade. **Ein Import ist noch keine nachgewiesene Laufzeitabhängigkeit**. Das erzeugte `docs/architecture/generated/php-symbols.json` wurde auf dieser GitHub-gestützten Arbeitsumgebung noch nicht gegen den gesamten Repository-Checkout generiert; der Generator wurde separat mit einem kleinen PHP-Beispiel syntaktisch und funktional geprüft. `--check` kann später reproduzierbar Abweichungen melden.


### Statisch belegte Beziehungen

Zusätzlich erfasst [`scripts/generate-architecture-relations.php`](../../scripts/generate-architecture-relations.php) nur **explizite** PHP-Strukturbeziehungen: `extends`, `implements`, Trait-Nutzung, Konstruktor-Typen, direkt benannte Eloquent-Relationen und `app(Foo::class)`/`resolve(Foo::class)`. Die resultierenden Kanten haben einen Dateipfad und eine Zeile. Externe Frameworkklassen und nicht auflösbare dynamische Beziehungen sind keine implizit bestätigten Runtime-Kanten.

```sh
php scripts/generate-architecture-inventory.php --write
php scripts/generate-architecture-relations.php --write
php scripts/generate-architecture-inventory.php --check
php scripts/generate-architecture-relations.php --check
```

Ergebnis: `docs/architecture/generated/php-relationships.json` (Quellbelege) und `module-relationships.mmd` (aggregierte, mit Referenzanzahlen beschriftete Modulübersicht). Beide sind nach einem vollständigen lokalen Checkout-Lauf einzuchecken; sie sind hier noch **nicht** als vollständig erzeugt ausgegeben.

### Vollständiger Eloquent-Modellgraph

Der separate [Eloquent-Generator](../../scripts/generate-architecture-eloquent.php) erfasst die direkt in `app/Models/*.php` deklarierten Relation-Methoden. Ein [JSON-Index](generated/eloquent-relationships.json) mit 26 Modellen, 48 Kanten und Quellzeilen sowie ein [Gesamtgraph](generated/eloquent-relationships.mmd) sind eingecheckt. Die beiden Ausgaben sind gegeneinander geprüft; ein kompletter lokaler Generator-`--check` ist weiterhin offen. Eloquent-Relationen sind nicht mit SQL-FKs gleichzusetzen.

### Physisches Datenbankschema (read-only)

[`scripts/export-architecture-postgres.php`](../../scripts/export-architecture-postgres.php) liest nach einer **separaten, kontrollierten Migration in einer wegwerfbaren PostgreSQL-Datenbank** `pg_catalog` und `information_schema`. Der Exporter selbst führt **keine** Migrationen, Schemaänderungen oder Schreibabfragen aus; er verlangt explizit `ARCH_DOC_DB_DISPOSABLE=1`, eine `pgsql:`-DSN und verwendet eine Read-only-Transaktion. Er exportiert Tabellen/Views, Spalten, Constraints, Indizes und nutzerdefinierte Trigger nach `postgresql-catalog.json` sowie FK-Quellenkanten nach `postgresql-fk-graph.mmd`. Die FK-Pfeile behaupten bewusst noch keine Kardinalitäten.

Der tatsächliche Datenbankexport wurde **noch nicht ausgeführt**, da in dieser Arbeitsumgebung keine migrierte PostgreSQL-Testinstanz vorhanden ist. Es gibt deshalb weiterhin **kein als vollständig behauptetes ERD**. Die vollständige Erhebung folgt erst nach sicherer Bereitstellung einer Wegwerf-DB; produktive und lokale persistente Vorschau-Datenbanken sind tabu.

## Modellarten und Fertigstellung

| Modell | Erste Quelle | Status |
| --- | --- | --- |
| PHP-Klassen, Interfaces, Enums, Traits und statische Abhängigkeiten | `app/**/*.php` | Tokenizer-Generator erstellt; vollständiger Checkout-Lauf und umfassende Abdeckungsprüfung offen |
| Fachmodule und ihre Eintrittspunkte | `app/Domain`, `app/Http`, `app/Filament`, `app/Livewire` | Schwerpunktpfade quellengeprüft |
| Physisches PostgreSQL-Schema und Constraints | ausgeführte, vollständige Migrationen in **isolierter Wegwerf-DB** | offen; Migrationsanzahl ist kein Schema |
| Eloquent-Beziehungen | Relation-Methoden der Models | 26 Modelquellen / 48 explizite Kanten als JSON und Mermaid vorhanden; **physische FKs unabhängig offen** |
| JSON-/Rich-Text-Komponenten | Validierung und Normalisierung (z. B. `CustomPageSetting`) | erste Typfamilien geprüft, Feldschema offen |
| Routes und Middleware | `routes/web.php`, `bootstrap/app.php`, Filament-Provider | Public-Routen und Read-Kontext geprüft, kompletter Runtime-Router offen |
| Zustandsautomaten | Domain-Services und Guards | fünf aktuell untersuchte Modelle / 35 quellverankerte Kanten; weitere Zustände und indirekte Übergänge offen |
| Ablauf-/Sequenzdiagramme | UI/Controller → Services → Persistenz/Events | erste zwei in diesem Ordner |
| Browserinstanzen und -übergänge | Blade, Alpine/Livewire und `resources/js` | Dateiinventar; dynamische Verifikation offen |
| Deployment und externe Systeme | Docker-/Laravel-Konfiguration; Plattformgrenze | offen; keine erfundene Hosttopologie |

### Reihenfolge der weiteren Arbeit

Zuerst maschinenlesbare vollständige Objektinventare und Quellpfad-IDs, danach deterministische Graphgenerierung; anschließend fachlich überprüfte Sequenz- und Zustandsmodelle. Erst danach werden die alten Großdokumente endgültig aufgeteilt und stark gekürzt. So werden falsche Altannahmen nicht bloß in kleinere Dateien verschoben. `testing` wird keine versteckte Voraussetzung für Dokumentations-Commits.
