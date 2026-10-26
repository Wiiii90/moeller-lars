# Media contract

`MediaAsset` is the reusable canonical original. Content surfaces reference it; detaching a usage never implies deleting the canonical file. Generated variants are rebuildable derivatives.

## Supported ingest

`MediaTypePolicy` owns the canonical allowlist and byte limits.

Current accepted media:

### Images
- JPEG (`image/jpeg`)
- PNG (`image/png`)
- WebP (`image/webp`)

### Video
- MP4 (`video/mp4`) with accepted H.264 video content
- WebM (`video/webm`) with accepted VP8, VP9 or AV1 video content

### Audio
- MP3 (`audio/mpeg`)
- M4A/AAC (`audio/mp4`)
- Ogg (`audio/ogg`)
- WAV (`audio/wav`)

Upload aliases are normalized through the ingest policy; persisted canonical MIME is content-derived. FFprobe verifies authoritative video streams and codecs for MP4/WebM instead of accepting a suspicious file from partial signature bytes alone. Content/container validation rejects incompatible payloads. Healthy uploaded originals may be reused by SHA-256/identity checks instead of duplicating Storage assets; an Artwork draft must still be created and canonically linked, never silently skipped.

## Upload limits and capacity

Format/safety ceilings are configured in bytes:

- `MEDIA_IMAGE_MAX_BYTES` — default **64 MiB**
- `MEDIA_VIDEO_MAX_BYTES` — default **150 MiB**
- `MEDIA_AUDIO_MAX_BYTES` — default 100 MiB

`MEDIA_STORAGE_QUOTA_BYTES` is the operator/platform-injected **whole-site** admission ceiling. Quota usage includes canonical media originals, generated variants and logical persistent application/database data. Logical database usage includes live editorial data, append-only Activity, bounded Undo receipts, retained Publication restore data/shared history payloads and other persistent application rows. PostgreSQL's physical database-file footprint is operational overhead and is not the quota value. Cached display values are not authoritative for upload admission.

The artist-facing Storage workspace deliberately separates normal navigation from authoritative filesystem measurement. Production and the dedicated local-preview container warm a durable display snapshot at container startup through `php artisan media:measure-capacity`. Normal navigation consumes that already-measured snapshot only and never turns a cache miss into a recursive filesystem walk. Successful ingest and physical cleanup refresh the snapshot at their existing file-mutation boundaries; the explicit Storage `Refresh` action remains a manual rescan/recovery boundary. Upload admission remains independently authoritative.

The dedicated local-preview image supplies a 5,000,000,000-byte allowance so capacity behavior can be evaluated locally without creating a production/application default. Production remains operator-controlled.

## One-time local thumbnail consolidation (pre-release only)

Older stored `public-v1` GD thumbnails may coexist with newly ingested `public-v2` libvips derivatives. `MediaThumbnailCutoverService`, exposed as `media:thumbnails:consolidate`, provides an explicit **read-only preview by default** and a separately gated `--apply` conversion. It is **not** a routine ingest path, scheduler, deployment migration, automatic backfill or Production repair command.

Apply is restricted in code to local/testing **PostgreSQL** and obtains both the media-ingest cache lock and Publication advisory/session lock. It byte-verifies immutable originals and conflicting existing variants, runs the authoritative quota check before staging extra bytes, installs new derivative files without overwriting old files, and transactionally updates the same `MediaVariant` IDs in Working, Committed and retained `publication_version_rows`. Existing restorable checkpoints require the **separately explicit** `--rebaseline-checkpoints` flag because checkpoint hashes change (the editorial history is retained); enabled rights delivery blocks apply and existing thumbnail rights selections require the separately explicit `--invalidate-thumbnail-rights`, followed by offline rights re-preparation. Failed staging must clean its own new bytes and leave the old registry intact; post-commit orphan cleanup is limited to unreferenced prior variants and is retryable. An opt-in conversion does **not** license rewriting the canonical original or deleting protected historical references.

**Environment restriction:** The artist-prepared **Validation database/media are protected cutover material**, regardless of any superficial resemblance to a test system. Neither default preview nor apply should be run there as part of documentation work. The first authorized end-to-end run belongs in an explicitly disposable, isolated local copy with synthetic data and separately reviewed Publication/rights/rollback checks; this source review did not execute the command. Do not retire historical GD regeneration until a proven cutover and residual-variant check establish that it is no longer necessary.

## Canonical original and variants

For every `MediaAsset`, authoritative technical identity includes generated storage key, content-derived MIME, byte size and SHA-256.

