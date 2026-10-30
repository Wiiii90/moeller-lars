# Dev Worker 1 — Projektstand

**Branch:** `dev` · **Worker:** Dev Worker 1 · **Lease:** 3 · **Bereich:** Bild-Upload, Thumbnails, Medienrechte und Validation-Cutover.

## Bestätigt
- Der lokale Thumbnail-Cutover wurde vom Nutzer erfolgreich durchgeführt: 138 Vorschaubilder konvertiert und vier wiederherstellbare Publication-Checkpoints angepasst. Das Archivoriginal bleibt unverändert.
- Ein echtes zuvor fehlschlagendes Künstlerbild wurde lokal nach Korrektur der Pixelobergrenze erfolgreich hochgeladen. Weitere große Uploads und die Browserabnahme sind offen.
- Für Bilder ist ausschließlich das neue libvips-`public-v2`-Vorschaubild vorgesehen (WebP, längste Seite maximal 960 Pixel). Galerie und Artwork-Detail verwenden die Vorschau, der Viewer das Original.
- Der lokale Medienrechte-Worker ist in die Datenbankwarteschlange eingebunden; Vorbereitung und öffentliche Rechteauslieferung bleiben standardmäßig deaktiviert. Ein erfolgreicher realer Joblauf ist noch nicht belegt.
- Validation vor Cutover: 143 alte und 18 neue Thumbnail-Kandidaten; keine bestätigten Varianten-ID-Kollisionen. Drei historische Publication-Checkpoints sind wiederherstellbar; keine pauschale Historienlöschung.
- Am 30.10.2026 wurde eine konsistente Sicherung von Validation-Datenbank, Medien und erforderlicher Konfiguration bei gestoppten Diensten erstellt, anschließend extern übertragen und per Prüfsummenvergleich bestätigt (Nutzerbericht). Validation wurde nach der Sicherung zunächst mit unverändertem Image erfolgreich neu gestartet. Die isolierte Wiederherstellungsprobe wurde vom Nutzer vorerst abgewählt.
- **Validation-Cutover erfolgreich (Nutzerbestätigung am 30.10.2026):** Das Validation-Plattformskript meldete `Valid configuration` und `Validation thumbnail cutover complete; new app is healthy and authenticated ingress is enabled` für den aus `dev@1e023f955fabd54f2ac355fc19c6e58fefe8fa49` gebauten Anwendungsstand. Die vollständige Offline-Konvertierung, der neue Anwendungsstart, Gesundheitscheck und die passwortgeschützte Freigabe wurden demnach abgeschlossen. Produktive Künstlerhomepage nicht umgestellt; browserbasierte Funktionsprüfung und isolierter Backup-Restore nicht bestätigt.

## Geltende Architektur und Grenzen
- `MediaIngestService` für unveränderte Originale; `MediaDerivativeGenerator` für Vorschaubilder; `MediaRightsArtifactService` für optionale unveränderliche Rechtekopien.
- Kein dauerhafter `public-v1`-Fallback. Der einmalige Offline-Konverter muss vorhandene Working-, Committed- und wiederherstellbare historische Versionen erhalten.
- Validation läuft über den gesonderten Plattform-Stack mit `APP_ENV=production`; den Umgebungsschutz bestehender lokaler Kommandos nicht umgehen. Neues Image erst nach kompatibler Offline-Datenkonvertierung aktivieren.
- `PUBLIC_RIGHTS_WORKSTREAM.md` und die bestehenden Medien-/Rechteverträge bei Quellcodeänderungen beachten. Tests gehören auf `testing`. Keine ungefragte Continuous Integration oder Deployment-Aktion.

## Ausstehend
1. Browserabnahme der Public Gallery, Artwork-Detailseite, Original-Viewer, Storage-Uploads und optionalen Rechteverarbeitung auf Validation.
2. Bei Bedarf die drei erhaltenen wiederherstellbaren Publication-Checkpoints praktisch auf Rücksetzbarkeit testen. Die Plattform meldete einen erfolgreichen Cutover, aber kein separater Restore-Test wurde bestätigt.
3. Die isolierte Wiederherstellungsprobe des bestätigten Cold-Backups bleibt auf ausdrücklichen Nutzerwunsch ausstehend.

**Nachweisgrenzen:** Lokaler Cutover und Künstlerupload durch Nutzer bestätigt; Cold-Backup und externe Prüfsumme bestätigt. Validation-Cutover auf dem Server vom Nutzer durch das abschließende Plattform-Erfolgsprotokoll bestätigt. Keine unabhängige Browserprüfung, keine gesonderte wiederhergestellte Publication-Historie und kein isolierter Backup-Restore nachgewiesen.

**Datensparsamkeit:** Nur fachliche Meilensteine, geprüfte Ergebnisse und offene Freigaben. Keine persönlichen Verbindungsdaten, privaten Betriebspfade, Zugangsdaten, Sicherungsdateinamen, Konsolenmitschnitte oder Fehlversuchsprotokolle in dieser öffentlichen Datei.
