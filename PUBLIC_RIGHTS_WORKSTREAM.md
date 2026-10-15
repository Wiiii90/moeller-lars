# PUBLIC RIGHTS WORKSTREAM — temporäre lebende Ausführungsakte

**Status:** AKTIV · **Start:** 2026-10-14
**Projekt:** `Wiiii90/moeller-lars` · **Zweig:** ausschließlich `dev` · **Abschluss:** erst nach vollständiger Implementierung, realer Abnahme, Dokumentationsabgleich und Entfernung DIESER TEMPORÄREN DATEI.

> **Achtung, Arbeitsstand:** Diese Root-Datei ist eine laufend zu aktualisierende Steuerungsakte für mehrere Chat-Kontexte. Die Architektur und das fachliche Ziel stehen in `docs/PUBLIC-RIGHTS-AND-PRIVACY.md`. `AGENTS.md`, `ui-skills.md`, `followup-skill.md` und der aktuelle Code gelten weiterhin. Diese Datei meldet bewusst **nicht** „fertig“, was nur geplant, programmiert oder ungetestet ist.

## 0. Soforteinstieg für JEDEN Fortsetzungs-/Implementierungschat

1. Diese Datei **zuerst vollständig lesen**; danach `AGENTS.md`, fachlichen Vertrag und gezielt die für den aktiven Arbeitsblock verantwortlichen Quellen prüfen. Kein Wiederanfangen bei alten Chat-Zusammenfassungen.
2. Neuesten SHA von `origin/dev` feststellen; diese Datei kann nach ihrem letzten Commit von anderen Chats überholt worden sein. Den letzten Commit, aktive Dateieigentümer und die **nächste freigegebene Einheit** hier prüfen.
3. Nur den **aktuellen zusammenhängenden Arbeitsblock** umsetzen. Technische Abhängigkeiten selbständig bündeln statt in dutzende Codex-Mikroaufträge zu zerlegen. Keine ungefragten Nebenbaustellen.
4. Direkt auf `dev` arbeiten; keine Branches, PRs, Worktrees, Checkout-Duplikate, CI-/Actions-Zugriffe, Produktionsserver-Eingriffe, Deployments oder Release-/main-Pushes. Konflikte bei parallelen Chats durch HEAD-Vergleich/Dateienabgleich und atomische erwartete SHA-Lease lösen, **niemals** blind überschreiben.
5. Änderungen gehören über zentrale Domain-/Service-Owner in die Architektur, **nicht** in zweite Frameworks, Page-Workarounds oder temporäre Listener. Public-CSP, Livewire-SPA, Admin-Audit und Notification-Pflichten beachten.
6. **Bei jedem echten Fortschritt dieselbe Datei im selben Commit mitziehen**: Status, Nachweise, relevante Dateien, offene Risiken, unmittelbarer nächster Schritt. Keine ausufernden Chronologien; die Dateiliste/Entscheidungen hier ersetzen keinen tatsächlichen Diff.
7. Am Ende jeder Sitzung unterscheide: Quellcode geprüft / Tests geschrieben / Tests ausgeführt / echte Browser- oder Netzwerkprüfung / durch Nutzer akzeptiert. Keine nicht gelaufenen Tests als Erfolg melden. Einen kurzen Status **mit Commit-SHA** geben.
8. Wenn der Chat voll wird: zuerst Workstream-Datei im Repository aktualisieren, **dann** einen Folgeprompt nach `followup-skill.md` mit exact aktuellem HEAD, aktiviertem Slice, Browserstand und Risiken erstellen. Neuer Chat muss keine Vorgeschichte erraten.

**Initialer Zustand dieser Datei:** Alle vier Arbeitsblöcke sind zur Umsetzung freigegeben; bisher keine dieser vier Vollimplementierungen abgeschlossen. Die bestehenden frühen Crawler-/TDM-Bausteine sind eine **teilweise implementierte Vorarbeit**, nicht das gesamte Schutzsystem. Zuerst Block A als gebündelte editoriale End-to-End-Einheit. Die anderen Blöcke erst nach Abgleich/Abnahme des jeweils dafür relevanten Codes. Echte Browser-/Serverprüfung ist von diesem reinen Planungsschritt nicht abgedeckt.

## 1. Nicht verhandelbare Produktentscheidungen

- **Verbindliche UI-Sprache: ausschließlich Englisch in Admin UND Public.** Der historische Komponentenname bleibt **Legal Disclaimer**. Neue Abschnitte heißen **Imprint**, **Privacy Policy**, **Copyright & AI Use** und **External Links**. Auch Platzhalter, Notifications, Validierungsfehler und öffentliche Anker sind Englisch. Deutsch ist nur in der internen Arbeitsdokumentation zulässig.

