# Öffentliche SPA-Dokumentnavigation und Instanzlebenszyklus

**Code-Referenz:** `dev@298bf74bce837385b8a7261b145d2b9fd887ed7c` (einheitlicher `.public-content-reveal`-Seitenhöhenbesitzer, native Paper-Impuls- und Ambient-Lebenszyklen). Alle hier beschriebenen Zeitwerte/Guards aus den aktuell gemergten JS-/Blade-/CSS-Quellen; kein Browser-/Laravel-End-to-End-Lauf.

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
        Boot->>Folio: advancePublicFolio am neuen DOM (auch Initial-Load)
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

## Journal-Slideshow, Exhibition-Map, Home-Hero und Viewer: Instanzgrenzen

Die breite SPA-Tabelle oben beschreibt nur die Besitzer. Die folgenden konkreten Quellpfade zeigen, wie ihre Unterinstanzen auf einem neuen Server-Dokument erneut entstehen und was bei Abbruch oder fehlender API geschieht.

| UI-Einheit | Initialisierung und positiver Pfad | Negativ-/Cleanup-Pfad |
| --- | --- | --- |
| [Journal-Slideshow](../../resources/js/journal-presentation.js) | Blade-`journal-gallery` setzt `data-journal-slideshow` und für jedes Medium `data-journal-slide`; bei mindestens zwei Folien schaltet JS nur `hidden`, `aria-hidden` und den `n / total`-Text um. Prev/Next ist zyklisch, ohne autoplay, Timer oder Fetch | Bei null/einer Folie keine Navigation-Listener; Click-Handler hängen ausschließlich an den seitenlokalen Schaltflächen und verschwinden beim Content-Swap mit deren DOM. Kein globaler Slider-Disposer nötig |
| [Exhibition-Map](../../resources/js/journal-presentation.js) | Nur bei explizitem Öffnen des `HTMLDialogElement` werden die lokale, versionsgebundene Leaflet-CSS/-JS und anschließend OpenStreetMap-Tiles nachgeladen. Bei vorhandener Map wird lediglich das sichtbare Canvas neu vermessen | Vor dem abgeschlossenen `await loadLeaflet()` prüft der Callback `disposed`, `dialog.isConnected` und `dialog.open`. Navigation/Unmount löst `ResizeObserver.disconnect()`, `map.remove()` und `dialog.close()` aus. Ungültige Koordinaten oder Ladefehler setzen einen kontrollierten Status („Map unavailable…“) statt stiller Karte. Das Modul hält ein gemeinsames Promise für Asset-Laden, nicht einen Timer |
| [Home Hero](../../resources/js/home-hero.js) | Listener sitzt direkt am Home-Hero-Gruppenknoten. Nur bei mindestens zwei serverseitig berechtigten Slides kann Prev/Next zyklisch schalten, das vorherige Video pausieren und den nächsten Fokus setzen | Beim neuen Content verschwindet der lokale Gruppenknoten samt Listener. Es wird kein eigener globaler Listener und kein offener Autoplay-/Polling-Prozess gehalten |
| [Artwork Viewer](../../resources/js/artwork-viewer.js) | Der Viewer normalisiert ausschließlich kanonische Image-/Video-Daten und behält die registrierte Sequenz als Grenze für Prev/Next. Image-Zoom/Pan unterscheidet sich vom nativen Videomodus | `AbortController` beendet globale `visibilitychange`, `pagehide`, `resize` und `orientationchange`-Listener bei SPA-Disposal. Offene Attention-Zeit wird geflusht, Video pausiert und geplante `requestAnimationFrame`-Neuberechnung abgebrochen. Escape bleibt native Dialogschließung |
| [Matomo](../../resources/js/matomo.js) | `matomo.js` wird bei passender Konfiguration nur einmal eingebunden; nach jedem echten Seiten-Boot werden `setCustomUrl`, `setDocumentTitle` und `trackPageView` zur bestehenden Queue geschrieben | Deklarative Click-Listener und `IntersectionObserver` werden mit dem Page-Disposer aufgehoben; fehlende Konfiguration bedeutet keinen neuen Track. Das einmal geladene SDK selbst ist nicht pro Seite neu zu laden |

**Grenze der Behauptung:** Diese Tabelle beschreibt Codepfade; kein reales SPA-Reentry, Scroll-/Focus-Restoration, Leaflet-Laden, Karten-Tile-Ausfall oder Reviewer-Akzeptanz wurde hier ausgeführt. Die asynchrone Map-Race-Abwehr ist belegbar, eine vollständige Browsergarantie daraus nicht.

## Native Folio-Papiermaschine und Seitenhöhenwechsel

