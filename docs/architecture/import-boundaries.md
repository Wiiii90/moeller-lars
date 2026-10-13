# Importgrenzen und Artwork-Undo-Readiness

**Quellenstand:** [vollständiges Importinventar](generated/app-source-audit.json) auf `ca162bcd5e5bbaf50bc6bdb9c6bc01640269d027`, ergänzt um konkrete aktuelle Service-Aufrufstellen. Alle Modulzahlen messen **Importdeklarationen, keine Runtime-Aufrufe**.

## Tatsächlich verbundene Modulgrenzen

Acht Modulbereiche bilden eine stark zusammenhängende Komponente im gerichteten Importgraphen: `Domain/Admin`, `Domain/Artwork`, `Domain/Content`, `Domain/Media`, `Domain/Publication`, `Domain/Storage`, `Models` und `Routing`. Das heißt: Jeder dieser Bereiche erreicht im **Importgraphen** alle anderen. Es beweist weder Runtime-Rekursion noch einen Architekturfehler.

| Gegenseitige Imports | Richtung A → B | B → A | Fachlicher Anhaltspunkt |
| --- | ---: | ---: | --- |
| Admin ↔ Content | 5 | 14 | Undo-Inversen und Editorial Audit |
| Admin ↔ Artwork | 2 | 7 | Undo-Lifecycle und Artwork Audit |
| Admin ↔ Publication | 3 | 1 | Publication-Audit-/Undo-Grenze |
| Artwork ↔ Media | 8 | 1 | Artwork-Ingest und Medienlösch-Folgen |
| Content ↔ Media | 7 | 13 | Public-Media-/Content-Rendern und Referenzentfernung |
| Content ↔ Models | 53 | 10 | Editorial Domain und Model-Level-Validatoren |
| Media ↔ Publication | 2 | 2 | Media-Bereinigung und Snapshot-Schutz |
| Content ↔ Routing | 4 | 1 | Public-Home-Resolver und SiteNodeRoute |

Weitere Paare sind über die [vollständigen Importkanten](generated/app-source-audit.json) prüfbar. Die Architektur soll diese Kanten nicht pauschal „wegrefactoren“, sondern bei fachlich notwendigen Änderungen die **konkreten Runtime-Callees** und Guards prüfen.

## Konkrete Abweichung: Artwork-Publish in Gallery und Undo

Zwei Publish-Pfade sind tatsächlich **aktiv referenziert**:

1. [GalleryWorkspaceArtworkActions](../../app/Filament/Pages/Concerns/GalleryWorkspaceArtworkActions.php) und [GalleryWorkspaceBatchActions](../../app/Filament/Pages/Concerns/GalleryWorkspaceBatchActions.php) verwenden [`ArtworkPublicationService::publish()`](../../app/Domain/Artwork/ArtworkPublicationService.php). Dessen `mediaReady()` akzeptiert Asset-ALT **oder ALT-Override** der ArtworkMedia-Relation. Video-Medien sind ohne Bild-Thumbnail publizierbar, sofern die Video-MIME-Regel greift.
2. [`AdminUndoService::undo()`](../../app/Domain/Admin/AdminUndoService.php) ruft über [`AdminLifecycleRegistry::applyInverse()`](../../app/Domain/Admin/AdminLifecycleRegistry.php) bei der Inverse von `artwork.unpublished` [`ArtworkEditorialService::publish()`](../../app/Domain/Artwork/ArtworkEditorialService.php) auf. Diese zweite Methode fordert **nichtleeren Asset-ALT** und **genau eine verfügbare Thumbnail-Variante** mit dem definierten Transformprofil – auch wenn das ursprüngliche Artwork über einen ALT-Override oder als Video publiziert werden durfte.

**Konsequenz:** Ein von der Gallery regulär publizierbares Artwork könnte beim späteren Undo einer Unpublication durch den strengeren alternativen Publish-Guard abgewiesen werden. Das ist eine **quellbelegte Guard-Differenz und konkrete Testhypothese**, noch kein ausgeführter Browser-/Datenbank-Fehlernachweis.

### Negativfallmatrix für eine kanonische Publish-Readiness