- Künstlerportfolio mit **maximal hochwertiger Bildpräsentation**: voller Originaldetailgrad im Viewer, Zoom, Seitenwechsel, Tastaturbedienung, Gallery/Home/Journal erhalten. **Keine** Reduzierung der Viewer-Qualität, sichtbaren Wasserzeichen, Rechtsklick-Fakes, JS-Hacks, störenden Layer oder künstlich erschwerten normalen Betrachtung.
- **SEO maximieren ohne KI-Verwertungsfreigabe**: normale öffentliche Such-/Bilderindexierung bleibt möglich. TDM-Vorbehalt, standardisierte Bildrechte-Metadaten, strukturierte SEO-Angaben und missbrauchsarme Bot-Kontrolle greifen zusammen. Robots/TDMRep sind keine technische Zugangssperre.
- **Downloads soweit technisch ehrlich möglich erschweren:** insbesondere Massen-/Script-Abrufe, Bandbreitenmissbrauch, Weiterverlinkung/Hotlinking und unerlaubtes Rehosting eindämmen. **Keine falsche Zusage**, dass ein öffentlich ausgeliefertes hochauflösendes Bild nicht gespeichert werden kann; Netzwerkteilnehmer erhalten dessen Bytes. Nativen Smartphone-Screenshot-Schutz kann eine gewöhnliche Website nicht erzwingen.
- **Kein Footer**, kein neuer Pflicht-Menüpunkt und kein scrollgetriggerter rechtlicher Overlay. Vorläufiger öffentlicher Platz ist unter Bild/Biografie/Social/Contact auf der derzeit „CV“ genannten **About Artist/Contact Custom Page**, wo die bisherige Haftungsablehnung steht. Die spätere konkrete visuelle Form bedarf Review gegen das reale Public-Layout; bestehender Content-Folio/Menu/Header/Animation sind Referenz.
- **Redaktionelle Eigenverantwortung des Künstlers:** Rechtstext darf auf seiner frei editierbaren/unpublishbaren Custom Page liegen. Keine Zwangspublikation oder globale Publisher-Sperre im Namen des Künstlers. **Technischer Entwickler** ist zuständig für korrekte CSP, Medien-/Bot-Schutzmechanismen, Matomo-Consent und zutreffendes tatsächliches Datenverhalten. Rechtliche Erreichbarkeit/Anbieterkennzeichnung in der endgültigen öffentlichen Konfiguration explizit prüfen, statt die Entscheidung des Künstlers zu übersteuern.
- General soll eine **überschaubare zentrale Rechtsredaktion** bekommen, Storage individuelle Medienrechte, technische Regeln und Metadaten-Bereitstellung bleiben bei Services/HTTP.
- Keine generierten erfundenen Impressumsadressen, Kontakt-/Hostingfakten, Rechtelage oder Rechte Dritter. Endgültige Texte basieren auf echten Angaben und sollen gegebenenfalls rechtlich geprüft werden. Keine pauschale Haftungsfreizeichnung, die ein Impressum ersetzt.
- Parallelchat verändert denselben `dev`. Einzelner commit muss gültig auf neuestem HEAD liegen. Keine CI-Lese- oder Laufoperationen. Der lokale Windows-Pfad `P:\moeller-lars` ist nützliche Benutzerinformation, **kein** von ChatGPT vorauszusetzender Containerzugriff.

## 2. Autoritäten und erprobter Ist-Stand

| Thema | Code-Owner / Quelle | Befund beim Start |
| --- | --- | --- |
| Globaler Arbeitsvertrag | `AGENTS.md`, `ui-skills.md`, `followup-skill.md` | Direkte dev-Arbeit, kein CI, systemische Umsetzung; Filament-Edit-Mutation benötigt DB+Audit+AdminNotifier |
| Public-Konzept | `docs/PUBLIC-IMPLEMENTATION-CONTRACT.md` | Strikte nonce-CSP, Livewire `wire:navigate`, ein Public-Layout, Folio und originalgetreuer Viewer |
| Fachliche Zielarchitektur | `docs/PUBLIC-RIGHTS-AND-PRIVACY.md` | Vier zusammenhängende Arbeitsblöcke mit detaillierten Design-/Abnahmekriterien |
| Legal General | `app/Filament/Pages/General.php` und `app/Models/PublicContentSetting.php` | Aktuell nur `default_media_copyright_notice` und `legal_disclaimer` |
| Legal Public | `resources/views/pages/partials/component-sequence.blade.php` | `legal_disclaimer` erscheint als „Rechtlicher Hinweis“ nur wenn Komponente gesetzt |
| Media-Rechte | `app/Models/MediaAsset.php`, `app/Domain/Media/MediaAssetEditorialService.php` | `credit`, ALT und `inherit/override/none` für Copyright; getrennte Dateimetadaten noch **nicht** vorhanden |
| Medien-Pipeline | `app/Domain/Media/MediaIngestService.php`, `app/Domain/Media/PublicMedia.php`, `app/Http/Controllers/PublicMediaController.php` | Archivoriginal unverändert; GD-WebP-Thumbnails ohne gezielte IPTC/XMP-Übernahme; öffentliche Originale über X-Sendfile |
| Credit-Rendering | `resources/views/pages/artworks/show.blade.php`, `app/Domain/Content/JournalMediaRenderer.php` | Journal nutzt `effectiveCopyrightNotice()`; Artwork-Detail prüft direkt individuelles Feld – geerbter Notice kann fehlen |
| Rechteprotokolle | `app/Http/Middleware/SecurityHeaders.php`, `app/Http/Controllers/PublicSeoController.php`, `routes/web.php` | Frühe Implementierung: öffentlicher `tdm-reservation: 1`, `/.well-known/tdmrep.json` und Trainingsbot-Gruppen in robots.txt; Browser-/Ingress-Realtest **offen** |
| Analytics | `resources/js/matomo.js`, `config/analytics.php`, `resources/views/layouts/app.blade.php` | Tracker lädt bei aktivierter Konfiguration vor Einwilligung; keine Opt-in-/Ablehn-/Widerrufssteuerung |
| Infrastruktur | separates `Wiiii90/server-platform` | Ingress/Bandbreite/Matomo-Deployment gehören dort hin; **keine** Änderungen oder CI-Zugriffe ohne eigenen Auftrag |

## 3. Gesamtabnahme / Definition of Done (G0–G10)

Ein Arbeitsblock zählt erst als „FERTIG“, wenn nicht nur der Code, sondern seine erwarteten Grenzen bestätigt sind. Für den **gesamten Workstream** gilt:

