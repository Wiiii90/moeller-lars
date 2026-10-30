# Dev Worker 1 — Projektstand

**Branch:** `dev` · **Worker:** Dev Worker 1 · **Lease:** 3 · **Bereich:** Bild-Upload, Thumbnails, Medienrechte und Validation-Cutover.

## Bestätigt
- Der lokale Thumbnail-Cutover wurde vom Nutzer erfolgreich durchgeführt: 138 Vorschaubilder konvertiert und vier wiederherstellbare Publication-Checkpoints angepasst. Das Archivoriginal bleibt unverändert.
- Ein echtes zuvor fehlschlagendes Künstlerbild wurde lokal nach Korrektur der Pixelobergrenze erfolgreich hochgeladen. Weitere große Uploads und die Browserabnahme sind offen.
- Für Bilder ist ausschließlich das neue libvips-`public-v2`-Vorschaubild vorgesehen (WebP, längste Seite maximal 960 Pixel). Galerie und Artwork-Detail verwenden die Vorschau, der Viewer das Original.
- Der lokale Medienrechte-Worker ist in die Datenbankwarteschlange eingebunden; Vorbereitung und öffentliche Rechteauslieferung bleiben standardmäßig deaktiviert. Ein erfolgreicher realer Joblauf ist noch nicht belegt.
- Validation vor Cutover: 143 alte und 18 neue Thumbnail-Kandidaten; keine bestätigten Varianten-ID-Kollisionen. Drei historische Publication-Checkpoints sind wiederherstellbar; keine pauschale Historienlöschung.
- Am 30.10.2026 wurde eine konsistente Sicherung von Validation-Datenbank, Medien und erforderlicher Konfiguration bei gestoppten Diensten erstellt und auf dem Server geprüft. Anschließend wurde Validation mit unverändertem Image erfolgreich neu gestartet. Die Gesundheitsprüfung und der geschützte Zugang wurden bestätigt. Externe Kopie und isolierte Wiederherstellungsprüfung sind noch offen. Kein Validation-Cutover erfolgt.

## Geltende Architektur und Grenzen
- `MediaIngestService` für unveränderte Originale; `MediaDerivativeGenerator` für Vorschaubilder; `MediaRightsArtifactService` für optionale unveränderliche Rechtekopien.
- Kein dauerhafter `public-v1`-Fallback. Der einmalige Offline-Konverter muss vorhandene Working-, Committed- und wiederherstellbare historische Versionen erhalten.
- Validation läuft über den gesonderten Plattform-Stack mit `APP_ENV=production`; den Umgebungsschutz bestehender lokaler Kommandos nicht umgehen. Neues Image erst nach kompatibler Offline-Datenkonvertierung aktivieren.
- `PUBLIC_RIGHTS_WORKSTREAM.md` und die bestehenden Medien-/Rechteverträge bei Quellcodeänderungen beachten. Tests gehören auf `testing`. Keine ungefragte Continuous Integration oder Deployment-Aktion.

## Ausstehend
1. Externe, geschützte Kopie der bestehenden Sicherung verifizieren und isolierte Wiederherstellung nachweisen.
2. Gesonderte Validation-Wartungsfreigabe und kontrollierte Offline-Thumbnail-Konvertierung mit Medien-/Historienprüfung.
3. Browserabnahme der Public Gallery, Artwork-Detailseite, Original-Viewer, Storage-Uploads und optionalen Rechteverarbeitung.

**Nachweisgrenzen:** Lokal durch Nutzer ausgeführter Cutover und Upload; Sicherungs- und Neustartprüfung von Validation durch Nutzer bestätigt. Externer Restore, Validation-Cutover und alle weiteren Browser-/Queue-Abnahmen nicht bestätigt.

**Datensparsamkeit:** Nur fachliche Meilensteine, geprüfte Ergebnisse und offene Freigaben. Keine persönlichen Verbindungsdaten, privaten Betriebspfade, Zugangsdaten, Sicherungsdateinamen, Konsolenmitschnitte oder Fehlversuchsprotokolle in dieser öffentlichen Datei.
