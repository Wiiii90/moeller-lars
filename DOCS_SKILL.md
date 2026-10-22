# DOCS_SKILL — lebende Arbeitsakte auf docs

**Einstieg:** [AGENTS](AGENTS.md) (Branch-/Sicherheitsregeln), [Docs-Index](docs/README.md) (Norm-Owner), [Architekturindex](docs/architecture/README.md) (End-to-End-Flüsse, Quellbelege), [Worker-Protokoll](docs/WORKER-PROTOCOL.md) (Wiederaufnahme, Regressionen). **Dies ist nur aktueller Arbeitsstand, kein zweiter Produktvertrag oder vollständiges Verlaufstagebuch.**

## Aktuelle Branch- und Quellenlage

- Repository `Wiiii90/moeller-lars`. **Dokumentationsänderungen nur auf `docs`; Implementierung und einzige aktive Testsuite auf `dev`.** Der frühere `testing`-Branch ist historisch und keine zweite Arbeitsspur. Keine Seitenbranch, Pull Requests, Force-Pushes, eigenmächtigen `dev`-/`main`-Merges, Continuous-Integration-Interaktion (auch keine Statusabfragen), Deployments oder Server-/Datenbankschreibzugriffe.
- Letzter echter Zwei-Eltern-Import von `dev` in `docs`: `dev@e72976af47a19ee1432c83488b88f8db15ca5b14`. **Letzter selektiv gelesener `dev`-Quellstand: `74e629274ac28828d9ce9e9c881ab87e7490f2d3` (22.10.2026).** Seit dem vorherigen gezielten Abgleich `dev@2ad31502` wurden 96 Commits mit 146 geänderten Pfaden triagiert. Neu beschriebene Owner sind auf ihrer jeweils genannten Quelle zu verifizieren; diese Sichtung ist **kein** kompletter 96-Commit-Methodenaudit oder App-Code-Merge.
- **Normalbetrieb:** Remote-Quellprüfung über die GitHub-Verbindung; die vorhandene lokale `P:\moeller-lars-docs`-Worktree-Ausnahme ist ausschließlich für **seltene, gebündelte und ausdrücklich abgestimmte Codex-Prüfungen** vorgesehen. Der Benutzer muss nicht nach jedem Docs-Commit lokal Befehle ausführen.
- **Geschützte Validation:** Der Künstler bereitet dort Cutover-Inhalte vor. Nicht als Test-/Wegwerf-Datenbank nutzen. Für vollständige Datenbank-Schema- oder destruktive Tests ausschließlich nachweislich isoliertes, frisches lokales PostgreSQL mit synthetischen Daten und getrennter Autorisierung.

## Fachliche Orientierung

