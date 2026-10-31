# Dev Worker 1 — aktueller Arbeitsstand

**Branch:** `dev` · **Lease:** 3 · **Zuständigkeit:** Admin-Dialog schließen bei Autosave; Medien-Upload, Vorschaubilder und optionale Medienrechte.

## Abgeschlossen
- **Thumbnail-Cutover lokal und auf Validation erfolgreich.** Validation meldete am 30.10.2026 für das Anwendungsimage aus `dev@1e023f955fabd54f2ac355fc19c6e58fefe8fa49`: Konfiguration gültig, Cutover abgeschlossen, neue Anwendung gesund, passwortgeschützter Zugang aktiv. Produktionshomepage wurde nicht geändert. **Nicht erneut ausführen.**
- Validation-Daten und Medien wurden zuvor konsistent gesichert; externe Kopie und Prüfsumme sind laut Nutzer bestätigt. Eine isolierte Wiederherstellungsprobe wurde ausdrücklich zurückgestellt.
- Ein zuvor fehlschlagender großer Künstlerbild-Upload wurde lokal nach Korrektur der Pixelgrenze erfolgreich getestet. Das ersetzt keine allgemeine Browserabnahme.

## Weiter gültig
- Originaldateien bleiben unverändert. Vorschaubilder: nur `public-v2` (libvips/WebP, längste Seite maximal 960 Pixel). Galerie/Artwork-Detail nutzen die Vorschau, Viewer das Original. Kein alter `public-v1`-Fallback.
- Zentrale Eigentümer: `MediaIngestService`, `MediaDerivativeGenerator`, `MediaRightsArtifactService`. Medienrechte-Vorbereitung und -Auslieferung standardmäßig **aus**; echter Warteschlangen-Joblauf noch nicht bestätigt.
- Verbindliche Regeln in `AGENTS.md`, Dev-Skill und `PUBLIC_RIGHTS_WORKSTREAM.md`. Tests auf `testing`, keine ungefragten Build-Pipelines oder Produktions-Deployments.

## Admin-Dialogschließen — Rücknahme des fehlerhaften Versuchs (31.10.2026)
- **Fehler bestätigt vom Nutzer:** Die nachträgliche Save-/Close-Überwachung in `AdminDialog::edit()` (Alpine-`x-data`, Request-Interceptor, Close-Listener) verursachte erhebliche Lags, falsche X-Beschriftung und nicht schließbare Dialoge. **Diese gesamte Überwachung wurde wieder entfernt.**
- **Wiederhergestellt:** Der vom Nutzer zuvor tatsächlich als schnell und funktionierend getestete einfache versionsgebundene Filament-X-Patch aus `52ea270`. Er schränkt nur die generische Livewire-Ladesperre am X auf die Unmount-Aktionen ein. Der native Filament-Schließvorgang und alle vorhandenen Speicher- und Validierungswege bleiben unverändert; es gibt keinen weiteren Listener oder Timer.
- **Wichtig:** Die Variante ist in Bedienbarkeit durch den Nutzer positiv beobachtet, **nicht** formal auf laufende/fehlgeschlagene Speichervorgänge abgesichert. Bei unbestätigten Änderungen vor dem Schließen kann ein Risiko bestehen. Keine erneute eigenmächtige Close-Überwachung einbauen. Erst gezielte Browser- und Persistenzprüfung, bevor über eine sichere Schließserialisierung entschieden wird.
- Rollback ausschließlich Dialog-Modul und Filament-Patch. Kein Validation-/Produktions-Deployment, keine CI-Ausführung.

## Exhibitions — public separators (31.10.2026)
- **Quellkorrektur:** Das `border-block` auf jedem geöffneten `details` erzeugte bei benachbarten geöffneten Exhibitions doppelte Trennlinien und außen unerwünschte erste/letzte Linien. Im bestehenden Owner `resources/css/public-presentation.css` werden Borders nur auf geöffneten Einträgen gesetzt: kein oberer Strich auf dem ersten, kein unterer auf dem letzten, und bei zwei direkt aufeinander folgenden geöffneten Einträgen nur die untere Linie des vorherigen. Keine neue JavaScript-Interaktion; geschlossene Liste und Inhalt bleiben unverändert.
- **Offen zur Design-Diskussion:** kompakte Datum/Titel/Organizer-Übersicht erhalten; optional ein kleines Cover vor dem Öffnen und eine sanfte Bildvergrößerung im aufgeklappten Zustand prüfen. Keine Entscheidung oder Umsetzung dazu. Browserabnahme der Trennlinien mit Einzel- und Mehrfachöffnung sowie kleinem Bildschirm noch offen.

## Noch offen, nicht Teil des abgeschlossenen Cutovers
- Browserabnahme von Galerie, Artwork-Detail, Original-Viewer und Storage-Uploads; bei Aktivierung der Medienrechte deren Warteschlangen- und Auslieferungsverhalten.
- Optional: praktische Wiederherstellung der drei erhaltenen Veröffentlichungs-Checkpoints und isolierte Backup-Restore-Probe. Beides noch **nicht nachgewiesen**, aber kein Anlass für erneuten Cutover.

**Nachweisgrenze:** Validation-Erfolg durch Nutzerausgabe des Plattformskripts bestätigt, nicht durch eine unabhängige Browserprüfung. Details früherer Fehler stehen bei Bedarf in Git; hier keine Versuchschronik.
