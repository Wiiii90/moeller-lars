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

**Implemented source projection (not browser/legal acceptance):** General retains five authored controls. The Custom Page component persists as `legal_disclaimer` but displays **Legal Disclaimer**; `PublicLegalContent` renders only nonempty, escaped sections with deterministic fragment targets `#legal-imprint`, `#legal-privacy`, `#legal-copyright-ai` and `#legal-external-links`. The existing public SPA fragment helper reveals the targeted subsection on navigation. The optional external-links note retains its historical stored value. These are one component and one Public rendering owner, not an extra legal page, footer or independent document store. Technical discoverability, runtime behavior and legal review still need separate evidence.

## B. Rights metadata across Storage, public files and SEO

### Current source

- `app/Models/MediaAsset.php`: bisherige `alt_text`, `credit`, `copyright_notice_mode`, `copyright_notice` plus **neue optionale** `file_creator`, `file_rights_holder`, `rights_usage_terms`, `license_inquiry_url`; `metadata` bleibt JSON. Der Artwork-Künstler wird ausdrücklich nicht automatisch zum Fotografen oder Dateirechteinhaber.
- [`MediaAssetEditorialService::updateMetadata()`](../../app/Domain/Media/MediaAssetEditorialService.php) speichert die alten und vier neuen Felder nach Plain-Text-/Längen- und HTTPS-URL-Prüfung mit `media.metadata_updated`-Audit **nur bei `isDirty()`**. [`ListMediaAssets::mediaEditSchema()`](../../app/Filament/Resources/MediaAssets/Pages/ListMediaAssets.php) bietet dazu einen eigenen Abschnitt `Image rights and licensing`. [`MediaRightsProjection::forAsset()`](../../app/Domain/Media/MediaRightsProjection.php) liefert `creator/rights_holder/credit/effectiveCopyrightNotice/usage_terms/license_inquiry_url` als **redaktionelle Rechteprojektion**, ohne Urheberschaft aus der Artwork-Relation zu raten. Weiterhin **keine** Einbettung in öffentliche Bildbytes.
- `app/Domain/Media/MediaIngestService.php` preserves original bytes and SHA-256/length under `originals/`. **New** WebP previews use bounded libvips (`public-v1`/`public-v2`), while FFmpeg generates immutable video posters (`video-poster-v1`); GD exists only for selected historical thumbnail recovery. Re-encoding a thumbnail does not itself add rights metadata: optional IPTC/XMP files are new revision-addressed copies from the separate rights preparation owner, not in-place edits of originals.
- `PublicMedia` decides public eligibility from accepted references; `PublicMediaController` serves authorized original/variant bytes through X-Sendfile. With an explicitly enabled, prepared selection, the **same** owners support revision-aware rights artifacts; without it they keep current source paths. The Viewer remains full resolution by design.
- Credits are not uniform across artwork, journal, custom images and Home. Artwork detail intentionally suppresses the automatic inherited copyright-notice row while preserving photographic credit; do not reintroduce it as a generic consistency fix.

### Canonical rights data and optional revisioned delivery

**Authorship remains precise.** The artist as creator of an artwork must not automatically be identified as photographer, file creator or photograph rights holder. The single `MediaRightsProjection` combines General's default copyright notice, per-asset `inherit | override | none` and the optional Storage fields `file_creator`, `file_rights_holder`, `rights_usage_terms`, `license_inquiry_url` with existing credit and ALT. Do not manufacture ownership, erase third-party attribution or create per-page rights stores. The forward media-rights migration extends Working and Committed assets; source presence alone does not prove that a particular deployment has migrated.

**Current prepared implementation (source inspected at `dev@dee05e4d`; rollout not proven):**

1. [`MediaIptcXmpWriter`](../../app/Domain/Media/MediaIptcXmpWriter.php) writes rights metadata only to a **new staging copy**, never the archived original or an existing immutable thumbnail. It validates source MIME/size/hash before copying and uses configured ExifTool (minimum 12.67). The build specifies ExifTool 13.55; actual image-build/runtime availability is **not established in this Docs review**. Mapping includes creator, rights holder, credit, notice, usage terms and licensing URL; DataMining reservation is gated by an explicit rights decision. Do not infer a rights-reservation authorization from a generic copyright notice. The [runtime and toolchain owner](../architecture/application-runtime-boundaries.md#offline-metadata-writer-ist-kein-public-runtime-owner) holds ExifTool/version/checksum/test details and the preflight-copy race.
2. [`MediaRightsArtifactService`](../../app/Domain/Media/MediaRightsArtifactService.php) prepares an immutable full-resolution image copy and its canonical WebP thumbnail using the same [revision identity](../../app/Domain/Media/MediaRightsArtifactRevision.php). Source hash, rights tags, writer policy and thumbnail transform contribute to the revision; versions cannot silently replace old bytes. Color/ICC, exact pixels, GPS/serial scrub, supported IPTC/XMP tags and metadata readback require **real-file tests**, not only declarations or written unit assertions.
3. [`MediaRightsWorkingPreparation`](../../app/Domain/Media/MediaRightsWorkingPreparation.php) is an **explicit offline path** operating under the existing Publication advisory lock. It prepares an image and its canonical thumbnail and selects both for Working. [`MediaRightsPublicationSelections`](../../app/Domain/Publication/MediaRightsPublicationSelections.php) records Working/checkpoint selections and participates in existing pending/change/restore rules. No parallel Publication system or inferred selection from the artist's edited text.
4. [`PublicMedia`](../../app/Domain/Media/PublicMedia.php) and [`PublicMediaController`](../../app/Http/Controllers/PublicMediaController.php) have **optional, revision-aware URLs/delivery** for verified selected artifacts and otherwise retain their current authorized original/variant path. Preparation and public delivery are independently disabled by default through `MEDIA_RIGHTS_PREPARATION_ENABLED` and `MEDIA_RIGHTS_DELIVERY_ENABLED`. An enabled source branch or installed class does **not** mean that rights-bearing public bytes are live.
5. Artifact integrity, immutable history/Undo references, safe reclamation, public asset eligibility, migration, HTTP range/cache semantics, operator storage/backups and authorized rollout remain distinct verification gates. No tracking, rate limiting, MIME acceptance or media authorization should change merely because metadata can be prepared.

**Required publication properties:** unchanged archival original bytes/SHA-256; full-quality viewer image and existing bounded-thumbnail quality; appropriate ICC/pixels and supported IPTC/XMP; no accidentally leaked GPS/camera serial; correct canonical copyright/creator/third-party metadata; atomic versioned storage and URL/cache identity; Working/Committed/History retention and deletion guards; public-only assets never widened to uncommitted Working media; and safe recoverability from failures. Rights updates must produce a deliberate **prepared revision**, not mutate a previously public immutable URL or publish an unprepared text change. The [media-reference lifecycle](../architecture/media-reference-and-cleanup.md) owns deletion/retention, and the [test/acceptance contract](../operations/testing.md) owns execution.

**Optional later:** signed C2PA/Content Credentials only with a separately verified signing and provenance workflow. Such credentials do not establish legal authorship or prevent copying; they are not prerequisites for the current revisioned metadata architecture.

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
- **Public image rights:** the optional revisioned preparation and delivery pipeline has its **single product owner in section B**. Its defaults are off, so a source-level artifact implementation cannot be described as activated public delivery. The artist does not want an automatic Artwork-detail copyright row; existing photographic credit remains.
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
