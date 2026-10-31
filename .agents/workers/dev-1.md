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

## Aktuelle Umsetzung — Admin-Dialogschließen
- Nach Nutzer-Browsertest war das zunächst nur entsperrte X tatsächlich schnell und schloss beim ersten Klick. Wegen ungesicherter Speicher-/Fehlerreihenfolge wurde die reine Entsperrung vorübergehend rückgängig gemacht.
- **Neue gemeinsame Lösung (Quelle, Browserabnahme offen):** Filament 5.7.6 erhält den eng begrenzten, versionsgeprüften X-Lade-Target nur für `AdminDialog::edit()`. Die vorhandenen `wire:change`- und Feld-Speichervorgänge bleiben unangetastet. Eine Alpine-Grenze im gemeinsamen Edit-Dialog beobachtet über Livewires native Action-Promises bestehende Anfragen der Komponente. Ohne laufende Anfrage ist X/Escape/Hintergrund sofort Filament-nativ schließbar; bei laufendem Speichern wird das Schließen bis zur abgeschlossenen Anfrage verzögert. Bei abgelehnter Aktion/Validierung bleibt der Dialog offen.
- **Unbedingt im Browser verifizieren:** direkt vom Textfeld auf X, laufende Anfrage mit hoher Latenz, mehrere schnelle Änderungen, Validierungs-/Netzwerkfehler, native Escape-/Backdrop-Schließung, Wiederöffnung, spezielle `afterStateUpdated`-Felder. Nachgewiesen ist nur Quellkompatibilität; kein lokaler Livewire-Lauf durch diesen Worker. Keine zweite Speicheranfrage, kein Polling/Timer und kein globaler Klick-Interceptor.

## Noch offen, nicht Teil des abgeschlossenen Cutovers
- Browserabnahme von Galerie, Artwork-Detail, Original-Viewer und Storage-Uploads; bei Aktivierung der Medienrechte deren Warteschlangen- und Auslieferungsverhalten.
- Optional: praktische Wiederherstellung der drei erhaltenen Veröffentlichungs-Checkpoints und isolierte Backup-Restore-Probe. Beides noch **nicht nachgewiesen**, aber kein Anlass für erneuten Cutover.

**Nachweisgrenze:** Validation-Erfolg durch Nutzerausgabe des Plattformskripts bestätigt, nicht durch eine unabhängige Browserprüfung. Details früherer Fehler stehen bei Bedarf in Git; hier keine Versuchschronik.