- [ ] **G0:** Alle vier Blöcke als implementiert + relevante Tests tatsächlich ausgeführt + Benutzerabnahme dokumentiert, oder **konkret begründete und ausdrücklich akzeptierte Ausnahmen**.
- [ ] **G1:** General verwaltet fünf semantisch richtige Rechtsfelder, keine doppelten Owner, keine Datenverluste; Admin-Persistenz, Audit, Notifier, Reload, mobile Form.
- [ ] **G2:** About Artist/Contact hat genau einen redaktionell gesteuerten Rechtliches-Bereich, alle wirklich erforderlichen Dokumentteile erreichbar, direkt verlinkbar und ohne Footer/Overlay; Künstler kann ihn ändern/veröffentlichen; reale Browserabnahme.
- [ ] **G3:** Medienrechte/-Credits aus derselben Source-of-Truth; auf ausdrücklichen Wunsch derzeit **kein sichtbarer Copyright-Hinweis auf Artwork-Detailseiten**, Fotocredits bleiben erhalten. Metadaten/SEO und Rechtliches getrennt überprüfen; Bildurheber versus Werkurheber korrekt.
- [ ] **G4:** IPTC/XMP in öffentlichen originalgetreuen Mediendateien und abgeleiteten Varianten korrekt mit unabhängigen Werkzeugen nachgewiesen; archivierte Originalbytes/SHA unverändert; Farb-/Pixelqualität des Viewers erhalten; Datenschutzmetadaten geprüft.
- [ ] **G5:** Nach Ändern individueller oder genereller Rechteinformationen werden öffentliche Artefakte **versioniert** erneuert und nicht durch alte immutable Cache-URLs überdeckt. Alte IDs, Publikations- und Undo-Historie bleiben konsistent.
- [ ] **G6:** `robots.txt`, TDMRep, HTML-/Media-Header und Bild-SEO wurden über echte öffentliche HTTP-Antworten validiert; gewöhnliche Suchmaschinen bleiben zugelassen; kein irreführender „Download verhindert“-Anspruch.
- [ ] **G7:** Missbrauchsschutz begrenzt **gemessene** massenhafte Abrufe, ohne Galerie-Navigation, Zoom, SEO, Karten, normale Besucher, Bandbreite oder Server instabil zu machen; fehlerrichtige 429/Retry-/Cache-/Range-Semantik verifiziert.
- [ ] **G8:** Bei fehlender Analytics-Zustimmung wird **kein** Matomo geladen/angesprochen; Opt-in, gleichwertige Ablehnung, Widerruf, Reload, SPA, Cookie-Bereinigung und Bedienbarkeit getestet; Datenschutztext deckt reale Datenflüsse ab.
- [ ] **G9:** Finale rechtliche Texte und erforderliche Anbieteridentität/Erreichbarkeit sind durch den tatsächlichen Verantwortlichen authorisiert; redaktionelle Veröffentlichung liegt beim Künstler. Compliance-Prüfpunkte dokumentieren, kein erfundener Rechtsstatus.
- [ ] **G10:** Keine veralteten Komponenten/Altlasten, keine wiederholten provisorischen Mechanismen, alle stabilen Verträge aktualisiert, Endstatus extern nachvollziehbar. **Dann** Root-Arbeitsdatei einschließlich `AGENTS.md`-Pointer entfernen und die bleibende Architektur in `docs/` konservieren.

## 4. Arbeitsblock A — General, Rechtliches, Copyright-Anzeige

**Status:** ◐ Implementierung als zusammenhängender `dev`-Änderungssatz vorbereitet; Migration, lokale Tests und Browserabnahme stehen aus. **Priorität:** zuerst. **Domänen:** General, Custom Page, Public-Blade, Validation, Audit, Notifications.

**Bestehende General Legal Felder (nicht duplizieren):**

1. `default_media_copyright_notice` → kurzer geerbter Medienhinweis, **kein** allgemeiner Langtext.
2. `legal_disclaimer` → künftig **optionale Hinweise zu externen Links**, nicht Haftungsausschluss. Bestehende Daten ausdrücklich **migrieren/bewahren**, nicht löschen.

**Drei neue Felder in General:**

3. **Impressum** → verständliche echte Anbieterangaben.
4. **Datenschutz** → zutreffende Angaben zu Betreiberschaft, Kontaktformular, Hosting, Serverlogs, Matomo, Karten, Empfängern, Aufbewahrung und Rechten.
5. **Urheberrecht und KI-Nutzung** → Langtext mit TDM-/KI-Vorbehalt, Reproduktions-/Lizenzbedingungen, Anfragen und korrektem Hinweis auf Rechte Dritter.

**Gesamt: fünf redaktionelle Felder.** Technische Bot- oder Cookie-Flags sind **keine** zusätzlichen Legal-Textfelder. Geeignete mehrzeilige Editoren für Langtexte statt willkürlicher kleiner TextInputs; kontrolliertes Rendering ohne HTML-/XSS-Öffnung. Inhaltlich getrennte Bereiche, visuell General-Referenz beibehalten.

**Public:** Bestehende Komponente `legal_disclaimer` in eine redaktionell verständliche, stabile `Legal Disclaimer`-Komponente umgestalten. Die drei neuen Sektionen, optionaler externer Hinweis und sinnvoller kurzer Medien-Copyright dürfen **nicht** in fünf gestalterisch unabhängige Custom Components zerfallen. Die Komponente bleibt als normaler Custom-Page-Block sortier-, publish- und editierbar, auch in verschachtelter Bildkomposition, soweit ihr Domain-Vertrag dies vorsieht. Vorläufig **unter Contact**, kein Footer. Semantische HTML-Abschnitte, echte benannte Deep Links zu Impressum/Datenschutz/Urheberrecht, mobile/Keyboard/Screenreader, keine störende Überlagerung.

**Media Notice:** Die gemeinsame Source-of-Truth `effectiveCopyrightNotice()` bleibt für Metadaten/andere autorisierte Projektionen bestehen. Auf Artwork-Detailseiten darf **kein automatischer Copyright-Hinweis** gerendert werden; nur vorhandene Credits bleiben. Dies ist eine explizite User-Entscheidung vor der Präsentation. Journal/Custom/Gallery/Home weiter nach autorisierter konkreter Darstellung und Bildnutzungsrechten; kein ungefragtes sichtbares © auf jedem Bild.

