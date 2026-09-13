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

Upload aliases are normalized through the ingest policy; persisted canonical MIME is content-derived. Content/container validation rejects incompatible payloads.

## Upload limits and capacity

Format/safety ceilings are configured in bytes:

- `MEDIA_IMAGE_MAX_BYTES` — default 20 MiB
- `MEDIA_VIDEO_MAX_BYTES` — default 100 MiB
- `MEDIA_AUDIO_MAX_BYTES` — default 100 MiB

`MEDIA_STORAGE_QUOTA_BYTES` is the operator/platform-injected admission ceiling. Cached display values are not authoritative for upload admission.

The artist-facing Storage workspace deliberately separates normal navigation from authoritative filesystem measurement. Production and the dedicated local-preview container warm a durable display snapshot at container startup through `php artisan media:measure-capacity`. Normal navigation consumes that already-measured snapshot only and never turns a cache miss into a recursive filesystem walk. Successful ingest and physical cleanup refresh the snapshot at their existing file-mutation boundaries; the explicit Storage `Refresh` action remains a manual rescan/recovery boundary. Upload admission remains independently authoritative.

The dedicated local-preview image supplies a 5,000,000,000-byte allowance so capacity behavior can be evaluated locally without creating a production/application default. Production remains operator-controlled.

## Canonical original and variants

For every `MediaAsset`, authoritative technical identity includes generated storage key, content-derived MIME, byte size and SHA-256.

- originals are never replaced by derivatives;
- technical identity/provenance is not freely editable through normal editorial UI;
- uploads are untrusted until validation succeeds;
- storage keys are application-generated;
- `MediaVariant` is rebuildable and has independent storage/checksum/state;
- missing required variants are integrity/readiness failures, not permission to expose the original as an arbitrary fallback.

Video/audio ingest does not imply transcoding/poster/waveform generation.

## Canonical reference architecture

Consumers reference existing `MediaAsset` IDs rather than copying files.

Structured consumers include:

- Artwork primary/additional media;
- Blog/Exhibition Cover and Gallery through `JournalEntryMedia`;
- CV direct image;
- Custom Page Image components;
- Home Image components;
- site identity/favicon.

Rich Text consumers reference media through canonical Markdown:

```markdown
![](media:<id>)
```

`RichTextMediaReference` owns parsing/formatting. There is no alternate Journal inline-image runtime syntax, alternate Rich Text image model or arbitrary external-image reference system.

Current Rich Text consumers include Blog body, Exhibition description, Custom Page Text/List rich text, CV body and Home rich text.

## Reference versus publication

Two questions are deliberately separate:

### `MediaReferenceQuery`

Answers whether an asset is referenced anywhere in canonical editorial data.

This protects deletion and powers Storage reference/destination inspection. A reference may belong to draft, inactive or presentation-disabled content.

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

Credit/copyright fields are editorial metadata and do not alter immutable technical identity.

## Editor selection

Storage is the authoritative reusable media library. Editor media selection should use the central lazy `MediaAssetSelect` pattern rather than eager full-library preloading/plucking.

Consumer-specific type restrictions remain explicit. Storage supporting audio does not make audio valid in every image/video consumer.

## Deletion and cleanup

Normal media deletion is conservative and reference-aware.

- any supported canonical reference blocks destructive deletion;
- removing one usage does not delete a shared asset;
- canonical Rich Text/direct-image references in Custom/CV/Home/Journal are part of deletion accounting;
- deleting a MediaAsset through an explicitly authorized cleanup path removes/updates all supported canonical references symmetrically before physical cleanup where the current domain contract allows it;
- physical cleanup failure may leave repairable private orphan bytes but must not reactivate a logically deleted record;
- variants follow their original lifecycle and remain rebuildable.

Presentation toggles such as Exhibition Gallery/Map do not silently detach unrelated media.

## Storage workspace

The artist-facing reusable media and capacity workspace is **Storage** at `/admin/storage`. It combines the reusable media library with storage-capacity context rather than maintaining separate Files and Storage workspaces.

Canonical behavior:

- the Storage status reflects authoritative capacity state, not merely library readiness;
- six top metrics combine library counts with capacity state;
- the shared Visual Stage remains three metric-aligned thirds in the artist workflow order **Upload Media Files → Capacity → Destinations**;
- each Visual Stage third uses one concise heading rather than stacked decorative headings;
- the Capacity visualization uses the configured allowance as its common denominator and measured authoritative originals as the used portion; generated variants remain outside that allowance because they are rebuildable derivatives;
- usage-area selection and the Destinations projection may highlight the same measured Storage breakdown without triggering a filesystem measurement;
- where a Storage usage area has an exact canonical library filter, Destinations selection and the library Usage filter stay synchronized; categories without an exact library equivalent remain visualization-only rather than applying an inaccurate filter;
- concrete destination totals are deliberately non-exclusive while the authoritative area breakdown remains exclusive, so a shared original may appear in multiple concrete destinations but contributes its bytes only once to capacity;
- attention retains integrity/context signals such as unused originals, uncatalogued originals, largest gallery and largest original when available;
- authoritative storage analysis starts from measured originals, not only `MediaAsset` rows, so uncatalogued originals remain detectable;
- the media library remains the operational surface for search, type/usage/status filtering, list/grid/dense views, selection, details, download, metadata editing and deletion;
- the persistent `Add Media File` row and the Stage dropzone both invoke the same native file input and canonical direct-ingest path;
- the shared data-table surface contains wide list tables inside their own horizontal overflow boundary rather than allowing table min-content to widen the admin shell;
- a newly accepted upload reloads the library on page one under newest-first ordering; active filters may intentionally keep a new file out of view;
- direct upload uses the canonical ingest/quota policy;
- authenticated image/video/audio preview/player behavior remains available;
- thumbnails/variants remain bounded and expensive original access stays on demand;
- technical hashes/storage paths are not primary artist-facing UI;
- reference filters include structured and Rich Text consumers through central reference rules;
- Dashboard reuses the same shared capacity component in compact form, fed only by the cached capacity snapshot so the dashboard does not add Storage reference-analysis work to normal navigation.

The current visualization renderer is an implementation detail beneath the existing admin theme/Visual Stage contract; browser acceptance may change its composition without changing the Storage domain contract above.

Legacy `/admin/media-files` and `/admin/media-assets` URLs are compatibility redirects only; they are not separate workspaces.

Do not resurrect the removed `StorageCapacity` Filament page, its duplicate table/filter model, stale Journal role constants, consumer-specific ad-hoc parsers, or a second storage/media table beside the canonical Storage library.

## Performance

Storage list/reference rendering must remain bounded.

`MediaReferenceCatalog` may aggregate references across structured/Rich Text consumers, but expensive global content scans should be treated as a real performance concern if browser measurements show they slow normal Storage navigation. Request-local caching can reduce repetition but is not evidence that a broad scan is cheap.

Normal Storage navigation, library filtering, visualization selection and destination drilldown must not trigger an authoritative recursive filesystem walk. Production/local-preview startup warms the durable display snapshot; successful file ingest and physical cleanup refresh it at their mutation boundaries; the explicit `Refresh` action is the manual authoritative rescan/recovery path. A display-refresh failure must not roll back an otherwise successful file mutation; the stale display snapshot is discarded instead.

Do not dismiss source-side media-reference/preload latency merely because local Docker amplifies it. See [ADMIN-PERFORMANCE.md](ADMIN-PERFORMANCE.md).

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

`php artisan media:verify` is the release/recovery integrity check. See [RELEASE.md](RELEASE.md).
