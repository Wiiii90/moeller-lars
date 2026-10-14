# Öffentliche SPA-Dokumentnavigation und Instanzlebenszyklus

**Code-Referenz:** `dev@62a104a0beef093aaa71ecf5c882e066b0dadca9` (einheitlicher `.public-content-reveal`-Seitenhöhenbesitzer, native Paper-Impuls- und Ambient-Lebenszyklen). Alle hier beschriebenen Zeitwerte/Guards aus den aktuell gemergten JS-/Blade-/CSS-Quellen; kein Browser-/Laravel-End-to-End-Lauf.

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

## Native Public-Folio-Papiermaschine, Home Hero und Gallery-Puffer

**Quellstand:** `dev@b6fa7a461331e73bb30ea4debaeb26bfeeffdbe9` als echter Zwei-Eltern-Merge auf `docs`.
Die normativen Public-Anforderungen liegen im
[Public-Vertrag](../PUBLIC-IMPLEMENTATION-CONTRACT.md); die folgenden
Angaben beschreiben die aktuelle Implementierung in
[public-folio-motion.js](../../resources/js/public-folio-motion.js),
[public-presentation.css](../../resources/css/public-presentation.css),
[app.js](../../resources/js/app.js) und
[home-hero.js](../../resources/js/home-hero.js).
**Quellprüfung und Testdateilektüre, kein ausgeführter Node-/Playwright-/Laravel-Browserlauf.**

### Ein Dokument-Shell, ein sichtbares Papier pro Fläche

Die öffentliche Livewire-Dokumentnavigation verwendet auf **allen** Public-Seiten
eine `#content > .public-content-reveal`-Hülle. Der äußere
`#content`-Bogen und `.site-navigation` bleiben ungeclippt;
ausschließlich die innere Reveal-Hülle bekommt während einer tatsächlich
benötigten Höhenanimation vorübergehend `overflow: clip`. Galerie, Artwork,
Blog, Exhibitions, Home und Custom/CV nutzen denselben Content-Owner.
`capturePublicFolio()` nimmt vor dem Swap die gerenderten Pseudo-Ecken,
**einen Content-Schatten** und die sichtbare vorherige Hüllenhöhe auf.
Die frühere Menü-Snapshot-/Menü-Pulse-Logik ist **entfernt**.

Content besitzt eine äußere helle Papierlage (`-5px 7px 0 var(--public-folio-soft)`). Das Menü hat seine innere Trennlinie und **eine** äußere helle Lage (`-8.5px 3.5px 0 3.5px var(--public-folio-soft)`). Der Menü-Spread entspricht dem vertikalen Offset, weshalb **die Hintergrundfläche selbst wieder bis zur Menüoberkante reicht**. Zugleich garantiert der stärkere negative x-Offset rechts mindestens **5 px Inset**. Bei der Menü-Ambient-Bewegung weicht die x-Achse zuerst nach links; die y-Achse kehrt **vor** x zurück, damit weder die obere Verbindung verloren geht noch die Fläche rechts übersteht. Die separaten Pseudoelement-Konturen bleiben ebenso oben angebunden. `--public-folio-depth` sowie die früheren mehrfachen dunklen/hellen Außenlagen sind entfernt. `FOLIO_PLANE_BASE` enthält nur `content: [-5,7]` und `menu: [-8.5,3.5]`. Eingesetzte SHA und CSS sind die Quelle für diese Werte;
kein realer Pixelvergleich im Browser wurde durchgeführt.

### Normale Bewegungen und Seitenwechsel