**A-Abnahme:** Fünf Formularwerte persistieren exakt, auditieren und benachrichtigen nur bei Änderung; edit + close/reopen; publizierte/ungepublizierte Komponente; alte Hinweise migriert; keine Pflichtinhalte erfunden; reale vorhandene About-Artist-Folio-Darstellung vom Benutzer abgenommen.

## 5. Arbeitsblock B — Storage-Metadaten, Medien-Engine, SEO-Bildrechte

**Status:** ◻ NICHT BEGONNEN. **Abhängigkeit:** Kanonische General-Felder/Rechteprojektion aus A.

**Storage ergänzt nur fehlende Fakten:** Fotograf/Datei-Creator, Inhaber der Dateirechte, ggf. spezifische Nutzungsbedingungen und Lizenzanfrage-/Rechte-URL. Vorhandene `alt_text`, `credit`, Copyright-Mode und Notice **weiterverwenden**. Werkurheber (Künstler) und Foto-Urheber nicht verwechseln; mehrere Rechteinhaber/Lizenzen nur so weit modellieren, wie tatsächlich erforderlich.

**Ein zentraler Media Rights Resolver:** General-Standard + MediaAsset Override/None + Artwork- und Fotoherkunft → gleicher Ausgabestand für Storage, öffentliche Bildnachweise, IPTC/XMP, HTTP-Header, Web-Seiten und `ImageObject`-SEO. Rechteinhaber **nicht** pauschal auf alle Assets behaupten. Der bestehende Modell- und MediaAssetEditorialService-Owner bleibt zuständig (inklusive Audit/Undo, echten Notifications im Livewire-UI).

**Datei-Pipeline:** Original bleibt **unverändertes Archiv** (SHA-256/Größe/Storage-Key dürfen nicht in place überschrieben werden). Öffentliche Artefakte/Varianten bekommen gültige IPTC/XMP-Informationen: Creator, Copyright Notice/Owner, Credit Line, Rights Usage Terms, Web Statement of Rights sowie den passgenauen IPTC-Datamining-Wert „Prohibited except for search engine indexing“, soweit rechtlich zutreffend. Öffentliche **Vollauflösung bleibt voll**, inklusive Farbraum/ICC; Thumbnails vorhandener Größen dürfen ihr bestehendes Thumbnail-Verhalten behalten. Sensible EXIF/GPS-/Seriennummerdaten aus öffentlichen Dateien entfernen, vorhandene Dritt-Lizenzen nicht still löschen. Formatabhängigkeit JPEG/PNG/WebP; ExifTool o. ä. nur nach Prüfung der Deployment-/Systemarchitektur, keine unkontrollierten externen Prozesse.

**Versionssichere Ableitung:** Bei General-/Storage-Rechteänderungen neu ausgeben, Public-Version/ETag/Cache-URL passend aktualisieren, nicht `immutable`-URLs auf alte Bytes verweisen lassen. Bestehende Mediapublikation, Preview, IDs, Referenzen, Publikationssnapshot, Audit, Export und Backup mitprüfen. Keine zusätzlichen ad-hoc Nebenablagen oder paralleles CDN/Medienmodell.

**SEO:** Metadaten und Schema.org `ImageObject` mit korrektem `creator`, `creditText`, `copyrightNotice`, `license`, `acquireLicensePage` nur auf echte Texte/URLs stützen; Google Images und Sitemap erhalten, ALT und Canonical beachten. Keine versehentliche offene Lizenz oder Blockade der Bilder durch SEO-„Schutz“.

**B-Abnahme:** Nachweis in öffentlichen Full-Quality-Dateien **und** WebP-Varianten durch unabhängigen IPTC/XMP-Reader; alter Originalhash identisch; geänderte Rechte sind im CDN/Browserverhalten sichtbar; Foto-Credit/No-Notice korrekt; Visuelles ohne Qualitätsverlust; Regressions- und Datenmigrations-Tests.

**Optionale spätere Erweiterung:** C2PA/Content Credentials als kryptografisch signierte Herkunftsbehauptung mit nachvollziehbarem Signer, NICHT als Downloadblockade oder falscher Urheberschaftsbeweis. Erst nach stabiler Kernpipeline.

## 6. Arbeitsblock C — KI-Crawler, Scraping, Missbrauchsschutz, Download-Erschwerung

**Status:** ◐ VORARBEIT IM REPO; PRODUKTABSCHLUSS OFFEN.

**Vorhanden (nicht als endgültig verifiziert):**
- `robots.txt` im `PublicSeoController` mit Trainings-/Datensammel-User-Agents `GPTBot`, `Google-Extended`, `ClaudeBot`, `CCBot`. Normale Suche bleibt zugelassen. Liste gelegentlich evidenzbasiert pflegen, aber **keine** unrealistische Forderung jeden lokalen Modellanbieter einzeln zu finden.
- Zentraler `tdm-reservation: 1` in `SecurityHeaders` für normale öffentliche HTTP-Antworten (auch Media, abhängig vom effektiven Ingress).
- `/.well-known/tdmrep.json` als allgemeiner maschinenlesbarer Vorbehalt.

**Offen:** Über HTTP, Browser und Produktions-Ingress prüfen, ob Header bei X-Sendfile, Range/206, Vorschauen, Dateien und Dot-Routen wirklich ankommen. robots/TDM nur freiwillige maschinenlesbare Signale.

**Serverseitige wirkliche Abrufkontrolle:** Normales menschliches Betrachten und vollständige Bildauflösung freigeben; massenhaftes URL-/Original-Fetching, wiederholte enumerierte Zugriffe und auffällige Bandbreite **erkennen, protokollieren und limitieren**. Eine zentrale bestehende Ingress/Application-Autorität wählen, keine JS/Timer-„Protection“. Für Regeln vorab Traffic-/Nutzungsprofil, legitime Viewer-Navigation, Googlebot/Imagebot, Suchmaschinen-Cache, Rate/Ranges, IPv4/IPv6, Proxy, gemeinsame IPs, Retry-After und False Positives berücksichtigen. Getrenntes `server-platform` nur auf expliziten Auftrag verändern; lokal kann man die App-seitige Autorität designen und testen.

