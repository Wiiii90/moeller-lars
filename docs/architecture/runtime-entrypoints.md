# HTTP-, Filament- und Browser-Einstiegspunkte

**Quellen:** [`routes/web.php`](../../routes/web.php), [`bootstrap/app.php`](../../bootstrap/app.php), [`AdminPanelProvider`](../../app/Providers/Filament/AdminPanelProvider.php), [`AppServiceProvider`](../../app/Providers/AppServiceProvider.php), [`resources/js/app.js`](../../resources/js/app.js). Der [Routenindex](runtime-entrypoints.json) enthält **alle 21 ausdrücklich in `routes/web.php` deklarierten GET-/POST-Routen (20 Controller-Routen und ein CSP-Asset-Closure)**, mit HTTP-Methode, aufgelöstem Preview-Präfix, Controller/Action beziehungsweise Closure und Quellzeile. **Kein** Ersatz für eine zur Laufzeit registrierte `artisan route:list`-Ausgabe; Filament/Framework registrieren weitere Routen.

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

## Maschinenlesbare Quellprüfung

Der [Routenprüfer](../../scripts/check-architecture-routes.php) vergleicht `routes/web.php` mit [`runtime-entrypoints.json`](runtime-entrypoints.json). Er verifiziert **alle expliziten** `Route::get/post`-Deklarationen in ihrer Quellreihenfolge, HTTP-Methode, (Preview-)Pfad, Zeilennummer sowie Controller/Action beziehungsweise das Public-CSP-Closure. Neue Routen, verrutschte Zeilen und abweichende Handler machen die Prüfung fehlgeschlagen:

```sh
php scripts/check-architecture-routes.php --check
```

**Parserhärtung (14.10.2026):** Der bisherige Regex-Matcher konnte auch in Kommentaren und PHP-Stringliteralen vorkommende `Route::get/post(...)`-Fragmente als angebliche Route erfassen. Der Quellprüfer nutzt nun `token_get_all()` und verlangt die tatsächlichen `Route`, `::`, `get/post`, `(`, ein single-quoted Pfadliteral und ein anschließendes Komma als PHP-Tokenfolge. Er erhält Methode, Pfad, Zeile und Handler-Position. `php scripts/check-architecture-routes.php --self-test` läuft ohne Laravel oder Route-Manifest und unterscheidet echte Routen von Kommentaren/Stringbeispielen; die gleichwertige Parserfunktion wurde unter PHP 8.4 isoliert mit gültigen Quellzeilen und Negativfällen ausgeführt. Es bleibt ein **statisches** Literalmodell, keine vollständige dynamische Router-Erhebung.

**Bereits vorher durchgeführt:** `php -l`, Positivprüfung und absichtlich fehlerhafte Zeilenangabe in einer isolierten PHP-8.4-Teststruktur. Der Routenindex wurde gegen den früheren Bestand abgeglichen. **15.10.2026:** Ein neuer unabhängiger GitHub-Quellabgleich hat alle **21** aktuellen Literal-Deklarationen einschließlich `/.well-known/tdmrep.json`, ihrer Reihenfolge und Quellzeilen in der kuratierten JSON-Datei nachgeführt. **Der native `php scripts/check-architecture-routes.php --check`-Lauf im vollständigen Laravel-Checkout bleibt offen.** Die Prüfung bootet keine Framework-Routen, kennt also weder automatisch registrierte Paket-Routen noch zur Laufzeit hinzugefügte Middleware.

## Explizite Routengruppen

| Gruppe | Routen | Zugriffsweg |
| --- | ---: | --- |
| Public Basis: Home, Contact POST, Sitemap, Robots, TDM-Reservation | 5 | Contact POST mit `EnforceHomePresentationGate` und `throttle:5,1`; `/.well-known/tdmrep.json` liefert die Site-weite TDM-Reservation ohne Login |
| CSP-sicheres Public-Livewire-Script `/site-assets/livewire-csp.js` | 1 | Benanntes Route-Closure, temporär `livewire.csp_safe=true`, `FrontendAssets::returnJavaScriptAsFile()`; per `finally` zurückgesetzt |
| `preview/*` | 6 | `ProtectArtistPreview` verlangt Admin, aktiviert `SitePreviewContext`, setzt Noindex und No-Store |
| `admin/media-preview/*`, `admin/media-download/*` | 4 | `AdminMediaController::authorizeAdmin()` prüft den Admin im Controller |
| Public Media `/media/original/*`, `/media/variant/*` | 2 | `PublicMediaController` erzwingt `PublicMedia`-Eligibility; Variant-Middleware prüft Medien-Datei |
| Public Content: Artwork, Journal-Entry, SiteSection | 3 | `EnforceHomePresentationGate` plus Slug-Constraints |

Eine Datei unter Storage hat deshalb **zwei getrennte Zugriffspolicies**: Admin-Media-Zugriff verlangt den authentifizierten Admin; Public-Media-Zugriff verlangt die explizite öffentliche Asset-/Variant-Freigabe. Beide Controller prüfen zusätzlich physische Verfügbarkeit.

## Public-SEO-Discovery und dynamische Grenzen (Stand `dev@cbc3f5ad`)

