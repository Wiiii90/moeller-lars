# Öffentliche SPA-Dokumentnavigation und Instanzlebenszyklus

**Code-Referenz:** `dev` bei `44667847f1de7ab43e1341c09ee2a5e4c0a40982` (achsengebundene Folio-Animation, Artwork-Detail-Höhenwechsel und korrigierte Mid-Flight-Shadow-Koordinaten). Die in `docs` übernommenen Quellen wurden statisch geprüft; es wurde hier kein Browser-/Laravel-End-to-End-Test ausgeführt.

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

## Native Folio-Ränder, Schatten und Artwork-Höhenübergänge

**Aktueller Referenzstand:** `dev@44667847f1de7ab43e1341c09ee2a5e4c0a40982`
(über einen Zwei-Eltern-Merge nach `docs` übernommen). Die Folio-Animation
benutzt nur die bestehenden `#content::before/::after`- und
`.site-navigation::before/::after`-Konturen sowie deren vorhandene
`box-shadow`-Lagen. Kein extra DOM, keine Timer/Intervalle, keine
eigenständige SPA-Route. Der [Public-Vertrag](../PUBLIC-IMPLEMENTATION-CONTRACT.md)
ist Eigentümer des angestrebten Designs, nicht dieses Quellinventar.

1. `app.js::initializePage()` initialisiert nur bei einer neuen
   `#content`-DOM-Identität und ruft `advancePublicFolio()` auch beim
   Initialbesuch auf. `livewire:navigating` ruft zuerst
   `capturePublicFolio()` und danach die Seiten-Disposer auf.
2. Der Capture enthält `top`, `bottom`, horizontale Kantenpositionen
   und Breiten der vier Pseudoelemente, die berechneten Menü- und
   Content-`box-shadow`-Werte sowie die aktuelle Höhe eines unmittelbar
   unter `#content` befindlichen `.artwork-detail`-Artikels.
   Ein späterer `livewire:navigated`-Bootstrap übernimmt diesen Snapshot
   an den nächsten echten DOM-Knoten.
3. `buildCornerFrames()` bewegt pro Keyframe nur eine vertikale Koordinate
   oder **ein gekoppeltes horizontales Offset-/Breitenpaar**, sodass der
   Papier-Rücklauf am Rand verbunden bleibt. Die vier
   `KeyframeEffect`/`Animation`-Dauerschleifen laufen mit **28.600 /
   31.800 ms** (Content) und **27.000 / 30.900 ms** (Menü).
   Die Menü-Konturen bleiben oben offen und unten verbunden; es gibt keine
   Rotation, diagonale Bewegung oder Menu-Height-Animation.
4. Bei normalen Seiten besitzen beide Flächen je eine zusätzliche
   `Element.animate()`-`box-shadow`-Schleife (Content **34.800 ms**,
   Menü **36.400 ms**). Die jeweils zwei hinteren bestehenden Papierlagen
   bewegen sich im beabsichtigten Ablauf achsenweise um 1–2 px.
   Der erste Schatten bleibt unverändert. Der nächste Zustand soll die
   zuvor gerenderte Zwischenposition sanft übernehmen.
5. **Nur Artwork-Detail → Artwork-Detail:** `animateArtworkHeight()`
   liest die zuvor gerenderte Höhe und die neue natürliche Artikelhöhe.
   Bei Differenz von mindestens 6 px animiert es die vorhandene
   `.artwork-detail`-Höhe **700 ms** per WAAPI. Währenddessen liegt
   `overflow: clip` am Artikel, **nicht an `#content`**; die
   äußere Kontur wird nicht beschnitten. Bei Fertigstellung oder
   Abbruch des Promise wird der ursprüngliche Inline-Overflow wiederhergestellt.
   Gallery, Blog, Home und ein Erstbesuch besitzen keinen alten
   Artwork-Artikel als Auslöser.
6. Der neuere `shadowMotionFrames()`-Aufbau unterscheidet `cycle`,
   `outward` und `inward` mit **wiederholten WAAPI-Keyframes als Ruhephasen**
   (kein Timer/Polling). Bei einem echten Artwork-Höhenwechsel wird die
   unendliche Content-Shadow-Schleife während der 700-ms-
   Artikelanimation **nicht parallel gestartet**. Stattdessen führt
   `playArtworkBuild()` eine endliche Paper-Schatten-Sequenz aus,
   deren Außen-/Innenrichtung von der Höhenänderung abhängt; ihr
   `animation.finished` startet die kontinuierliche Content-Schatten-
   Animation nur dann neu, wenn `root.querySelector('#content')`
   weiterhin auf das animierte Element zeigt. Bei fehlender WAAPI oder
   fehlgeschlagenem Start versucht der Pfad, das normale Ambient-
   Breathing wiederherzustellen. Die Menü-Schleife läuft unabhängig.
   Der frühere gleichzeitige Content-Shadow-Breathing/Reveal-Konflikt
   wird so **auf Quellpfadebene** vermieden; DOM-Lifecycle und visuelle
   Wirkung wurden hier nicht browsergetestet.

### Statisch belegte Negativpfade und Nachweise

