# Public rights, artwork protection and privacy — implementation plan

**Scope and evidence:** This document owns accepted product constraints, rights/privacy integration requirements and remaining verification criteria. Its source observations include historical snapshots, most recently the stated `dev@434975e1bd09` B2 artifact checkpoint; they are not a blanket runtime or browser acceptance of the current `dev` branch. General Legal and media-rights implementation exists in source, but migrations, enabled rollout, current runtime behavior, the artist's legal copy and legal review must each be verified separately.

## Product boundaries and ownership

- **Product language is English throughout the Admin and public site.** Preserve the existing component name `Legal Disclaimer`; new form labels, section headings, summaries and deep-link fragments are English. This overrides the earlier German UI labels in this plan. German terminology for legal concepts is permitted in internal discussion, not as a surprise UI-language change.

- This is a high-quality, artwork-first public artist portfolio. Preserve full-resolution public Artwork Viewer, zoom, keyboard, native scroll, established public menu, white Content folio, public CSP and search discoverability. No watermark/low-resolution substitution, permanent footer, forced new main-menu page, obstructive overlay, right-click theatre or duplicate public shell.
- The artist retains responsibility and editorial control of the authored information on the About Artist / Contact **Custom Page**, currently named `CV` in parts of the UI. He may change/unpublish it. Do not invent compulsory publishing guards or argue against artist control. A developer should nevertheless make required information clearly findable.
- The site developer owns technical CSP, media delivery/abuse controls, crawl/TDM signals, Matomo consent enforcement and truthful data processing behavior. Artist-written words cannot replace that enforcement.
- Confirmed editorial placement: retain the existing editable **Legal Disclaimer** under About Artist/Contact for Imprint and Privacy; do not create a global footer, additional global legal navigation or separate postal-address fields without a new decision. The artist still controls publication. **This does not establish legal discoverability**: inspect navigation, SPA, Home Gate and deep links against applicable requirements.
- Do not auto-publish or invent names, addresses, rights ownership, processing disclosures or legal advice. Professional legal review remains appropriate for final content.

## A. General → Content → public legal rendering

### Current source-of-truth

`app/Filament/Pages/General.php` now exposes **five** Legal inputs: the short `default_media_copyright_notice` (max 500 characters), plus `legal_imprint`, `legal_privacy`, `legal_usage_rights`, and the historical persisted key `legal_disclaimer` (visible label **External Links Notice**). The default notice occupies a full-width form row; the other four inputs form a responsive 2×2 grid, remain scrollable and hide only the visible scrollbar. All five have help affordances. The re-used **Legal Disclaimer** Custom Page component reads these texts through `PublicLegalContent`, displaying only nonempty escaped sections with stable deep-link anchors.

`MediaAsset::effectiveCopyrightNotice()` continues to implement `inherit | override | none` for supported rights and metadata projections. **The Artwork detail deliberately does not display an automatic copyright-notice row**, including the inherited General default; the existing photographic credit remains. This is an explicit product decision, not missing implementation work. Browser acceptance and legal correctness are still separate.

### Implemented five-field General schema — content and runtime acceptance outstanding

1. **Default copyright notice** (existing, concise inherited notice; e.g. `© Lars Möller. Alle Rechte vorbehalten.` only for media where he actually holds relevant rights).
2. **Impressum** (new, properly authored provider identification and address; no fabricated data).
3. **Datenschutz** (new, truthful privacy information based on real app/server/Matomo/contact/map behavior; technically the site developer must own correctness).
4. **Urheberrecht & KI-Nutzung** (new, broader copyright, reproduction/license inquiries, §44b UrhG TDM rights reservation in accurate terms, and distinctions for third-party photography).
5. **External Links Notice** (persisted key `legal_disclaimer`; OPTIONAL; no blanket release from liability, existing content retained).

No per-crawler, per-method or per-media copyright text fields in General. Fixed technical safeguards are application-owned, not arbitrary toggles that accidentally compromise rights.

### Public rendering / editor

