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

Die kanonische Ownership der Public-Readiness ist vor einer Produktkorrektur zu entscheiden; beide legitimen Aktionswege sollten dieselben fachlichen Publikationsvoraussetzungen einhalten. Auf `docs` wird **kein Runtime-Code** verändert und der parallele `testing`-Branch nicht berührt.

## Prüfstatus

**Geprüft:** Modulimport-Kanten und die konkreten Methoden-/Container-Aufrufstellen einschließlich ihrer voneinander abweichenden Conditions.

**Offen:** tatsächliches Ergebnis im vollständig migrierten Laravel-Lauf (Video- und Override-ALT-Inversen), dynamische weitere Call-Sites sowie die sachgerechte Konsolidierung der Publish-Owner. Import-SCCs sind keine Beweise für Instanziierungszyklen.
