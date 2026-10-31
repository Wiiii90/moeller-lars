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

## Aktuelles Problem — Admin-Dialoge
- **Diagnose/Quellkorrektur:** Im nativen Filament-5.7.6-Modal ist das X ein Icon-Button mit globalem `wire:loading.attr="disabled"` ohne `wire:target`. Ein Textfeld löst beim Blur durch `AdminDialog::edit()` die reguläre `callMountedAction`-Speicherung aus; der Schließknopf kann dadurch für den ersten Klick deaktiviert sein. Die einzige Änderung beschränkt die Schaltfläche im versionsgeprüften Vendor-Template auf ihre eigenen Unmount-Vorgänge und behält Filament-Schließen und den einen Autosave-Owner bei. Build-/Browserabnahme ausstehend, insbesondere Blur→X während Speichern, nach gelungener Änderung, No-op und Validierungsfehler.
- Filament-Version und Originalvorlage beim Build strikt prüfen; kein zusätzlicher Browser-Listener, kein neues Save/Close-Verfahren und keine Änderungen am scroll-lock Patch.

## Noch offen, nicht Teil des abgeschlossenen Cutovers
- Browserabnahme von Galerie, Artwork-Detail, Original-Viewer und Storage-Uploads; bei Aktivierung der Medienrechte deren Warteschlangen- und Auslieferungsverhalten.
- Optional: praktische Wiederherstellung der drei erhaltenen Veröffentlichungs-Checkpoints und isolierte Backup-Restore-Probe. Beides noch **nicht nachgewiesen**, aber kein Anlass für erneuten Cutover.

**Nachweisgrenze:** Validation-Erfolg durch Nutzerausgabe des Plattformskripts bestätigt, nicht durch eine unabhängige Browserprüfung. Details früherer Fehler stehen bei Bedarf in Git; hier keine Versuchschronik.