**Geprüfter Quellenstand:** `dev@298bf74bce837385b8a7261b145d2b9fd887ed7c`, als echter Zwei-Eltern-Merge in `docs` enthalten. [Normativer Public-Vertrag](../PUBLIC-IMPLEMENTATION-CONTRACT.md), [JS-Owner](../../resources/js/public-folio-motion.js), [CSS-Owner](../../resources/css/public-presentation.css), [Blade-Shell](../../resources/views/layouts/app.blade.php), [App-Bootstrap](../../resources/js/app.js). **Nur statische Quellprüfung:** weder Laravel-/Livewire-Browserlauf noch vollständige Node-/Playwright-Suite hier ausgeführt.

### DOM, Datenaufnahme und Eigentümer

Die öffentliche Seite wird weiterhin von Laravel/Blade und Livewire Document Navigation ausgeliefert. Die bestehende weiße Fläche `#content` mit ihren `::before`-/`::after`-Konturen und den bestehenden `box-shadow`-Papierlagen bleibt sichtbar und **ungeclippt**. Das Layout legt für **alle** Public-Inhalte ein einziges `#content > .public-content-reveal`-Element um den tatsächlichen Seiteninhalt. Nur dieses innere Element erhält beim Höhenwechsel kurzfristig ein `overflow: clip`, **nicht** `#content` und nicht das Bild selbst. Seine natürliche neue Höhe bleibt der Zielwert; das CSS-`min-height` der äußeren Fläche kann unabhängig davon weiter gelten. `public-presentation.css` hat die leere-Seite-Selektoren entsprechend auf die innere Ebene umgestellt. Das ist eine gemeinsame Public-Struktur, keine zweite Dokumentrouting- oder Animationsschicht.

`app.js` führt `initializePage()` nur bei einer neuen `#content`-DOM-Identität aus. Vor dem Swap speichert `capturePublicFolio()` die vier tatsächlich berechneten Pseudoelement-Geometrien (Top/Bottom/Offset/Breite), beide berechneten Shadow-Werte und die aktuelle Höhe von `.public-content-reveal`. Der `livewire:navigating`-Handler entsorgt anschließend die übrigen seitenlokalen Viewer-/Map-/Navigation-/Tracking-Besitzer. `livewire:navigated` baut die neue Seite und ruft `advancePublicFolio()` auf. Die Page-Height-Animation ist **nicht mehr Artwork-Detail-exklusiv**: Home, Gallery, Artwork, Blog, Exhibitions, Custom und CV benutzen denselben Pfad.

### Start und Navigation: zwei unterschiedliche Phasen

| Phase | Geprüfter Owner / Effekt | Tatsächliche Dauer oder Guard |
| --- | --- | --- |
| Erster Seitenbesuch ohne vorherigen Snapshot | `startAmbient()`: vier native `KeyframeEffect`-Schleifen auf den vorhandenen Content-/Menü-Pseudoelementen, je eine `Element.animate()`-Schleife für die bereits vorhandenen Shadow-Papierlagen | Content-Ecken **19.800/23.600 ms**, Menü-Ecken **21.900/25.800 ms**; Shadow Content **24.700 ms**, Shadow Menü **28.100 ms**, jeweils `iterations: Infinity` |
| Jeder echte SPA-Document-Swap | `pulsePaper()` erzeugt pro verfügbarer Fläche zwei **endliche** Pseudo-Ecken-Animationen plus eine endliche Shadow-Animation. Content und Menü sind getrennte Bewegungsbesitzer; gleiche Höhen schließen den Papierimpuls **nicht** aus | Content **1.480 ms**, Menü **980 ms**, jeweils nur eine Iteration |
| Wechsel mit messbar unterschiedlicher innerer Inhaltshöhe | `animatePageHeight()` animiert nur `.public-content-reveal` von zuvor gemessener zu neuer natürlicher Höhe und stellt den ursprünglichen Inline-Overflow nach Fulfillment oder Rejection wieder her | **980 ms**, nur wenn beide Höhen endlich, die alte positiv und die Differenz **mindestens 7 px** ist |
| Ende der endlichen Papieranimationen | `Promise.allSettled(animation.finished)` triggert `startAmbient()` erneut, **nur wenn** `root.querySelector('#content')` bzw. `.site-navigation` noch dasselbe Ziel-Element liefert | Kein JS-Timer, kein Polling; bei fehlender nativer Animationsunterstützung wird auf CSS-Standbild/ggf. verfügbaren Ambient-Pfad zurückgefallen |