- Reuse the existing Custom Page component type and order/action/published behavior; preserve the established English `Legal Disclaimer` component name while extending its content with a migration/compatibility path for `legal_disclaimer`.
- From that component render whichever of the authored legal sections have actual content; no invented defaults. Preferred compact concept: quietly identifiable Imprint / Privacy Policy / Copyright & AI Use links/expandable subsections **inside the existing About/Contact content**, not a footer or new public page. Optional external-link note last. Ensure accessible keyboard/focus, logical headings, anchors/deep links, mobile widths, opening direct fragments across Livewire SPA and no hidden unreachable disclosures.
- The inherited default may appear only in specifically authorized media/metadata projections, **not** as an automatic Artwork-detail copyright line. Photographic credit remains. The long rights statement belongs in the Legal Disclaimer component. Do not stamp `© Lars Möller` onto every artwork or page.
- Test site/artist preview and published/unpublished paths, including the Public Home Gate. Distinguish technical reachability from the artist's decision to publish.

### Implementation note (2026-10-14)

The implementation keeps the persisted component type `legal_disclaimer` for migration
compatibility, but calls it **Legal Disclaimer** in the Admin and renders authored
Imprint, Privacy Policy, Copyright & AI Use, then the unchanged legacy
external-links note through one shared `PublicLegalContent` projection.
General stores the three new nullable long texts without inserting any legal
placeholders; the older default-media notice remains the fourth independent
copyright authority and the legacy optional text is preserved. The public UI
uses unobtrusive native details/summary and deterministic fragment identifiers
(`#legal-imprint`, `#legal-privacy`, `#legal-copyright-ai`,
`#legal-external-links`), which a bundled Vite helper reveals on SPA/hash
navigation. Only nonempty authored sections render and texts are HTML-escaped.
The artwork detail instead retains the photographic credit and intentionally shows no automatic Copyright Notice. The General external-links field and projected subsection now both carry the English label **External Links Notice**.

This is implementation intent/source work, not a declaration that the tests
have run or the actual browser layout has been accepted. The artist still
needs to supply accurate legal text. Do not turn this temporary status note
into a permanent misleading completion claim.

## B. Rights metadata across Storage, public files and SEO

### Current source

- `app/Models/MediaAsset.php`: bisherige `alt_text`, `credit`, `copyright_notice_mode`, `copyright_notice` plus **neue optionale** `file_creator`, `file_rights_holder`, `rights_usage_terms`, `license_inquiry_url`; `metadata` bleibt JSON. Der Artwork-Künstler wird ausdrücklich nicht automatisch zum Fotografen oder Dateirechteinhaber.
- [`MediaAssetEditorialService::updateMetadata()`](../../app/Domain/Media/MediaAssetEditorialService.php) speichert die alten und vier neuen Felder nach Plain-Text-/Längen- und HTTPS-URL-Prüfung mit `media.metadata_updated`-Audit **nur bei `isDirty()`**. [`ListMediaAssets::mediaEditSchema()`](../../app/Filament/Resources/MediaAssets/Pages/ListMediaAssets.php) bietet dazu einen eigenen Abschnitt `Image rights and licensing`. [`MediaRightsProjection::forAsset()`](../../app/Domain/Media/MediaRightsProjection.php) liefert `creator/rights_holder/credit/effectiveCopyrightNotice/usage_terms/license_inquiry_url` als **redaktionelle Rechteprojektion**, ohne Urheberschaft aus der Artwork-Relation zu raten. Weiterhin **keine** Einbettung in öffentliche Bildbytes.
- `app/Domain/Media/MediaIngestService.php` preserves original bytes and SHA-256/length under `originals/`. **New** WebP previews use bounded libvips (`public-v1`/`public-v2`), while FFmpeg generates immutable video posters (`video-poster-v1`); GD exists only for selected historical thumbnail recovery. Re-encoding a thumbnail does not itself add rights metadata: optional IPTC/XMP files are new revision-addressed copies from the separate rights preparation owner, not in-place edits of originals.
- `app/Domain/Media/PublicMedia.php` determines whether an asset is public based on publication references. `app/Http/Controllers/PublicMediaController.php` then sends eligible original/variant bytes using `X-Sendfile`. Viewer uses originals by design.
- Credits are not uniform across artwork, journal, custom images and Home. Artwork detail intentionally suppresses the automatic inherited copyright-notice row while preserving photographic credit; do not reintroduce it as a generic consistency fix.

### Target single rights projection (no duplicate authorities)

