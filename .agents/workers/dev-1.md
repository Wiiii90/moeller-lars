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
- **Ursache:** Der native Filament-5.7.6-X-Icon-Button wird während eines Livewire-Speichervorgangs durch seine globale `wire:loading.attr="disabled"` deaktiviert. Ein Textfeld löst beim Verlassen des Fokus über `AdminDialog::edit()` die reguläre `callMountedAction`-Speicherung aus. Der erste X-Klick kann daher ins Leere gehen.
- **Schutzentscheidung:** Die zuerst implementierte Einschränkung dieser Lade-Sperre wurde wieder entfernt: Sie garantiert nicht, dass bei Validierungs- oder Speicherfehlern die Daten vor dem Schließen erfolgreich persistiert wurden. Bis zum browserbasierten Nachweis bleibt die Filament-Standard-Sperre erhalten, selbst wenn gelegentlich ein zweiter Klick nötig ist. Kein zusätzlicher Listener, kein zweiter Autosave und keine Änderung am Scroll-Lock.
- **Abnahmebedingung für eine spätere Ein-Klick-Lösung:** Eine einzige Schließanforderung darf erst *nach* erfolgreicher Bestätigung aller ausstehenden Mutationen schließen. Bei Validierungs-/Serverfehler, gleichzeitigem Speichern und erneuter Bearbeitung muss das Fenster offen bleiben und die Feldwerte erhalten. Filament-/Livewire-Originalverhalten und tatsächliche Browser-Reihenfolge prüfen, bevor der Native-X-Lebenszyklus geändert wird. Tests auf `testing`, keine Browserprüfung oder Buildausführung durch diesen Dev-Worker behaupten.

## Noch offen, nicht Teil des abgeschlossenen Cutovers
- Browserabnahme von Galerie, Artwork-Detail, Original-Viewer und Storage-Uploads; bei Aktivierung der Medienrechte deren Warteschlangen- und Auslieferungsverhalten.
- Optional: praktische Wiederherstellung der drei erhaltenen Veröffentlichungs-Checkpoints und isolierte Backup-Restore-Probe. Beides noch **nicht nachgewiesen**, aber kein Anlass für erneuten Cutover.

**Nachweisgrenze:** Validation-Erfolg durch Nutzerausgabe des Plattformskripts bestätigt, nicht durch eine unabhängige Browserprüfung. Details früherer Fehler stehen bei Bedarf in Git; hier keine Versuchschronik.