- originals are never replaced by derivatives;
- technical identity/provenance is not freely editable through normal editorial UI;
- uploads are untrusted until validation succeeds;
- storage keys are application-generated;
- `MediaVariant` is rebuildable and has independent storage/checksum/state;
- missing required variants are integrity/readiness failures, not permission to expose the original as an arbitrary fallback.

Video ingest creates a separate immutable WebP poster via FFmpeg without transcoding the original. Audio produces no poster or waveform. Historical video records may lack a poster; new video uploads create one.

## Immutable preview, rights artifact and retention policy (source `dev@2ad31502ea4e`)

The current [Media ingest owner](../app/Domain/Media/MediaIngestService.php) uses **libvips for every new image thumbnail** (bounded to 960px, with a distinct `public-v2` transform profile for eligible high-resolution originals). FFmpeg produces the immutable `video-poster-v1` WebP preview of a supported video. `MediaPreviewVariant` is the common selector for both thumbnail profiles and video poster, shared across Admin, Gallery, Publication and Public. GD is retained only for historical variant regeneration; it is not a second pipeline for new uploads. The ingest lock spans derivative preparation, and authoritative capacity admission reserves original plus new preview bytes once, without double-counting existing variants. Unsupported, missing or corrupt required previews are failures, not permission to serve a different asset.

Media rights-bearing image bytes are a **separate immutable artifact**: `MediaRightsArtifactService` prepares SHA-256/revision-addressed copies without rewriting originals or existing variants; `MediaRightsPublicationSelections` selects Working/checkpoint/LIVE versions under the Publication lock. Preparation and delivery are independently **opt-in** via `rights_preparation_enabled` and `rights_delivery_enabled`. The source contains optional preparation, revisioned serving and bounded orphan reclamation; presence of these services is not proof that Validation or Production enables them. See [Public rights and privacy](public/rights-privacy.md) and the [Publication architecture](architecture/publication.md).

Artwork draft deletion and optional **delete original** are not the same operation. The Artwork/relationship change and eligible MediaAsset logical deletion are transactional and audited; physical bytes must be queued through the durable `PublicationMediaCleanupService` outbox, rechecked against Working/Committed/history references, and drained/retried separately. A successful logical transaction does **not** assert that physical files were synchronously removed. No persistent Validation data may be used as a cleanup test fixture.

## Canonical reference architecture

Consumers reference existing `MediaAsset` IDs rather than copying files.

Structured consumers include:

- Artwork primary/additional media;
- Blog/Exhibition Cover and Gallery through `JournalEntryMedia` (Exhibition Cover/Gallery accept canonical image **or video**, Blog Cover stays image-only; Exhibition media can display authored File credits/copyright overrides);
- historical CV direct-image references were retired with the `cv_entries` table; current CV/Vita is Custom Page List/Text content;
- Custom Page Image components;
- Home Image components;
- site identity/favicon.

Rich Text uses the **same canonical `MediaAsset` identifier** in both historical Markdown (`![](media:<id>)`) and current visual HTML. `RichTextDocument` converts legacy Markdown only when entering the visual editor; no compulsory database rewrite occurs. `RichTextMediaReference` discovers managed image references and `SafeRichTextRenderer` validates and safely renders both formats under the current media-eligibility and link policy. HTML rendering is not a separate image library or a general license to insert external assets. The visual editor selects available Storage assets through `MediaAssetSelect`, never uploads a second file through RichEditor; its defensive attachment provider rejects such uploads. Deleting an image node leaves the reusable Storage asset intact.

Public image ALT uses canonical Storage metadata and a validated occurrence-specific override when allowed; captions are visible descriptions **not** substitutes for ALT. Existing external HTTP(S) content links and legacy media compatibility require `SafeLinkPolicy` and the Content Security Policy (CSP); editor URL acceptance does not prove a browser may load a remote image. See [Editorial Rich Text](content/rich-text.md) and [Public Security](architecture/public-security-privacy-boundaries.md).

Current Rich Text consumers include Blog body, Exhibition description, Custom Page Text/List rich text and Home rich text; historical CV-specific editor/table paths remain retired. Structured Journal Cover/Gallery media and inline images are independent consumer roles, with the same canonical Storage ownership.

## Reference versus publication

Two questions are deliberately separate:

### `MediaReferenceQuery`

