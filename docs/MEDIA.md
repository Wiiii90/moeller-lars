# Media contract

`MediaAsset` is the reusable canonical original. Content surfaces reference it; detaching a usage never implies deleting the canonical file. Generated variants are rebuildable derivatives.

## Supported ingest

Current explicit browser-safe allowlist:

- JPEG (`image/jpeg`)
- PNG (`image/png`)
- WebP (`image/webp`)
- MP4 (`video/mp4`) with detected H.264 (`avc1`/`avc3`) video
- WebM (`video/webm`) with detected VP8, VP9 or AV1 video

Images receive the required generated image derivative(s), including the thumbnail used by current public/admin consumers. Video originals are stored canonically without an implicit transcoding service or generated poster-frame contract.

Media type is determined from content, not trusted filename extension or browser metadata.

## Upload limits and capacity

Format/safety ceilings are configured in bytes:

- `MEDIA_IMAGE_MAX_BYTES` — default 20 MiB
- `MEDIA_VIDEO_MAX_BYTES` — default 100 MiB

The canonical application policy is `MediaTypePolicy`; consumers must not duplicate these limits as independent constants.

`MEDIA_STORAGE_QUOTA_BYTES` is the storage-admission ceiling. Every accepted original passes the canonical ingest/capacity path before durable storage. A UI/display cache is never sufficient for authoritative upload admission.

Image processing also enforces decoded-pixel/dimension safety before expensive decoding as defined by the release/runtime contract.

## Canonical original

For every `MediaAsset`, authoritative technical identity includes the generated storage key, content MIME, byte size and SHA-256.

- Original bytes are not replaced by generated derivatives.
- Technical identity/provenance is not freely editable through the editorial UI.
- New originals are untrusted until validation succeeds.
- Invalid/suspicious files are rejected or quarantined according to the ingest path.
- Storage keys are application generated and are not derived as trusted filesystem paths from user filenames.

## Variants

`MediaVariant` represents a generated derivative of one canonical original.

Variants:

- are rebuildable;
- never overwrite original metadata;
- have their own storage identity/checksum/state;
- must not silently fall back to serving the original when a consumer contract requires a specific derivative.

Missing required public derivatives are integrity/readiness findings until regenerated and verified.

## Usage references

Current first-class consumers include Artwork, Exhibitions, Custom Page content/site identity and Blog/CV-related usages supported by the application.

Rules:

- consumers reference existing `MediaAsset` IDs rather than copying files;
- content-specific type/publication rules still apply (for example favicon/image-only fields);
- Artwork media usages preserve explicit role/order/ALT semantics;
- moving an Artwork between Galleries does not duplicate or detach its media references;
- replacing primary Artwork media is an explicit editorial operation rather than an implicit delete-and-recreate of unrelated records.

## ALT and editorial metadata

`MediaAsset.alt_text` is the canonical asset-level ALT value. A usage-specific ALT override is allowed only where the relevant relation explicitly supports it and intentionally takes precedence.

Required public ALT data is not manufactured from filenames, IDs or another unrelated field at render time.

Credit/copyright fields are editorial metadata and do not alter the immutable technical identity of the original.

## Deletion

Normal media deletion is conservative and reference-aware.

- Any supported live reference blocks destructive deletion.
- Removing one usage does not delete a shared asset.
- Logical database/audit state commits before physical cleanup is treated as successful.
- Physical cleanup failure is surfaced as an operation failure and may leave private orphaned bytes for later repair; it must not be converted into a false success or reactivate a deleted asset.
- Variants follow the lifecycle of their canonical original but remain rebuildable.

## Media workspace

The admin Media workspace is a bounded/paginated library.

- Search/filter/list/grid modes share canonical query/filter state.
- Image thumbnails use bounded generated derivatives.
- Expensive original preview is loaded on demand through authenticated media-preview routes.
- Usage information uses relationship counts/aggregates rather than per-row lookup fanout.
- Media uses the single canonical admin theme; it does not load a page-local stylesheet.

## Public delivery

Public routes expose only media allowed by the requesting public content context. Raw storage paths are never public route parameters.

Video may exist in the Media library without implying that every public Gallery/Blog/Exhibition surface renders video. A consumer must explicitly support the media type before making it public; no arbitrary codec/transcoding fallback is assumed.

## Verification

Durable verification covers:

- allowlisted content/MIME classification;
- type-specific byte limits;
- invalid/corrupt input rejection;
- canonical checksum/byte-size preservation;
- storage-key/path safety;
- storage quota admission;
- derivative generation/integrity where required;
- reference-aware deletion guards;
- cleanup failure semantics.

`php artisan media:verify` is the release/recovery integrity check for persisted Media records and their files. See [RELEASE.md](RELEASE.md) for its role in Validation/restore checks.
