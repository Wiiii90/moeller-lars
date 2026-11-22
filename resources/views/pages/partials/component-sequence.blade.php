    @php
        $isEmbedded = (bool) ($isEmbedded ?? false);
        $imageBlockPrefix = $imageBlockPrefix ?? 'page';
        $homeImageViewerEnabled = (bool) (($isHome ?? false) && ($enableImageViewer ?? false));
    @endphp
    <div class="custom-page{{ $isHome ? ' home-components' : '' }}{{ $isEmbedded ? ' custom-page--embedded' : '' }}" @unless($isEmbedded)aria-label="{{ $label }}"@endunless @if ($homeImageViewerEnabled && ! $isEmbedded) data-artwork-viewer-sequence @endif>
        @foreach (\App\Models\CustomPageSetting::layoutProjection($blocks) as $blockIndex => $block)
            @php
                $blockIndex = $block['_root_index'] ?? $blockIndex;
                $type = is_array($block) ? ($block['type'] ?? null) : null;
                $componentPublished = is_array($block) ? \App\Models\CustomPageSetting::componentPublished($block) : false;
            @endphp
            @continue(! $isPreview && ! $componentPublished)

            @if (($block['layout_divider_before'] ?? 'none') !== 'none')
                <div class="custom-page__divider custom-page__divider--{{ $block['layout_divider_before'] }}" aria-hidden="true"></div>
            @endif

            @if ($type === 'layout')
                <section id="custom-page-component-{{ $imageBlockPrefix }}-{{ $blockIndex }}"
                    class="custom-page__component custom-page__layout custom-page__layout--mode-{{ $block['layout_mode'] ?? 'rows' }} custom-page__layout--columns-{{ $block['columns'] }} custom-page__layout--layout-{{ $block['column_layout'] ?? 'equal' }} custom-page__layout--gap-{{ $block['gap'] }}"
                    aria-label="Layout">
                    @foreach ($block['areas'] as $areaIndex => $area)
                        <div class="custom-page__layout-area custom-page__layout-area--border-{{ $area['border'] }}"
                            data-layout-area="{{ $area['id'] }}">
                            @if (($area['blocks'] ?? []) !== [])
                                @include('pages.partials.component-sequence', [
                                    'blocks' => $area['blocks'],
                                    'label' => $label,
                                    'isEmbedded' => true,
                                    'imageBlockPrefix' => $imageBlockPrefix,
                                ])
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif

            @if (in_array($type, \App\Models\CustomPageSetting::MEDIA_COMPONENT_TYPES, true))
                @php
                    $assetId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
                    $asset = $assetId !== null ? $assets->get($assetId) : null;
                    $isVideo = $asset !== null && \App\Domain\Media\MediaTypePolicy::isVideo((string) $asset->getAttribute('mime_type'));
                    $posterVariant = $isVideo && $asset->getAttribute('state') === 'available'
                        ? \App\Domain\Media\MediaPreviewVariant::videoPoster($asset) : null;
                    $posterUrl = $posterVariant ? $media->variantUrl($posterVariant) : null;
                    $variant = $asset !== null && $asset->getAttribute('state') === 'available' && ! $isVideo
                        ? $media->thumbnailVariantForAsset($asset) : null;
                    $decorative = (bool) ($block['image_decorative'] ?? false);
                    $imageAlignment = \App\Models\CustomPageSetting::imageAlignment($block);
                    $imageAlt = $asset !== null && ! $decorative ? $media->altTextForAsset($asset) : '';
                    $loading = $blockIndex === 0 ? 'eager' : 'lazy';
                    $imageScalePercent = \App\Models\CustomPageSetting::imageScalePercent($block);
                    $imageScale = $imageScalePercent / 100;
                @endphp

                @if ($asset !== null && ($variant !== null || $isVideo))
                    {{-- Livewire retains head styles across navigation and history.
                         Bind each nonce-authorized rule to its scale, not a page-local
                         block index that can identify a different image after a swap. --}}
                    @if ($imageScale !== 1.0)
                        @push('public-component-styles')
                            <style nonce="{{ request()->attributes->get(\App\Http\Middleware\SecurityHeaders::STYLE_NONCE_ATTRIBUTE) }}">
                                .custom-page__image--scale-{{ $imageScalePercent }} {
                                    --custom-page-image-width: {{ 28 * $imageScale }}rem;
                                }
                            </style>
                        @endpush
                    @endif
                    <figure id="custom-page-image-{{ $blockIndex }}" class="custom-page__component custom-page__media custom-page__image custom-page__image--scale-{{ $imageScalePercent }} custom-page__image--align-{{ $imageAlignment }}">
                        @if ($isVideo)
                            <video src="{{ $media->originalUrlForAsset($asset) }}"
                                @if ($posterUrl) poster="{{ $posterUrl }}" @endif
                                @if ($posterVariant)
                                    width="{{ $posterVariant->getAttribute('width') }}"
                                    height="{{ $posterVariant->getAttribute('height') }}"
                                @endif
                                @if ($decorative) aria-hidden="true" @else aria-label="{{ $imageAlt }}" @if (\App\Models\CustomPageSetting::videoControls($block)) controls @endif @endif
                                muted loop playsinline preload="metadata" data-public-loop-video></video>
                        @else
                            <img src="{{ $media->variantUrl($variant) }}" alt="{{ $imageAlt }}" loading="{{ $loading }}" decoding="async" @if ($homeImageViewerEnabled && ! $decorative) {!! $imageViewer->attributes('home-image-'.$imageBlockPrefix.'-'.$blockIndex, $media->originalUrlForAsset($asset), $imageAlt) !!} @endif>
                        @endif
                    </figure>
                @endif
            @endif

            @if ($type === 'text')
                <section id="custom-page-component-{{ $imageBlockPrefix }}-{{ $blockIndex }}" class="custom-page__component">
                    <div class="custom-page__copy">
                        @if (filled($block['title'] ?? null))
                            @if ($isHome)<h2>{{ $block['title'] }}</h2>@else<h3>{{ $block['title'] }}</h3>@endif
                        @endif
                        @if (filled($block['body'] ?? null))
                            <div class="rich-text">{!! $homeImageViewerEnabled ? $imageViewer->renderRichText((string) $block['body'], 'home-rich-'.$imageBlockPrefix.'-'.$blockIndex) : $richText->render((string) $block['body']) !!}</div>
                        @endif
                    </div>
                </section>
            @endif

            @if ($type === 'contact')
                <div id="custom-page-component-{{ $imageBlockPrefix }}-{{ $blockIndex }}" class="custom-page__component custom-page__contact">
                    <x-contact :general-settings="$generalSettings" :children="$componentSettings->contactChildren($block)" :title="$block['title'] ?? null" :heading-id="'contact-heading-'.$imageBlockPrefix.'-'.$blockIndex" />
                </div>
            @endif

            @if ($type === 'legal_disclaimer')
                @php($legalSections = \App\Domain\Content\PublicLegalContent::sections($generalSettings))
                @if ($legalSections !== [])
                    <section id="custom-page-component-{{ $imageBlockPrefix }}-{{ $blockIndex }}" class="legal-disclaimer custom-page__component custom-page__legal" aria-labelledby="legal-heading-{{ $imageBlockPrefix }}-{{ $blockIndex }}">
                        <h2 id="legal-heading-{{ $imageBlockPrefix }}-{{ $blockIndex }}">Legal Disclaimer</h2>
                        <div class="custom-page__legal-sections">
                            @foreach ($legalSections as $legalSection)
                                <details id="legal-{{ $legalSection['slug'] }}" class="custom-page__legal-section">
                                    <summary>{{ $legalSection['label'] }}</summary>
                                    <div class="custom-page__legal-text rich-text">{!! $richText->render($legalSection['text']) !!}</div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endif
        @endforeach
    </div>
