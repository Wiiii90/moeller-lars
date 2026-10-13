# HTTP-, Filament- und Browser-Einstiegspunkte

**Quellen:** [`routes/web.php`](../../routes/web.php), [`bootstrap/app.php`](../../bootstrap/app.php), [`AdminPanelProvider`](../../app/Providers/Filament/AdminPanelProvider.php), [`AppServiceProvider`](../../app/Providers/AppServiceProvider.php), [`resources/js/app.js`](../../resources/js/app.js). Der [Routenindex](runtime-entrypoints.json) enthält **alle 19 ausdrücklich in `routes/web.php` deklarierten GET-/POST-Routen**, mit HTTP-Methode, aufgelöstem Preview-Präfix, Controller/Action und Quellzeile. **Kein** Ersatz für eine zur Laufzeit registrierte `artisan route:list`-Ausgabe; Filament/Framework registrieren weitere Routen.

## Request-Pipeline und Working/LIVE

```mermaid
sequenceDiagram
    participant Browser
    participant Middleware as UseCommittedPublicState
    participant Context as CommittedRead
    participant Web as Web Middleware + Controller
    participant Grammar as PublicationPostgresGrammar
    participant DB as PostgreSQL

    Browser->>Middleware: HTTP Request
    alt GET/HEAD und nicht admin, preview, livewire oder up
        Middleware->>Context: run(next)
        Context->>DB: Transaktion; REPEATABLE READ
        Context->>Web: Request weitergeben
        opt Unqualifizierte getrackte Eloquent-Tabelle
            Web->>Grammar: Query
            Grammar->>DB: committed.<table>
        end
    else Ausgenommener Pfad oder andere HTTP-Methode
        Middleware->>Web: Ohne CommittedRead
    end
    Web-->>Browser: Response
```

`bootstrap/app.php` setzt `UseCommittedPublicState` global an den Anfang, `RecordOperationalMetrics` ans Ende sowie `SecurityHeaders` und `AttachPublicationState` in den Web-Stack. Der Committed-Read-Kontext betrifft GET/HEAD außerhalb `admin*`, `preview*`, `livewire*` und `up`. `PublicationPostgresGrammar` qualifiziert darin nur getrackte Tabellen, **keine globale PostgreSQL-`search_path`-Umschaltung**.

## Explizite Routengruppen

| Gruppe | Routen | Zugriffsweg |
| --- | ---: | --- |
| Public Basis: Home, Contact POST, Sitemap, Robots | 4 | Contact POST mit `EnforceHomePresentationGate` und `throttle:5,1` |
| `preview/*` | 6 | `ProtectArtistPreview` verlangt Admin, aktiviert `SitePreviewContext`, setzt Noindex und No-Store |
| `admin/media-preview/*`, `admin/media-download/*` | 4 | `AdminMediaController::authorizeAdmin()` prüft den Admin im Controller |
| Public Media `/media/original/*`, `/media/variant/*` | 2 | `PublicMediaController` erzwingt `PublicMedia`-Eligibility; Variant-Middleware prüft Medien-Datei |
| Public Content: Artwork, Journal-Entry, SiteSection | 3 | `EnforceHomePresentationGate` plus Slug-Constraints |

Eine Datei unter Storage hat deshalb **zwei getrennte Zugriffspolicies**: Admin-Media-Zugriff verlangt den authentifizierten Admin; Public-Media-Zugriff verlangt die explizite öffentliche Asset-/Variant-Freigabe. Beide Controller prüfen zusätzlich physische Verfügbarkeit.

## Filament, scoped Events und Publication-Response

`AdminPanelProvider::panel()` registriert Panel-ID/-Pfad `admin`, SPA, `web`-Guard, Resource-/Page-Discovery, Authentication inklusive `DeferMatomoReporting` und die eigene Navigation (Dashboard, General, Pages, Storage, Activity, Analytics, Preview, Commit, View Site). Aus dem Provider allein ist **nicht** die tatsächliche URL jeder entdeckten Filament-Action ableitbar.

`AppServiceProvider::register()` bindet `PublicationReadContext`, `AdminMutationSnapshotBuffer` und `AdminUndoContext` scoped, und installiert die spezielle PostgreSQL-Query-Grammar. In `boot()` koppelt er die Eloquent-Ereignisse `updating/updated/created/deleting/deleted` an den Snapshot-Buffer. Diese automatische Vorher-/Nachher-Erfassung ist **kein automatisches Audit-Event und kein Notification-Versand**.

[`AttachPublicationState`](../../app/Http/Middleware/AttachPublicationState.php) sendet `X-Publication-Pending` nur bei `X-Livewire`-Requests, wenn der Snapshot-Buffer eine möglicherweise geänderte Publication-Lage signalisiert; dann wird `PublicationService::hasPendingChanges()` abgefragt. Ein Header auf jeder HTTP-Antwort wäre eine falsche Architekturannahme.

## Public-JavaScript

`layouts/app.blade.php` bindet über Vite `resources/js/app.js` sowie Public-CSS-Entries ein. `app.js` initialisiert beim `DOMContentLoaded` oder direkt bei bereits bereitem Dokument: `artwork-viewer.js`, `home-hero.js`, `journal-presentation.js`, `matomo.js` und `public-navigation.js`. Dies sind belegte Import-/Initialisierungsbeziehungen, **noch keine** dynamisch verifizierten Browserzustände.

**Offen:** tatsächlich registrierte Framework-/Plugin-Routen, dynamische Livewire-Actions, Package-Middleware, Controller-Verzweigungen, UI-Events und Runtime-Checks in einer sicheren isolierten Umgebung. Keine CI- oder Preview-DB-Interaktion.
