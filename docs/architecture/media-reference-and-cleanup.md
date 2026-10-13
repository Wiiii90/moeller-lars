# Media-Referenzen und physische Bereinigung: Quellenpfade und Negativfälle

**Quellstand:** Anwendungs-PHP aus `dev` `1ca4014cae997b0b4a40422944b90a0328524f53`, in `docs` enthalten. **Statischer Source-Audit**, kein ausgeführter Datenbank-/Storage-/Browser-Test. Die [Media-Produktregeln](../MEDIA.md#deletion-and-cleanup) sind normativ; dieses Dokument macht ihre tatsächlichen Call-Pfade, Guards und Abweichungen sichtbar.

## Drei unabhängige Grenzen

| Grenze | Owner | Was tatsächlich passiert |
| --- | --- | --- |
| **Referenzermittlung** | [`MediaReferenceQuery`](../../app/Domain/Media/MediaReferenceQuery.php) | Eloquent-Relationen für Artwork/Journal/Site Identity und direktes JSON/Rich Text in Custom Page, Journal und Home entscheiden, ob ein MediaAsset *fachlich* referenziert ist. Diese Query benutzt **Working**; sie ist kein Beweis physisch ausgelieferter Bytes |
| **Logische Löschung** | [`MediaAssetEditorialService::delete()`](../../app/Domain/Media/MediaAssetEditorialService.php) | Unter Transaction-Advisory-Lock und Asset-Row-Lock: Schlüssel sichern, bekannte Canonical-Usages entfernen, Varianten und Asset auf `deleted` setzen und Audit schreiben. Ein Asset-Löschrequest ist keine sofortige Garantie, dass alle Bytes weg sind |
| **Physische Löschung** | [`PublicationMediaCleanupService`](../../app/Domain/Publication/PublicationMediaCleanupService.php) | Getrennter Dateilösch-Guard über aktuelles `committed.media_assets`, weiterhin verfügbare `publication_version_rows` und Working-State/Referenzen. Ein Queue-Eintrag kann warten, bis sein Schutz entfällt |

```mermaid
sequenceDiagram
    participant UI as Storage Action
    participant Service as MediaAssetEditorialService
    participant Ref as MediaReferenceQuery
    participant SQL as Working / LIVE / History
    participant Cleanup as PublicationMediaCleanupService
    participant Disk as Private Media Disk

    UI->>Service: delete(asset)
    Service->>SQL: BEGIN; Publication advisory lock; lock asset
    Service->>Ref: isReferenced(asset)
    opt Any recognized references
        Service->>SQL: Remove canonical artwork / journal / favicon / Custom / Home references
    end
    Service->>SQL: Mark variants and asset deleted; append Audit
    alt Current committed asset is still live
        Service->>Cleanup: queue(assetId, keys)
    end
    Service->>SQL: COMMIT
    alt Immediate-cleanup path
        Service->>Cleanup: deleteNow(keys)
    else Deferred-cleanup path
        Note over Cleanup: Wait for a later drain
    end
    Cleanup->>SQL: Check committed, version rows and Working references
    alt Still protected
        Cleanup-->>Cleanup: Keep / queue keys
    else Safe to delete
        Cleanup->>Disk: Delete original + variant keys
    end
```

## Bestätigte Quellabweichung: Home List fehlt im Media-Index

Die [Home-Komponenten-Validierung](../../app/Domain/Content/HomePresentationEditorialService.php) unterstützt `list` mit `media_asset_id` und `items[].body` (Rich Text), und der [öffentliche Component-Renderer](../../resources/views/pages/partials/component-sequence.blade.php) verwendet beide. `CustomPageSetting` hat dasselbe Modell.

**Asymmetrie:**

- [`MediaReferenceQuery::mediaIdsForCustomPage()`](../../app/Domain/Media/MediaReferenceQuery.php) zählt direkte Image-/List-Medien plus `RichTextMediaReference::idsFromCustomPageBlocks()` (einschließlich List-Item-Body).
- [`MediaReferenceQuery::mediaIdsForHome()`](../../app/Domain/Media/MediaReferenceQuery.php) iteriert Under Construction und Custom, verarbeitet aber ausschließlich `image.media_asset_id` und `text.body`; `list.media_asset_id` und `list.items[].body` fehlen.
- [`MediaAssetEditorialService::removeHomeReferences()`](../../app/Domain/Media/MediaAssetEditorialService.php) räumt ebenso nur Home-`image` und `text` auf, während `removeCustomPageReferences()` ausdrücklich List-Bild und List-Eintrags-Rich-Text behandelt.
- [`MediaReferenceCatalog::contentReferenceRowsByMediaId()`](../../app/Filament/Support/MediaReferenceCatalog.php), die **einzelnen referenzierenden Storage-Einträge** bereitstellt, bildet bei Home ebenfalls nur Image und Text ab. Ihr Custom-Page-Zweig unterscheidet ausdrücklich **List component image** und **List item Rich Text**. [`MediaStorageReferenceCatalog`](../../app/Filament/Support/MediaStorageReferenceCatalog.php) verwendet diese unvollständigen Home-Referenzzeilen wiederum für Storage.

**Folge (aus dem Code ableitbar):** Ausschließlich durch eine Home-List referenzierte Medien können im Storage-Usage-Filter, in der **detaillierten Storage-Referenzliste** und beim `isReferenced()`-Guard als unreferenziert erscheinen. Eine ausdrücklich bestätigte Medienlöschung kann dann den MediaAsset auf `deleted` setzen, ohne diese Home-List-Payloads zu aktualisieren. Ob ein konkreter Public-Request sichtbaren Schaden zeigt oder Dateibytes gelöscht werden, hängt zusätzlich vom öffentlichen Renderer und dem **separaten LIVE-/History-Schutz** ab und ist hier **nicht** getestet.

Das ist eine **Source-konkrete Coverage-Lücke**, keine Erweiterung des Produktvertrags. Der fachliche Fix gehört auf `dev` in die gemeinsamen Media-Referenz-/Removal-Owner samt Regressionen, nicht als paralleler Home-Workaround auf `docs`.

## Recovery-Space und Retry-Semantik

[`SiteStorageReclaimService::reclaim()`](../../app/Domain/Storage/SiteStorageReclaimService.php) hält zunächst denselben Publication-Advisory-Lock. Es schützt den neuesten LIVE-Checkpoint und die im Working-Kontext eingetragene Source-Checkpoint-ID. Andere noch verfügbare Snapshots verlieren ihre `publication_version_rows` und werden auf `snapshot_available=false` gesetzt; **Checkpoint-Metadaten und Activity/Audit bleiben**. Die aktiven Admin-Undo-Receipts werden freigegeben. Die zu löschenden Keys werden vor dem Entfernen der Snapshot-Referenzen in der Cleanup-Queue hinterlegt. Erst **nach Transaction-Commit** laufen `mediaCleanup->drain()`, Reclaim der generierten Varianten und der Capacity-Refresh.

[`MediaVariantRegenerationService::reclaimRebuildableFiles()`](../../app/Domain/Media/MediaVariantRegenerationService.php) löscht nicht blind: nur verfügbare, rekonstruierbare WebP-Thumbnail-Varianten aktueller Transformprofile von verfügbaren Image-Originalen; pro Variante mit Cache-Lock. Vor dem Löschen prüft `renderExpectedBytes()` Quelle, Dimensionen, gespeicherte Byteanzahl und SHA-256. Ein späteres `ensureFile()` darf die gleiche öffentliche URL nur bei exakt passenden Bytes wieder bedienen. Die Variante bleibt als DB-Row erhalten.

**Wichtige asynchrone Fehlergrenzen:**

- `drain()` belässt geschützte oder nicht löschbare Queue-Einträge, statt den History-/Working-Schutz zu übergehen. `deleteNow()` kann wegen Schutz in die Queue überführen.
- Schlägt die **unmittelbare** physische Dateilöschung nach einer bereits committeten logischen Löschung fehl, meldet `deleteNow()` einen Fehler, fügt die fehlgeschlagenen Keys aber **nicht automatisch** derselben Queue hinzu. Der Storage-Action-Handler erkennt `state=deleted` und zeigt `File cleanup failed`; ein künftiger `Free storage`-Lauf kann Working-Deleted-Keys erneut aufnehmen. Das ist eine quellenbelegte **Retry-Lücke für die direkte Fehlerroute**, noch kein nachgewiesener dauerhafter Orphan.
- `MediaAssetEditorialService::delete()` gibt auch beim bereits zuvor auf `deleted` stehenden Asset `true` zurück, ohne neues Delete-Audit. Der normale Storage-UI-Handler zeigt nach erfolgreichem Service-Rücklauf `File deleted`. Ob ein bereits gelöschtes Asset durch einen **stalen** Livewire-Action-Request erneut erreichbar ist, ist noch zu prüfen; nicht als normale Benutzerreproduktion ausgeben.
- Die Transaction-Grenze des Reclaim endet **vor** `drain()`, Variantenlöschung und Capacity-Refresh. Ein späterer Fehler darf nicht als Rollback bereits freigegebener Undo-/Snapshot-Wurzeln missverstanden werden.

## Gezielte isolierte Regressionen

| Testfall | Erwarteter fachlicher Invariant |
| --- | --- |
| Home Custom List verweist ausschließlich über `media_asset_id` auf Asset A | A erscheint als **referenziert**; autorisierte Löschung bereinigt die List-Bildreferenz ohne andere Listdaten zu verlieren |
| Home Under Construction List verweist ausschließlich über `items[].body` auf Asset B | B erscheint als **referenziert**; autorisierte Löschung entfernt nur kanonische Rich-Text-Referenzen |
| Home List-Medium im Storage-Destination-Filter und in der Detailreferenz | Beide Projektionen sowie Gesamt-In-Use zeigen konsistent die Home-List-Referenz, statt parallele unvollständige Home-Scanner zu behalten |
| Gleichzeitige Home- und Custom-Page-List-Referenz, plus Artwork/Journal-Usage | Alle konkreten Referenz-Owner werden symmetrisch bereinigt; fremde MediaAssets bleiben unberührt |
| LIVE/History referenziert A nach Working-Deletion | A-Original/Variant-Dateien bleiben bestehen, bis **alle** tatsächlichen Recovery-Roots sicher entfallen |
| Fehlgeschlagene unmittelbare Storage-Disk-Löschung | Logisch gelöscht, wahrheitsgemäße Danger Notification, klarer Retry-/Queue-Pfad, kein falsches Success-Audit |
| Reclaim mit geschütztem LIVE/Working-Source und altem Snapshot | Nur freigebbare Manifeste/Receipts verschwinden, Checkpoint-/Activity-Historie bleibt; keine geschützten Media-Dateien löschen |
| Original fehlt, Thumbnail ist nicht bytegleich rekonstruierbar oder Varianten-Lock belegt | Variante nicht als sicher reclaimbar löschen; bestehende DB-Metadaten und öffentliche IDs bleiben unverändert |
| Bereits gelöschtes Asset über stale Action-ID | Keine neu behauptete fachliche Löschung oder Erfolgsmeldung ohne echte Änderung |

**Verifikationsgrenze:** Die bereits in einem anderen Workstream berichteten Storage-Tests ersetzen keine hier durchgeführte Prüfung dieser konkret abgegrenzten Home-List-Fälle. Kein Test/Database-Reset/Browser-Run/CI wurde für diesen Architekturbeleg ausgeführt.
