@php
    $assetId = $get('favicon_media_asset_id');
    $asset = is_numeric($assetId)
        ? \App\Models\MediaAsset::query()
            ->where('state', 'available')
            ->whereIn('mime_type', \App\Domain\Media\MediaTypePolicy::IMAGE_MIME_TYPES)
            ->with('variants')
            ->find((int) $assetId)
        : null;
    $thumbnail = $asset?->getRelationValue('variants')->first(static function (\App\Models\MediaVariant $variant): bool {
        return $variant->getAttribute('variant_kind') === \App\Domain\Media\MediaIngestService::THUMBNAIL_KIND
            && $variant->getAttribute('transform_profile') === \App\Domain\Media\MediaIngestService::TRANSFORM_PROFILE
            && $variant->getAttribute('state') === 'available';
    });
@endphp

<div class="admin-favicon-preview" aria-live="polite">
    <div class="admin-favicon-preview__visual" aria-hidden="true">
        @if ($thumbnail instanceof \App\Models\MediaVariant)
            <img src="{{ route('admin.media.variant', $thumbnail) }}" alt="" loading="lazy" decoding="async">
        @else
            <span>—</span>
        @endif
    </div>
    <div class="admin-favicon-preview__copy">
        <strong>Site icon</strong>
        @if ($asset instanceof \App\Models\MediaAsset)
            <span>{{ $asset->getAttribute('original_filename') }}</span>
            <div class="admin-toolbar">
                <button class="admin-action is-danger" type="button" wire:click="removeFavicon">Remove</button>
            </div>
        @else
            <span>No favicon selected</span>
        @endif
    </div>
</div>