| Phase | Owner und Bedingung | Werte aus aktuellem JS |
| --- | --- | --- |
| Initialbesuch/Ambient | `startAmbient()` erzeugt je zwei native Pseudo-Ecken-Schleifen an Content und Menü sowie jeweils eine Shadow-Schleife. Menü-Top und horizontale Kanten bleiben stabil; der linke untere Endpunkt bleibt stets **höher** als der rechte, beide berühren den Menüboden nicht. Shadow-Frames sind achsweise und über Bewegungsdistanz/Ruhezeiten gewichtet. | Content-Ecken **25.100 / 29.700 ms**, Menü-Ecken **34.200 / 38.800 ms**, Content-Shadow **30.200 ms**, Menü-Shadow **36.400 ms**; jeweils unendlich |
| Livewire-Document-Swap | `advancePublicFolio()` löst `pulsePaper()` **nur am Content** aus, auch bei unveränderter Inhaltshöhe. Zwei Pseudo-Ecken und genau **ein** Außen-Shadow folgen endlichen, zielgeometriebasierten Schritten. Die Rückkehr zur CSS-Geometrie verhindert kumulatives Aufweiten bei wiederholten Wechseln. Das neue Menü beginnt nur wieder mit Ambient, nicht mit einem künstlichen Navigationsimpuls. | Papier-Impuls **1.240 ms**, genau eine Iteration |
| Sichtbarer Seitenhöhenwechsel | `visibleHeightTransition(previous, next, viewport, bodyTop)` beschränkt Start/Ziel auf die aktuell sichtbare Höhe **plus 48 px**; `animatePageHeight()` überspringt den Verlauf, wenn die sichtbare Differenz kleiner als **7 px** ist. Die tatsächlich lange Seite behält nach Ende ihre natürliche Auto-Höhe. | Innere Höhe **780 ms** bei erforderlicher sichtbarer Differenz |
| Home-Hero-Pfeil ohne Seitenwechsel | `initializeHomeHero()` reicht den einen bestehenden Slide-Toggle an `animatePublicHeroChange()` weiter: aktuelle Pose/Höhe messen, konkurrierende Content-/Height-Effekte abbrechen, Slide sichtbar schalten, dieselbe Content-Papiermaschine ausführen; Viewer, Focus und zyklischer Index bleiben beim Hero-Owner. | Papier **1.240 ms**, Höhe ggf. **780 ms**, **kein** Menu-Pulse, keine synthetische History |
| Abschluss | `pulsePaper()` nutzt ein `activePaperPulses`-`WeakMap`-Token **pro Element und aktuellem Durchlauf**. `Promise.allSettled(animation.finished)` darf Ambient nur starten, wenn **Token und aktives DOM-Element** noch übereinstimmen. | Alte abgebrochene Hero-Promises können damit nicht mehr die neue Phase überschreiben |

Die beidseitigen Content-Randlinien kürzen beim Impuls ihre oberen/unteren
Enden um maximal **40/46 px** beim Wachsen bzw. **32/38 px** beim Schrumpfen.
Auf kurzen Flächen bleiben mindestens **40 px** Kontur sichtbar.
`buildCornerImpulse()` startet bei der aktuellen gerenderten Position,
misst aber alle Auslenkungen relativ zur **statischen Zielkontur**, nicht
relativ zur vorher schon verschobenen Kontur. Die authored Start-/Zielposition
wird am letzten Keyframe exakt wieder erreicht. Menü-Ambient nutzt
linke Bottom-Stationen `32% → 60% → 42% → 73% → 32%`,
rechts `1px → 17% → 6% → 26% → 1px` bei fester oberer Verbindung;
dies sind **zwei asynchrone** Perioden, keine synchrone Menü-Pulse-Kette.

### Gallery-Bildladepuffer: getrennt vom Folio

[public-gallery-loading.js](../../resources/js/public-gallery-loading.js)
wird über `app.js::initializePage()` gestartet und als eigener Seiten-
Disposer eingetragen. Die vollständige publizierte Artwork-Liste und
Artwork-Viewer-Reihenfolge bleiben im DOM. In
[artworks/index.blade.php](../../resources/views/pages/artworks/index.blade.php)
werden **die ersten zwei** Bilder eager geladen; nur das **erste**
bekommt `fetchpriority=high`. Weitere Bilder bleiben zunächst native
`loading=lazy`. Der `IntersectionObserver` mit `rootMargin:
'960px 0px'` beobachtet ausschließlich den Gallery-Bereich
`[data-gallery-image-buffer]`. Höchstens **zwei** nahe, noch nicht
fertige Bilder werden gleichzeitig durch Umstellung desselben vorhandenen
`img.loading` auf `eager` vorgeladen; `src` bleibt unverändert,
zusätzliche High-Priority-Requests oder Bild-Klone gibt es nicht.
`load`/`error` geben den Puffer frei. Bei SPA-Disposal beendet
`AbortController` die Eventlistener, der Observer wird getrennt und
die Warteschlangen werden geleert. Ohne IntersectionObserver bleibt das
normale native Lazy Loading bestehen.

