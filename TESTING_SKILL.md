# TESTING_SKILL.md — lebender Testing-Handoff

Stand 2026-10-15. Repository: `Wiiii90/moeller-lars`. **Aktueller Arbeitsstand, kein chronologisches Protokoll.** Änderungen ersetzen veraltete Angaben; Git enthält die Historie.

## Arbeitsort, Git, Zuständigkeit

- **Ausschließlich `testing` beschreiben** im Worktree `P:\moeller-lars\.worktrees\testing`. Working Directory, Branch und Status vor Befehlen prüfen. Der Agent-Standardpfad kann auf das Stammverzeichnis zeigen.
- `P:\moeller-lars` gehört den unabhängigen `dev`-Workern. Dort weder Branch wechseln noch ändern, testen, bauen oder Abhängigkeiten installieren. Separaten dev-Stash niemals anwenden/löschen. Keine zusätzlichen Branches/Worktrees. Gemeinsames Git-Verzeichnis mit `git rev-parse --git-common-dir` finden. `/.worktrees/` ist lokal ignoriert; Docker und Vite schließen diesen Ordner inzwischen ebenfalls aus.
- Vor Schreibvorgängen Live-HEADs `origin/testing` und `origin/dev` prüfen und überlappende Änderungen abgleichen. **Dev nur auf gesonderten Auftrag** als echten Zwei-Eltern-Merge von `origin/dev` nach testing übernehmen; nicht vom lokalen dev-Checkout. Neuere dev-Funktionen und bereits geprüfte Testing-Fixes erhalten; konfliktbetroffene Regressionen ausführen.
- Nach abgeschlossener erfolgreicher Testrunde `TESTING_SKILL.md` im selben Commit aktualisieren, normal zu `origin/testing` pushen und beide HEADs abgleichen. Bei Divergenz stoppen; kein Force-Push, Reset, Rebase, unbeauftragter Merge oder Rücktransfer zu dev. Promotion nach dev braucht Benutzerfreigabe.
- Kein CI/Actions/Workflow, Deployment, Server, Release oder Preview-System anfassen. `dev`, `docs`, `main` nicht beschreiben. Vor Aufgaben die relevanten Teile von `AGENTS.md`, `ui-skills.md`, `tests/README.md` und die tatsächlichen Produktions-Owner lesen. Allgemeine Dokumentation gehört zum Docs-Workstream.

## Obligatorische DB- und Dateisicherheit

- DB-Feature-/Integration-/Migrationstests **ausschließlich** in einem eigenen frischen PostgreSQL-17-Container mit `tmpfs`-Daten, eindeutiger DB/User und zufälligem `127.0.0.1`-Port, **ohne** persistente Volumes/Bind-Mounts. Docker-Konfiguration, anfangs leeres Schema und SQL-Serveridentität unabhängig positiv verifizieren.
- **Vor jedem Testprozess** Laravel mit den tatsächlichen `phpunit.xml`-Overrides booten. Effektive Laravel/PDO-Verbindung (Host/Port/DB/User/Serveridentität) gegen Container prüfen; `DB_URL=null`, keine übernommene `.env`, kein Config-Cache. Laravel Storage/Medien (privat/öffentlich), Session/View/Package/Service-Caches und PHP-Sys-/Upload-Temp müssen eigene temporäre Ziele haben und positiv geprüft sein.
- **Erst nach positivem Nachweis** im isolierten Prozess `GITHUB_ACTIONS=true` und `MOELLER_LARS_DISPOSABLE_TEST_DATABASE=1` aktivieren; `LocalPreviewDatabaseGuard` unverändert lassen, niemals Persistent-Preview-Flag aktivieren. Exitcode 0 ist kein Isolation-Nachweis.
- Die persistente Compose-Datenbank `moeller_lars_testing` und das Volume `moeller-lars_postgres_data` niemals als Testziel nutzen. Lokalen History-Reset und destruktive Befehle nur mit geprüfter Wegwerf-DB **und** isoliertem Dateisystem ausführen. Ausschließlich selbst erzeugte Container/Verzeichnisse entfernen.
- Keine Workarounds, parallelen Owner oder Fake-Green-Assertions. Tests prüfen fachlich beobachtbares Verhalten statt Quelltext, CSS-Klassen, Zahlenbudgets oder zufällige Fixturewerte. Fehlerhafte Fixtures korrigieren, echte Produktionsfehler kanonisch beheben. `Working`-Redaktion von `Committed`-Öffentlichkeit trennen.
- Invarianten: Admin-Panel-Autorisierung auch bei bereits gemounteten Anfragen/Rechteentzug; gelockte originale Editor-Snapshots; atomare Mutationen, Undo und Rollback/Retry; append-only Audit; **eine persistierte und eine unmittelbare Erfolgsmeldung je realer Aktion**, auch bei mehreren Audits; No-op/Fehler ohne neue Audits/Erfolgsmeldungen. Inbox-eigene Mutationen melden sich nicht selbst.
- Tests vorzugsweise als ganze fachliche Suite ausführen, Einzeldateien nur bei Fehlschlägen. Keine mechanische Wiederholung unveränderter Großsuiten. Evidenz ausdrücklich `SOURCE-REVIEWED`, `TEST-AUTHORED`, `TEST-RUN` oder `BROWSER-VERIFIED` nennen; Teilmengen nie als vollständige Repository-/Browserabnahme ausgeben.