- **General defaults** → **per-MediaAsset override** → **public media metadata, viewer/detail credits, structured data and embedded file metadata**. Preserve existing `inherit | override | none` semantics and never silently replace third-party credits.
- **Source umgesetzt ab `dev@9c01a9d0`:** Storage erhält jetzt optionale Felder für **creator/photographer of the file**, **file rights holder**, **usage terms** und **HTTPS license inquiry URL** sowie die zentrale `MediaRightsProjection`. Die Migration [`2026_10_15_180000_add_media_file_rights.php`](../../database/migrations/2026_10_15_180000_add_media_file_rights.php) ergänzt `public.media_assets` und bei PostgreSQL explizit `committed.media_assets` (vier Spalten, keine neue Publication-Tabelle). **Nur Code vorhanden**: Migrationen/Featuretests/Browser/DB in diesem Dokumentationszweig nicht ausgeführt. Für Medienmetadaten und SEO soll genau diese Projektion genutzt werden, statt neue, divergierende Rechtequellen einzuführen. Artwork artist is separately represented in the Artwork domain; do not confuse painting author with photograph copyright holder. Existing `credit`, ALT, canonical copyright notice and override already cover other facts.
- Support IPTC/XMP Copyright Notice, Creator, Copyright Owner, Credit Line, Rights Usage Terms, Web Statement of Rights and the IPTC/PLUS Data Mining controlled vocabulary value **'Prohibited except for search engine indexing'** when valid for the rights owner. Write only supported canonical tag IDs and verify via an independent metadata reader, not by guessing string labels. Check creator/photographer and artwork author separately.
- Archive original stays byte-for-byte immutable and retains its stored hash and size. Do not mutate files behind `storage_key` in place. Use a deliberate, atomic public artifact/metadata publishing pipeline: derive or copy a full-quality/public representation without downscaling, embed safe approved IPTC/XMP fields, preserve color/ICC information, handle JPEG/PNG/WebP capabilities, update public URLs/cache keys/hashes, and leave the authoring original intact. Assess extra storage cost, publication snapshots/versioning, server backups and X-Sendfile compatibility *before* implementation. No second incidental image/thumbnail system.
- Regenerate/re-publish rights-bearing public variants when rights fields change. Preserve existing crop/thumbnail quality rules where these are already intentionally smaller; never replace full-res viewer originals with thumbnails. Scrub privacy-sensitive source metadata, particularly GPS/location and camera serial, from public copies unless expressly intended. Do not accidentally destroy third-party embedded license terms.
- Coordinate upload, existing-media migration, editorial updates, deletion, audit/Undo, public gate, preview and cached variants. Runtime tests: metadata present in full-size public bytes AND thumbnails, source original hash unchanged, credit inheritance/overrides/none correct, sensitive location removed, image pixel dimensions and expected visual quality unchanged, media visibility not widened by provenance feature.
- Optional later: cryptographically signed C2PA/Content Credentials after verified signer and provenance workflow. This can authenticate provenance, not guarantee authorship or stop saving/copying; do not install a signing system as a prerequisite for IPTC/XMP.

**Implementierungsstand `dev@c61bc0f4` (B2-Vorarbeit, keine öffentlichen Artefakte):** [`MediaIptcXmpWriter`](../../app/Domain/Media/MediaIptcXmpWriter.php) **existiert jetzt** als Offline-/Staging-Service, der mit [`MediaRightsProjection`](../../app/Domain/Media/MediaRightsProjection.php) definierte XMP-Tags für JPEG/PNG/WebP auf einer **exklusiv neu angelegten Kopie** des lesbaren Originalpfads erstellt; er schreibt nie das archivierte Original, bestehende Variant oder eine öffentliche URL um. [`config/media.php`](../../config/media.php) besitzt dafür `MEDIA_EXIFTOOL_BINARY`. Symfony Process führt den konfigurierten ExifTool-Binary ohne Shell aus und fordert Version **≥12.67**. Der Source-Writer mappt `XMP-dc:Creator`, `XMP-plus:CopyrightOwnerName`, `XMP-photoshop:Credit`, `XMP-dc:Rights`, `XMP-xmpRights:UsageTerms` und `XMP-plus:LicensorURL`. Eine Lizenzanfrage-URL ist **nicht** automatisch eine autoritative `WebStatement`-URL. `XMP-plus:DataMining` wird **nur bei explizitem boolschem Berechtigungsflag** gesetzt; ein allgemeines Artwork-Copyright darf bei separat benanntem Fotorechteinhaber ohne ausdrücklichen Override nicht als dessen Fotografenrecht behauptet werden. Der Writer prüft seit `dev@f4da0424` **vor dem Kopieren** zusätzlich `finfo`-MIME gegen `MediaAsset.mime_type` sowie `filesize` und `hash_file('sha256')` gegen die autoritativen Asset-Attribute; während der Kopie fordert er exakt die erwartete Bytezahl. Die Prüfung erfolgt **vor** dem Kopiervorgang, ohne File-Lock oder erneutes Hashen der kopierten Quelle – eine zwischen Check und Copy veränderte Datei ist daher weiterhin ein gezielter Integritäts-/Race-Testfall. Auf der Staging-Kopie entfernt der Writer nur **bekannte** GPS-/Seriennummern-Tags, prüft Bildabmessungen und meldet SHA-256/Bytes; das beweist weder vollständige Metadatenbereinigung, ICC-/Pixelgleichheit noch ExifTool-Tag-Readback.

