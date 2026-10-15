# Public rights, artwork protection and privacy — implementation plan

Status: architectural contract and implementation criteria. Block A General/Legal Disclaimer has source changes authored, but migration application, local test execution and browser acceptance are still open. No provider identity or legal wording has been invented. Last reviewed: 2026-10-14.

## Product boundaries and ownership

- **Product language is English throughout the Admin and public site.** Preserve the existing component name `Legal Disclaimer`; new form labels, section headings, summaries and deep-link fragments are English. This overrides the earlier German UI labels in this plan. German terminology for legal concepts is permitted in internal discussion, not as a surprise UI-language change.

- This is a high-quality, artwork-first public artist portfolio. Preserve full-resolution public Artwork Viewer, zoom, keyboard, native scroll, established public menu, white Content folio, public CSP and search discoverability. No watermark/low-resolution substitution, permanent footer, forced new main-menu page, obstructive overlay, right-click theatre or duplicate public shell.
- The artist retains responsibility and editorial control of the authored information on the About Artist / Contact **Custom Page**, currently named `CV` in parts of the UI. He may change/unpublish it. Do not invent compulsory publishing guards or argue against artist control. A developer should nevertheless make required information clearly findable.
- The site developer owns technical CSP, media delivery/abuse controls, crawl/TDM signals, Matomo consent enforcement and truthful data processing behavior. Artist-written words cannot replace that enforcement.
- Default proposed location: rework the existing `legal_disclaimer` component after CV, social media and Contact into a compact **Legal Disclaimer** component. Until the public browser UI has been inspected, this is a placement decision, **not** a final design. No new global footer. Impressum and Datenschutz must be recognizable, easily/reliably accessible on the site; inspect the actual navigation, SPA, Home Gate and direct linking rather than assuming the CV URL is always accessible. Preserve artist publication choice.
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

- `app/Models/MediaAsset.php`: editorial `alt_text`, `credit`, `copyright_notice_mode`, `copyright_notice`, nullable JSON `metadata`; rights inheritance exists.
- `app/Domain/Media/MediaAssetEditorialService.php` edits only those four metadata fields and audits genuine changes. It does not embed rights metadata in media bytes.
- `app/Domain/Media/MediaIngestService.php` copies canonical original bytes unchanged to `originals/` with SHA-256/length and creates `variants/` WebP thumbnails using PHP GD; re-encoding does not explicitly preserve/add IPTC/XMP and may drop embedded rights metadata. Do not conflate DB metadata with embedded file metadata.
- `app/Domain/Media/PublicMedia.php` determines whether an asset is public based on publication references. `app/Http/Controllers/PublicMediaController.php` then sends eligible original/variant bytes using `X-Sendfile`. Viewer uses originals by design.
- Credits are not uniform across artwork, journal, custom images and Home. Artwork detail intentionally suppresses the automatic inherited copyright-notice row while preserving photographic credit; do not reintroduce it as a generic consistency fix.

### Target single rights projection (no duplicate authorities)

- **General defaults** → **per-MediaAsset override** → **public media metadata, viewer/detail credits, structured data and embedded file metadata**. Preserve existing `inherit | override | none` semantics and never silently replace third-party credits.
- Add to Storage only missing rights facts: **creator/photographer of the *file***, **rights holder(s) of the file**, and optional **license/permissions inquiry URL or usage condition override**, preferably inherit-if-not-specified. Artwork artist is separately represented in the Artwork domain; do not confuse painting author with photograph copyright holder. Existing `credit`, ALT, canonical copyright notice and override already cover other facts.
- Support IPTC/XMP Copyright Notice, Creator, Copyright Owner, Credit Line, Rights Usage Terms, Web Statement of Rights and the IPTC/PLUS Data Mining controlled vocabulary value **'Prohibited except for search engine indexing'** when valid for the rights owner. Write only supported canonical tag IDs and verify via an independent metadata reader, not by guessing string labels. Check creator/photographer and artwork author separately.
- Archive original stays byte-for-byte immutable and retains its stored hash and size. Do not mutate files behind `storage_key` in place. Use a deliberate, atomic public artifact/metadata publishing pipeline: derive or copy a full-quality/public representation without downscaling, embed safe approved IPTC/XMP fields, preserve color/ICC information, handle JPEG/PNG/WebP capabilities, update public URLs/cache keys/hashes, and leave the authoring original intact. Assess extra storage cost, publication snapshots/versioning, server backups and X-Sendfile compatibility *before* implementation. No second incidental image/thumbnail system.
- Regenerate/re-publish rights-bearing public variants when rights fields change. Preserve existing crop/thumbnail quality rules where these are already intentionally smaller; never replace full-res viewer originals with thumbnails. Scrub privacy-sensitive source metadata, particularly GPS/location and camera serial, from public copies unless expressly intended. Do not accidentally destroy third-party embedded license terms.
- Coordinate upload, existing-media migration, editorial updates, deletion, audit/Undo, public gate, preview and cached variants. Runtime tests: metadata present in full-size public bytes AND thumbnails, source original hash unchanged, credit inheritance/overrides/none correct, sensitive location removed, image pixel dimensions and expected visual quality unchanged, media visibility not widened by provenance feature.
- Optional later: cryptographically signed C2PA/Content Credentials after verified signer and provenance workflow. This can authenticate provenance, not guarantee authorship or stop saving/copying; do not install a signing system as a prerequisite for IPTC/XMP.

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

