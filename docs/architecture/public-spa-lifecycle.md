# Öffentliche SPA-Dokumentnavigation und Instanzlebenszyklus

**Code-Referenz:** `dev` bei `8fb60db6a1a427ce6f905e5d8a65454c7d39ec45`, nach `docs` übernommen. Dieser Ablauf wurde **aus den aktuellen JS-/Blade-/Middleware-Quellen abgeleitet**, aber nicht im Browser oder mit Laravel-End-to-End ausgeführt.

## Architekturentscheidung

Die öffentliche Website bleibt **serverseitig mit Laravel/Blade gerendert**, benutzt für interne Wechsel nun aber **Livewire 4 Document Navigation**. Das ist kein eigenes JavaScript-Routing, kein JSON-API-Frontend und kein Umbau der serverseitigen Domain-/Publication-Schicht. Die bestehenden Public-Routen und der `UseCommittedPublicState`-GET/HEAD-Kontext bleiben maßgeblich. [`public-spa.js`](../../resources/js/public-spa.js) entscheidet für einen einzigen dokumentweiten Click-Listener, welche bisher nicht mit `wire:navigate` versehenen Links via `Livewire.navigate()` delegiert werden; die Dokument-Swap-Implementierung gehört Livewire.

## Request- und Browsersequenz

```mermaid
sequenceDiagram
    participant User as Browser / Link
    participant Filter as public-spa.js
    participant Folio as public-folio-motion.js
    participant LW as Livewire 4 Navigate
    participant PHP as Laravel Public Route
    participant DB as CommittedRead / PostgreSQL
    participant Boot as app.js
    participant Track as Matomo

    User->>Filter: Link im neuen DOM
    Filter->>Filter: isPublicSpaDestination?
    alt Extern, Download, geschützter/systemischer Pfad oder gleicher Anker
        Filter-->>User: normale Browsernavigation
    else erlaubter öffentlicher Link
        Filter-->>LW: Livewire.navigate(href), falls API bereit
        LW->>PHP: neue Dokument-GET-Anfrage
        PHP->>DB: CommittedRead für berechtigten Public-GET
        DB-->>PHP: veröffentlichte Daten
        PHP-->>LW: neues Blade-HTML + Head-Metadaten
        LW-->>Boot: livewire:navigating mit onSwap-Hook
        Boot->>Folio: capturePublicFolio am alten DOM
        Boot->>Boot: alte Disposer vor Swap ausführen
        Boot->>Boot: applyPublicLayoutFromMeta am Swap
        LW-->>Boot: livewire:navigated
        Boot->>Boot: Viewer, Hero, Journal, Tracking, Navigation initialisieren
        Boot->>Folio: advancePublicFolio am neuen DOM, nur bei echtem Besuch
        Boot->>Track: eine PageView pro Initialisierung
    end
```

Der `onSwap`-Callback wird vom `livewire:navigating`-Ereignis installiert und liest [`meta[data-public-layout]`](../../resources/views/layouts/app.blade.php) für die CSS-Variablen `--public-page`, `--public-shell-max`, `--public-art-max` und `--public-content-padding`. Der aktuell gelieferte CSP-Nonce macht serverseitige neue Inline-Styles nicht zur globalen Quelle; der Browser setzt die **serverautorisierten Werte** über CSSOM.

## CSP-sicheres Livewire-Asset und Framework-Grenze

Die Blade-Shell bindet Livewire 4 mit `@livewireScripts(['nonce' => $publicStyleNonce, 'url' => route('public.livewire.csp')])` an [die benannte Route](../../routes/web.php) `GET /site-assets/livewire-csp.js`. Das Route-Closure setzt `config('livewire.csp_safe')` **nur vorübergehend** auf `true`, erzeugt über `FrontendAssets::returnJavaScriptAsFile()` das öffentliche Script und stellt den vorherigen Wert in `finally` wieder her. Dies ist **keine globale Änderung** des Admin-/Filament-Livewire-Bundles; die Public-CSP braucht kein `unsafe-eval`. `public-spa.js` schließt das Asset-Präfix explizit von der SPA-Navigation aus.