**Testquelle:** [Gallery-Unit](../../tests/js/public-gallery-loading.test.mjs)
prüft Distanz-/Prioritätsauswahl, Zwei-Bilder-Grenze, Nachrücken nach
`load`/`error` und Verzicht auf weitere Promotion nach Disposal.
Ein Browsernetzwerktrace, echte Bildladezeiten und eine Server-
MediaVariant-Regeneration wurden hier **nicht** geprüft. Der Puffercode
ersetzt kein Backend-Paging und keine Media-Publication-Guards.

### Offene Prüfgrenzen

- Der **früher dokumentierte Home-Hero-Promise-Race** (nur DOM-Identität
  beim alten Papier-Finish) ist durch `activePaperPulses`-Token
  **quellseitig korrigiert**. `activeHeightAnimations` als eigene
  `WeakMap` schützt die Overflow-Restoration und cancelt auch bei einem
  Folgebesuch **ohne** sichtbare Höhendifferenz die alte Clip-Animation.
  Der dazugehörige Browser-/Microtask-Negativtest bleibt eine sinnvolle
  Ergänzung; ein Lauf wurde hier nicht behauptet.
- Der SPA-`app.js::disposePage()` verwaltet noch **keine** eigene Liste
  der auf abgelösten DOM-Knoten gestarteten unendlichen nativen Pseudo-/Shadow-
  Animation-Handles. `document.getAnimations()` und Back/Forward-Retention
  brauchen eine **echte** Browserprüfung, bevor von einem Leak gesprochen
  werden darf; mit dem Hero-spezifischen In-place-Cancel nicht verwechseln.
- [Folio-Unit](../../tests/js/public-folio-motion.test.mjs),
  [Browser-Fixture](../../tests/browser/public-folio-spa.spec.mjs)
  und [Hero-Unit](../../tests/js/home-hero.test.mjs) umfassen
  absolute Contour-Geometrie, nur eine Papierlage, verkürzte
  Viewport-Höhen, wechselnde Seitenarten, Top-anknüpfendes Menü und
  Home-Hero-Content. Reale Laravel-/Livewire-Wechsel, Netzwerk-
  Wasserfall, Accessibility/Reduced Motion und visuelle Nutzerabnahme
  wurden in diesem `docs`-Workstream **nicht** ausgeführt.
- Die alte `animateArtworkHeight()`-700-ms-Semantik,
  Content-Menü-Pulse-Kopplung, mehrfachen Außen-Papierlagen,
  `--public-folio-depth` und früheren 1.480-/980-ms-Laufzeiten
  sind **historisch**, nicht erneut als aktueller Vertrag zu verwenden.

## Fachliche und technische Grenzen

- Öffentliche GET/HEAD-Anfragen bleiben an `UseCommittedPublicState` und Publication-Media-Policy gebunden; SPA-Navigation darf keine Entwurfsdaten öffentlich ausliefern. Nicht-GET-Formulare wie Contact folgen ihrem normalen HTTP-Schutz.
- Die bestehende zyklische ganze-Item-Navigation behält ihren eigenen `sessionStorage`-Handoff; der Document-Swap soll ihn nicht durch Pixel-Scrolling ersetzen.
- Der Admin benutzt ebenfalls Filament/Livewire-SPA, aber mit eigener Auth-/Lifecycle-Policy. Aus dem Public-SPA-Skript ist **keine** Admin-Route ableitbar.
- **Zu überprüfen:** tatsächliche Livewire-Head-/Nonce-Updates, Scroll-/Focus-Restoration, Back/Forward, Wechsel zwischen Parent/Child-Menüs, erneute offene Viewer/Map-Instanzen, Matomo pro echtem Seitenwechsel und Escape/Hydration-Verhalten. Ein Quell-Diagramm allein ist kein Browsertest.
