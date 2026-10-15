# Architektur: Quelleninventar und Modelle

Diese Sektion entsteht auf dem dauerhaft erlaubten Branch `docs`. Der ebenfalls dauerhaft erlaubte Branch `testing` ist ein getrennter Arbeitsbereich; Änderungen und Tests dort sind nicht Aufgabe dieses Dokumentationszweigs. Laufende Anwendungscodeänderungen bleiben auf `dev`. Neue `dev`-Commits werden bewusst und ohne History-Rewrite in `docs` übernommen. Keine CI-Interaktion.

## Einstieg

1. [Bestandsaufnahme](inventory.md) — nachprüfbarer Quellcodekatalog mit Klassifikation, Abhängigkeiten und bekannten Abdeckungslücken.
2. [Vollständiger PHP-Quellenkatalog](php-source-audit.md) — **eingefrorener** Originalbestand mit 248 PHP-Typdateien und 900 Imports; aktueller Git-Tree nach Child-Cleanup **245 PHP-Dateien**. Die historische +1-Importrechnung ist nicht mehr die aktuelle Vollsumme. [Modulgraph](generated/app-module-imports.mmd) bleibt ein eingefrorener Generatorstand.
3. [Klassen-Importdiagramme](class-import-diagrams.md) — 20 Modulansichten des eingefrorenen 900er-Importstands; nach dem Cleanup nicht vollständig regeneriert.
4. [Importgrenzen und Artwork-Publish-Abweichung](import-boundaries.md) — Modulzyklus und konkret unterschiedliche Gallery-/Undo-Guards.
5. [Eloquent-Relationen](eloquent-relations.md) — 26 Modeldateien und 48 Relationen, jetzt zusätzlich als [generierter JSON-Graph](generated/eloquent-relationships.json) und [Mermaid-Gesamtgraph](generated/eloquent-relationships.mmd).
6. [Fachliche Lebenszyklen](lifecycles.md) — fünf eingefrorene Source-State-Machines (historisch 35 Kanten) **plus** quellengeprüfte Home-Achsen, Publication-Working/LIVE/Version-Kontexte und Journal-Blog/Exhibitions-Templatewechsel ohne Inhaltsmigration; Guards, No-op, Audits und Lock-Grenzen jeweils getrennt.
7. [Admin-/Publication-Aufrufstellen](admin-publication-calls.md) — 21 belegte direkte Methodenaufrufe über 14 Klassen, mit separatem JSON/Diagramm und Source-Anchor-Check.
8. [Publication und öffentlicher Lesezugriff](publication.md) — erster durchgehender Daten- und Ablaufgraph.
9. [Restore, Revert und Reset](publication-working-operations.md) — Activity UI bis zum Working-Snapshot, mit drei getrennten Ablaufdiagrammen, Guards und No-ops.
10. [Strukturierte Komponenten](structured-components.md) — sechs persistierte JSON-Typen samt **dekorativen Image-`children[]`** als optionaler geordneter Textebene; Model-Guards, eigene Edit-Actions/Parent- und Child-Zeilen, native Float-Darstellung und rekursive Media-Referenzlücke. [UI-Zielauflösung/gemischte Selection-Grenze](ui-surfaces.md#custom-page-image-children-komponentenaktionen-und-selektionsgrenze) und [native Cross-Level-Placement-Kette](ui-surfaces.md#native-hierarchie-placement-pages-und-custom-pages) getrennt. Contact/Legacy-List-Context/Image-Scale bleiben erklärt; [JSON-Typkatalog](structured-components.json) ist noch eingefroren.
11. [Runtime-Einstiegspunkte](runtime-entrypoints.md) — 21 explizite HTTP-Routen mit [PHP-Quellprüfer](../../scripts/check-architecture-routes.php), Middleware, Filament-Discovery und Public-JS.
12. [Public-SPA-Dokumentlebenszyklus](public-spa-lifecycle.md) — Livewire-4-Navigation mit deterministischen, URL-gebundenen Eckpositionen und CSP-Nonce, Dokument-Swap/Disposer, Content-only-Folio-Impuls, Hero-Completion-Guard und separatem Gallery-Bildladepuffer; **Bladeseitiger 2-eager-Stand gegen derzeit abweichenden Public-Vertrag** quellenbelegt.
13. [Filament-/Livewire-Komposition und Pages-Identity-Negativpfad](ui-surfaces.md) — **eingefrorener** UI-Katalog (37 Einheiten, 26 Trait-Kanten, 72 literal Actions); aktueller Tree mit nur noch **23 Concerns** und **sieben Custom-Page-Concerns**. [JSON](generated/ui-surfaces.json)/[Mermaid](generated/ui-composition.mmd) wurden noch nicht regeneriert; Pages-Identity-Negativpfad separat quellengeprüft.
**Weitere aktuelle fachliche Pfade:** [Editorial-Mutationsketten](editorial-mutation-paths.md) — Home-Sichtbarkeit als eigenständiger Routing-/Menu-Guard und Custom-Page-Image-Select von Livewire-Ereignis über gesperrte Domaintransaktion, Audit, No-op, Projektion und Notification bis zur begrenzten Index+Typ-Stale-Target-Prüfung. Quelle `dev@f69e0e7e`; Tests nur gelesen.

14. [Admin Notification transport](admin-notification-transport.md) — quellenverankerte Audit-/Inbox-/Header-Sequenz, Session-/Livewire-Dispatch, Retention und nicht runtimegeprüfte Negativpfade.
15. [PostgreSQL-Schemaentwicklung](schema-evolution.md) — Up-Migrationsbelege, Table/View-Unterschiede, historische Zustände und [vier neue Medienrechte-Spalten in Working/`committed`](schema-evolution.md#aktuelle-media-rechte-schema-parität-migration-2026_10_15_180000); keine reale PostgreSQL-Abnahme, historischer Migrationskonflikt bleibt.
16. [Exhibition Map und Edit-Autosave](exhibition-map.md) — UI–Domain–Geocoder–Persistenz-Kette samt Fehlpfaden.
17. [Media-Referenzen, Cleanup und Reclaim](media-reference-and-cleanup.md) — DB-Commit-vor-Dateilöschung, direkte-vs-queued Retry-Grenzen; [Source-Coverage-Matrix](media-reference-and-cleanup.md#quellbasierte-medienreferenzmatrix) für Working, Storage-Detail, PublicMedia und Cleanup über Image-Children/Home-List sowie **bitgenau gesicherten Variant-Reclaim/Rebuild**.
18. [Application-Build und Runtime-Plattformgrenzen](application-runtime-boundaries.md) — Docker Build/Entrypoint/Apache/X-Sendfile/Local Preview getrennt von Deployment/DB-Migration/Smoke; zwei konkrete Source-Negativfälle zu Release-HTTP-Marker und Matomo-Konfigurationsschalter.

Diese Dokumente sind **beschreibende Architekturbelege**, keine zweite normative Quelle neben den bestehenden Fachverträgen. Maßgebliche Anforderungen bleiben vorläufig [ARCHITECTURE](../ARCHITECTURE.md), [DATA-MODEL](../DATA-MODEL.md), [Publication](../ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md), [Dialog](../ADMIN-DIALOG-CONTRACT.md) und [Public](../PUBLIC-IMPLEMENTATION-CONTRACT.md). Widersprüche werden explizit markiert und beim Neuschnitt auf genau einen Vertrags-Eigentümer reduziert.

**[Journal-Content-Lifecycle](journal-content-lifecycle.md):** Blog Publish/Scheduled als zeitlicher **Query-Guard ohne Cron-Statuswechsel**, Exhibition Publish/Archive/Restore/Temporal/Map, gemeinsame JournalEntryMedia-Rich-Text- und Media-Delete-Nebenwirkungen, öffentliches Template/LIVE/Preview-Filtering; eigenständige No-op-/Audit-/Rollback-Prüffälle.

**[Gallery-/Artwork-Publication-Fluss](artwork-gallery-publication.md):** Einzel-Publish/No-op, **atomare Gallery-Batch-Transaktion**, öffentliche/Preview-Eligibility, gemeinsame Viewer-Reihenfolge und abweichende Undo-Inverse mit ALT-/Video-/Thumbnail-Grenzen. Die Norm-/Testmatrix bleibt beim bestehenden [Importgrenzen-Owner](import-boundaries.md).

**Aktueller Public-Legal-Code (`dev@c33d7e9c`):** [Public-Security-Quellenfluss](public-security-privacy-boundaries.md#neue-general--public-legal-quelle-stand-devc33d7e9c) trennt bereits geschriebenen General-/Legal-Disclaimer-Code samt englischem UI von noch offenen Migrationen, Realtests, Consent und Media-Rights. [Admin-Tabellenvertrag](../ADMIN-TABLE-CONTRACT.md#component-sequences) besitzt nun auch das zentrale hierarchische Sort/Nest-Grenzmodell.

**[Public-Sicherheits-, Rechte- und Privacy-Grenzen](public-security-privacy-boundaries.md):** technisch getrennter LIVE/Preview/Contact/Media-Request-Kontext, strikte nonce-CSP, TDM- und robots-Reservation, PublicMedia-Eligibility, Matomo-Consent-Lücke und neue verschachtelte Image-Child-Referenzlücken. Produkt-/Umsetzungsplan bleibt [PUBLIC-RIGHTS-AND-PRIVACY](../PUBLIC-RIGHTS-AND-PRIVACY.md); Quellbelege sind keine Runtime-Verifikation.
Die [Datenschutz-Quellmatrix](public-security-privacy-boundaries.md#6-public-datenschutz-getrennte-kontakt--tracking--und-kartendatenflüsse) trennt DB-Kontakt, privaten SMTP-Empfänger, Matomo-Browsertraffic, OSM-Tiles und bislang unbestätigte Server-/Log-/Retention-Realität.

**[Activity/Undo-Lifecycle](activity-undo-lifecycle.md):** AuditEvent und individualisierbares, befristetes Undo-Receipt sind keine Publication-Version; Ordering-Nettozyklen, Snapshot-After-Guards, Media-Inverse, Lifecycle-Inverse, persönliche Receipt-Guards und das **nicht atomare** Activity-Selection-Batch sind als echte State-/Transaktionspfade beschrieben.

**[Verbindliche Abdeckungs- und Abschlussmatrix](completion-criteria.md):** Die vorhandenen 19 Architekturtexte und 58 Mermaid-Dateien/-Blöcke sind Material, kein fachlicher Vollständigkeitsbeweis. Die Matrix benennt die für einen endlichen Dokumentationsabschluss nötigen Quellflüsse, offene Inhalte, später isoliert ausführbare Verifikation und die Artefaktklassen für dauerhaft versus nur lokal benötigte Graphen. **Codex erst nach Abschluss der fachlichen Dokumentation.**

## Quellen- und Abdeckungsregel

- **Quellgeprüft**: konkrete Klasse, Methode, Konstante oder Route im bezeichneten Git-Commit gelesen; nur diese konkrete Aussage gilt als geprüft.
- **Statisch inventarisiert**: aus Repositorypfaden oder Quelltext extrahiert; keine Aussage über dynamische Framework-Registrierung.
- **Noch offen**: dynamische Auflösung, vollständige Datenbankschema-/FK-Prüfung oder tatsächliches Browserverhalten noch nicht verifiziert.
- **Laufzeitverifiziert**: erst nach ausdrücklich ausgeführtem, sicherem Test oder Browsercheck; derzeit hier **keine** solche Behauptung.

Dokumentierte Quellbelege beziehen sich auf den Ausgangsstand `82879135c0ab18dd88b07663c1e214a4ade0e45b`. Diese SHA ist **Belegstand, kein festzuhaltender Arbeits-HEAD**. Beim Einholen von `dev` werden betroffene Belege und Diagramme neu geprüft. Wo in den folgenden Dokumenten eine Klassenreferenz steht, verweist sie auf einen Repositorypfad; generierte Kataloge dürfen niemals vorgeben, die gesamte Laufzeitdynamik allein aus Imports abzuleiten.

## Wiederholbare PHP-Quellinventur

Der erste lokale Generator liegt in [`scripts/generate-architecture-inventory.php`](../../scripts/generate-architecture-inventory.php). Er behandelt PHP-Imports ausschließlich auf Namespace-Scope; Closure-Variablen und Trait-Uses sind keine Dateiimports. 

Die **isolierte** Tokenizer-Selbstprüfung läuft ohne vollständigen Checkout, Laravel oder Datenbank: `php scripts/generate-architecture-inventory.php --self-test`. Sie enthält Fixtures für Closure-`use`, geklammerte Namespaces, Trait-`use`, anonyme Klassen, Gruppierungsimporte und String-Interpolation. Dieser Fixture-Lauf ersetzt nicht `--write`/`--check` gegen die vollständigen realen App-Quellen.

 Er benötigt lediglich PHP und bootet weder Laravel noch Datenbank oder CI:

```sh
php scripts/generate-architecture-inventory.php --write
php scripts/generate-architecture-inventory.php --check
```

Er inventarisiert via PHP-Tokenizer alle deklarierten Typen in `app/**/*.php` einschließlich Fundstelle und die syntaktischen Datei-`use`-Imports; außerdem erfasst er Domain-Moduldateien und Migrationspfade. **Ein Import ist noch keine nachgewiesene Laufzeitabhängigkeit**. Das erzeugte `docs/architecture/generated/php-symbols.json` wurde auf dieser GitHub-gestützten Arbeitsumgebung noch nicht gegen den gesamten Repository-Checkout generiert; der Generator wurde separat mit einem kleinen PHP-Beispiel syntaktisch und funktional geprüft. `--check` kann später reproduzierbar Abweichungen melden.


### Statisch belegte Beziehungen

Der Constructor-Dependency-Scanner verwendet zusätzlich einen klammerbewussten Parameter-Tokenizer: Attribute und Default-Expressions zählen nicht als Constructor-Typ; Commas innerhalb von Array- oder Attributargumenten trennen keine neuen Parameter. Union- und Intersection-Typen werden nur vor der echten Parametervariablen ausgewertet. Vier isolierte PHP-8.4-Fixtures (Attribute, Union, Intersection, verschachtelte Defaults) sind bestanden; volle Source-Graph-Regeneration bleibt offen.

Die Syntaxprüfung des Relationship-Generators besitzt außerdem `--self-test` (ohne `php-symbols.json` oder realen Checkout). Der zunächst eingefügte Selbsttest lag versehentlich vor der Initialisierung von `$mode`; die CLI-Modusprüfung steht jetzt vor dem Fixture-Einstieg. Dies ist ein **behobener Sourcefehler**; ein vollständig ausgeführter nativer Generatorskriptlauf bleibt separat offen. Fünf isolierte Fälle trennen globale `app()/resolve()`-Helper einschließlich vollständig qualifizierter `\\app()/\\resolve()`-Aufrufe von Methodenaufrufen (`$obj->resolve()`), statischen Calls (`Container::resolve()`), Funktionsdeklarationen und anderen Namespaces. Nur die echten globalen Helper gelten als **statische Service-Locator-Kanten**. Ein auf Quellcodesyntax gestützter Helper ist noch kein nachgewiesener Container-Laufzeitaufruf. Der lokale PHP-8.4-Fixture-Test des Erkennungsprädikats bestand; der vollständige native Aufruf des Remote-Skripts ist unabhängig offen.

Zusätzlich erfasst [`scripts/generate-architecture-relations.php`](../../scripts/generate-architecture-relations.php) nur **explizite** PHP-Strukturbeziehungen: `extends`, `implements`, Trait-Nutzung, Konstruktor-Typen, direkt benannte Eloquent-Relationen und `app(Foo::class)`/`resolve(Foo::class)`. Die resultierenden Kanten haben einen Dateipfad und eine Zeile. Externe Frameworkklassen und nicht auflösbare dynamische Beziehungen sind keine implizit bestätigten Runtime-Kanten.

```sh
php scripts/generate-architecture-inventory.php --write
php scripts/generate-architecture-relations.php --write
php scripts/generate-architecture-inventory.php --check
php scripts/generate-architecture-relations.php --check
```

**Geprüfte Eingabegrenze (14.10.2026):** Das Skript liest zwingend die vom Inventory-Generator erzeugte Datei `docs/architecture/generated/php-symbols.json`; diese Datei ist auf dem aktuellen `docs`-Tree **nicht vorhanden**. Somit ist `--check` derzeit nicht unabhängig ausführbar, bevor der vorgeschaltete Tokenizer in einem echten Checkout durch `--write` die Eingabe erzeugt hat. Die gesondert vorliegende `generated/app-source-audit.json` ist ein anders entstandener, quellenverankerter Katalog und darf nicht als gleichwertiger nativer Input ausgegeben werden. In den dort nachgewiesenen **900** internen `App\...`-Importdeklarationen der 248 PHP-Quellen gibt es derzeit **keinen** gruppierten `use` und **keine** `as`-Aliasdeklaration. Der Alias-Zweig des Relationsparsers bleibt eine isoliert zu prüfende Syntaxfähigkeit, keine aktuell nachgewiesene Produktionsabweichung. Der bestehende inkrementelle Git-Blob-Abgleich bestätigt unveränderte interne Imports auf dem geprüften `dev`-Stand.

Ergebnis: `docs/architecture/generated/php-relationships.json` (Quellbelege) und `module-relationships.mmd` (aggregierte, mit Referenzanzahlen beschriftete Modulübersicht). Beide sind nach einem vollständigen lokalen Checkout-Lauf einzuchecken; sie sind hier noch **nicht** als vollständig erzeugt ausgegeben.

### Vollständiger Eloquent-Modellgraph

Der separate [Eloquent-Generator](../../scripts/generate-architecture-eloquent.php) erfasst die direkt in `app/Models/*.php` deklarierten Relation-Methoden. Der bisherige rein textbasierte Regex wurde durch einen **PHP-Tokenizer-Matcher** ersetzt: Er berücksichtigt Kommentare/Zeilenumbrüche und erfasst ausschließlich öffentliche, parameterlose Methoden mit explizitem passenden Relation-Rückgabetyp und direktem `return $this->relation(Model::class)` (inklusive nachfolgender Methodenchains). `self::class` wird auf das besitzende Model aufgelöst. Dynamische oder mehrdeutige Targets, verdeckte Methoden und abweichende Rückgabetypen werden nicht erraten. Über `php scripts/generate-architecture-eloquent.php --self-test` lassen sich fünf isolierte Positiv-/Negativ-Fixtures **ohne Checkout** ausführen. Die Parserfunktion und dieser Prüffallumfang wurden auf lokalem PHP 8.4 separat tatsächlich ausgeführt; der native vollständige Checkout-`--check` bleibt offen. Ein [JSON-Index](generated/eloquent-relationships.json) mit 26 Modellen, 48 Kanten und Quellzeilen sowie ein [Gesamtgraph](generated/eloquent-relationships.mmd) sind eingecheckt. Die beiden Ausgaben sind gegeneinander geprüft; ein kompletter lokaler Generator-`--check` ist weiterhin offen. Eloquent-Relationen sind nicht mit SQL-FKs gleichzusetzen.

### Physisches Datenbankschema (read-only)

[`scripts/export-architecture-postgres.php`](../../scripts/export-architecture-postgres.php) liest nach einer **separaten, kontrollierten Migration in einer wegwerfbaren PostgreSQL-Datenbank** `pg_catalog` und `information_schema`. Der Exporter selbst führt **keine** Migrationen, Schemaänderungen oder Schreibabfragen aus; er verlangt explizit `ARCH_DOC_DB_DISPOSABLE=1`, eine `pgsql:`-DSN und verwendet eine Read-only-Transaktion. Er exportiert Tabellen/Views, Spalten, Constraints, Indizes und nutzerdefinierte Trigger nach `postgresql-catalog.json` sowie FK-Quellenkanten nach `postgresql-fk-graph.mmd`. Die FK-Pfeile behaupten bewusst noch keine Kardinalitäten.

Der tatsächliche Datenbankexport wurde **noch nicht ausgeführt**, da in dieser Arbeitsumgebung keine migrierte PostgreSQL-Testinstanz vorhanden ist. Es gibt deshalb weiterhin **kein als vollständig behauptetes ERD**. Die vollständige Erhebung folgt erst nach sicherer Bereitstellung einer Wegwerf-DB; produktive und lokale persistente Vorschau-Datenbanken sind tabu.

## Modellarten und Fertigstellung

| Modell | Erste Quelle | Status |
| --- | --- | --- |
| PHP-Klassen, Interfaces, Enums, Traits und statische Abhängigkeiten | `app/**/*.php` | **248 Typen und 900 Importkanten** im eingefrorenen unabhängigen Quellkatalog; aktueller Tree **245 PHP-Dateien**, neue Importgesamtsumme nach Refactor **nicht nachgezählt**, Diagramme noch nicht regeneriert; nativer Tokenizer-Lauf offen |
| Fachmodule und ihre Eintrittspunkte | `app/Domain`, `app/Http`, `app/Filament`, `app/Livewire` | Schwerpunktpfade quellengeprüft |
| Physisches PostgreSQL-Schema und Constraints | ausgeführte, vollständige Migrationen in **isolierter Wegwerf-DB** | offen; Migrationsanzahl ist kein Schema |
| Eloquent-Beziehungen | Relation-Methoden der Models | 26 Modelquellen / 48 explizite Kanten als JSON und Mermaid vorhanden; **physische FKs unabhängig offen** |
| JSON-/Rich-Text-Komponenten | `CustomPageSetting`, Home-Editorial-Validatoren, Public Renderer | sechs persistierte Komponenten samt verschachtelten Feldregeln, Normalisierung und Public-Readiness quellenbelegt; **Runtime-/Import-Akzeptanz offen** |
| Routes und Middleware | `routes/web.php`, `bootstrap/app.php`, Filament-Provider | Public-Routen und Read-Kontext geprüft, kompletter Runtime-Router offen |
| Zustandsautomaten | Domain-Services und Guards | fünf aktuell untersuchte Modelle / 35 quellverankerte Kanten; weitere Zustände und indirekte Übergänge offen |
| Ablauf-/Sequenzdiagramme | UI/Controller → Services → Persistenz/Events | Publication, Restore/Revert/Reset, Admin Notifications, Exhibition Map und Public SPA als getrennte statische Quellflüsse; **Runtime-Races/Browser nicht getestet** |
| Browserinstanzen und -übergänge | Blade, Alpine/Livewire und `resources/js` | konkrete SPA-Disposer, Map/Viewer/Slideshow/Matomo-Pfade und native Folio-Animation quellengeprüft; **dynamische Browser-/Accessibility-Abnahme offen** |
| Deployment und externe Systeme | [Build-/Boot-/HTTP-Trace](application-runtime-boundaries.md), [Release-Vertrag](../RELEASE.md) und [Plattformgrenze](../SERVER-OPERATIONS-BASELINE.md) | Source-Guards, Health/Smoke und Preview-vs-Production-Migration dokumentiert; Host-/Ingress-/Restore-Laufzeit, Release-Marker-URL und Matomo-Flag-Negativfälle **offen** |

### Reihenfolge der weiteren Arbeit

Das kuratierte [HTTP-Literalmanifest](runtime-entrypoints.json) ist am **15.10.2026** mit den **21** aktuellen `routes/web.php`-GET/POST-Deklarationen einschließlich TDM-Discovery und Quellzeilen (Quelle `dev@cbc3f5ad`) abgeglichen worden. [Dynamische und negative HTTP-Quellgrenzen](runtime-entrypoints.md#public-seo-discovery-und-dynamische-grenzen-stand-devcbc3f5ad) sind separat beschrieben. Der native Checker, `artisan route:list` und echte GET/HEAD-/Ingress-Tests bleiben offen.

Die bisher erstellten Objektinventare bleiben als quellengebundene **historische Ausgangsbasis** erhalten. **Jetzt** folgen die fachlichen Sequenz-/Zustandsmodelle, Owner-/Guard-/Fehlerpfad-Lücken und die Konsolidierung bestehender Dokumente; der vollständige lokale Generator-/Artefaktlauf folgt **erst danach** gemäß [Abschlussmatrix](completion-criteria.md). Keine CI-Interaktion, kein Codex-Verbrauch während offener Schreibarbeit; `testing` ist keine verdeckte Voraussetzung.
