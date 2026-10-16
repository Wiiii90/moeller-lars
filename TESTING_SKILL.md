# TESTING_SKILL.md — lebender Testing-Handoff

Stand 2026-10-16. Repository: `Wiiii90/moeller-lars`. **Aktueller Arbeitsstand, kein chronologisches Protokoll.** Änderungen ersetzen veraltete Angaben; Git enthält die Historie.

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

## Abgeschlossene gemeinsame Integration

Remote-dev `78c82627d4eca8a9fe1e3a1dde3ff67bfd4aba69` mit Testing `1e1dfa6b192312bc38525940e02d210f12d742a0` als echter Zwei-Eltern-Merge integriert. **Der Benutzer hat für diesen Abschluss die geprüfte reguläre Fast-Forward-Rückführung nach `origin/dev` ausdrücklich freigegeben.** Keine allgemeine Freigabe weiterer Promotionen. Hauptcheckout/Stash unverändert lassen; Abschlusscommit mit `[skip ci]`, danach Remote-HEADs abgleichen.

### Gesamtregression — tatsächlich ausgeführt

| Gruppe | Bestanden | Assertions |
| --- | ---: | ---: |
| PHP Unit | 27 | 69 |
| PHP Integration ohne separate Concurrency | 131 | 848 |
| PHP Feature | 461 | 3954 |
| PHP Migration | 7 | 19 |
| Explizite PostgreSQL-Concurrency | 10 | 508 |
| JavaScript | 62 | keine separate Summe |
| Chromium-Komponenten | 15 | von Playwright nicht gezählt |
| Chromium E2E | 26 | von Playwright nicht gezählt |
| Chromium Profiling | 1 | von Playwright nicht gezählt |

**740 unterschiedliche Tests bestanden; endgültige Läufe: 0 Fehler/Skips. PHP: 636 Tests / 5398 Assertions.** Die 626 regulären PHP-Fälle liefen gemeinsam; Concurrency separat. B2 mit tatsächlichem ExifTool-13.55-Schreiben/Readback (6 Tests / 75 Assertions) ist in Integration enthalten.

### Isolation, Build und fachliche Entscheidungen

- Eigene PG17.11/tmpfs-Container mit DB/User, zufälligem Loopback-Port und ohne persistente DB-Mounts; anfänglich leere Schemas und Clusteridentität unabhängig geprüft. CLI-/HTTP-Laravel/PDO/PHPUnit und Dateiziele positiv verifiziert. PHP und Browser mit getrennten DBs, Storage/Medien, Cache/Session/Views, Framework-Caches und PHP-Temp. Eigene leere `.env.testing` nur in Runtime, `DB_URL` leer, kein Config-Cache, Guard unverändert. PHP nutzt PHPUnit-Array-Treiber, Browser eigene Dateitreiber. Eigene Testressourcen entfernt; unbekannte Storage-Dateien erhalten.
- Vite-Produktionsbuild mit 177 Natural-Earth-Features erfolgreich; Composer-Konfiguration/Plattformanforderungen und optimiertes Autoloading ohne Scripts/Plugins erfolgreich (10137 Klassen); Syntaxprüfung 489 PHP-Dateien. Eigene PHP-8.3.35/GD-/ExifTool-13.55-/Apache-X-Sendfile-Runtime aus bestehendem Dockerfile. Testing-Frontendmanifest und alle 49 geänderten Produktionsdateien per SHA dem validierten Snapshot zugeordnet. Kein Deployment/vollständiger Release-Build.
- Zwei Konflikte fachlich gelöst: neue atomare General-Social-Kaskade mit optimistischem Editor-Schutz verbunden; Contact-Test in bestehender Pages-Struktur zusammengeführt. Löschung kaskadiert durch Custom Pages/beide Home-Templates, versteckte konfigurierte Profile bleiben zulässig. Dev-Validierungen und explizite Instagram-Leerlistenregel erhalten. Drei Audits einer General-Aktion liefern eine Notification/ein Header-Ereignis.
- Produktions-Netto-Diff geprüft: Autorisierung gemounteter Anfragen, originale Editor-Snapshots, Filament-Formfehler/Projektionen, atomare Home-/Pages-/Journal-Mutationen, JSONB-No-ops, Reihenfolgen, Medienlöschung/Retry und einmaliges Feedback erhalten. Undo verwendet bestehende Publikationssperre vor Receipt/Datensperren; Revert liest LIVE/Parent und Restore aktuelle Snapshot-Verfügbarkeit unter dieser Sperre.
- **Zusätzlicher Reclaim-Integritätsfehler korrigiert:** Audit-Rollback stellte Snapshot-Verfügbarkeit wieder her, obwohl Originalbytes gelöscht waren. Jetzt erst atomare Root-Freigabe/Audit, anschließend bestehende physische Queue-Bereinigung. Byte/SHA- und echte konkurrierende Rollback-Regression erfolgreich. Keine neue Trash-/Lock-/Cleanup-Infrastruktur; nie committed Medien behalten direkte Löschsemantik ohne Undo-Beleg.
- Bestehende Teststruktur konsolidiert; keine historischen SitePages-/Architecture-Duplikate restauriert. Redundantes RefreshDatabase/verwaister Import entfernt. Geschützte Dialog-Lock-Logiktests und Dashboard-Autosave-/Schließanimationsnachweise erhalten; heutige zugängliche Button-/Switch-Namen, gemeinsamer Login-Helper, keine aufgeweichten Assertions. Concurrency als `integration`/`concurrency` gruppiert; explizit aus testing: `& tests/Support/RunPublicationConcurrency.ps1 --compact`. Ohne Wegwerf-Umgebung begründet nicht ausführbar, kein behaupteter Nachweis.
- Strikte Public-CSP und kanonische SPA-/History-/Viewer-Owner erhalten; SecurityHeaders entsprechen neuem dev. Fail-closed Public-Livewire-4.4.0-Patch bei Composer `--no-scripts` explizit anwenden; Admin-Bundle unverändert. Filament-5.7.6-Scroll-Lock vor Asset-Publikation erhalten. Echte Original-/Thumbnail-Bytes, SHA/Decodierung, Working/Committed, Viewer und CSP/SPA/Back/Forward im Browser geprüft. B2 bleibt ohne öffentliche Auslieferungsintegration.

### Grenzen

Chromium/synthetische Fixtures sind keine Benutzerabnahme der Homepage oder vollständige Geräte-/Browsermatrix. Touch/Pinch/Video, weitere Lock-Interleavings, externer Datei-/Backupverlust und reale Mail-/Geocoder-/Analytics-Transporte offen; UI-Konkurrenzfeedback speziell für Undo geprüft. Unvorbereiteter Dashboard-Kaltstart unter paralleler Windows-Bind-Mount-Last: 15,6 s; Views regulär vorbereitet, Zeitlimits unverändert. Rechtliche Texte, Matomo-Consent und B2-Auslieferungsintegration bleiben gesondert. Hauptcheckout vor eigener Vorschau vom Benutzer kontrolliert aktualisieren; dieser Auftrag ändert ihn nicht.

## Pflegepflicht

Nach jeder Runde: aktuelle Matrix und offene Risiken berichtigen, erledigte Punkte entfernen, nicht appendieren. **Keine** Container-IDs, langen Git-Commitlisten, Retry-Zeitlinien oder abgeschlossenen Bug-Narrative. Ziel: unter 10.000 Zeichen, Sicherheitsregeln erhalten. Nach sicherer Validierung Commit + normaler Push auf testing; HEADs prüfen und nur tatsächlich erreichte Ergebnisse berichten.