**Evaluieren statt still einschalten:** Hotlink/Referer-Schutz (umgehbar, SEO-/Preview-Gefahr), signierte/kurzlebige Media-URLs (kein Byte-Schutz, Index-/Cache-Gefahr), Abwehr bekannter missbräuchlicher Clients. Nur aktivieren, wenn sie konkret helfen und nicht Bildsuche/Portfolio schädigen.

**Rechte-Durchsetzung/Nachverfolgung:** Optional Reverse-Image-/Fingerprint-Suche, Rechte-Nachweis und nachvollziehbarer Takedown-/Lizenzanfrage-Prozess. Keine Rechtebehauptung über Fremdfotografien.

**C-Abnahme:** Beispielverkehr normal/abusive/SEO gemessen, Rate Limits wirksam und ohne Kollateralschäden; Fehlermeldungen/429 und Cache/Range korrekt; realer Public Viewer/Google Images unverändert; Dokumentation der Grenzen (kein absoluter Downloadschutz).

## 7. Arbeitsblock D — Matomo, Consent, Datenschutz, produktiver Cutover

**Status:** ◻ NICHT BEGONNEN. **Sicherheits-/Rechts-Blocker:** öffentliche Matomo-Browsertracking-Freigabe ohne vorherige Zustimmung nicht zulässig, solange das gewählte Betriebsmodell sie verlangt.

**Ist:** `resources/js/matomo.js` lädt bei aktiviertem `MATOMO_TRACKING_ENABLED` sofort Matomo, sendet Pageviews/Events und aktiviert Heartbeat. Kein opt-in/decline/withdraw. Das Setup des tatsächlich laufenden Servers wurde hier nicht inspiziert.

**Ziel:** Ein einziger von der Website gehaltener Consent-Lifecycle. **Vor Zustimmung:** kein Matomo-JS, keine Pageviews, kein Event, kein Heartbeat, keine Trackingcookies, keine Browser-Netzwerkrequests an Matomo. **Zustimmung:** Start genau einmal, pro Livewire-Pagenavigation genau definierter Pageview. **Ablehnung:** weiter volle künstlerische Nutzung, kein Dark Pattern; gleichwertiges Ablehnen/Zustimmen. **Widerruf:** unmittelbar wirksam, passende Cookies entfernen, Listener/Observer stoppen, Entscheidung mit Ablauf dauerhaft speichern. Matomo-Consent-APIs als zusätzliche Verteidigung, nicht als Ersatz des Preload-Gates.

**UI:** keine permanente Leiste am Footer, kein Artwork-overdeckendes Vollbild oder eigenmächtiger Public-Style. Unaufdringliche, gut zugängliche Zustimmung/Datenschutzeinstellungen; es muss trotzdem eine Wiederaufrufmöglichkeit geben, ohne den Künstler zu verpflichten, in Custom Pages technische Cookieschalter zu setzen. Den tatsächlichen Standort/Design mit Benutzer am Browser abnehmen.

**Datenschutztext:** konkret aus den realen Datenflüssen und der identifizierten verantwortlichen Person formulieren: Hosting + Serverlogs, Formular + Mailversand, Matomo-Host/Retention/Anonymisierung, Kartenkacheln, Cookies/Storage, Betroffenenrechte/Kontakt. General enthält den redaktionellen Datenschutzhinweis, die technische Trackingkontrolle liegt im Runtime.

**D-Abnahme:** kalter Browser/ohne Cookies, Ablehnen, Zustimmen, Reload, SPA-Wechsel, Widerruf, erneut laden, bereits vorhandene alte Cookies; Mobile/Keyboard/Screenreader; Browser-Netzwerk zeigt **null Matomo-Requests vor Zustimmung**. Server-Flags, Laufzeit-Analytics, Logs und Live-Site vor späterem Cutover kontrollieren. Solange nicht abgeschlossen: öffentliches Browsertracking nicht ungesichert aktivieren. **Keine** implizite Produktionskonfigurationsänderung durch diesen Workstream.

## 8. Schnittstellen, Reihenfolge und keine verlorenen Ideen

- **Reihenfolge für Implementierung:** A → B → C → D, sofern der Nutzer die Priorität nicht ändert. C-Teil TDM bereits vorhanden. Privacy/Matomo ist für Livebetrieb ein unabhängiges Stop-Gate; D darf deshalb bei passender Abstimmung vorgezogen werden. Ein Block = eine in sich geschlossene Implementation mit allen Tests/Dokumenten, **nicht** zehn autonome Codex-Wellen.
- **Fachliche Entscheidungen offen bis echter UI-Abnahme:** ob die kompakten Legal-Sektionen als native `details`/Disclosure, Anchor-Navigation oder anderer kleiner komponierter Teil erscheinen; Consent-Settings-Anker ohne Footer; tatsächliche Serverrate-Limits, legitime Image-Bot-Ausnahmen; genaue urheberrechtliche Texte, Adresse, Rechte einzelner Fotos, Hostingdaten.
- **Nicht zu vergessen:** Bildmetadaten in Original-öffentlichen Ableitungen UND Vorschaubildern, geerbter Copyright-Fehler auf Artwork-Detailseite, `ImageObject` SEO, C2PA optional, aggressive automatische Datensammlung, Hotlink-/signierte URL Abwägung, `X-Sendfile`-Header, IPv6/NAT-Fehlersperren, Datenschutz der Exif-Daten, Bilder-SEO, Matomo SPA, Bestandsmedia-/Rollback-Migration, Cache/ETag/immutable, General-Rechtstexte und öffentliche Anzeige; **alles in einen kohärenten Eigentümerfluss**.
- **Nicht aufzublähen:** keine Pflicht für extra Rechtstext pro Media oder KI-Bot, keine zusätzlichen Crawler-Abwehr-Plugins oder laufenden Timer, keine Duplicate Storage/Public-Media-Technik, keine unautorisierte Server-/CI-Arbeit.