- Current `resources/js/matomo.js` loads and tracks immediately when `MATOMO_TRACKING_ENABLED` is true: pageview, click/view events, link tracking and a 15-second heartbeat. There is no opt-in, refusal or revocation. Cookie-free JavaScript by itself is not automatically exempt under TDDDG/DSGVO. Production flags/server were **not** inspected.
- Build one public-site-owned consent controller, not an artist-editable Custom Page widget. No tracker script, event, analytics request, cookie or heartbeat **before affirmative opt-in**. Equal accept/reject, refusal without preventing art access, persistent choice with expiry, accessible revision/revocation, cookie cleanup where applicable; no permanent footer or intrusive full-art overlay. Matomo `requireConsent` is a defense-in-depth option, not a substitute for gating script loading.
- Livewire `wire:navigate` must not reload or double-count pageviews and must stop all initialized event observers on refusal/revocation. Check old tracking cookies and any existing visitors across deployment. Preserve admin/reporting independence from public browser tracking.
- Artist-facing privacy wording and actual responsible party are to be authored only after data flow inspection: contact submission/email/retention, server logs, host, Matomo configuration and retention/anonymization, third-party map tiles, purpose/legal grounds/recipients. Do not write fake declarations or automatically publish placeholders.
- Verify in real browser/network: cold visit; decline; accept; navigate; refresh; withdraw; refreshed denied state; no analytics pre-consent; keyboard/mobile and stable content geometry. Until complete, public browser Matomo tracking must stay disabled where prior consent cannot be collected.

## Dependency-ordered delivery: four cohesive slices, not scattered microtasks

**Slice 1 — Ownership/editorial content and rendering.** Finalize actual General Legal fields (three new, existing default, reused optional external-links field), canonical validation/save/audit + notifications, rework the existing legal component, preserve published/move/editor functionality, and resolve inherited copyright on Artwork detail. No footer. Real-browser visual acceptance required.

**Slice 2 — Complete media rights pipeline.** MediaAsset rights attribution, one rights projection, metadata writer/publisher preserving archived source, safe regenerated derivatives, consistent credits and schema.org ImageObject, uploads/changes/versioning/tests. Confirm at least one genuine representative image and thumbnail with metadata reader, plus unchanged high-res viewer.

**Slice 3 — Stop abusive extraction while preserving SEO.** Audit media request surface, implement ingress/application rate/bot-abuse control based on measured thresholds and verify Google Images, normal gallery and full-res viewer; evaluate hotlink and signed URLs only after evidence. Existing robots/TDM signals are only a partial start.

**Slice 4 — Matomo/consent and privacy cutover.** Full technical gating + non-obstructing consent management + concrete, artist-supplied/legal-reviewed privacy disclosure + browser/network validation. Track deployment flag and server configuration separately, not with a guessed repo constant.

These slices can be sequenced according to active public/admin work. This is a plan, not authorization for concurrent rewrites, CI access, server changes or deployment. Direct work remains on `dev` only, with HEAD lease/reconciliation. No CI interaction, no unrelated changes, and no unverified 'finished' claims.

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
