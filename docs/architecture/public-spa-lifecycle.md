# Öffentliche SPA-Dokumentnavigation und Instanzlebenszyklus

**Code-Referenz:** `dev` bei `7c332b32762029b7e9da83491f52b12860bef9ed`, durch echten Merge nach `docs` übernommen. Dieser Ablauf wurde **aus den aktuellen JS-/Blade-/Middleware-Quellen abgeleitet**, aber nicht im Browser oder mit Laravel-End-to-End ausgeführt.

## Architekturentscheidung

Die öffentliche Website bleibt **serverseitig mit Laravel/Blade gerendert**, benutzt für interne Wechsel nun aber **Livewire 4 `wire:navigate` als SPA-Transport**. Das ist kein eigenes JavaScript-Routing, kein JSON-API-Frontend und kein Umbau der serverseitigen Domain-/Publication-Schicht. Die bestehenden Public-Routen und der `UseCommittedPublicState`-GET/HEAD-Kontext bleiben maßgeblich. [`public-spa.js`](../../resources/js/public-spa.js) entscheidet nur, welche bereits gerenderten Links für Livewire-Navigation markiert werden; die Dokument-Swap-Implementierung gehört Livewire.

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
        Filter-->>User: wire:navigate markiert
        User->>LW: Klick
        LW->>PHP: neue Dokument-GET-Anfrage
        PHP->>DB: CommittedRead für berechtigten Public-GET
        DB-->>PHP: veröffentlichte Daten
        PHP-->>LW: neues Blade-HTML + Head-Metadaten
        LW-->>Boot: livewire:navigating mit onSwap-Hook
        Boot->>Folio: advancePublicFolio (persistierter Node, Reduced-Motion-Guard)
        Boot->>Boot: alte Disposer vor Swap ausführen
        Boot->>Boot: applyPublicLayoutFromMeta am Swap
        LW-->>Boot: livewire:navigated
        Boot->>Boot: vorherige Instanzen/Listener entsorgen
        Boot->>Boot: Viewer, Hero, Journal, Tracking, Navigation initialisieren
        Boot->>Filter: neue öffentliche Links markieren
        Boot->>Track: eine PageView pro Initialisierung
    end
```

Der `onSwap`-Callback wird vom `livewire:navigating`-Ereignis installiert und liest [`meta[data-public-layout]`](../../resources/views/layouts/app.blade.php) für die CSS-Variablen `--public-page`, `--public-shell-max`, `--public-art-max` und `--public-content-padding`. Der aktuell gelieferte CSP-Nonce macht serverseitige neue Inline-Styles nicht zur globalen Quelle; der Browser setzt die **serverautorisierten Werte** über CSSOM.

## Welche Links teilnehmen

[`isPublicSpaDestination()`](../../resources/js/public-spa.js) verlangt HTTP(S), denselben Origin, keinen fremden `target` und keinen Download. Die ausgeschlossenen Pfadpräfixe sind `/admin`, `/api`, `/pulse`, `/livewire`, `/logout`, `/media`, `/storage` und `/vendor`. Zusätzlich bleiben Links mit `data-navigation-parent-link` bewusst bei ihrer bestehenden Menüebenen-Interaktion und werden **nicht** als gewöhnliche `wire:navigate`-Links markiert. `rel=external` und `data-public-full-load` verhindern die `wire:navigate`-Markierung ebenfalls. Ein Fragmentlink zur **gleichen Pfad-/Query-Kombination** behält normales Browserscrollen. Diese Liste ist eine konkrete JS-Routinggrenze, **nicht** das vollständige Laravel-Sicherheits-/Autorisierungsmodell; die Route schützt weiterhin Preview/Media.

## Lebensdauer, Disposer und Seitenwechsel

| Initialisierung in [`app.js`](../../resources/js/app.js) | Scope | Bei neuem `#content` |
| --- | --- | --- |
| [Artwork Viewer](../../resources/js/artwork-viewer.js) | Aus der aktuellen Body-Komposition | Zurückgegebener Disposer; AbortController beendet globale Listener |
| [Home Hero](../../resources/js/home-hero.js) | DOM der aktuellen Seite | Keine globale Disposal-Rückgabe vom Bootstrap verwendet; konkrete Event-Owner im Modul |
| [Journal/Exhibition](../../resources/js/journal-presentation.js) | Journal-Slideshow und Map | Zurückgegebener Disposer, insbesondere Karteninstanzen |
| [Matomo](../../resources/js/matomo.js) | Tracker-Script global, deklarative Browser-Events je Seite | Alte Click/Observer-Liste entsorgen; Script nur einmal injizieren, `setCustomUrl`, `setDocumentTitle`, `trackPageView` pro Aufruf |
| [Public Navigation](../../resources/js/public-navigation.js) | Haupt- und Child-Rails, Window-State | Listener am Body entfernen und alle Window-/Resize-Observer entsorgen |
| [Public SPA Link Marker](../../resources/js/public-spa.js) | Gerade vorhandene Links | Zugelassene Links mit `wire:navigate` markieren; kein eigener Router/Disposer |