| Änderungsfläche | Normativer Owner | Zusammenspiel/Regression |
| --- | --- | --- |
| Shared Admin/Fokus/Popup/Sortierung | [Control](docs/ADMIN-CONTROL-CONTRACT.md), [Dialog](docs/ADMIN-DIALOG-CONTRACT.md), [Tabellen](docs/ADMIN-TABLE-CONTRACT.md), aktuelles `dev/ui-skills.md` | [UI-Surfaces](docs/architecture/ui-surfaces.md) und [Cross-Feature-Gates](docs/WORKER-PROTOCOL.md#non-regression-gate-before-changing-a-shared-owner) |
| Visueller Editor und Storage-Auswahl | [Rich Text](docs/EDITORIAL-RICH-TEXT.md), [Media](docs/MEDIA.md) | [Cross-feature compositions](docs/architecture/cross-feature-compositions.md), Journal/Home/Custom; Legacy Markdown und HTML, ALT, Undo/Blur |
| Exhibitions, Journal und Bildviewer | [Exhibition Editorial](docs/EXHIBITION-EDITORIAL-CONTRACT.md), [Public](docs/PUBLIC-IMPLEMENTATION-CONTRACT.md) | [Journal-Lifecycle](docs/architecture/journal-content-lifecycle.md), [Media Viewer](docs/architecture/cross-feature-compositions.md#2-exhibitionjournal-composition-and-editorial-visibility) |
| Localization und Regionalformat | [Admin Localization](docs/ADMIN-LOCALIZATION-CONTRACT.md) | Authentifizierte Oberfläche ≠ Public-Sprache; persistierte Activity-/Artwork-Texte niemals automatisch übersetzen |
| Public Header/SPA/Gallery | [Public](docs/PUBLIC-IMPLEMENTATION-CONTRACT.md) | [SPA-Lifecycle](docs/architecture/public-spa-lifecycle.md), getrennte Paper-/Divider-/Title-Owner und kontrollierte Viewer/Video-Instanzen |
| Working/LIVE/Undo/History | [Publication/Activity](docs/ADMIN-ACTIVITY-PUBLICATION-CONTRACT.md), [Notifications](docs/ADMIN-NOTIFICATION-CONTRACT.md) | [Publication-Graph](docs/architecture/publication.md), [Media Schutz](docs/architecture/media-reference-and-cleanup.md) |
| Migration, Rights, Cutover | [Schema](docs/architecture/schema-evolution.md), [Migration](docs/MIGRATION-INVARIANTS.md), [Rights](docs/PUBLIC-RIGHTS-AND-PRIVACY.md) | Die echte Rechts-Artefakt-Implementierung besitzt derzeit die lebende `dev/PUBLIC_RIGHTS_WORKSTREAM.md`, keine zweite aktive Checkliste auf `docs` |

Bei Code-Nachträgen **alten Vertragstext ersetzen** und am bestehenden Owner konsolidieren; nicht bei jeder neuen Klasse einen separaten Graphen oder einen zweiten temporären Skill erzeugen. Erstellte Tests sind keine bestandenen Tests; Quell-/Stilprüfung ersetzt keine Browser-/Künstlerabnahme.

## Prüfevidenz und verbleibende Grenzen

- Der native **source-only Dokumentationsabschluss** auf `docs@317d255e2ea2c52dbb862c78e780830daad9ab53` verwendete PHP 8.3.33, zwei Checker, fünf Parser-Selbsttests und acht Generator-`--check`-Modi; 77 Markdown-Dateien, 1.221 lokale und 199 Heading-Verweise. **43 eingeordnete Artefakte**: 20 ältere Importdiagramme als strukturierte Abhängigkeitsbelege, daneben Workflow-/Lifecycle-/Publication-/UI-Kompositionen. [Tatsächliche Ergebnis- und A–D-Matrix](docs/architecture/completion-criteria.md#technischer-source-only-abschluss-19102026).
- Spätere `dev`-Quellen und dieser Docs-Stand wurden **nicht erneut lokal generiert/getestet**; ältere generierte Verzeichnisse tragen weiterhin ihre wirklichen historischen Quell-SHAs. Neues [End-to-End-Kompositionsmodell](docs/architecture/cross-feature-compositions.md) ist **kuratiert**, nicht ein erneuter automatischer Klassengraph.
- Separat offen: echtes migriertes physisches Datenbankschema, Autoren-/Browser-Akzeptanz, isolierte Anwendungstests, Rechteartefakt-Auslieferung und Release/Cutover. Rechte- und Media-/History-Freigaben nicht aus bloßer Quelldokumentation ableiten.

## Nächste Handlungen nur nach konkretem Auftrag

1. **Jetzt:** die neuen `dev`-Änderungen als selektiven Owner-Abgleich auf `docs` dokumentieren, indexieren und nachvollziehbar committen; nur remote Link-/Ref-Prüfung.
2. **Später separat genehmigt:** aktuellen `docs`-Architekturbestand **konfliktbewusst nach `dev` bringen**, ohne die neueren `dev`-Fassungen von `AGENTS.md`, `ui-skills.md`, `followup-skill.md`, `TESTING_SKILL.md` oder `PUBLIC_RIGHTS_WORKSTREAM.md` durch historische `docs`-Varianten zu überschreiben. [Integrationsverfahren](docs/WORKER-PROTOCOL.md#later-controlled-docs---dev-integration--not-executed-by-this-document).
3. **Nur selten, gebündelt:** nativen Link-/Generator-/Quellcheck via ausdrücklich freigegebenem Codex-Chat im bestehenden Docs-Worktree; kein täglicher Pflichtlauf. Ein solcher Check braucht die reale Quellversion, nicht umetikettierte historische Inventare.