**Toolchain als Source vorhanden, nicht gebaut/abgenommen (`dev@eabbc01f`):** Das [`Dockerfile`](../../Dockerfile) pinnt jetzt ExifTool **13.55** als CPAN-Tarball mit festgelegtem SHA-256, verifiziert Tarball-Digest, Binary-Version und schreibbares `DataMining`-Tag beim Container-Build in gemeinsamem `php-base` (Application/Release/Local Preview). Debian Bookworms bisherige 12.57 genügt weiterhin **nicht**, wird aber nicht mehr als alleinige Runtime-Paketquelle angenommen. Dies ist nur ein deklarierter Build-Pfad; weder dessen CPAN-Download/Hash noch der tatsächliche Containerlauf wurden hier ausgeführt. [`PublicMediaController`](../../app/Http/Controllers/PublicMediaController.php) liefert weiterhin bisherige autorisierte Original-/Variant-Dateien unter ihren bestehenden SHA-ETag/`immutable`-URLs, kein versionierter Rechte-Derivat-Owner. Es gibt **keine** bestätigte Anbindung des neuen Writers an Publication, Preview/Undo, Dateibereinigung, SEO-`ImageObject` oder eine im Browser ausgelieferte neue Kopie. Die [Unit-Tests](../../tests/Unit/Media/MediaIptcXmpWriterTest.php) und der neue [Real-File-Integrationstest](../../tests/Integration/Media/MediaIptcXmpWriterRuntimeTest.php) sind **verfasst, nicht ausgeführt**. Letzterer erzeugt JPEG/PNG/WebP-Fixtures mit ICC-Profil, schreibt bekannte XMP-/GPS-Tags, ruft den Staging-Writer auf und behauptet Original-Byteidentität, pixelgleichen Dekodierinhalt, ICC-Erhalt und Metadata-Readback. Erst eine tatsächliche Ausführung mit installierter Toolchain belegt diese Aussagen. Offen bleiben Fehler-/Privacy-/Race-Abdeckung, eindeutig versionierte atomar veröffentlichte Artefakte und LIVE/History-/Reference-/Cleanup-Integration. Der [Runtime-Audit](../architecture/application-runtime-boundaries.md#offline-metadata-writer-ist-kein-public-runtime-owner) gehört dieser Toolchain-Grenze.

### SEO consistency, not surrendering artwork rights

- Use actual image elements, canonical public landing pages and useful ALT. Add appropriate schema.org `ImageObject` metadata on artwork/other eligible image pages: `contentUrl`, `creator` (actual image creator), `creditText`, `copyrightNotice`, and accurate `license` / `acquireLicensePage` URLs *only* where an actual conditions/contact page exists. An image being visible is not an open license. Derive fields from the same canonical rights projection.
- Google image indexing must continue; evaluate sitemap/image discovery, structured data and crawler accessibility with real Search Console later. Hotlink blocks and expiring/signed content URLs can break external previews/indexing; do not enable either reflexively.

## C. Copying and automated scraping: realistic protection model

This is an explicit protection goal, not an omission. Prioritize **making scripted/bulk copying costly, detectable and stoppable without degrading public image quality**.

1. **Already implemented but unverified in deployment:** `GET /robots.txt` explicitly excludes `GPTBot`, `Google-Extended`, `ClaudeBot`, `CCBot` while allowing ordinary search; `GET /.well-known/tdmrep.json` publishes a site-wide `location:/` TDM reservation; `app/Http/Middleware/SecurityHeaders.php` emits public `tdm-reservation: 1` including eligible media. These communicate rights/voluntary crawler constraints, not technical authentication. TDM JSON root pattern was checked against the W3C Community Group example. Ensure media header survives ingress/X-Sendfile and dot-prefixed route reaches Laravel.
2. **Still to implement:** narrowly scoped server/ingress protections for unusually rapid original/variant requests, enumeration and abusive bandwidth. Choose the single ingress/application authority after inspecting `server-platform` ownership and measured traffic, not an independent JS/timeout-based protection. Preserve normal gallery browsing, viewer swipes, search engine image crawling, legitimate embeds and contact flow. Transparent predictable HTTP behavior, observability, safe thresholds and limits, no blanket IP blocks that damage shared-NAT or search access.
3. **Assess, do not blindly add:** Referrer-aware hotlink controls may reduce embedding/bandwidth but are spoofable and can break Google Images/external links. Temporary signed media links prevent simple raw URL reuse but not copying visible bytes; they also jeopardize indexability and cache if misapplied. Implement only if measured abuse and actual public behavior justify them.
4. **Not effective against determined users:** right-click/context-menu disabling, hiding `src`, CSS background/canvas changes and JavaScript tricks. Neither screenshots nor fetching browser-received high-resolution image bytes can be reliably prohibited on an open website. Do not sell these as protection or sacrifice visual quality.
5. **Optional later after evidence:** rights/reverse-image monitoring, documented notice/takedown process, media fingerprints/provenance checks. Invisible forensic marking must be evaluated for integrity and quality; never modify the works silently.

Acceptance: realistic browser image browsing and high-resolution viewer work unchanged; targeted abusive request simulations rate-limited and auditable; Googlebot/image SEO still works; tests for rate-limit boundary, HTTP cache/range/206 behavior, public/private asset authorization and concurrent traffic. No unsupported assertion of absolute technical download prevention.

## D. Matomo, consent and real privacy data

**Current product decision (2026-10-15):** A new Matomo consent UI is **deferred**, not delivered or accepted. Do not quietly enable visitor tracking without an adequately reviewed lawful basis/required choice. Building such a controller later belongs to the single public analytics owner; no second artist-editable consent widget, new footer or JS timer is authorized. The factual technical source trace is in [Public security/privacy boundaries](../architecture/public-security-privacy-boundaries.md#6-public-datenschutz-getrennte-kontakt--tracking--und-kartendatenflüsse), not duplicated here.

- **Contact is DB plus optional SMTP**, not email-only. The public submitter writes a `ContactMessage` before attempting private-recipient mail delivery, retains it upon SMTP unavailable/error, and returns `Your message was received.`. This requires a real retention/deletion policy and truthful privacy wording. `General.public_email` is public; `General.contact_recipient_email` and `config('contact.recipient')` are private delivery configuration. Use the verified public email consistently in the legal text, without inventing a postal address.
- **Browser Matomo vs server data are independent.** The current JavaScript loads Matomo and logs pageviews/events/heartbeat when configured, without prior opt-in. Separately, Caddy/Apache access logs and potential Matomo log import can collect request data even if browser tracking is disabled. Neither active Production/Validation flags, actual IP anonymization/retention nor the server importer are verified by this repository.
- **Exhibition maps** render a closed dialog with a local Leaflet script but potentially contact external OpenStreetMap tile servers on opening. Confirm exact network behavior and corresponding privacy disclosure.
- **Public image rights:** original ingest remains archived byte-identically. Current published full-quality originals and WebP variants do not get automatic IPTC/XMP rights metadata. Publishing additional rights metadata requires a verified format-aware **versioned public artifact/cache identity**, without mutating the archived original or incorrectly reusing a year-long immutable URL. A visible automatic Artwork copyright row remains explicitly unwanted; credits remain.
- **Before final legal wording:** confirm controller identity/address, publicly displayed contact, actual host/data-processing parties, mail relay, contact deletion and backups, runtime tracking/reporting/logimport, analytics retention/anonymization, map tile traffic, and applicable legal review. Do not infer general retention from log rotation or write fabricated legal assurances.
- **Later acceptance if consent is approved:** no tracker script, events, cookies or heartbeat before eligible consent, balanced accept/reject, persistence/revocation, SPA navigation/observer cleanup, accessible layout and real browser/network verification. No production browser tracking when it lacks its required consent mechanism.


## Dependency-ordered delivery: four cohesive slices, not scattered microtasks

**Slice 1 — Ownership/editorial content and rendering.** Finalize actual General Legal fields (three new, existing default, reused optional external-links field), canonical validation/save/audit + notifications, rework the existing legal component, preserve published/move/editor functionality, and resolve inherited copyright on Artwork detail. No footer. Real-browser visual acceptance required.

**Slice 2 — Complete media rights pipeline.** MediaAsset rights attribution, one rights projection, metadata writer/publisher preserving archived source, safe regenerated derivatives, consistent credits and schema.org ImageObject, uploads/changes/versioning/tests. Confirm at least one genuine representative image and thumbnail with metadata reader, plus unchanged high-res viewer.

**Slice 3 — Stop abusive extraction while preserving SEO.** Audit media request surface, implement ingress/application rate/bot-abuse control based on measured thresholds and verify Google Images, normal gallery and full-res viewer; evaluate hotlink and signed URLs only after evidence. Existing robots/TDM signals are only a partial start.

**Slice 4 — Privacy verification; conditional analytics activation.** Verify actual browser Matomo configuration, access-log/import behavior, retention, anonymization, Contact and map data flows, and artist-supplied/legal-reviewed disclosures against the real environment. A new consent UI remains **deferred** by product decision; do not add a cookie banner or acceptance modal under this slice. If tracking requires consent, keep it disabled until a separately authorized single-owner consent solution is legally and technically verified. Check deployment flags and server configuration at the platform owner instead of assuming a repository default reflects production.

These slices are an ownership and acceptance sequence, not four claims of missing source implementation or an authorization to activate tracking, deploy, alter infrastructure or redo independently accepted work. Reconcile current `dev` source and existing verified evidence before treating any slice as outstanding or complete.

## Source verification / references

Repo owners: `General.php`, `PublicContentSetting.php`, `MediaAsset.php`, `MediaAssetEditorialService.php`, `MediaIngestService.php`, `PublicMedia.php`, `PublicMediaController.php`, `PublicSeoController.php`, `SecurityHeaders.php`, `component-sequence.blade.php`, `artworks/show.blade.php`, `JournalMediaRenderer.php`, `matomo.js`, `layouts/app.blade.php`.

Standards and implementers:
- https://www.w3.org/community/reports/tdmrep/CG-FINAL-tdmrep-20220216/
- https://www.iptc.org/std/photometadata/documentation/userguide/
- https://www.iptc.org/std/photometadata/specification/IPTC-PhotoMetadata-2025.1.html
- https://developers.google.com/search/docs/appearance/structured-data/image-license-metadata
- https://developers.google.com/search/docs/appearance/google-images
- https://developer.matomo.org/guides/tracking-consent
- https://exiftool.org/
- https://spec.c2pa.org/specifications/specifications/2.1/specs/C2PA_Specification.html

### Rights artifacts: source state at dev@434975e1bd09

B2 is no longer merely an offline writer proposal. `MediaRightsArtifactService`, `MediaRightsArtifactRevision`, `MediaRightsWorkingPreparation`, the `media:rights:prepare` command and `MediaRightsPublicationSelections` now define immutable JPEG/PNG/WebP rights-bearing copies, a revision registry, Working/Checkpoint selections, preparation readiness and optional revision-aware public delivery. `PublicMedia` and the media controllers must respect the selected revision; a rights-text change alone must not mutate canonical originals or silently publish an unprepared revision. Rights-artifact files are subject to integrity and reference guards, with a bounded locked reclamation path separate from original cleanup.

**Environment boundary:** Preparation and rights delivery are distinct configuration flags and migration prerequisites. They have **not** been validated against Validation, Production or a real deployment in this docs reconciliation. Verification of metadata/ICC/pixel integrity, revisions, HTTP response semantics and migration compatibility belongs to isolated tests and subsequent authorized acceptance, not to the completed historical source-only generator pass.

