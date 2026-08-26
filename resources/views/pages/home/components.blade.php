<div class="custom-page home-components" aria-label="Home presentation">
    @if ($components === [])
        <p class="public-empty-state">This Home presentation does not have content yet.</p>
    @else
        @foreach ($components as $componentIndex => $component)
            @php($type = is_array($component) ? ($component['type'] ?? null) : null)

            @if ($type === 'image')
                @php
                    $assetId = is_numeric($component['media_asset_id'] ?? null) ? (int) $component['media_asset_id'] : null;
                    $asset = $assetId !== null ? $assets->get($assetId) : null;
                    $decorative = (bool) ($component['image_decorative'] ?? false);
                    $imageAlt = $asset !== null && ! $decorative ? trim((string) $asset->getAttribute('alt_text')) : '';
                    $variant = $asset !== null ? $media->thumbnailVariantForAsset($asset) : null;
                @endphp

                @if ($asset !== null && $variant !== null)
                    <figure class="custom-page__component custom-page__media custom-page__image">
                        <img
                            src="{{ $media->variantUrl($variant) }}"
                            alt="{{ $imageAlt }}"
                            loading="{{ $componentIndex === 0 ? 'eager' : 'lazy' }}"
                            decoding="async"
                        >
                    </figure>
                @endif
            @endif

            @if ($type === 'text')
                <section class="custom-page__component">
                    <div class="custom-page__copy">
                        @if (filled($component['title'] ?? null))
                            <h2>{{ $component['title'] }}</h2>
                        @endif
                        @if (filled($component['body'] ?? null))
                            <div class="rich-text">{!! $richText->render((string) $component['body']) !!}</div>
                        @endif
                    </div>
                </section>
            @endif

            @if ($type === 'divider')
                <div class="custom-page__divider" aria-hidden="true"></div>
            @endif
        @endforeach
    @endif
</div>