Die Auswärts-/Einwärtsrichtung wird im aktuellen Code aus der alten und neuen inneren Höhe abgeleitet; ohne endliche Messwerte gilt der Impuls als wachsend. `buildCornerImpulse()` koppelt beim horizontalen Schritt den äußeren Offset mit der Rücklaufbreite und bewegt vertikale Endpunkte separat. `buildPageImpulse()` setzt die beiden hinteren vorhandenen Shadow-Lagen je Achse um und endet beim berechneten Zielstil. In `buildCornerFrames()` und `buildShadowFrames()` sind wiederholte WAAPI-Keyframes **Ruhephasen, keine Intervalle**. Auf einem Swap startet nicht gleichzeitig eine Ambient- und eine Übergangsanimation desselben Elements/derselben CSS-Eigenschaft; der Start des Ambients ist an die endlichen `finished`-Promises gebunden.

Die statische Menügrundform beginnt und endet innerhalb ihrer Menüfläche. Der aktuelle CSS-Basisschatten des Menüs liegt bei `(-1,1)` und `(-3,2)` px mit jeweils 1 px Spread, Content bei `(-5,7)` und `(-8,10)` px. `FOLIO_PLANE_BASE` und die Testfixtures spiegeln diese konkreten Werte; sie sind **keine** Zusage eines bereits im Browser abgenommenen Aussehens.

### Negativpfade und Verifikation

- **WAAPI-/DOM-Abbruch:** `nativeCorner()` fängt fehlende native Unterstützung/Exceptions ab und gibt `null` zurück; fehlgeschlagene Shadow-Animationen lassen die CSS-Papierlagen stehen. Der Animation-Finish-Callback prüft die Identität des aktuellen DOM-Ziels, bevor er auf diesem eine neue Endlosschleife startet. Das verhindert einen falschen Neustart auf einem abgelösten Ziel, garantiert aber **nicht** seine Garbage Collection.
- **Handle-Lebensdauer:** Der bestehende `app.js::disposePage()` besitzt **keine** explizite Folio-`cancel()`-Liste für die unendlichen Pseudo-/Shadow-Handles. Das gilt auch nach dem neuen einheitlichen `pulsePaper`-Pfad. Es bleibt eine Laufzeit-/Retention-Testlücke bei schnellem Back/Forward und mehreren SPA-Swaps, **kein nachgewiesener Speicherleck**. Hinzu kommt die Overflow-Restoration eines eventuell bereits abgelösten inneren DOM-Knotens.
- **Testquellen:** [Unit-Test](../../tests/js/public-folio-motion.test.mjs) enthält den neuen gemeinsamen Seiten-Impuls, die Menü- und Shadow-Koordinaten sowie Height-Fixtures. [Browser-Test](../../tests/browser/public-folio-spa.spec.mjs) erzeugt die einheitliche Content-Reveal-Hülle und testet Varianten für Gallery, Blog, Home, CV und Exhibitions, nicht nur Artwork. Es handelt sich um **gelesene Testfälle**, nicht in diesem Dokumentations-Workstream ausgeführte Tests. Der reale Public-Livewire-Swap, Scroll-/Focus-Verhalten, unterschiedliche Inhaltsformen sowie Accessibility/Reduced Motion brauchen weiterhin Browser-Review.
- **Historisch ersetzt:** `animateArtworkHeight()`, `playArtworkBuild()`, `buildArtworkRevealFrames()` und die alte auf Artwork beschränkte 700-ms-Reveal-Semantik sind nicht mehr aktueller Produktcode. Auch die älteren 34,8/36,4-s-Shadow-Werte und negative Menü-Bottom-Offsets sind abgelöst. Die frühere zusätzliche `.public-folio-decoration`-Schicht und der separate Einmal-Swap-Effekt dürfen nicht reaktiviert werden.

## Fachliche und technische Grenzen

- Öffentliche GET/HEAD-Anfragen bleiben an `UseCommittedPublicState` und Publication-Media-Policy gebunden; SPA-Navigation darf keine Entwurfsdaten öffentlich ausliefern. Nicht-GET-Formulare wie Contact folgen ihrem normalen HTTP-Schutz.
- Die bestehende zyklische ganze-Item-Navigation behält ihren eigenen `sessionStorage`-Handoff; der Document-Swap soll ihn nicht durch Pixel-Scrolling ersetzen.
- Der Admin benutzt ebenfalls Filament/Livewire-SPA, aber mit eigener Auth-/Lifecycle-Policy. Aus dem Public-SPA-Skript ist **keine** Admin-Route ableitbar.
- **Zu überprüfen:** tatsächliche Livewire-Head-/Nonce-Updates, Scroll-/Focus-Restoration, Back/Forward, Wechsel zwischen Parent/Child-Menüs, erneute offene Viewer/Map-Instanzen, Matomo pro echtem Seitenwechsel und Escape/Hydration-Verhalten. Ein Quell-Diagramm allein ist kein Browsertest.