**Nachgezogener Source-Fix des Shadow-Parsers:** Die zunächst in
`dev@b3439bb` belegte doppelte Escapierung des RegExp-Literals in
`renderedShadowCoords()` lieferte für CSS-Pixelwerte keine Matches;
ein isolierter Node-22-Regex-Prüffall bestätigte das. Der
anschließende Commit `e8ae20429d378ce74c26a4c96c469597b1717bf8`
korrigiert das JS-RegExp-Literal auf einfache Backslashes **und**
den entsprechenden [Mid-Flight-Test](../../tests/js/public-folio-motion.test.mjs).
Die beiden Quelldateien auf `dev@21e907f4557b0114389f9b17438ca300ca701df3`
wurden erneut gelesen. Der frühere Regex-Fehler ist damit
**quellseitig behoben** und nicht länger als offener produktiver
Bug zu führen. Die echte Ausführung des korrigierten JS-Tests und
Browser-Akzeptanz bleiben unabhängig offen. Der ebenfalls neue Unit-Test
behandelt einen schrumpfenden und einen wachsenden
Artwork-Detail-zu-Detail-Swap mit absoluter Artikelhöhe.


**Folio-Unit-Test nachgezogen (Quellstand `dev@44667847f`):**
Der zwischenzeitliche Test-Widerspruch nach `dev@44f3d01b`
(altes `MENU_VARIANTS[0]`, fehlende Shadow-Spread-Berücksichtigung)
wurde durch `dev@44667847f1de7ab43e1341c09ee2a5e4c0a40982`
in der bestehenden [JS-Testdatei](../../tests/js/public-folio-motion.test.mjs)
quellseitig beseitigt. Sie benutzt nun die geänderten Menü-Endpunkte,
`FOLIO_PLANE_BASE`, CSS-nahe Fixture-Offsets, einen
Spread-toleranten Pixelkoordinaten-Parser und Assertions für
sichtbare seitliche/untere Papierabstände. Hinzugekommen sind
Ruhe-Keyframes, achsenweise Wiederaufnahme nach unterbrochenem
Schattenzyklus, getrennte Ein-/Ausfahr-Richtungen beim Artwork-
Höhenübergang und die Sequenz **eine 700-ms-Content-Shadow-
Animation → Ambient-Breathing erst nach `finished`**.
Der alte konkrete Source-/Test-Widerspruch ist **behoben**;
dies ist keine Behauptung eines hier ausgeführten vollständigen
`node --test`- oder Playwright-Laufs. Auch reale Rendering-
Abstände und die Browser-Lebensdauer sind nicht getestet.

**Weiterhin offene Lebensdauer:** `animatePaper()` startet **vier unendliche
Pseudo-`Animation`-Objekte und zwei unendliche Shadow-Animationen** pro
neuem Dokument; weder deren Handles noch ein entsprechender
`cancel()`-Disposer werden vom `app.js::disposePage()` geführt.
Beim Artwork-Höhenübergang existiert zusätzlich ein zeitlich begrenzter
Animation-Handle, dessen Finish/Reject zwar Inline-Overflow bereinigt,
der aber nicht explizit an den SPA-Seiten-Disposer gebunden ist.
Nach Entfernung der alten DOM-Knoten ist eine tatsächliche
Retention/CPU-Belastung **nicht** nachgewiesen. Benötigt wird ein echter
Browserlauf mit mehreren Navigationen, Back/Forward, aktivem
`document.getAnimations()`-Bestand und abgelösten Zielen sowie dem
Artwork-Overflow bei unterbrochenen 700-ms-Übergängen.

**Testabgrenzung:** Die aktualisierten JS-/Playwright-Dateien
[Unit](../../tests/js/public-folio-motion.test.mjs) und
[Browser](../../tests/browser/public-folio-spa.spec.mjs) wurden statisch
gelesen; weder `node --test` für die komplette Datei noch Playwright
oder eine reale Livewire-/Laravel-Navigation wurden hier ausgeführt.
`prefers-reduced-motion` unterdrückt die laufenden Schleifen im
aktuellen Source nicht automatisch; Browser-/Accessibility-Abnahme offen.

**Historisch abgelöst:** Die frühere
`.public-folio-decoration`-Struktur, Debuginstrumentierung und die
1150-ms-Einmalübergänge sind kein aktueller Architekturvertrag.

## Fachliche und technische Grenzen

- Öffentliche GET/HEAD-Anfragen bleiben an `UseCommittedPublicState` und Publication-Media-Policy gebunden; SPA-Navigation darf keine Entwurfsdaten öffentlich ausliefern. Nicht-GET-Formulare wie Contact folgen ihrem normalen HTTP-Schutz.
- Die bestehende zyklische ganze-Item-Navigation behält ihren eigenen `sessionStorage`-Handoff; der Document-Swap soll ihn nicht durch Pixel-Scrolling ersetzen.
- Der Admin benutzt ebenfalls Filament/Livewire-SPA, aber mit eigener Auth-/Lifecycle-Policy. Aus dem Public-SPA-Skript ist **keine** Admin-Route ableitbar.
- **Zu überprüfen:** tatsächliche Livewire-Head-/Nonce-Updates, Scroll-/Focus-Restoration, Back/Forward, Wechsel zwischen Parent/Child-Menüs, erneute offene Viewer/Map-Instanzen, Matomo pro echtem Seitenwechsel und Escape/Hydration-Verhalten. Ein Quell-Diagramm allein ist kein Browsertest.
