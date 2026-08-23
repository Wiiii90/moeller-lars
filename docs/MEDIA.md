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

Upload aliases such as common M4A/WAV/Ogg browser MIME variants are normalized through the ingest policy; the persisted canonical MIME is content-derived.

Media type is determined from content, not trusted filename extension or browser metadata. Container/content validation rejects incompatible payloads, including video content disguised as M4A audio.

## Upload limits and capacity

Format/safety ceilings are configured in bytes:

- `MEDIA_IMAGE_MAX_BYTES` — default 20 MiB
- `MEDIA_VIDEO_MAX_BYTES` — default 100 MiB
- `MEDIA_AUDIO_MAX_BYTES` — default 100 MiB

Consumers must not duplicate these limits as independent constants.

`MEDIA_STORAGE_QUOTA_BYTES` is the application storage-admission ceiling injected by the operator/platform contract. Every accepted original passes the canonical capacity path before durable storage. Cached display values are never authoritative for upload admission.

Image processing also enforces decoded-pixel/dimension safety before expensive decoding.

## Canonical original

For every `MediaAsset`, authoritative technical identity includes generated storage key, content-derived MIME, byte size and SHA-256.

- original bytes are not replaced by generated derivatives;
- technical identity/provenance is not freely editable through normal editorial UI;
- new originals are untrusted until validation succeeds;
- invalid/suspicious files are rejected or quarantined according to the ingest path;
- storage keys are application-generated and never trusted user filesystem paths.

## Variants

`MediaVariant` represents a generated derivative of one canonical original.

Variants:
- are rebuildable;
- never overwrite original metadata;
- have independent storage identity/checksum/state;
- must not silently fall back to the original when a consumer requires a derivative.

Current image consumers use generated image derivatives where required. Video/audio ingest does **not** imply a transcoding, poster-frame, waveform or generated-thumbnail contract.

## Usage references

Consumers reference existing `MediaAsset` IDs rather than copying physical files.

Current usage contexts include Artwork, Exhibitions, Blog/Journal content, Custom Page/CV content and site identity. Consumer-specific rules still apply.

Important rules:
- moving Artwork between Galleries does not duplicate/detach its MediaAssets;
- removing Artwork from a Gallery is an Artwork assignment change, not media deletion;
- replacing primary Artwork media changes the usage relation and preserves reusable assets;
- shared references remain visible before destructive actions.

Gallery primary Artwork media is visual image/video. Audio being supported in Files does not automatically make audio a primary Gallery visual. Optional Artwork-specific audio is a separate feature contract.

## ALT and editorial metadata

`MediaAsset.alt_text` is the canonical asset-level ALT value. A usage-specific override is allowed only where the relation explicitly supports it.

Required public ALT is not manufactured from filenames/IDs at render time.

Credit/copyright fields are editorial metadata and do not alter immutable technical identity.

## Deletion

Normal media deletion is conservative and reference-aware.

- any supported live reference blocks destructive deletion;
- removing one usage does not delete a shared asset;
- logical DB/audit state commits before physical cleanup is treated as successful;
- physical cleanup failure is surfaced and may leave private orphan bytes for repair; it must not reactivate a deleted record or be misreported as full success;
- variants follow their canonical original lifecycle but remain rebuildable.

## Files workspace

The artist-facing reusable media workspace is **Files**. It is optimized for finding/reusing/managing assets, not for imitating a Gallery contact sheet.

Canonical behavior:
- compact List as primary high-density mode;
- optional Grid and Dense modes;
- search/filter state shared across modes where applicable;
- direct upload through canonical ingest/quota policy;
- authenticated image/video/audio preview/player behavior appropriate to the media kind;
- metadata editing and actual reference-location inspection;
- image previews use bounded derivatives;
- expensive original access is on demand;
- technical hashes/storage paths are not primary artist-facing UI;
- shared admin theme owns generic geometry; Files-specific layout is limited to actual media-library needs.

## Public delivery

Public routes expose media only through an allowed public content context. Raw storage paths are never public route parameters.

A MediaAsset being accepted into Files does not mean every public consumer supports it. Each public surface must explicitly support the media kind before rendering it.

For example:
- Gallery Artwork visual media supports image/video under the Gallery contract;
- audio remains manual/opt-in wherever a future consumer explicitly supports it;
- no arbitrary codec/transcoding fallback is assumed.

## Verification

Durable verification covers:
- allowlisted content/MIME classification;
- type-specific byte limits;
- invalid/corrupt/container-mismatch rejection;
- canonical checksum/byte-size preservation;
- storage-key/path safety;
- storage quota admission;
- derivative generation/integrity where required;
- reference-aware deletion guards;
- cleanup failure semantics.

`php artisan media:verify` is the release/recovery integrity check for persisted Media records and files. See [RELEASE.md](RELEASE.md).