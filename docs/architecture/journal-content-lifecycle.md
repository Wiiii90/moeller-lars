# Journal-Inhalte: Blog-/Exhibition-Lifecycle, Medien und öffentlicher Lesepfad

**Quellstand und Nachweisart:** Bestehende Architekturbeobachtungen mit gezieltem Abgleich zentraler Lese-, Schedule-, Exhibition- und Medienpfade bis `dev@dee05e4d7bc0ac70081a4c8f998442701bc7d630`. Der Abgleich prüft **Quellcode**, nicht ausgeführte Tests, Browserverhalten oder bereits veröffentlichte Daten. [Public-Vertrag](../public/site.md) beschreibt die Darstellung, [Exhibition-Editorial](../content/exhibitions.md) die fachlichen Felder, [Rich Text](../content/rich-text.md) den gemeinsamen Editor, [Medienreferenzen](media-reference-and-cleanup.md) die Lösch-/History-Sicherheit.

Dieses Dokument beantwortet eine Architekturfrage: **Wie komponiert ein Journal die beiden Eintragsarten, ihre eigenen Zustände, Media-Referenzen und den autorisierten Public-Lesepfad, ohne Zuständigkeiten zu vermischen?**

## Getrennte Zustandsachsen statt eines vereinheitlichten „Journal-Status“

