# Öffentliche SPA-Dokumentnavigation und Instanzlebenszyklus

**Code-Referenz:** `dev` bei `eef99104540d40f154533fa0743eec75bdfa6d9b` (neue kontinuierliche Content-/Menü-Folio-Animation einschließlich nachgezogener JS-/Browser-Tests), zur kontrollierten Übernahme nach `docs`. Der Ablauf ist aus JavaScript/CSS/Blade/Middleware-Quellen abgeleitet; weder Browser noch Laravel-End-to-End sind hier ausgeführt worden. Dieser Ablauf wurde **aus den aktuellen JS-/Blade-/Middleware-Quellen abgeleitet**, aber nicht im Browser oder mit Laravel-End-to-End ausgeführt.

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

## Native Folio-Ränder: kontinuierliche Content- und Menüanimation

Der aktuelle [`public-folio-motion.js`](../../resources/js/public-folio-motion.js) auf dem geprüften `dev`-Stand animiert die **bereits vorhandenen** Pseudoelemente `#content::before/::after` und `.site-navigation::before/::after`, ohne zusätzliche Linien, HTML-Knoten, Layer, Intervalle oder einen separaten SPA-Router. Der [Public-Vertrag](../PUBLIC-IMPLEMENTATION-CONTRACT.md) beschreibt das angestrebte Erscheinungsbild; dessen visuelle Abnahme ist ausdrücklich noch offen. Die native Daueranimation unterscheidet sich vom **abgelösten, nur bei Navigation ausgelösten 1150-ms-Zustandswechsel**.

**Konkret nachverfolgter Lifecycle:**

1. `app.js::initializePage()` prüft die Identität des aktuellen `#content` und baut nur bei neuem Element Viewer, Hero, Journal, Analytics und Navigation auf. **Direkt danach ruft es `advancePublicFolio()` auf**, auch bei der initialen `DOMContentLoaded`-Initialisierung; der bisherige `pendingVisit`-Guard wurde entfernt. Jede weitere Livewire-Document-Navigation ruft die gleiche Initialisierung nach dem Swap auf.
2. `livewire:navigating` liest mit `capturePublicFolio()` die **tatsächlich berechneten** `top`-/`bottom`-Positionen beider Content- und beider Menü-Pseudoelemente sowie die drei vorhandenen Content-Schatten. Die Page-Disposer laufen vor dem Body-Swap; beim `livewire:navigated` wird das neue DOM initialisiert, wobei die aufgefangene Geometrie den Anfang seiner nächsten Animation bildet. Fehlt ein DOM-Teil oder sind Pseudoelemente unsichtbar, wird dafür keine Konturanimation erzeugt.
3. Content und Menü erhalten zusammen **vier native `KeyframeEffect`-Instanzen** mit `pseudoElement: ::before/::after`, `iterations: Infinity`, `ease-in-out` und `new Animation(effect, document.timeline).play()`. Die Content-Dauern sind **16.000/19.400 ms**, die Menü-Dauern **17.400/20.800 ms**. Die erste und letzte Frame-Geometrie stimmen überein, um Schleifensprünge zu vermeiden.
4. Die drei ursprünglichen Content-`box-shadow`-Layer bleiben dieselben. Nur die beiden hinteren vertikalen Offsets variieren in einer **18.200-ms-Endlosschleife** über `content.animate()`; die vorderste Schattenlage bleibt fest. Artwork, Contentbreite, Typografie, Menübreite und die seitlichen Positionen werden nicht animiert.
5. Das Menü erhält nun eine **asymmetrische** bestehende CSS-Kontur: Westseite mit kurzem oberen Rücklauf statt unterer Querlinie; Ostseite mit verbundenen oberen/unteren Rückläufen, die ungefähr **.24rem** in die weiße Menüfläche reichen. Die beiden Menü-Pseudoelemente bleiben Teil ihrer normalen `.site-navigation`-Box und verändern die gemessene Navigationsbreite nicht.
6. Ohne Unterstützung für `KeyframeEffect`/`Animation` oder bei Fehlern bleibt die ursprüngliche statische CSS-Kontur sichtbar. Es gibt **keinen JS-Timer und kein Polling**; die Endlosschleife läuft ausschließlich über die native Browser-Animations-Zeitleiste. Der Code unterdrückt die kleine Dekoration bei `prefers-reduced-motion: reduce` derzeit **nicht**; die dafür erforderliche Accessibility-/visuelle Entscheidung ist weiterhin offen.