## 8a. Verifizierte Datenflüsse und Entscheidungen für die nächste juristische Textrunde (2026-10-15)

**Gültige Nutzerentscheidung:** Impressum und Datenschutz bleiben vorerst unter About Artist/Contact in der existierenden editierbaren `Legal Disclaimer`-Komponente. Keine neue globale Impressumsverlinkung, kein Footer und kein entsprechender UI-Umbau ohne neue Freigabe. Matomo-Consent-UI vorerst ebenfalls **zurückgestellt**, nicht als technisch erledigt kennzeichnen. Die rechtliche Restproblematik wird nicht dadurch „behoben“, dass wir sie aus dem Plan löschen. Browsertracking sollte nicht ohne den nötigen Betriebs-/Einwilligungscheck freigegeben werden.

**Inhalte und General-Quelle:** Alle UI-Labels Englisch, die redaktionellen Langtexte dürfen Deutsch sein. `General.public_email` ist die öffentliche E-Mail; `General.contact_recipient_email` ist **privater Zustellempfänger**, kein Impressumskontakt. `ContactDeliveryReadiness::resolveRecipient()` nutzt beim Zustellempfänger zuerst General und danach `config('contact.recipient')` als Server-Fallback. Für Impressum/Privacy zentral auf denselben zutreffenden öffentlichen Kontakt verweisen, keine voneinander abweichenden statischen E-Mail-Adressen in drei Textfeldern. Postalische Anschrift kommt ausschließlich als redaktioneller Text **in das vorhandene Impressum-Feld**; keine weiteren General-Adressfelder erfinden. Gesetzlich tatsächlich erforderliche Angaben dürfen trotzdem nicht durch einen bloßen unbestimmten Verweis entfallen.

**Quellcodebestätigte Datenverarbeitung (`moeller-lars`, `dev`):**
- `PublicContactController`: Name, E-Mail und Freitext (maximal 160/320/5000 Zeichen) werden als `ContactMessage` in der PostgreSQL-Datenbank gespeichert **und** bei vorhandener Konfiguration per SMTP als E-Mail an den Zustellempfänger gesendet. Bei SMTP-Ausfall bleibt der Datensatz bestehen; der Admin kann Nachrichten über `DashboardFeed::deleteContact()` einzeln löschen. Eine automatische Speicherfrist für `contact_messages` wurde in den geprüften Quellen **nicht** gefunden. Die Formulierung „nur per Mail verarbeitet“ ist falsch.
- `resources/js/matomo.js`: bei gesetzter Laufzeit-Trackingkonfiguration sofortiges Laden von `matomo.js`, Pageview und Klick/View-Events, Link Tracking sowie Heartbeat; kein Consent-Gate. Aus `config/analytics.php` allein ist der **tatsächliche** Production-/Validation-Schalter nicht abzulesen. `server-platform/docs/CURRENT-STATE.md` nennt als **beabsichtigte Validation-Konfiguration** Tracking=false/Reporting=true, kein Beweis des live aktiven Zustands.
- `resources/views/pages/exhibitions.blade.php` setzt die Ausstellungs-Karte in einen zunächst geschlossenen Dialog; beim Öffnen lädt die Anwendung lokales Leaflet und die Karte benötigt externe OSM-Kacheln. OSM-URLs kommen aus `ExhibitionMapPresentation`. Reale Netzwerkanfragen/deren Trigger und Datenschutzhinweis im Browser prüfen.
- `Wiiii90/server-platform` auf `main` (Repository-Vertrag, nicht Live-Beweis): Hosting-Einstieg über Caddy, Apache-Backend, Produktionsanwendung mit PostgreSQL und privatem Medienvolume, SMTP über selbst betriebenes Stalwart/Mail-Setup, Matomo On-Premise separat. `docs/MATOMO.md` definiert **zusätzlich zu browserseitigem Matomo** serverseitigen Import rotierter Apache-/Caddy-Zugriffslogs in eine **andere Matomo-Website**. Caddy-Quelllogs enthalten u.a. IP, Pfad und User-Agent, keine Abfrageparameter oder Cookies im speziellen Importlog. Das *Deaktivieren von Browsertracking* deaktiviert nicht automatisch serverseitige Access Logs oder deren Matomo-Import; das ist gesondert wahrheitsgemäß im Datenschutztext zu berücksichtigen.
- `server-platform/docs/LOG-RETENTION.md`: Caddy-Importquelle Rotationsgrenzen 8 Dateien / 192 Stunden; systemd-Journalziel 14 Tage; Matomo-Datenbank- und Apache-Log-Aufbewahrung müssen anhand tatsächlicher Konfiguration/Deployment geprüft werden. Repo nennt keine verbindliche Löschfrist für Contact Messages. Nicht aus diesen Grenzen pauschal auf alle gespeicherten personenbezogenen Daten schließen.
- **Fehlende Tatsachen, vor endgültigem Datenschutztext zu verifizieren:** tatsächliche verantwortliche Betreiberidentität/Adresse und offizielle öffentliche E-Mail; aktueller Trackingstatus, Matomo IP-Anonymisierung/Opt-out/Retention und Log-Importeraktivierung; Apache-Logs und Zusteller-Relay; Kontakt-Nachrichtenlöschpraxis; Karten-Kacheln bei geöffnetem Dialog; Backup-/Restore-Aufbewahrung. Repositoryzustand ist kein Live-Konfigurationsnachweis.