Dieser Mechanismus ist aus [`routes/web.php`](../../routes/web.php), [`layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) und [`public-spa.js`](../../resources/js/public-spa.js) geprüft. **Nicht ausgeführt:** ausgelieferte Script-Response, Content-Type/Caching, CSP-Header im Browser, temporäre Konfiguration bei parallelen Long-Lived-Worker-Anfragen oder eine echte Livewire-Navigation.

## Welche Links teilnehmen

[`isPublicSpaDestination()`](../../resources/js/public-spa.js) verlangt HTTP(S), denselben Origin, keinen fremden `target` und keinen Download. Die ausgeschlossenen Pfadpräfixe sind `/admin`, `/api`, `/pulse`, `/livewire`, `/logout`, `/media`, `/storage`, `/vendor` und `/site-assets`. Statische Medien-/Dokument-Endungen und bestimmte Preview-Systempfade werden ebenfalls ausgeschlossen. Zusätzlich bleiben Links mit `data-navigation-parent-link` bewusst bei ihrer bestehenden Menüebenen-Interaktion und werden **nicht** als gewöhnliche `wire:navigate`-Links markiert. `rel=external` und `data-public-full-load` verhindern den Delegationsaufruf ebenfalls. Vorhandene `wire:navigate`-Links lässt der Click-Listener Livewire selbst behandeln; bei fehlender `window.Livewire.navigate`-API bleibt normales Browser-Navigieren erhalten. Modifier-Klicks, bereits behandelte Events und andere Maustasten werden nicht abgefangen. Ein Fragmentlink zur **gleichen Pfad-/Query-Kombination** behält normales Browserscrollen. Diese Liste ist eine konkrete JS-Routinggrenze, **nicht** das vollständige Laravel-Sicherheits-/Autorisierungsmodell; die Route schützt weiterhin Preview/Media.

## Lebensdauer, Disposer und Seitenwechsel

| Initialisierung in [`app.js`](../../resources/js/app.js) | Scope | Bei neuem `#content` |
| --- | --- | --- |
| [Artwork Viewer](../../resources/js/artwork-viewer.js) | Aus der aktuellen Body-Komposition | Zurückgegebener Disposer; AbortController beendet globale Listener |
| [Home Hero](../../resources/js/home-hero.js) | DOM der aktuellen Seite | Keine globale Disposal-Rückgabe vom Bootstrap verwendet; konkrete Event-Owner im Modul |
| [Journal/Exhibition](../../resources/js/journal-presentation.js) | Journal-Slideshow und Map | Zurückgegebener Disposer, insbesondere Karteninstanzen |
| [Matomo](../../resources/js/matomo.js) | Tracker-Script global, deklarative Browser-Events je Seite | Alte Click/Observer-Liste entsorgen; Script nur einmal injizieren, `setCustomUrl`, `setDocumentTitle`, `trackPageView` pro Aufruf |
| [Public Navigation](../../resources/js/public-navigation.js) | Haupt- und Child-Rails, Window-State | Listener am Body entfernen und alle Window-/Resize-Observer entsorgen |
| [Public SPA Click Delegation](../../resources/js/public-spa.js) | Ein globaler Listener, beim Modulladen registriert | Zulässige bisher unmarkierte Links per `Livewire.navigate()`; ohne API nativer Fallback; kein per Seite neu registrierter Listener |

`initializePage()` speichert die letzte `#content`-Elementidentität. Ist sie unverändert, bricht der Bootstrap **ohne erneute Initialisierung oder zusätzliche PageView** ab. Beim aktuellen `dev`-Stand werden vorhandene Disposer **bereits bei `livewire:navigating` vor dem Body-Swap** aufgerufen und die `currentContent`-Identität gelöscht. Beim folgenden `livewire:navigated` entsorgt die Init-Routine verbleibende Disposer defensiv, setzt die neue Identity und baut die Instanzen auf. Das verhindert, dass Modal-/Map-/Tracking-Observer am abgelösten Body weiterlaufen. Ein erstmaliger Besuch erfolgt bei `DOMContentLoaded` bzw. sofort, wenn das Dokument bereits bereit ist; spätere Navigationswechsel nutzen `livewire:navigated`. Diese Regeln sind **Codeverhalten**, keine Zusage bereits bestandener Browser-Zustandsprüfung.

## Neuer DOM-Knoten, übertragene Folio-Geometrie und WAAPI-Animation

Der Folio-Lebenszyklus in [`public-folio-motion.js`](../../resources/js/public-folio-motion.js) beim Quellstand `8fb60db6a1a427ce6f905e5d8a65454c7d39ec45` **verwendet keinen persistierten Knoten**. [`layouts/app.blade.php`](../../resources/views/layouts/app.blade.php) rendert **in jedem neuen `#content`** eine normale `.public-folio-decoration` mit zwei absoluten, nichtinteraktiven Kindknoten `__left` und `__right`. Das ursprüngliche, vollständige asymmetrische Atelier-Konturpaar existiert weiterhin ohne JavaScript über `#content::before/::after` als statischer Fallback.

Die genaue Reihenfolge aus [`app.js`](../../resources/js/app.js) ist:

1. Beim ersten Dokument wird `initializePage()` ausgeführt. `advancePublicFolio()` läuft **nicht** auf Initialladung.
2. `livewire:navigate` setzt `pendingVisit=true`.
3. Bei `livewire:navigating` erfasst `capturePublicFolio()` die **berechneten** Positionen (links/rechts `top`, `bottom`, `width`) der alten Kontur, **sofern** sie bereits `data-folio-active` besitzt; danach laufen die Page-Disposer, und `onSwap(applyPublicLayoutFromMeta)` wird registriert.
4. Nach dem Dokument-Swap startet `livewire:navigated` die normalen Page-Instanzen für das neue `#content`. Nur bei `pendingVisit` aktiviert `advancePublicFolio()` am **neuen** DOM-Knoten die nächste der zehn kuratierten Varianten. Ihre sechs Werte beschreiben vier vertikale Randpositionen und zwei Return-Stroke-Breiten; die unmittelbar vorige Variante wird ausgeschlossen. Ein vorhandener Snapshot wird als Startgeometrie verwendet, andernfalls die gespeicherte Variante.
5. Für beide Seiten werden die Zielwerte **zuerst als normale Style-Eigenschaften** gesetzt. Wenn `Element.animate()` verfügbar ist, interpoliert die native Web Animations API von der erfassten Startgeometrie zum Ziel über **920 ms**. Bei fehlender WAAPI oder einem Animationsfehler bleiben die Zielwerte statisch gültig. Erst danach aktiviert `data-folio-active` die neue Kontur und unterdrückt via `:has()` die statischen Pseudoelemente.

### Folio motion and local review mode

`public-folio-motion.js` erlaubt ausschließlich auf Loopback-Hosts (`localhost`, `127.0.0.1`, `::1`) den expliziten Diagnoseparameter `?folio-motion=debug`. Er setzt einen tab-lokalen `sessionStorage`-Wert, der bei internen SPA-Wechseln erhalten bleibt; `?folio-motion=off` beendet ihn. Im Modus `debug` verwendet das Modul eine deterministische Variantensequenz, markiert den Body mit `data-public-folio-debug` und interpoliert die sichtbaren Konturen **1800ms** statt normal **920ms**. Nur diese lokale Opt-in-Vorschau übersteuert reduzierte Bewegung. Der Debugmodus ist kein zweiter Router oder Persistenzpfad und auf fremden Hosts nicht aktivierbar.

**Beleggrenze:** JS-Modul und zugehörige Testquellen beim `dev`-Stand `8fb60db6` statisch geprüft; kein Browser- oder Testlauf in diesem Dokumentationsdurchgang. Der überzeichnete Debugmodus ist keine Produktionsabnahme.

`prefers-reduced-motion: reduce` und fehlende Konturelemente verhindern die dekorative Aktivierung; es gibt keine App-Polling-Timer oder eigenen Frame-Loops. Die Animation ändert nicht die Content-Breite, Navigation oder Speicherung. Der Code ist **keine** `@persist`-DOM-Übernahme und **keine** CSS-`@property`-Transition auf dem alten Element.

Die [JS-Testquelle](../../tests/js/public-folio-motion.test.mjs) deckt Variantenlogik und Geometrie in isolierten Fällen ab; sie wurde hier **gelesen, nicht ausgeführt**. Offen bleiben der reale Livewire-Swap, WAAPI-Interpolation bei schnellen Navigationen, `:has()`-Fallback, History/Back-Forward, Reduced-Motion und die ausdrückliche Browser-Abnahme der experimentellen Varianten. Der [Public-Vertrag](../PUBLIC-IMPLEMENTATION-CONTRACT.md) beschreibt die beabsichtigte Folio-Darstellung, nicht ihren bereits nachgewiesenen Abnahmestatus.

## Fachliche und technische Grenzen

- Öffentliche GET/HEAD-Anfragen bleiben an `UseCommittedPublicState` und Publication-Media-Policy gebunden; SPA-Navigation darf keine Entwurfsdaten öffentlich ausliefern. Nicht-GET-Formulare wie Contact folgen ihrem normalen HTTP-Schutz.
- Die bestehende zyklische ganze-Item-Navigation behält ihren eigenen `sessionStorage`-Handoff; der Document-Swap soll ihn nicht durch Pixel-Scrolling ersetzen.
- Der Admin benutzt ebenfalls Filament/Livewire-SPA, aber mit eigener Auth-/Lifecycle-Policy. Aus dem Public-SPA-Skript ist **keine** Admin-Route ableitbar.
- **Zu überprüfen:** tatsächliche Livewire-Head-/Nonce-Updates, Scroll-/Focus-Restoration, Back/Forward, Wechsel zwischen Parent/Child-Menüs, erneute offene Viewer/Map-Instanzen, Matomo pro echtem Seitenwechsel und Escape/Hydration-Verhalten. Ein Quell-Diagramm allein ist kein Browsertest.