**Quellgeprüfte Tests, nicht ausgeführt:** [`tests/js/public-folio-motion.test.mjs`](../../tests/js/public-folio-motion.test.mjs) erfasst inzwischen `MENU_VARIANTS`, vier unendliche Pseudo-Effekte, initialen Start, übertragene Geometrie, statische Fallbacks und Schatten. [`tests/browser/public-folio-spa.spec.mjs`](../../tests/browser/public-folio-spa.spec.mjs) wurde im zweiten `dev`-Commit ebenfalls nachgezogen. Ein zwischen den beiden Commits kurzfristig vorhandener alter Import von `chooseNextFolioVariant` ist deshalb **kein offener Defekt des final geprüften `dev`-Standes**. Die neuen Tests sind hier nicht ausgeführt; insbesondere tatsächliche native Pseudo-WAAPI-Unterstützung, DOM-/Animation-Cleanup, schnelle Back/Forward-Navigation und Browserwirkung bleiben ohne Laufzeitnachweis.

**Offener Lebensdauer-Negativpfad:** `advancePublicFolio()` erzeugt pro neuem `#content` über `playPseudo()` vier **unendliche** `Animation`-Objekte; auch der `content.animate()`-Shadow-Handle wird nicht gespeichert. `disposePage()` kennt aktuell keinen eigenen Folio-Disposer oder `cancel()`-Pfad. Nach dem normalen Livewire-Swap sind die vorherigen `#content`-/`.site-navigation`-Ziele laut [Layout](../../resources/views/layouts/app.blade.php) nicht ausdrücklich per `@persist` stabilisiert. Ob der Browser die abgelösten unendlichen Animationen automatisch beendet und freigibt, wird aus der bloßen DOM-Entfernung **nicht** als garantiert angenommen. Die vorhandenen [JS-Fixtures](../../tests/js/public-folio-motion.test.mjs) prüfen Start/Keyframes/Fallback, aber keinen wiederholten Document-Swap mit beobachteter Anzahl aktiver Animations-Handles. Späterer Browser-Test: **Initialisierung → mehrere interne Wechsel → Back/Forward → abgelöste Animationsziele/CPU/Handles prüfen**, ohne neue Timerschleifen einzuführen. Dies ist eine **Testlücke**, kein behaupteter Memory-Leak.

**Abgelöste Architektur:** Die frühere `.public-folio-decoration`-DOM-Schicht, Debug-Queryparameter und Debug-Storage sowie die nur beim Navigieren angestoßenen 1150-ms-Folio-Transitions sind keine aktuellen Verträge. Laravel/Blade und Livewire 4 bleiben die einzigen öffentlichen Routing-/Document-Swap-Eigentümer.

## Fachliche und technische Grenzen

- Öffentliche GET/HEAD-Anfragen bleiben an `UseCommittedPublicState` und Publication-Media-Policy gebunden; SPA-Navigation darf keine Entwurfsdaten öffentlich ausliefern. Nicht-GET-Formulare wie Contact folgen ihrem normalen HTTP-Schutz.
- Die bestehende zyklische ganze-Item-Navigation behält ihren eigenen `sessionStorage`-Handoff; der Document-Swap soll ihn nicht durch Pixel-Scrolling ersetzen.
- Der Admin benutzt ebenfalls Filament/Livewire-SPA, aber mit eigener Auth-/Lifecycle-Policy. Aus dem Public-SPA-Skript ist **keine** Admin-Route ableitbar.
- **Zu überprüfen:** tatsächliche Livewire-Head-/Nonce-Updates, Scroll-/Focus-Restoration, Back/Forward, Wechsel zwischen Parent/Child-Menüs, erneute offene Viewer/Map-Instanzen, Matomo pro echtem Seitenwechsel und Escape/Hydration-Verhalten. Ein Quell-Diagramm allein ist kein Browsertest.