**Bestätigte Bildpipeline / technische Entscheidung:**
- `MediaIngestService` archiviert jede Originaldatei **byteidentisch mit SHA-256** und erzeugt bis 960px WebP-Thumbnails via GD; XMP/IPTC-Ausgabe in publizierten Full-Quality-Dateien oder Thumbnails gibt es noch **nicht**. `MediaAssetEditorialService::updateMetadata()` verarbeitet nur `alt_text`, `credit`, `copyright_notice_mode`, `copyright_notice`; er erzeugt keine neue Mediendatei. `PublicMediaController` liefert bisher Storage-Original bzw. Variante mittels `X-Sendfile`, SHA-256-ETag und ein Jahr `immutable` aus. Deshalb erfordert nachträgliche Rechteänderung **eine versionierte öffentliche Datei-/Cacheidentität**, ohne Archive zu verändern.
- IPTC = International Press Telecommunications Council (definiert Bildrechte-/Informationsfelder). XMP = Extensible Metadata Platform (standardisierte Einbettung, ISO 16684). IPTC `Data Mining`-Vokabular bietet **`DMI-PROHIBITED-EXCEPTSEARCHENGINEINDEXING`** als URI `http://ns.useplus.org/ldf/vocab/DMI-PROHIBITED-EXCEPTSEARCHENGINEINDEXING`. Nicht eine frei erfundene Zeichenkette in irgendein Feld schreiben. Für öffentliche JPEG/PNG/WebP ein unterstütztes, mit echtem Format gegengeprüftes Werkzeug wie ExifTool verwenden; ExifTool unterstützt XMP in WebP. ICC/Farben und unveränderte Pixel im Vollformat getrennt testen; bestehende Originale nicht anfassen.
- Medienrechte betreffen ggf. zwei Schöpfungen: das **Kunstwerk** und die **Fotografie/Datei** des Werks (§ 72 UrhG, falls einschlägig). Bei selbst angefertigten oder eindeutig lizenzierten Fotos keine überflüssigen Fotografen-Pflichtfelder. Bei Drittaufnahmen Lizenz, Fotograf/Creator, Rechteinhaber und etwaige Credits **assetbezogen** und wahrheitsgemäß; Künstler als Werkurheber bleibt eigene Information. Der gewünschte **Verzicht auf sichtbare automatisierte Artwork-Copyright-Zeilen** bleibt bestehen. Metadaten/strukturierte SEO brauchen keine zusätzliche sichtbare Detailseiten-Caption.
- Ingress laut `server-platform/host/etc/caddy/sites-enabled/moeller-lars.caddy`: Caddy verwaltet Redirects, den geschützten Pfadfilter und Reverse Proxy. Spezifische Mengen-/Bandbreitenlimits für öffentliche Originalmedien wurden dort nicht vorgefunden. Keine eigenmächtige Serveränderung und keine falsche Behauptung vollständiger Download-Verhinderung. Nach Media Rights Resolver + öffentlichen Artefakten gezielt eine **einzige** Ingress-/Media-Request-Missbrauchsregel auf Basis tatsächlich gemessenen Verkehrs planen. Echte Suchmaschinen-Bildindexierung und Viewer erhalten.

**Nächster aktiver Bauabschnitt:** B (Rechte- und Metadaten-Pipeline) mit Datenbankschema/Storage-Editor, zentralem Rights Resolver, versionierten Full-Quality-Ausgaben/Thumbnails, IPTC-XMP-Writer und unabhängigen Format-/Qualitätstests; anschließend Bild-SEO und serverseitige Scraping-Abwehr. A-Migration/PHP-/Browsertests nach wie vor separat offen. Keine CI, kein Deploy, kein Servereingriff.

## 9. Lebendes Übergabeprotokoll / Statusaktualisierung

**Arbeitsstand (2026-10-14, Erstanlage)**

| Slice | Quellcode | Tests | Browser/Runtime | Nutzerabnahme | nächster Schritt |
| --- | --- | --- | --- | --- | --- |
| A General/Rechtliches | Drei neue General-Felder, bestehende Legal-Komponente, Public-Details/Fragments und Artwork-Vererbung als Änderungssatz; Commit nachprüfen | Regressionstests geschrieben, noch nicht ausgeführt | Offen | UI nicht abgenommen | Isolierte Migration/Pest/Node-JS/Browserprüfung ausführen, Nutzerabnahme für Rechtliches |
| B Metadaten/SEO | Offen | Offen | Offen | fachliches Ziel angenommen | Nach A Public-Artefakt-Pipeline planen/umsetzen |
| C KI/Bot/Download | TDM/Robots **teilweise** vorhanden | Frühe Tests ergänzt; nicht hier ausgeführt | Offen | fachliches Ziel angenommen | Server-Missbrauchskontrolle + echte Header/SEO-Abnahme |
| D Consent/Datenschutz | Offen | Offen | Offen | fachliches Ziel angenommen | Consent-Lifecycle + Datenschutzdaten integrieren |

**Aktiver Slice:** A (Quellcode implementiert, Benennungen zurück auf Englisch korrigiert; Migration, lokale Tests und Browserabnahme weiter offen). **Arbeitseigentümer:** dieser Rechte-Workstream; andere laufende Chats können sich gleichzeitig um unabhängig benannte Dateien kümmern. Bevor gemeinsame Owner wie `General.php`, `PublicContentSetting.php`, `MediaAsset.php`, `component-sequence.blade.php` oder `AGENTS.md` verändert werden, aktuelle HEAD-Diffs prüfen und laufende Paralleländerungen versöhnen.