Ein `SiteSection` vom Typ Journal verweist über seine Identität auf BlogPost- oder Exhibition-Einträge. Das aktive `JournalTemplate` bestimmt, **welche** Eintragsfamilie Admin und Public präsentieren; ein Wechsel zwischen Blog und Exhibitions löscht die gerade inaktive Familie nicht ([Template-Lifecycle](lifecycles.md#8-journal-templatewechsel-präsentation-statt-inhaltsmigration)).

Drei weitere Zustände sind unabhängig: (1) Publikation der Journal-`SiteSection`, (2) Domain-State eines Eintrags und (3) die Working-/Committed-Version. Der Artikelstatus allein öffnet deshalb weder eine öffentliche Route noch eine Medien-URL.

| Dimension | BlogPost | Exhibition |
| --- | --- | --- |
| Gespeicherte States | `draft`, `scheduled`, `published`, `unpublished`, `archived` | `draft`, `published`, `archived` |
| Public-Eintrag | `published` mit `published_at <= now()` **oder** `scheduled` mit `scheduled_at <= now()` | `state=published` |
| Zusätzlich erforderlich | aktives Blog-Template, veröffentlichte SiteSection und Committed-Lesekontext | aktives Exhibitions-Template, veröffentlichte SiteSection und Committed-Lesekontext |
| Authentifizierte Preview | nicht archivierte Posts des gewählten Templates aus Working | nicht archivierte Exhibitions des gewählten Templates aus Working |
| Zeitlicher Status | Fälligkeit ist ein Abfragefilter, **keine** automatische Statusänderung | `temporalState(now)` ergibt `upcoming/current/past/unknown` unabhängig von Published |
| Strukturierte Medien | `JournalEntryMedia` Cover und Gallery, Rich-Text-Body | derselbe `JournalEntryMedia`-Owner, Rich-Text-Description; `gallery_enabled` steuert sichtbare Gallery |

Eine terminierte Veröffentlichung braucht zunächst einen expliziten **Publication-Commit**, der den Scheduled-Stand ins öffentliche Committed-Schema überträgt. Erst danach kann der fortschreitende Zeitpunkt den öffentlichen Read-Filter erfüllen. Zeitablauf allein schreibt **keinen** neuen `published`-Status und keine neue Committed-Version; eine vergangene Exhibition wird nicht automatisch unpublished. Die autoritativen Leseregeln stehen in [`BlogPost::scopePubliclyVisible()`](../../app/Models/BlogPost.php) und [`Exhibition::temporalState()`](../../app/Models/Exhibition.php).

## 1. BlogPost: Editorial-Transaktion, Schedule und Read-Guard

[`BlogEditorialService`](../../app/Domain/Content/BlogEditorialService.php) besitzt die fachlichen Statusübergänge unter Datenbanktransaktion und Datensatzsperre:

- **Publish:** akzeptiert Draft/Scheduled/Unpublished, entfernt `scheduled_at`, prüft Body/Rich Text und benötigte Medien und protokolliert den wirklichen Übergang. Ein bereits Published Post ist dabei ein No-op.
- **Schedule:** verlangt einen **zukünftigen** Termin und einen publizierbaren Inhalt. Der neue `scheduled`-State und `scheduled_at` werden gespeichert; das Scheduling ist kein Hintergrundjob, der den State später selbst ändert.
- **Unpublish/Archive/Restore Draft:** unterschiedliche erlaubte Herkunftszustände und jeweils eigene Audit-Übergänge. Published oder Scheduled Posts dürfen nicht direkt gelöscht werden. Nach erster Veröffentlichung/Terminierung sind ihre Slugs geschützt; ein Journal-Wechsel benötigt eine ausdrücklich dafür vorgesehene Operation.
- **Edit und Media:** tatsächliche Metadaten- **oder** strukturierte Medienänderungen werden auditiert; ein gewöhnlicher Edit-No-op soll nicht als echte Änderung gemeldet werden.

**Aktueller, eng begrenzter Quellenbefund:** Im überprüften `BlogEditorialService::schedule()` gibt es **keinen Gleichheits-Guard** für `state=scheduled` mit genau demselben zukünftigen Termin. Nach `save()` wird auch für diesen Fall ausdrücklich `blog_post.scheduled` auditiert. [`JournalWorkspace::schedulePostAction()`](../../app/Filament/Pages/JournalWorkspace.php) versendet bei jedem erfolgreichen Domain-Rücksprung `Publication scheduled`, ohne selbst Werte zu vergleichen. Zwei identische Aufrufe können somit auf Quellpfadebene eine weitere Audit-/Erfolgsmeldung auslösen, obwohl der sachliche Termin unverändert bleibt. **Nicht** behauptet wird eine bereits ausgeführte Reproduktion, ein tatsächlicher Undo-Receipt-Zähler oder eine vom Nutzer akzeptierte Änderung der Sollsemantik. Das ist eine konkrete zu klärende No-op-Frage, kein allgemeiner Audit-Auftrag.

**Read-Verhalten:** [`BlogEditorialService::publicQuery()`](../../app/Domain/Content/BlogEditorialService.php) nutzt `BlogPost::scopePubliclyVisible()`. Auch nach Fälligkeit kann der gespeicherte State weiterhin `scheduled` heißen. Eine Admin-Kennzahl, die diese State-Gruppe zählt, ist daher **nicht automatisch** gleichbedeutend mit „noch in einer auszuführenden Queue“. Listing, Einzelroute, Sitemap und Medien-Eligibility müssen denselben Read-/Zeit-/Committed-Kontext beachten; Public darf nicht einfach überall auf `state=published` reduzieren.

## 2. Exhibition: Editorial-/Temporal-State und Maps

[`ExhibitionEditorialService`](../../app/Domain/Content/ExhibitionEditorialService.php) trennt die persistierten Übergänge von der Kalenderdarstellung:

- **Publish** wechselt von Draft/Archived nach Published und prüft Zeitraum, verfügbare Medien und bei eingeschalteter Karte Koordinaten. Bereits Published ist ein No-op.
- **Unpublish** setzt Published auf Draft; es gibt keinen eigenen persistierten Exhibition-`unpublished`-Wert.
- **Archive** bewahrt den vorherigen Zustand in `archived_from_state`. **Restore** prüft die Public-Readiness erneut, bevor ein vorher publizierter Eintrag wieder Published werden darf; bei fehlender Bereitschaft fällt die Rückkehr bewusst auf Draft. Ein Restore ist somit keine garantierte Wiederveröffentlichung.
- **Temporal State** aus `starts_on`/`ends_on` kann Upcoming, Current, Past oder Unknown ergeben, ohne den gespeicherten Editorial-State zu ändern. Bei einer Ausstellung ohne Enddatum gilt nur der Starttag als Current; danach Past.

**Organizer, Venue, strukturierte Adresse, Sichtbarkeit, Vernissage und Datums-Override** gehören vollständig zum [Exhibition-Editorial-Vertrag](../content/exhibitions.md), nicht zu einer zweiten fachlichen Spezifikation hier. Die [Map-Architektur](exhibition-map.md) besitzt den konkreten Edit-/Geocoding- und Fehlerablauf. Sie nutzt dieselbe Exhibition-Transaktion: geänderte Adressfelder entwerten alte Koordinaten; eine aktive Karte benötigt neu ermittelte gültige Daten, und Änderungen eines Published-Eintrags müssen erneut die Public-Readiness erfüllen.

## 3. Geteilte JournalEntryMedia und mittelbare Zustandsänderungen

[`JournalEntryMediaService::syncStructuredMedia()`](../../app/Domain/Content/JournalEntryMediaService.php) ist für BlogPost **und** Exhibition derselbe Cover-/Gallery-Schreibowner. Nur übergebene Medienfelder werden aktualisiert; selbst ohne schmutzige Post-/Exhibition-Felder ist ein **media-only**-Edit eine echte Änderung. Die persistenten JournalEntryMedia-Referenzen gehören jeweils genau einem dieser beiden Domain-Einträge; kein frei erfundener generischer Morph-Owner.

Die [öffentliche Media-Readiness](../../app/Domain/Content/JournalEntryMediaService.php) prüft Referenzen, Verfügbarkeit, Varianten und eingebettete Rich-Text-Medien. **Eine Exhibition mit `gallery_enabled=false` behält ihre gespeicherten Gallery-Usages**, aber diese werden für ihre aktuelle öffentliche Bereitschaft ausgelassen. Spätere Aktivierung muss sie erneut berücksichtigen. Ein MediaAsset in Working oder in einer inaktiven Template-Familie ist nicht automatisch öffentlich auslieferbar.

**Mittelbare Zustandsänderung beim Entfernen eines Assets:** `detachAsset()` entfernt strukturierte Usages und `media:ID`-Referenzen aus Rich Text, ordnet verbleibende Medien neu und schreibt Audit-Ereignisse bei betroffenen Eintragsownern. Wird der einzige relevante Blog-Body dadurch leer, setzt die implementierte Bereinigung einen Published Post auf `unpublished` oder einen Scheduled Post auf `draft` und löscht `scheduled_at`. Nicht daraus ableiten: eine beliebige Exhibition werde beim Medienentfernen pauschal nach Draft zurückgesetzt. Die physische Dateilöschung bleibt zusätzlich von Working-, Committed- und History-Referenzen abhängig ([Media-Referenzen und Cleanup](media-reference-and-cleanup.md)).

### Aktuelle Journal-Präsentation: Disclosure und Medien-Captions

Präsentation und gespeicherter Zustand bleiben getrennt. Ein aufgeklappter Exhibition-Detailblock ist **kein** Publish-/Temporal-State-Wechsel; seine akzeptierte Disclosure-/Cover-Darstellung gehört zum [Public-Vertrag](../public/site.md#exhibition-chronology-and-detail-disclosure).

[`JournalMediaRenderer::render()`](../../app/Domain/Content/JournalMediaRenderer.php) behandelt Bilder und Videos über denselben Media-Zugriff, aber mit verschiedenen Ausgaben: Bilder benutzen kanonische Thumbnail-/ALT-/Viewer-Attribute, Videos ein verifiziertes Poster und das Originalvideo als Inline-Player. Ein Verbraucher entscheidet per `showCaption`, ob die sichtbare Figcaption erscheint. Eine ausgeblendete Caption entfernt **weder** ALT, Credit, Copyright-Metadaten noch öffentliche Rechteprüfungen. Blog-/Exhibition-Präsentationen können Cover-/Gallery-Captions ausblenden, andere Verbraucher können den Default behalten. Slideshow, Grid und Mosaic bleiben verschiedene Darstellungen, keine eigenen Medienmodelle.

**Optionaler Journal-Viewer:** Journal-Body und Exhibition-Description nutzen den zentralen [Rich-Text-Vertrag](../content/rich-text.md). `image_viewer_enabled` ist eine **eintragsbezogene** Option für Bilder in Cover, strukturierter Gallery und Rich Text. Der gemeinsame `PublicImageViewer` darf nur die Bilder **des aktuellen Journal-Eintrags** zum Durchblättern anbieten: keine Links in Gallery-Artwork-Detailseiten und kein Sprung zu anderen Journal-Einträgen. Videos bleiben Inline-Player; die native Player-Bedienleiste hängt seit `dev@97973406` vom je **Cover-/Gallery-Usage gespeicherten** `video_controls_enabled` ab (fehlende/alte Werte: aktiv). Die Exhibition-Cover-Aktion und die Blog-/Exhibition-Gallery-Zeilen bieten **Show video playback controls** an. Blog Cover akzeptiert weiter nur Bilder. `JournalEntryMediaService` ist der einzige Speicher-Owner, `JournalMediaRenderer` der einzige öffentliche Ausgabe-Owner; das Bild-Viewer-Flag ist davon unabhängig. Neue Gallery-Zeilen ohne frühere Mediennutzung erhalten den aktivierten Default und brauchen keine vorhandene Zeile zum Speichern. Der hier geprüfte Quellpfad ist **keine ausgeführte Browser- oder Migration-Abnahme**. Home kann denselben Viewer für aktivierte Bilder verwenden; keine zweite Viewer-Implementierung oder eigene Medienroute.

## 4. Öffentlicher Controller und Template-Eligibility

[`PublicSiteSectionController::journal()`](../../app/Http/Controllers/PublicSiteSectionController.php) wählt die öffentliche Blog- oder Exhibitions-Ansicht allein nach dem aktiven JournalTemplate. Für Blog gelten `BlogEditorialService::publicQuery()` und zusätzliche Journal-/Read-Kontextfilter; für Exhibition die Published-Filterung. Die authentifizierte Preview nutzt stattdessen nicht archivierte Working-Einträge. Eine Blog-Einzelroute verlangt ausdrücklich das Blog-Template; nach Wechsel zu Exhibitions bleibt der Post gespeichert, ist aber über diese öffentliche Detailroute nicht mehr erreichbar.

[`PublicMedia::isPublicAsset()`](../../app/Domain/Media/PublicMedia.php) entscheidet separat über öffentliche Assets anhand des aktiven Templates, Entry-State, der Journal-SiteSection und bei Exhibition-Galerien `gallery_enabled`. Preview-Sichtbarkeit, gespeicherte Referenz und öffentliche URL sind daher **verschiedene Berechtigungsgrenzen**.

## 5. Noch fehlende, präzise Nachweise

Dies sind **kleine Prüfgrenzen**, keine bereits ausgeführten Tests oder ein vollständiges Projekt-Backlog:

| Bereich | Zu prüfende Eigenschaft |
| --- | --- |
| Blog Schedule und Uhrzeit | Wiederholtes `schedule(T)` bei stabiler Uhr (Audit/Notification/No-op) sowie `T-ε`/`T+ε` im selben zuvor committed Zustand, inklusive Public Read und Admin-Kennzahl |
| Publication/Restore | Bereits Published als No-op; Exhibition-Restore nach verlorener Karten-/Medienbereitschaft darf nicht unberechtigt neu veröffentlichen |
| Medienreferenzen | Media-only-Edits und indirektes Unpublish/Restore-Draft bei gelöschtem letztem Blog-Medium; deaktivierte Exhibition-Gallery bleibt gespeichert, aber öffentlich gesperrt |
| Browser/Public | Inaktives Template, Preview gegenüber Committed, viewer-spezifische Bildnavigation und Video-Playback über reale öffentliche Routen |

Quellenprüfung ersetzt weder isolierte Domain-/Datenbanktests noch Browserabnahme. Die [Testing-Strategie](../operations/testing.md) definiert, welche Szenarien bei einer autorisierten späteren Testintegration tatsächlich ausgeführt werden.