`initializePublicApplication()` speichert die letzte `#content`-Elementidentität. Ist sie unverändert, bricht der Bootstrap **ohne erneute Initialisierung oder zusätzliche PageView** ab. Beim aktuellen `dev`-Stand werden vorhandene Disposer **bereits bei `livewire:navigating` vor dem Body-Swap** aufgerufen und die `currentContent`-Identität gelöscht. Beim folgenden `livewire:navigated` entsorgt die Init-Routine verbleibende Disposer defensiv, setzt die neue Identity und baut die Instanzen auf. Das verhindert, dass Modal-/Map-/Tracking-Observer am abgelösten Body weiterlaufen. Ein erstmaliger Besuch erfolgt bei `DOMContentLoaded` bzw. sofort, wenn das Dokument bereits bereit ist; spätere Navigationswechsel nutzen `livewire:navigated`. Diese Regeln sind **Codeverhalten**, keine Zusage bereits bestandener Browser-Zustandsprüfung.

## Persistiertes Folio und CSS-Animation beim Navigieren

Der aktuelle Public-Transport verwendet seit dem Merge des `dev`-Standes
`a85930b4fedbb1416463e22a4e515f751a430112` eine **persistierte,
rein dekorative Folio-Kontur**, getrennt vom ersetzten Seiteninhalt:

- [`layouts/app.blade.php`](../../resources/views/layouts/app.blade.php)
  definiert innerhalb von `#content` eine nichtinteraktive,
  `aria-hidden` `.public-folio-decoration` in
  `@persist('public-folio-decoration')`; die tatsächliche Entscheidung
  über das Beibehalten des Nodes trifft Livewire.
- [`app.js`](../../resources/js/app.js) ruft synchron beim
  `livewire:navigating`-Ereignis **vor** dem Dispose der alten
  Viewer-/Map-/Navigation-Instanzen `advancePublicFolio()` auf.
  Dieser Aufruf gehört **nicht** zur späteren
  `initializePublicApplication()`-Reinitialisierung. Der
  `onSwap`-Handler übernimmt weiterhin die Public-Layout-Metawerte.
- [`public-folio-motion.js`](../../resources/js/public-folio-motion.js)
  verwaltet genau **zehn** vordefinierte Konturen mit je vier
  `rem`-Abständen (oben/unten auf linker und rechter Seite). Die
  Auswahl `chooseNextFolioVariant()` schließt die gerade gewählte
  Variante aus; eine neue Navigation soll also nicht zufällig denselben
  Zielzustand wählen. Die aktuelle Varianten-ID lebt im JS-Modul, die
  bereits animierte **Zwischengeometrie** im persistierten DOM/CSSOM.
- Bei fehlender `.public-folio-decoration` oder
  `prefers-reduced-motion: reduce` erfolgen **keine** dekorativen
  CSSOM-Änderungen. Es gibt **keine** Polling-Timer, Frame-Loops,
  `setTimeout`-Kette oder zweite Navigation.
- [`public-presentation.css`](../../resources/css/public-presentation.css)
  registriert vier `@property`-Werte als CSS-`<length>` und animiert
  diese auf demselben Element mit **780 ms**. Die beiden Pseudoelemente
  zeichnen die linke und rechte Kontur; das absolute, nichtinteraktive
  Folio verändert die eigentlichen Content-Boxabmessungen nicht.
  Wiederholte Navigationen retargeten das laufende CSS-Transition-Ziel,
  anstatt die gestrichene Kontur durch erneutes DOM-Mounten neu zu starten.

Die lokale Testquelle
[`tests/js/public-folio-motion.test.mjs`](../../tests/js/public-folio-motion.test.mjs)
enthält Fälle für zehn erlaubte Ziele, Ausschluss der alten Variante und
positive Vierer-Abstände, wurde hier aber **nur gelesen und nicht ausgeführt**.
Der [Public-Implementation-Contract](../PUBLIC-IMPLEMENTATION-CONTRACT.md)
enthält die zugehörige visuelle Absicht, einschließlich
`wire:transition.navigate` auf `#content`. Die derzeitige
Folio-/Crossfade-Darstellung war bei diesem Quellenstand **noch nicht als
vom Nutzer browserabgenommen bestätigt**. Offen sind echte
Livewire-`@persist`-Übernahme, Bewegung bei schnellen Folgewechseln,
History/Back-Forward, Browserunterstützung für typed CSS Properties und
Reduced-Motion-Verhalten in der laufenden Oberfläche.

## Fachliche und technische Grenzen

- Öffentliche GET/HEAD-Anfragen bleiben an `UseCommittedPublicState` und Publication-Media-Policy gebunden; SPA-Navigation darf keine Entwurfsdaten öffentlich ausliefern. Nicht-GET-Formulare wie Contact folgen ihrem normalen HTTP-Schutz.
- Die bestehende zyklische ganze-Item-Navigation behält ihren eigenen `sessionStorage`-Handoff; der Document-Swap soll ihn nicht durch Pixel-Scrolling ersetzen.
- Der Admin benutzt ebenfalls Filament/Livewire-SPA, aber mit eigener Auth-/Lifecycle-Policy. Aus dem Public-SPA-Skript ist **keine** Admin-Route ableitbar.
- **Zu überprüfen:** tatsächliche Livewire-Head-/Nonce-Updates, Scroll-/Focus-Restoration, Back/Forward, Wechsel zwischen Parent/Child-Menüs, erneute offene Viewer/Map-Instanzen, Matomo pro echtem Seitenwechsel und Escape/Hydration-Verhalten. Ein Quell-Diagramm allein ist kein Browsertest.