## Validierter Stand

- Testing-HEAD `71d94c378a4c45dd8269f3df06cae0b0bb75ac7a`; integrierter dev-Stand `f4da042482b9281e77ce96c93d740562a3dd7489`. Live-Refs nach Abschluss am 2026-10-15: origin/testing identisch, origin/dev `3821ee6f2e094de6b063ded262572a6f2f7cda02`. Kein Merge in dieser Runde. Seit f4da liegt in dev ein neuer `MediaIptcXmpWriterRuntimeTest`; vor Integration den aktuellen Test- und Produktionsdiff gezielt prüfen.
- **TEST-RUN auf integriertem f4da-Code, ein Pest-Prozess, 22 Dateien:** Gallery **52/52 (475 Assertions)**, Unit **27/27 (69)**, Migration **7/7 (19)**; gesamt **86/86 (563)**, keine Fehler/Skips. Einschließlich historischer Custom-Page-Formatkompatibilität, Legacy-Home-Hero/Site-Section und Journal-Ordering. Frischer PostgreSQL 17/tmpfs-Container; Laravel/PDO-Identität, leere DB, PHPUnit-Overrides, DB_URL und getrennte Storage-/Cache-/Session-/PHP-Tempziele vor dem Lauf positiv geprüft. Nur eigene Ressourcen entfernt.
- **Weitere aktuelle Evidenz:** Public/Contact, CommittedPublicRead, Seo sowie Integration/Content, Publication und Database **88/88 (479 Assertions)** in einem Prozess. Pages **98/98**, Integration/Media **32/32**, Public/Media **2/2**, Unit/Media **3/3**, CommittedBlogGallery **1/1**; JS **55/55**. Vorherige Admin-/Home-/Pages-/Gallery-/Storage-/Activity- und kleine Admin-Suiten wurden in früheren Runden geprüft; kein Anspruch auf vollständige Repository-Abnahme.
- Testing-spezifische Produktionskorrekturen bleiben ausschließlich auf testing bis zu gesonderter Promotion. Werkzeuge: PHP 8.3.33, Composer-Lock-Abhängigkeiten vorhanden, Node 22/npm. Windows unterstützt `@laravel/multiplex` optional nicht; bekannte PSR-4-Warnung `AdminHeaderNotificationProbe`.

## Offene Arbeit – priorisiert

1. Vor größeren Testabschnitten aktuellen dev-Stand vergleichen; zuerst den neuen `MediaIptcXmpWriter`/Runtime-Test gezielt integrieren und validieren. Den lokalen dev-Checkout nicht verwenden oder verändern.
2. Public Rights/CSP/SEO-HTTP-Verträge weiter inventarisieren; `PUBLIC_RIGHTS_WORKSTREAM.md` und `docs/PUBLIC-RIGHTS-AND-PRIVACY.md` besitzen diesen Scope.
3. Browser-E2E offen: Livewire-SPA-History, CSP-Nonce, Artwork-Viewer, Dialog-Scroll-Lock, Drag-and-drop, Medienauslieferung und Feedback. Komponententests sind keine Browserabnahme.
4. Ungedeckte Risiken: parallele Transaktionen/Undo/Publikation, Dateisystem-Berechtigungs-/Gerätefehler, Notification-Transport nach Commit, echter Geocoder/Analytics, Reverse-Proxy/X-Sendfile, ExifTool Readback/ICC/Pixel/GPS und versionierte öffentliche Artefakte. Keine Vollständigkeitsbehauptung.
## Pflegepflicht

Nach jeder Runde: aktuelle Matrix und offene Risiken berichtigen, erledigte Punkte entfernen, nicht appendieren. **Keine** Container-IDs, langen Git-Commitlisten, Retry-Zeitlinien oder abgeschlossenen Bug-Narrative. Ziel: unter 10.000 Zeichen, Sicherheitsregeln erhalten. Nach sicherer Validierung Commit + normaler Push auf testing; HEADs prüfen und nur tatsächlich erreichte Ergebnisse berichten.
