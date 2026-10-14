    @php
        $isEmbedded = (bool) ($isEmbedded ?? false);
        $imageBlockPrefix = $imageBlockPrefix ?? 'page';
    @endphp
    <div class="custom-page{{ $isHome ? ' home-components' : '' }}{{ $isEmbedded ? ' custom-page--embedded' : '' }}" @unless($isEmbedded)aria-label="{{ $label }}"@endunless>
        @foreach ($blocks as $blockIndex => $block)
            @php
                $type = is_array($block) ? ($block['type'] ?? null) : null;
                $componentPublished = is_array($block) ? \App\Models\CustomPageSetting::componentPublished($block) : false;
            @endphp
            @continue(! $isPreview && ! $componentPublished)

            @if ($type === 'image')
                @php
                    $assetId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
                    $asset = $assetId !== null ? $assets->get($assetId) : null;
                    $variant = $asset !== null && $asset->getAttribute('state') === 'available' ? $media->thumbnailVariantForAsset($asset) : null;
                    $decorative = (bool) ($block['image_decorative'] ?? false);
                    $imageAlt = $asset !== null && ! $decorative ? $media->altTextForAsset($asset) : '';
                    $loading = $blockIndex === 0 ? 'eager' : 'lazy';
                    $imageScale = \App\Models\CustomPageSetting::imageScalePercent($block) / 100;
                @endphp

                @if ($asset !== null && $variant !== null)
                    @php
                        $imageChildren = $decorative && is_array($block['children'] ?? null) && $block['children'] !== []
                            ? $block['children'] : [];
                    @endphp
                    @if ($imageChildren !== [])
                        <div class="custom-page__image-group">
                    @endif
                    {{-- Public CSP rejects style attributes; authorize the per-image
                         CSS variables with the request's nonce instead. --}}
                    @if ($imageScale !== 1.0)
                        @push('public-component-styles')
                            <style nonce="{{ request()->attributes->get(\App\Http\Middleware\SecurityHeaders::STYLE_NONCE_ATTRIBUTE) }}">
                                #custom-page-image-{{ $blockIndex }} {
                                    --custom-page-image-width: {{ 28 * $imageScale }}rem;
                                }
                            </style>
                        @endpush
                    @endif
                    <figure id="custom-page-image-{{ $blockIndex }}" class="custom-page__component custom-page__media custom-page__image{{ $imageChildren !== [] ? ' custom-page__image--wrapped' : '' }}">
                        <img src="{{ $media->variantUrl($variant) }}" alt="{{ $imageAlt }}" loading="{{ $loading }}" decoding="async">
                    </figure>
                    @if ($imageChildren !== [])
                        @include('pages.partials.component-sequence', [
                            'blocks' => $imageChildren,
                            'label' => $label,
                            'isEmbedded' => true,
                            'imageBlockPrefix' => $imageBlockPrefix.'-'.$blockIndex,
                        ])
                        </div>
                    @endif
                @endif
            @endif

            @if ($type === 'text')
                <section class="custom-page__component">
                    <div class="custom-page__copy">
                        @if (filled($block['title'] ?? null))
                            @if ($isHome)<h2>{{ $block['title'] }}</h2>@else<h3>{{ $block['title'] }}</h3>@endif
                        @endif
                        @if (filled($block['body'] ?? null))
                            <div class="rich-text">{!! $richText->render((string) $block['body']) !!}</div>
                        @endif
                    </div>
                </section>
            @endif

            @if ($type === 'list')
                @php
                    $listAssetId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
                    $listAsset = $listAssetId !== null ? $assets->get($listAssetId) : null;
                    $listVariant = $listAsset !== null && $listAsset->getAttribute('state') === 'available'
                        ? $media->thumbnailVariantForAsset($listAsset)
                        : null;
                @endphp
                <section class="custom-page__component">
                    <div class="custom-page__list-layout{{ $listVariant !== null ? ' custom-page__list-layout--with-media' : '' }}">
                        <div class="custom-page__copy">
                            @if (filled($block['title'] ?? null))
                                @if ($isHome)<h2>{{ $block['title'] }}</h2>@else<h3>{{ $block['title'] }}</h3>@endif
                            @endif
                            <div class="custom-page__list">
                                @foreach (($block['items'] ?? []) as $item)
                                    @continue(! is_array($item) || (! $isPreview && ! \App\Models\CustomPageSetting::listItemPublished($item)))
                                    <article class="custom-page__list-item">
                                        <div class="custom-page__list-line">
                                            <strong>{{ $item['title'] ?? '' }}</strong>
                                            @if (filled(\App\Models\CustomPageSetting::listItemContext($item)))
                                                <span class="custom-page__context">{{ \App\Models\CustomPageSetting::listItemContext($item) }}</span>
                                            @endif
                                        </div>
                                        @if (filled(\App\Models\CustomPageSetting::listItemDetails($item)))
                                            <div class="rich-text">{!! $richText->render((string) \App\Models\CustomPageSetting::listItemDetails($item)) !!}</div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>
                        @if ($listVariant !== null && $listAsset instanceof \App\Models\MediaAsset)
                            <img
                                class="custom-page__list-media"
                                src="{{ $media->variantUrl($listVariant) }}"
                                alt="{{ $media->altTextForAsset($listAsset) }}"
                                loading="lazy"
                                decoding="async"
                            >
                        @endif
                    </div>
                </section>
            @endif

            @if ($type === 'divider')
                @php
                    $dividerVariant = is_string($block['variant'] ?? null)
                        && in_array($block['variant'], \App\Models\CustomPageSetting::DIVIDER_VARIANTS, true)
                            ? $block['variant']
                            : 'thin';
                @endphp
                <div class="custom-page__divider custom-page__divider--{{ $dividerVariant }}" aria-hidden="true"></div>
            @endif

            @if ($type === 'contact')
                <div class="custom-page__component custom-page__contact">
                    <x-contact :general-settings="$generalSettings" :children="$componentSettings->contactChildren($block)" :title="$block['title'] ?? null" :heading-id="'contact-heading-'.$imageBlockPrefix.'-'.$blockIndex" />
                </div>
            @endif

            @if ($type === 'legal_disclaimer')
                @php($legalSections = \App\Domain\Content\PublicLegalContent::sections($generalSettings))
                @if ($legalSections !== [])
                    <section class="legal-disclaimer custom-page__component custom-page__legal" aria-labelledby="legal-heading-{{ $imageBlockPrefix }}-{{ $blockIndex }}">
                        <h2 id="legal-heading-{{ $imageBlockPrefix }}-{{ $blockIndex }}">Rechtliches</h2>
                        <div class="custom-page__legal-sections">
                            @foreach ($legalSections as $legalSection)
                                <details id="rechtliches-{{ $legalSection['slug'] }}" class="custom-page__legal-section">
                                    <summary>{{ $legalSection['label'] }}</summary>
                                    <div class="custom-page__legal-text">{{ $legalSection['text'] }}</div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endif
        @endforeach
    </div>
