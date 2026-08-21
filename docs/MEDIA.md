# Media domain and editorial workspace

`MediaAsset` is the reusable canonical asset. Artwork/gallery placement, Exhibition placement, Vita/CV use, Blog use, and site-identity use are references to that asset; detaching a reference never deletes the canonical file. Physical deletion remains an explicit Media action and is blocked while any supported reference exists.

## Supported ingest types

The application currently admits only an explicit browser-safe allowlist:

- JPEG (`image/jpeg`)
- PNG (`image/png`)
- WebP (`image/webp`)
- MP4 (`video/mp4`) with a detected H.264 (`avc1`/`avc3`) video track
- WebM (`video/webm`) with a detected VP8, VP9, or AV1 video track

Images receive the existing WebP thumbnail derivative. Video is stored canonically without transcoding or generated poster frames in this slice; the admin inspector streams the canonical asset through the authenticated media-preview route and uses the browser-native video player.

The Media schema already stores MIME type, byte size, checksum, nullable dimensions, and extensible JSON metadata, so video support does not require a database migration. Video assets intentionally have null width/height until a future metadata extractor is introduced.

## Upload policy and storage admission

Type-specific file-size ceilings are operator configuration, expressed in bytes:

- `MEDIA_IMAGE_MAX_BYTES` (default 20 MiB)
- `MEDIA_VIDEO_MAX_BYTES` (default 100 MiB)

These are format/safety limits, not quota accounting. Every accepted upload still passes through the existing serialized `MediaIngestService` path and calls `MediaCapacityService::assertCanStoreOriginal()` before any write. `MEDIA_STORAGE_QUOTA_BYTES` therefore remains the single authoritative #59 admission ceiling; this feature does not implement a second quota.

## Workspace behavior

The global Media workspace defaults to a paginated compact list. Search covers filename, ALT text, credit, copyright, and MIME. Filters cover type/MIME, referenced/unreferenced state, consuming context, and media state. Grid and dense no-thumbnail modes reuse the same Livewire filter/search state rather than issuing separate library semantics.

The inspector loads the original only on demand and shows all current first-class consumers in one usage list. List/grid pages load only bounded thumbnail derivatives for image identification and use relationship counts instead of per-row usage queries.

## Integration boundary

Gallery/Artwork, Exhibitions, Blog, Vita/CV, and General/Site Identity should attach existing `MediaAsset` IDs rather than copying files. Content-specific validation still applies: favicon and other image-only fields must continue to constrain their selections even though the Media library itself also contains video.

Public Gallery/Blog/Exhibition video rendering is deliberately not enabled by this slice. Consumers may add video rendering when their own domain contract is ready by checking the asset MIME against `MediaTypePolicy::VIDEO_MIME_TYPES` and serving the existing canonical media route. No transcoding service, arbitrary codec fallback, or implicit image assumption should be introduced at that boundary.