Die neue [`/.well-known/tdmrep.json`-Deklaration](../../routes/web.php) führt nach [`PublicSeoController::tdmReservation()`](../../app/Http/Controllers/PublicSeoController.php) und liefert eine Site-weite JSON-Reservation für `location: /` und `tdm-reservation: 1`. Sie steht vor dem generischen `/{section}`-Handler. [`SecurityHeaders`](../../app/Http/Middleware/SecurityHeaders.php) setzt daneben einen `tdm-reservation: 1`-HTTP-Header auf normalen Nicht-Preview-Public-Responses. Solche Signale sind **weder** eine Zugangssperre **noch** eine Laufzeitverifikation der tatsächlichen Auslieferung durch den Ingress. Der fachliche [Security-Audit](public-security-privacy-boundaries.md#3-rechte-signale-sind-keine-zugangssperren) besitzt diese Grenze.

**Quellbasierte Negativrouten:** `/{section}/{slug}` und `/{section}` sind durch die explizite Slug-RegEx `[a-z0-9]+(?:-[a-z0-9]+)*` eingeschränkt. Sie erlauben keine allgemeine mehrstufige Fallback-Route. `preview/*` liegt hinter `ProtectArtistPreview`; `admin/media-preview/*` und `admin/media-download/*` prüfen separat den Admin im Controller. [`UseCommittedPublicState`](../../app/Http/Middleware/UseCommittedPublicState.php) aktiviert LIVE-Read nur für GET/HEAD außerhalb der ausgeschlossenen Admin-/Preview-/Livewire-/Health-Pfade. POST `/contact` gehört nicht zu diesem automatischen GET/HEAD-Read, sondern hat eigene Gate-/Rate-/Controller-Guards. Daraus lassen sich **keine** Laufzeit-Statuscodes oder tatsächlich entdeckte Filament-URLs ableiten.

**Spätere reproduzierbare Abnahme:** `check-architecture-routes.php --check` für Literalmanifest, danach `artisan route:list` mit dynamischer Filament-/Livewire-Registration sowie getrennte HTTP-GET/HEAD-/Auth-/CSP-Checks für `/.well-known/tdmrep.json`, `/preview/{section}`, `/media/*` und `/{section}/{slug}`. Nur in einem sicher isolierten vollständigen Checkout, ohne Preview-/Produktions-DB.

## Filament, scoped Events und Publication-Response

`AdminPanelProvider::panel()` registriert Panel-ID/-Pfad `admin`, SPA, `web`-Guard, Resource-/Page-Discovery, Authentication inklusive `DeferMatomoReporting` und die eigene Navigation (Dashboard, General, Pages, Storage, Activity, Analytics, Preview, Commit, View Site). Aus dem Provider allein ist **nicht** die tatsächliche URL jeder entdeckten Filament-Action ableitbar.

`AppServiceProvider::register()` bindet `PublicationReadContext`, `AdminMutationSnapshotBuffer` und `AdminUndoContext` scoped, und installiert die spezielle PostgreSQL-Query-Grammar. In `boot()` koppelt er die Eloquent-Ereignisse `updating/updated/created/deleting/deleted` an den Snapshot-Buffer. Diese automatische Vorher-/Nachher-Erfassung ist **kein automatisches Audit-Event und kein Notification-Versand**.

[`AttachPublicationState`](../../app/Http/Middleware/AttachPublicationState.php) sendet `X-Publication-Pending` nur bei `X-Livewire`-Requests, wenn der Snapshot-Buffer eine möglicherweise geänderte Publication-Lage signalisiert; dann wird `PublicationService::hasPendingChanges()` abgefragt. Ein Header auf jeder HTTP-Antwort wäre eine falsche Architekturannahme.

Die genaue **Admin-Commit-UI-Sequenz** (Alpine-Event, Livewire-Methodenaufruf, optionaler Header, Notification) ist jetzt in [Publication](publication.md#admin-commit-navigation-livewire-und-notification) dokumentiert.

## Public-JavaScript und Livewire Document Navigation

Die öffentliche Anwendung verwendet beim Quellenstand `18b0a5943aec407fa1bc57e557a2aadcdbf395da` **Livewire 4 Document Navigation** als dokumentbasierten SPA-Transport über unveränderte serverseitige Blade-/Laravel-Public-Routen. [`layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) bindet Vite, Livewire-Styles und das öffentliche CSP-sichere Livewire-Script via `route('public.livewire.csp')` mit Nonce ein; [`resources/js/app.js`](../../resources/js/app.js) startet Artwork-Viewer, Home-Hero, Journal, Matomo und Navigation und delegiert eligible Content-Links durch einen einmal installierten Click-Handler via `Livewire.navigate()` und die CSS-Metadatenübernahme an `public-spa.js`. Im Gegensatz zum früheren **einmaligen `DOMContentLoaded`-Bootstrap** werden diese Seitenteile bei `livewire:navigated` nach einem echten `#content`-Wechsel erneut aufgebaut, die vorherigen globalen Listener/Observer dabei entsorgt.

Der [vollständige belegte Request-/JS-Lebenszyklus](public-spa-lifecycle.md) unterscheidet die Link-Ausschlüsse, serverseitige LIVE-GET-Reads, `livewire:navigating`/`onSwap`, CSP-Layoutmetadaten, Matomo-PageViews und die Disposer der einzelnen JS-Module.

**Offen:** tatsächliche Laravel-/Package-Route-Registrierung, interaktive SPA-/Browser-/Back-Forward-Läufe, Nonce-Rotation, Scroll-/Focus-Verhalten, dynamische Filament-/Livewire-Actions und eine sichere integrierte Laufzeitumgebung. Kein CI- oder Preview-DB-Zugriff.