**Contract:** detect every managed-asset reference in canonical editorial data. **Current source coverage is incomplete** for decorative Image children and Home Lists; an empty Working reference result is therefore not evidence of complete safety. The [source-verified coverage matrix](architecture/media-reference-and-cleanup.md#quellbasierte-medienreferenzmatrix) is the sole cross-reader inventory.

This powers Storage reference/destination inspection and informs **canonical reference-aware deletion**. It does **not** uniformly block deletion: `MediaAssetEditorialService::delete()` checks whether references exist and, when they do, invokes the domain-owned removal path before marking the asset and variants deleted. A reference may belong to draft, inactive or presentation-disabled content.

For a Journal SiteSection, reference discovery includes **both retained Blog and Exhibition entry worlds** even when only one Journal template is currently active. Template switching does not make inactive retained content “unreferenced”.

### `PublicMedia`

Answers whether an asset is actually eligible for ordinary public delivery now.

Publication considers current consumer/lifecycle/presentation state. A referenced asset is not automatically public.

Examples:

- a published Exhibition Cover remains public under the normal Exhibition publication contract;
- Exhibition Gallery assets are public only when the Exhibition is published on the active public Exhibitions Journal **and** `gallery_enabled=true`;
- disabling Gallery leaves its `JournalEntryMedia` rows referenced/stored but removes ordinary public delivery eligibility;
- draft/private assets may be visible through protected preview without becoming public.

## Protected preview media

Protected preview uses the same MediaAsset/MediaVariant identities under authenticated preview routes:

```text
/preview/media/original/{mediaAsset}
/preview/media/variant/{mediaVariant}
```

`PublicMedia` chooses preview URLs while `SitePreviewContext` is active. Preview context does not make `PublicMedia::isPublicAsset()` return true and does not create a second media type.

## ALT and editorial metadata

`MediaAsset.alt_text` is the canonical asset-level ALT value.

Usage-specific overrides exist only where an explicit consumer contract supports them.

- Artwork may retain its supported usage-specific ALT semantics.
- structured Journal Cover/Gallery runtime uses **MediaAsset ALT exclusively**;
- legacy Journal `alt_text_override` values may remain stored as historical compatibility data, but runtime rendering/readiness ignores them and newly synchronized Journal usages write null;
- canonical embedded Rich Text images fall back to MediaAsset ALT when Markdown does not provide an occurrence-specific value.

Required public ALT is never manufactured from filenames/IDs at render time.

Credit/copyright and the optional `file_creator`, `file_rights_holder`, `rights_usage_terms`, `license_inquiry_url` fields are editorial metadata; they do not alter immutable technical identity or determine authorship automatically. `MediaRightsProjection` feeds an opt-in immutable rights-artifact preparation system, which can select separate Working/Committed revisions under the Publication lock. With preparation explicitly enabled, **genuine changes to stored rights fields or General's inherited default now dispatch background preparation after commit**, and newly ingested images do likewise; no work is dispatched for unchanged values or while preparation is disabled. These jobs share the existing database queue and single rights-artifact writer, not a second ingestion pipeline. Prepared bytes are **not automatically publicly delivered**: delivery requires independently enabled configuration, migration, current Working/checkpoint selections and publication readiness across all publicly eligible images. The [rights and privacy owner](public/rights-privacy.md#b-rights-metadata-across-storage-public-files-and-seo) defines the exact event sources and acceptance gates.

## Editor selection

Storage is the authoritative reusable media library. Editor media selection should use the central lazy `MediaAssetSelect` pattern rather than eager full-library preloading/plucking.

Consumer-specific type restrictions remain explicit. Storage supporting audio does not make audio valid in every image/video consumer. Home and Custom Page structured video components own `video_controls_enabled` through the shared Image component schema and render path (default true for existing/missing data); a decorative media component omits native controls. Journal's shared media renderer currently emits native controls on video, and its `image_viewer_enabled` option governs images only. Gallery/Artwork sound policy is a separate concern. Do not infer global playback-control uniformity or add a second media playback implementation.

## Deletion and cleanup

Normal media deletion is conservative and reference-aware.

- references must be discovered and removed or updated by the **single canonical editorial deletion transaction** before the asset is logically deleted; their presence is not an unconditional refusal to delete;
- removing one usage does not delete a shared asset;
- canonical Rich Text and direct-image references in Custom Pages, Home, Journal, Artwork and site identity are part of deletion accounting; retired CV database rows do not reappear as a current reference source;
- deleting a MediaAsset through an explicitly authorized cleanup path removes/updates all supported canonical references symmetrically before physical cleanup where the current domain contract allows it;
- physical cleanup failure may leave repairable private orphan bytes but must not reactivate a logically deleted record;
- variants follow their original lifecycle and remain rebuildable.

Presentation toggles such as Exhibition Gallery/Map do not silently detach unrelated media.

**Current source boundary (reviewed against `dev@e8db4295`; no tests executed here):** The canonical reference/read/removal paths still do **not** cover every structured component equally. The detailed [reader and deletion matrix](architecture/media-reference-and-cleanup.md#quellbasierte-medienreferenzmatrix) owns the concrete cases: decorative Custom Page Image children with Rich Text media, Home List direct media or retained entry bodies, and the Custom Page List direct-medium Public eligibility check. `MediaReferenceQuery::mediaIdsForHome()` and `PublicMedia::activeHomeReferencesAsset()` inspect Image/Text, not List; `MediaAssetEditorialService::removeHomeReferences()` likewise lacks List handling. `RichTextMediaReference::idsFromCustomPageBlocks()` does not recurse into Image children; `PublicMedia::publishedCustomPageReferencesAsset()` does not authorize List's own `media_asset_id`. The presence of a source-level omission does not establish a completed file deletion or successful browser exploit; do not assert full reference symmetry or physical cleanup from a successful database change. Any correction belongs to the existing common Media/structured-component owners, not independent Home-specific reference systems.

### Gallery, Artwork usage and Storage deletion

A `MediaAsset` is a reusable Storage file; an `Artwork` is a separate editorial record. **When explicit Storage deletion is authorized**, [`MediaAssetEditorialService`](../app/Domain/Media/MediaAssetEditorialService.php) must remove all related usages within its single canonical transaction, unpublish any Artwork losing its primary usage and remove that Artwork from its Gallery without deleting unrelated assets. An unassigned Artwork remains as historical/editorial metadata; a Gallery card must never be left orphaned. Do **not** add a second Gallery-local cleanup mechanism. Reference discovery is an input to this removal transaction, **not** a general hard block; subsequent physical-media removal is subject to Working, LIVE and retained-history protection.

The Gallery editor may retain incomplete Draft artworks. Public and artist-preview artwork-card queries are limited to media-renderable Artworks with exactly one available image/video primary, effective ALT and (for images) a valid thumbnail. `PublicMedia` must not hide a violated invariant as a successful render. Newly created Artworks remain Draft until explicitly published.

Add Artwork ingests an upload into reusable Storage first and then atomically persists the Artwork, its primary link and ALT override. Edit follows the same boundary: ingestion occurs **outside** any enclosing Artwork database transaction, while metadata, primary linkage and Artwork-specific ALT update together **inside** it. Failure of an Artwork mutation must not claim to roll back a Storage `MediaAsset` after its files were written; failed linkage must not leave an orphan Gallery Draft. Existing incomplete Drafts can be repaired in Edit by selecting/uploading new primary media and entering an ALT.

Usage-specific ALT is authored per Artwork through `ArtworkMedia.alt_text_override` and the canonical `MediaAssetEditorialService`, rather than mutating the shared Storage asset's ALT. The intended public readiness and publishing guards must use the **same effective ALT** (override or asset), and Preview must not fail with HTTP 500 for incomplete drafts. **Current implementation discrepancy:** [Gallery and Undo use different publish-guards](architecture/import-boundaries.md), so this final invariant is a requirement and **not yet fully verified for Undo**.

Required regression coverage includes Storage/Gallery primary deletion, shared usage, Add/Edit ALT, incomplete Draft repair and Draft→Published.

## Storage workspace

The artist-facing reusable media and capacity workspace is **Storage** at `/admin/storage`. It combines the reusable media library with storage-capacity context rather than maintaining separate Files and Storage workspaces.

Canonical behavior:

- the Storage status reflects authoritative **whole-site** capacity state, not merely library readiness;
- six top metrics combine library counts with capacity state;
- the shared Visual Stage remains exactly three metric-aligned thirds in the artist workflow order **Upload Media Files → Total Capacity → Media Distribution**; Stage content must not extend the shared Stage height;
- each Visual Stage third uses one concise heading rather than stacked decorative headings;
- the Total Capacity visualization uses the configured allowance as its common denominator and total quota-relevant site usage as the used portion; its coarse used segments are Original Media, Generated Variants and Application Data;
- Application Data is logical persistent database usage. Internal Activity/Undo/Publication/shared-history components contribute to Total Capacity but are not promoted into separate media filters or Media table rows;
- Media Distribution remains a media-only projection because its rows can map to the media library below. It lists all matching distribution rows inside the fixed third and scrolls internally rather than truncating rows or growing the Stage;
- where a Media Distribution row has an exact canonical library filter, selecting it and the library Usage filter stay synchronized; categories without an exact library equivalent remain visualization-only rather than applying an inaccurate filter;
- concrete media destination totals are deliberately non-exclusive while authoritative original bytes remain exclusive, so a shared original may appear in multiple concrete destinations without being charged twice as an original;
- attention retains integrity/context signals such as unused originals, uncatalogued originals, largest gallery and largest original when available;
- authoritative storage analysis starts from measured originals, not only `MediaAsset` rows, so uncatalogued originals remain detectable;
- the media library remains the operational surface for search, type/usage/status filtering, list/grid/dense views, selection, details, download, metadata editing and deletion;
- the persistent `Add Media File` row and the Stage dropzone both invoke the same native file input and canonical direct-ingest path;
- the shared data-table surface contains wide list tables inside their own horizontal overflow boundary rather than allowing table min-content to widen the admin shell;
- a newly accepted upload reloads the library on page one under newest-first ordering; active filters may intentionally keep a new file out of view;
- direct upload uses the canonical ingest/quota policy. **For multi-file selections (source `dev@e3d0b507`)**, the existing dropzone transfers and ingests each selected file through a separate Livewire upload request **in order**, rather than sending the entire selection as one request above the per-request transport ceiling. The same `processDirectMedia()`/`MediaIngestService::ingestUnique()` owner handles validation, duplicate/usage/quota decisions and derivative creation. The UI aggregates file progress, genuine server-side failures and one overall result; there is no second upload pipeline, background job or timer-based ingestion. The authored code has **not** been browser-tested here with representative large multi-file batches, failed transfers and duplicate/quota conditions;
- authenticated image/video/audio preview/player behavior remains available;
- thumbnails/variants remain bounded and expensive original access stays on demand;
- technical hashes/storage paths are not primary artist-facing UI;
- reference filters include structured and Rich Text consumers through central reference rules;
- Dashboard reuses the same shared capacity component in compact form, fed only by the cached capacity snapshot so the dashboard does not add Storage reference-analysis work to normal navigation;
- `Refresh` and `Free storage` share the action rail beneath Total Capacity. `Free storage` is the explicit destructive recovery-space action; its concise hover/focus hint states that it clears Undo history, old restore snapshots and rebuildable thumbnails before the shared danger confirmation: all current Undo receipts are cleared, older unprotected Publication restore snapshots are released, safely rebuildable generated thumbnails are removed, and media/history garbage collection may then remove data that has no remaining recovery root;
- Activity itself is permanent and is never reclaimed by this action. The current LIVE Publication snapshot and the Publication snapshot currently serving as Working's restore/revert source remain protected. Commit metadata also remains factual history even when an older snapshot payload is no longer restorable;
- generated files are reclaimed only when the current variant pipeline proves they can be recreated from canonical originals. Logical site usage is refreshed immediately; PostgreSQL's physical database file may shrink only later through normal database maintenance.

The current visualization renderer is an implementation detail beneath the existing admin theme/Visual Stage contract; browser acceptance may change its composition without changing the Storage domain contract above.

Do not resurrect the removed `StorageCapacity` Filament page, its duplicate table/filter model, stale Journal role constants, consumer-specific ad-hoc parsers, or a second storage/media table beside the canonical Storage library.

## Performance

Storage list/reference rendering must remain bounded.

`MediaReferenceCatalog` may aggregate references across structured/Rich Text consumers, but expensive global content scans should be treated as a real performance concern if browser measurements show they slow normal Storage navigation. Request-local caching can reduce repetition but is not evidence that a broad scan is cheap.

Normal Storage navigation, library filtering, visualization selection and destination drilldown must not trigger an authoritative recursive filesystem walk. Production/local-preview startup warms the durable display snapshot; successful file ingest and physical cleanup refresh it at their mutation boundaries; the explicit `Refresh` action is the manual authoritative rescan/recovery path. A display-refresh failure must not roll back an otherwise successful file mutation; the stale display snapshot is discarded instead.

Do not dismiss source-side media-reference/preload latency merely because local Docker amplifies it. See [ADMIN-PERFORMANCE.md](admin/performance.md).

## Public delivery

Public routes expose media only through an allowed public content context. Raw storage paths are never public route parameters.

A MediaAsset being accepted into Storage does not mean every public consumer supports it. Each public surface explicitly defines supported media kinds and publication conditions.

## Verification

Durable verification covers:

- allowlisted MIME/content classification;
- byte/pixel/container limits;
- checksum/storage-key integrity;
- quota admission;
- derivative integrity where required;
- canonical reference detection;
- public/preview policy separation;
- reference-aware deletion;
- cleanup failure semantics;
- uncatalogued authoritative-original detection;
- exclusive area accounting and non-exclusive concrete destination drilldown;
- bounded Storage reference projection.

`php artisan media:verify` is the release/recovery integrity check. See [RELEASE.md](operations/release.md).