Alle Zeilen setzen eine vorhandene, veröffentlichte Gallery SiteSection, genau eine Primärrelation und einen verfügbaren `MediaAsset` voraus. **Gallery** bezeichnet den tatsächlich verwendeten `ArtworkPublicationService::mediaReady()`-Guard; **Undo-Re-Publish** bezeichnet `ArtworkEditorialService::publish()` im `AdminLifecycleRegistry`. Ohne ausdrücklich genannten verfügbaren Bild-Thumbnail existiert keiner.

| Primärmedium und ALT-Quelle | Gallery Publish | Undo-Re-Publish nach Unpublish | Beleg |
| --- | --- | --- | --- |
| Bild, Asset-ALT vorhanden, **genau ein** verfügbarer passender Thumbnail | akzeptiert | akzeptiert | gemeinsamer Positivfall, sofern weitere Invarianten erfüllt bleiben |
| Bild, Asset-ALT leer, **ArtworkMedia ALT-Override** vorhanden, genau ein passender Thumbnail | akzeptiert | **abgewiesen** | Gallery prüft `altOverride || asset.alt_text`; Editorial prüft nur `asset.alt_text` |
| Video, gültiger Asset-ALT, **kein** Bild-Thumbnail | akzeptiert | **abgewiesen** | Gallery akzeptiert `MediaTypePolicy::isVideo()` ohne Thumbnail; Editorial fordert dennoch genau ein verfügbares Thumbnail |
| Video, **nur** ALT-Override, kein Thumbnail | akzeptiert | **abgewiesen** | beide abweichenden Voraussetzungen zusammen |
| Bild, Asset-ALT vorhanden, **keine** verfügbare passende Thumbnail-Variante | abgewiesen | abgewiesen | Thumbnail ist für beide Bildpfade nötig |
| Video oder nichtbildliches Asset mit verfügbarer passender Thumbnail-Variante und Asset-ALT | Gallery nur für unterstütztes Video; anderer MIME abgewiesen | Editorial hat **keine eigene MIME-Guard-Klausel** im geprüften `publish()` | mögliche umgekehrte Guard-Lücke; Erreichbarkeit eines solchen Datensatzes separat testen |

Die Matrix zeigt **Quellbedingungen**, keine erfolgreich gespeicherten Demonstrationsdatensätze. Bei einem Undo einer `artwork.unpublished`-Activity führt der Inverse-Schlüssel `artwork.published` im Registry-Code zur strengeren Editorial-Methode. Deren `ValidationException` entsteht **vor** dem eigentlichen DB-Mutationsblock; aus dem gezeigten Code ist daher nicht abzuleiten, dass sie trotz Abbruch den Zustand auf Published setzt. Ob die übergeordnete Undo-Action den Fehler korrekt sichtbar meldet und den Receipt behält, ist eine zusätzliche Livewire-/DB-Akzeptanz.

**Separat zu prüfen:** konsistente Gallery-UI-Readiness, Domain-Publish und Undo-Wiederveröffentlichung; ALT-Override für Bilder; Video mit Asset-ALT und ohne Thumbnail; Primärmedien-/Galerie-Zustände; bereits publiziertes Artwork/No-op; invalidierte Medienvarianten; Validation/Rollback; Audit/Notification genau bei realen Änderungen. Die geplante Korrektur muss die **eine kanonische Domain-Readiness** zum gemeinsamen Owner machen; keine dritte UI-Heuristik oder Anpassung historischer Undo-Receipts auf `docs`.


Die kanonische Ownership der Public-Readiness ist vor einer Produktkorrektur zu entscheiden; beide legitimen Aktionswege sollten dieselben fachlichen Publikationsvoraussetzungen einhalten. Auf `docs` wird **kein Runtime-Code** verändert und der parallele `testing`-Branch nicht berührt.

## Prüfstatus

**Geprüft:** Modulimport-Kanten und die konkreten Methoden-/Container-Aufrufstellen einschließlich ihrer voneinander abweichenden Conditions.

**Offen:** tatsächliches Ergebnis im vollständig migrierten Laravel-Lauf (Video- und Override-ALT-Inversen), dynamische weitere Call-Sites sowie die sachgerechte Konsolidierung der Publish-Owner. Import-SCCs sind keine Beweise für Instanziierungszyklen.