**Fortschrittslog – nur wesentliche Zustandswechsel eintragen:**
- 2026-10-14: General-Feld `legal_disclaimer` im Admin in **External Links Notice** umbenannt (eigene Glühbirnenhilfe, Persistence-Feedback); Public-Unterabschnitt gleich benannt. Die bestehende umfassendere Custom-Page-Komponente heißt weiterhin **Legal Disclaimer**; Datenbankschlüssel und bestehende Texte bleiben unberührt. Vier General-Legal-Textareas bleiben regulär scrollbar, aber ohne sichtbare Scrollbar, wie die bestehenden scrollbaren Admin-Stages. CSS auf diesen Bereich begrenzt; Regressionstest angepasst. Lokaler Laufzeit-/Browsertest weiterhin offen.
- 2026-10-14: Nachkontrolle der vier Editor-/Home-Projektionen: auch die kombinierte Platzhalterformulierung `Legal Disclaimer from General` vollständig englisch; ohne Schema- oder Funktionsänderung.
- 2026-10-14: **Vorpräsentation – verbindliche UI-Entscheidung:** General → Legal: kurzes Default Copyright Notice auf voller Breite, darunter exakt vier gleich große Textareas im responsiven 2×2-Raster (Imprint, Privacy Policy, Copyright & AI Use, Legal disclaimer). Alle fünf Labels nutzen die gleichen AdminHelp-Glühbirnen wie Contact. Artwork-Detailseite zeigt vorerst **keine** Copyright-Notice-Zeile (auch kein geerbter Standard); fotografischer Credit bleibt erhalten. Professionelle DE-Inhaltstexte werden **erst nach** den Feldänderungen separat geliefert, ohne automatische Datenmutation. Änderungen an der Source als Commit, Browserabnahme offen.
- 2026-10-14: **Explizite Nutzerkorrektur:** Die englische Produktoberfläche darf nicht in deutschen UI-Text übergehen. Den ursprünglichen Komponenten-Namen `Legal Disclaimer` im Admin, Home, Custom Pages und Public wiederhergestellt; neue General-Feldlabels, Errors, Platzhalter, Notifications, Abschnittstitel und Link-Fragmente auf Englisch vereinheitlicht. Gespeicherte Daten/Kennung `legal_disclaimer` sowie Legal-/Privacy-Funktionalität bleiben unverändert; Tests/Browser weiterhin nicht ausgeführt.
- 2026-10-14: Früher technischer Schritt (`eedc0cbb4274e1ab7dd8408796720baf02eb7539`) führte TDM/Header/Robots ein, aber **keinen** Rate-Limiter, Copyright-Metadaten oder Matomo-Consent.
- 2026-10-14: Fachliche Gesamtarchitektur in `docs/PUBLIC-RIGHTS-AND-PRIVACY.md` (`f03bd7e9aa3634aca9dd43cf736efa5eb956d1a9`) zusammengeführt.
- 2026-10-14: Diese Root-Arbeitsakte eingerichtet (`658b8f5464cdf00ad8ca2e986d74b24b1e34d523`), noch keine Implementierung.
- 2026-10-14: Historische `legal_disclaimer`-Formatierung wird auf Änderungen *anderer* General-Felder nicht mehr beiläufig normalisiert; eigener Regressionstest. Explizites Editor-Speichern normalisiert wie gewohnt. Noch kein lokaler Test-/Browser-Nachweis.
- 2026-10-14: Kritischer Publication-Schema-Check: neue General-Felder müssen in **public und committed** identisch ergänzt werden. Migration korrigiert, Integrationstest für `PublicationSchemaGuard` und Publication-Commit ergänzt. Ohne diese Korrektur wäre der Cutover der neuen Felder blockiert. **Testausführung noch offen.**
- 2026-10-14: Auch Home-Labels und Custom-Page-Validierungsfehler auf **Rechtliches** abgestimmt; keine Änderung der gespeicherten `legal_disclaimer`-Kennung oder der Veröffentlichungspolitik.
- 2026-10-14: Block A Quelle in `4f2b0ed1f312911a5ec7a2fe106d849e45c4e232` committed; separat anschließend Hashchange-Event-Callback auf parameterlose Invocation korrigiert, damit das Browser-Event nicht als Document-Root missverstanden wird. Noch keine Laufzeitabnahme.
- 2026-10-14: **Block A Implementierung**: `legal_imprint`, `legal_privacy`, `legal_usage_rights` als neue nullable General-Felder mit 20k-Validierung und Event-Autosave/Audit/Notification; unveränderte historische Kennung `legal_disclaimer`, jetzt als „Rechtliches“ mit Abschnittsprojektion und ankergesteuerten, nativen Details gerendert. Bestehenden Alttext unverändert als optionale externe Hinweise bewahrt; Artwork-Detail zeigt effektiven Copyright-Hinweis. Quell-/PHP-/JS-Regressionstests ergänzt. **Noch keine Testausführung, Migration oder Browserabnahme behaupten**; Commit-SHA über git log/aktiven Head ermitteln. Alte Komponentendaten behalten ihre interne Kennung.

**Nach JEDEM Slice aktualisieren:** Implementierungs-SHA, veränderte Systeme, genaue Tests mit Befehlen/Ergebnissen, gezielt noch unverified/browser-pending, neu entdeckte Risiken, offene Entscheidungen, aktuell freigegebener nächster Slice. Kein „fertig“ bei nur statischer Sicht. Gesondert bestätigen, ob die Originalbilder unangetastet geblieben sind.

## 10. Schließen / Entfernen der Root-Datei

Diese Datei ist absichtlich **temporär**. Erst wenn G0–G10 erfüllt oder ausdrücklich und nachvollziehbar abgenommen ausgenommen sind:

1. Alle dauerhaft erforderlichen Architekturregeln, technischen Designentscheidungen und Betriebs-/Datenschutzprozesse in passende `docs/`, `AGENTS.md` und Tests übertragen und gegen Code verifizieren.
2. In `AGENTS.md` den Verweis auf diese Root-Datei wieder entfernen, ebenso sonstige temporäre Hinweise.
3. Diese Root-Datei **löschen**; kein „final-v2“, keine zweite aktive Checkliste, keine bleibende tote Arbeitsakte.
4. Den tatsächlichen Endzustand, real verifizierte Systeme und verbleibende optionale Verbesserungen im abschließenden Handoff nennen. Kein eigenes Release nach `main` oder Deploy ohne gesonderte Autorisierung.
